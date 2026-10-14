<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment\Providers;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Services\Payment\HttpClient;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;

/**
 * PayPal payment provider.
 *
 * Uses PayPal Orders v2 for the charge flow: create an
 * order, redirect the customer to the approval URL, capture
 * on return. The refund flow uses Payments v2 captures.
 *
 * Credentials come from commerce_store_settings:
 *   payments.paypal.mode           'sandbox' | 'live'
 *   payments.paypal.client_id
 *   payments.paypal.client_secret
 *   payments.paypal.webhook_id
 *
 * The isConfigured() check reads the client id and the
 * client secret. A store with an empty either value cannot
 * offer PayPal at checkout.
 *
 * Two things differ from Stripe:
 *
 *   1. Every call needs an OAuth access token. The token
 *      comes from POST /v1/oauth2/token with HTTP Basic
 *      auth. The provider fetches one token per request
 *      and caches it on the instance (the container
 *      resolves the provider as a singleton per request).
 *
 *   2. Webhook signature verification is a round trip to
 *      PayPal's verify-webhook-signature endpoint. PayPal
 *      does not compute a local HMAC.
 *
 * Stage 010 Session 4.
 */
final class PayPalProvider implements PaymentProvider
{
    private const SANDBOX_API_BASE = 'https://api-m.sandbox.paypal.com';
    private const LIVE_API_BASE    = 'https://api-m.paypal.com';

    /** Cached access token for this request. */
    private ?string $accessToken = null;

    public function __construct(
        private HttpClient $http,
        private StoreSettingsRepository $settings,
        private OrderRepository $orders,
    ) {
    }

    public function key(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function isConfigured(StoreContext $ctx): bool
    {
        if (!$ctx->has()) {
            return false;
        }
        $clientId = trim($this->settings->string('payments.paypal.client_id', ''));
        $secret   = trim($this->settings->string('payments.paypal.client_secret', ''));
        return $clientId !== '' && $secret !== '';
    }

    /**
     * Create a PayPal Order and return the approval URL.
     * The customer is redirected to the URL, approves, and
     * PayPal sends them back with a token query parameter.
     * The order's payment_status stays 'unpaid' until
     * PAYMENT.CAPTURE.COMPLETED arrives (or the return
     * controller captures and writes the state).
     *
     * @param array<string,mixed> $context
     * @return array{ok: bool, redirect_url?: string,
     *               provider_reference?: string,
     *               error?: string}
     */
    public function charge(Order $order, array $context): array
    {
        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'PayPal is not configured.'];
        }

        $returnUrl = (string) ($context['return_url'] ?? '');
        $cancelUrl = (string) ($context['cancel_url'] ?? '');

        $amountString = number_format($order->grandTotal, 2, '.', '');

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order->orderNumber,
                'custom_id'    => (string) $order->id,
                'amount' => [
                    'currency_code' => strtoupper($order->currencyCode),
                    'value'         => $amountString,
                ],
            ]],
            'application_context' => [
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
                'user_action'         => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ],
        ];

        $response = $this->http->postJson(
            $this->apiBase() . '/v2/checkout/orders',
            $payload,
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 201 && $response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body']),
            ];
        }

        $paypalOrderId = (string) ($response['body']['id'] ?? '');
        $approveUrl    = $this->linkByRel($response['body'], 'approve');
        if ($paypalOrderId === '' || $approveUrl === '') {
            return ['ok' => false, 'error' => 'PayPal returned no approval URL.'];
        }

        return [
            'ok'                 => true,
            'redirect_url'       => $approveUrl,
            'provider_reference' => $paypalOrderId,
        ];
    }

    /**
     * Capture a PayPal Order.
     *
     * @return array{ok: bool, provider_reference?: string,
     *               error?: string}
     */
    public function capture(Order $order, string $providerReference): array
    {
        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'PayPal is not configured.'];
        }

        if ($providerReference === '') {
            return ['ok' => false, 'error' => 'Missing PayPal order id.'];
        }

        $response = $this->http->postJson(
            $this->apiBase() . '/v2/checkout/orders/'
                . rawurlencode($providerReference) . '/capture',
            [],
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }

        // Already-captured is not an error for a production
        // provider. The customer may have refreshed the
        // return page, or the webhook may have captured
        // concurrently. PayPal returns HTTP 422 with
        // name = ORDER_ALREADY_CAPTURED. Resolve the
        // existing capture id and return success so the
        // customer is not shown a spurious failure.
        if ($response['status'] === 422
            && (string) ($response['body']['name'] ?? '') === 'ORDER_ALREADY_CAPTURED'
        ) {
            $existingCaptureId = $this->fetchCapturedId($providerReference);
            if ($existingCaptureId !== '') {
                return ['ok' => true, 'provider_reference' => $existingCaptureId];
            }
            return [
                'ok'    => false,
                'error' => 'PayPal reports this order as already captured, '
                         . 'but the capture could not be read back.',
            ];
        }

        if ($response['status'] !== 201 && $response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body']),
            ];
        }

        $captureId = $this->captureIdFromOrder($response['body']);
        if ($captureId === '') {
            return ['ok' => false, 'error' => 'PayPal returned no capture id.'];
        }

        return ['ok' => true, 'provider_reference' => $captureId];
    }

    /**
     * Refund an amount against the order's original capture.
     *
     * @return array{ok: bool, refund_id?: string,
     *               error?: string}
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array
    {
        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'PayPal is not configured.'];
        }

        $captureId = (string) ($order->paymentReference ?? '');
        if ($captureId === '') {
            return ['ok' => false, 'error' => 'No payment reference on the order.'];
        }

        $payload = [
            'amount' => [
                'currency_code' => strtoupper($order->currencyCode),
                'value'         => number_format($amount, 2, '.', ''),
            ],
        ];

        $response = $this->http->postJson(
            $this->apiBase() . '/v2/payments/captures/'
                . rawurlencode($captureId) . '/refund',
            $payload,
            [
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . $idempotencyKey,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 201 && $response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body']),
            ];
        }

        $refundId = (string) ($response['body']['id'] ?? '');
        if ($refundId === '') {
            return ['ok' => false, 'error' => 'PayPal returned no refund id.'];
        }

        return ['ok' => true, 'refund_id' => $refundId];
    }

    /**
     * Verify a PayPal webhook signature.
     *
     * Posts the five PayPal headers and the raw body to
     * /v1/notifications/verify-webhook-signature. PayPal
     * returns verification_status: SUCCESS or FAILURE.
     *
     * @param array<string,string> $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $token = $this->token();
        if ($token === '') {
            return false;
        }

        $webhookId = trim($this->settings->string('payments.paypal.webhook_id', ''));
        if ($webhookId === '') {
            return false;
        }

        $transmissionId   = (string) ($headers['paypal-transmission-id']   ?? '');
        $transmissionTime = (string) ($headers['paypal-transmission-time'] ?? '');
        $transmissionSig  = (string) ($headers['paypal-transmission-sig']  ?? '');
        $certUrl          = (string) ($headers['paypal-cert-url']          ?? '');
        $authAlgo         = (string) ($headers['paypal-auth-algo']         ?? '');

        if ($transmissionId === '' || $transmissionTime === '' ||
            $transmissionSig === '' || $certUrl === '' || $authAlgo === '') {
            return false;
        }

        $payload = [
            'transmission_id'   => $transmissionId,
            'transmission_time' => $transmissionTime,
            'cert_url'          => $certUrl,
            'auth_algo'         => $authAlgo,
            'transmission_sig'  => $transmissionSig,
            'webhook_id'        => $webhookId,
            'webhook_event'     => json_decode($rawBody, true) ?: new \stdClass(),
        ];

        $response = $this->http->postJson(
            $this->apiBase() . '/v1/notifications/verify-webhook-signature',
            $payload,
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return false;
        }
        if ($response['status'] !== 200) {
            return false;
        }

        $status = (string) ($response['body']['verification_status'] ?? '');
        return $status === 'SUCCESS';
    }

    /**
     * Act on a verified PayPal webhook event.
     *
     * @param array<string,string> $headers
     * @return array{handled: bool, event_type: string,
     *               event_id?: string, order_id?: int,
     *               note?: string}
     */
    public function handleWebhook(string $rawBody, array $headers): array
    {
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return ['handled' => false, 'event_type' => '', 'note' => 'Malformed payload.'];
        }

        $eventId   = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['event_type'] ?? '');
        $resource  = $event['resource'] ?? [];
        if (!is_array($resource)) {
            $resource = [];
        }

        $orderId = $this->orderIdFromResource($resource);

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handleCaptureCompleted($orderId, $resource, $eventId, $eventType);

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.REVERSED':
                return [
                    'handled'    => true,
                    'event_type' => $eventType,
                    'event_id'   => $eventId,
                    'order_id'   => $orderId,
                    'note'       => 'Capture ' . $eventType . '. Order state unchanged.',
                ];

            case 'PAYMENT.CAPTURE.REFUNDED':
                return [
                    'handled'    => true,
                    'event_type' => $eventType,
                    'event_id'   => $eventId,
                    'order_id'   => $orderId,
                    'note'       => 'Refund confirmation received.',
                ];

            default:
                return [
                    'handled'    => true,
                    'event_type' => $eventType,
                    'event_id'   => $eventId,
                    'order_id'   => $orderId,
                    'note'       => 'Event type not consumed by the handler.',
                ];
        }
    }

    // =================================================================
    // Private helpers
    // =================================================================

    private function apiBase(): string
    {
        $mode = trim($this->settings->string('payments.paypal.mode', 'sandbox'));
        return $mode === 'live' ? self::LIVE_API_BASE : self::SANDBOX_API_BASE;
    }

    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $clientId = trim($this->settings->string('payments.paypal.client_id', ''));
        $secret   = trim($this->settings->string('payments.paypal.client_secret', ''));
        if ($clientId === '' || $secret === '') {
            return '';
        }

        $basicAuth = base64_encode($clientId . ':' . $secret);

        $response = $this->http->post(
            $this->apiBase() . '/v1/oauth2/token',
            'grant_type=client_credentials',
            [
                'Authorization: Basic ' . $basicAuth,
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );

        if ($response['error'] !== null) {
            return '';
        }
        if ($response['status'] !== 200) {
            return '';
        }

        $token = (string) ($response['body']['access_token'] ?? '');
        if ($token === '') {
            return '';
        }
        $this->accessToken = $token;
        return $token;
    }

    /**
     * @param array<string,mixed> $resource
     */
    private function orderIdFromResource(array $resource): int
    {
        $customId = (string) ($resource['custom_id'] ?? '');
        return $customId !== '' ? (int) $customId : 0;
    }

    /**
     * @param array<string,mixed> $body
     */
    private function linkByRel(array $body, string $rel): string
    {
        $links = $body['links'] ?? [];
        if (!is_array($links)) {
            return '';
        }
        foreach ($links as $link) {
            if (is_array($link) && ($link['rel'] ?? '') === $rel) {
                return (string) ($link['href'] ?? '');
            }
        }
        return '';
    }

    /**
     * Read the capture id from an existing PayPal order.
     *
     * Used when capture() receives ORDER_ALREADY_CAPTURED:
     * the order exists and was captured, but the capture
     * id is not carried in the 422 error body. A GET on
     * the order returns the same shape the success path
     * parses.
     */
    private function fetchCapturedId(string $paypalOrderId): string
    {
        if ($paypalOrderId === '') {
            return '';
        }

        $token = $this->token();
        if ($token === '') {
            return '';
        }

        $response = $this->http->get(
            $this->apiBase() . '/v2/checkout/orders/'
                . rawurlencode($paypalOrderId),
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return '';
        }
        if ($response['status'] !== 200) {
            return '';
        }

        return $this->captureIdFromOrder($response['body']);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function captureIdFromOrder(array $body): string
    {
        $units = $body['purchase_units'] ?? [];
        if (!is_array($units) || $units === []) {
            return '';
        }
        $payments = $units[0]['payments'] ?? [];
        if (!is_array($payments)) {
            return '';
        }
        $captures = $payments['captures'] ?? [];
        if (!is_array($captures) || $captures === []) {
            return '';
        }
        return (string) ($captures[0]['id'] ?? '');
    }

    /**
     * @param array<string,mixed> $resource
     * @return array{handled: bool, event_type: string,
     *               event_id?: string, order_id?: int,
     *               note?: string}
     */
    private function handleCaptureCompleted(int $orderId, array $resource, string $eventId, string $eventType): array
    {
        if ($orderId <= 0) {
            return ['handled' => false, 'event_type' => $eventType,
                    'event_id' => $eventId, 'note' => 'No order id in resource.'];
        }
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            return ['handled' => false, 'event_type' => $eventType,
                    'event_id' => $eventId, 'order_id' => $orderId,
                    'note' => 'Order not found.'];
        }

        if ($order->paymentStatus === 'paid') {
            return ['handled' => true, 'event_type' => $eventType,
                    'event_id' => $eventId, 'order_id' => $orderId,
                    'note' => 'Order already marked paid.'];
        }

        $captureId = (string) ($resource['id'] ?? '');

        $this->orders->update($orderId, [
            'payment_status'    => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
            'payment_reference' => $captureId,
        ]);

        return ['handled' => true, 'event_type' => $eventType,
                'event_id' => $eventId, 'order_id' => $orderId,
                'note' => 'Order marked paid.'];
    }

    /**
     * @param array<string,mixed> $body
     */
    private function extractError(array $body): string
    {
        $message = (string) ($body['message'] ?? '');
        if ($message !== '') {
            return $message;
        }
        $name = (string) ($body['name'] ?? '');
        if ($name !== '') {
            return $name;
        }
        return 'PayPal returned status ' . (string) ($body['status'] ?? 'unknown');
    }
}
