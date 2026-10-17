<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment\Providers;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\PesapalRequestRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Services\Payment\HttpClient;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;

/**
 * Pesapal payment provider.
 *
 * Uses Pesapal API 3.0 for the charge flow: submit an
 * order, redirect the customer to Pesapal's hosted page,
 * receive the IPN when the customer completes.
 *
 * Credentials come from commerce_store_settings:
 *   payments.pesapal.mode             'sandbox' | 'live'
 *   payments.pesapal.consumer_key
 *   payments.pesapal.consumer_secret
 *   payments.pesapal.ipn_id
 *   payments.pesapal.callback_url
 *   payments.pesapal.refund_enabled
 *
 * The isConfigured() check reads the consumer key and the
 * consumer secret. A store with an empty either value
 * cannot offer Pesapal at checkout.
 *
 * Two things differ from Stripe and PayPal:
 *
 *   1. The access token expires after 5 minutes. The
 *      provider fetches a fresh token on every request.
 *      No caching.
 *
 *   2. Pesapal does not sign its IPN notifications. The
 *      verification is a round trip to GetTransactionStatus
 *      after confirming the order_tracking_id against
 *      commerce_pesapal_requests.
 *
 * Stage 010, sub-stage 010.2.
 */
final class PesapalProvider implements PaymentProvider
{
    private const SANDBOX_API_BASE = 'https://cybqa.pesapal.com/pesapalv3';
    private const LIVE_API_BASE    = 'https://pay.pesapal.com/v3';

    public function __construct(
        private HttpClient $http,
        private StoreSettingsRepository $settings,
        private OrderRepository $orders,
        private PesapalRequestRepository $pesapalRequests,
    ) {
    }

    public function key(): string
    {
        return 'pesapal';
    }

    public function label(): string
    {
        return 'Pesapal';
    }

    public function isConfigured(StoreContext $ctx): bool
    {
        if (!$ctx->has()) {
            return false;
        }
        $key    = trim($this->settings->string('payments.pesapal.consumer_key', ''));
        $secret = trim($this->settings->string('payments.pesapal.consumer_secret', ''));
        return $key !== '' && $secret !== '';
    }

    /**
     * Probe Pesapal's API with the stored credentials.
     *
     * The test is the RequestToken handshake. A 200 with a
     * token in the body means the consumer key and secret
     * are valid for the configured mode.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $key    = trim($this->settings->string('payments.pesapal.consumer_key', ''));
        $secret = trim($this->settings->string('payments.pesapal.consumer_secret', ''));

        if ($key === '' || $secret === '') {
            return [
                'ok'      => false,
                'message' => 'Pesapal consumer key and consumer secret must both be set.',
            ];
        }

        $mode = trim($this->settings->string('payments.pesapal.mode', 'sandbox'));

        $response = $this->http->postJson(
            $this->apiBase() . '/api/Auth/RequestToken',
            [
                'consumer_key'    => $key,
                'consumer_secret' => $secret,
            ]
        );

        if ($response['error'] !== null) {
            return [
                'ok'      => false,
                'message' => 'Pesapal is unreachable: ' . $response['error'],
            ];
        }

        $status = (int) $response['status'];

        if ($status === 200) {
            $token = trim((string) ($response['body']['token'] ?? ''));
            if ($token === '') {
                return [
                    'ok'      => false,
                    'message' => 'Pesapal responded HTTP 200 but returned no token.',
                ];
            }
            return [
                'ok'      => true,
                'message' => 'Pesapal authenticated (HTTP 200). Credentials are valid for '
                           . $mode . ' mode.',
            ];
        }

        if ($status === 401) {
            return [
                'ok'      => false,
                'message' => 'Pesapal rejected the credentials (HTTP 401). '
                           . 'Check the consumer key and secret below.',
            ];
        }

        if ($status === 0) {
            return [
                'ok'      => false,
                'message' => 'Pesapal is unreachable: no HTTP response. '
                           . 'Check the server network connection.',
            ];
        }

        return [
            'ok'      => false,
            'message' => 'Pesapal returned HTTP ' . $status . '.',
        ];
    }

    /**
     * Submit a Pesapal order and return the redirect URL.
     *
     * The customer is redirected to Pesapal's hosted page,
     * pays, and Pesapal sends them back to the return URL
     * the platform supplied. The order's payment_status
     * stays 'unpaid' until the IPN (or the return
     * controller's status check) confirms the payment.
     *
     * @param array<string,mixed> $context  Recognised keys:
     *        'return_url' - where the customer returns.
     *
     * @return array{ok: bool, redirect_url?: string,
     *               provider_reference?: string,
     *               error?: string}
     */
    public function charge(Order $order, array $context): array
    {
        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'Pesapal is not configured.'];
        }

        $returnUrl = (string) ($context['return_url'] ?? '');
        if ($returnUrl === '') {
            return ['ok' => false, 'error' => 'Missing return URL for Pesapal.'];
        }

        $ipnId = trim($this->settings->string('payments.pesapal.ipn_id', ''));
        if ($ipnId === '') {
            return ['ok' => false, 'error' => 'Pesapal IPN ID is not set.'];
        }

        // The merchant_reference must be unique per order.
        // The platform uses the order number, prefixed to
        // distinguish it from any other id Pesapal might
        // see from this store.
        $merchantReference = 'ORD-' . $order->orderNumber;

        $payload = [
            'id'              => $merchantReference,
            'currency'        => strtoupper($order->currencyCode),
            'amount'          => round($order->grandTotal, 2),
            'description'     => 'Order ' . $order->orderNumber,
            'callback_url'    => $returnUrl,
            'notification_id' => $ipnId,
            'billing_address' => [
                'email_address' => '',
                'phone_number'  => '',
                'country_code'  => '',
                'first_name'    => '',
                'middle_name'   => '',
                'last_name'     => '',
                'line_1'        => '',
                'line_2'        => '',
                'city'          => '',
                'state'         => '',
                'postal_code'   => '',
                'zip_code'      => '',
            ],
        ];

        $response = $this->http->postJson(
            $this->apiBase() . '/api/Transactions/SubmitOrderRequest',
            $payload,
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body'], $response['status']),
            ];
        }

        $trackingId     = trim((string) ($response['body']['order_tracking_id'] ?? ''));
        $redirectUrl    = trim((string) ($response['body']['redirect_url'] ?? ''));
        $merchantRefOut = trim((string) ($response['body']['merchant_reference'] ?? ''));

        if ($trackingId === '' || $redirectUrl === '') {
            return ['ok' => false, 'error' => 'Pesapal returned no redirect URL.'];
        }

        // Record the pending order so verifyWebhook() can
        // confirm the IPN's tracking id when it arrives.
        // Pesapal does not sign its IPNs; this row is the
        // security boundary.
        $this->pesapalRequests->record([
            'order_id'          => $order->id,
            'order_tracking_id' => $trackingId,
            'merchant_reference' => $merchantRefOut !== ''
                                    ? $merchantRefOut
                                    : $merchantReference,
            'amount'            => (float) $order->grandTotal,
            'currency_code'     => (string) $order->currencyCode,
        ]);

        return [
            'ok'                 => true,
            'redirect_url'       => $redirectUrl,
            'provider_reference' => $trackingId,
        ];
    }

    /**
     * Pesapal has no explicit capture step. The payment
     * completes on Pesapal's hosted page; the IPN or the
     * return controller's status check writes the paid
     * state. The method exists to satisfy the interface.
     *
     * @return array{ok: bool, provider_reference?: string,
     *               error?: string}
     */
    public function capture(Order $order, string $providerReference): array
    {
        return [
            'ok'                 => true,
            'provider_reference' => $providerReference,
        ];
    }

    /**
     * Read the current status of a Pesapal transaction.
     *
     * Public entry point for the return controller. The
     * IPN handler uses the same query logic internally;
     * this method exposes it without the repository write.
     *
     * @return array{ok: bool, status?: string,
     *               confirmation_code?: string,
     *               error?: string}
     */
    public function readTransactionStatus(string $trackingId): array
    {
        if ($trackingId === '') {
            return ['ok' => false, 'error' => 'Missing tracking id.'];
        }

        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'Pesapal is not configured.'];
        }

        $response = $this->http->get(
            $this->apiBase() . '/api/Transactions/GetTransactionStatus'
                . '?orderTrackingId=' . rawurlencode($trackingId),
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => 'Pesapal status query returned HTTP ' . $response['status'],
            ];
        }

        return [
            'ok'                => true,
            'status'            => (string) ($response['body']['payment_status_description'] ?? ''),
            'confirmation_code' => (string) ($response['body']['confirmation_code'] ?? ''),
        ];
    }

    /**
     * Refund an amount against the original Pesapal charge.
     *
     * Pesapal's refund endpoint has limitations: the
     * merchant must approve, multiple refunds are not
     * allowed, mobile money can only be fully refunded,
     * card can be partial. The plan defaults refund_enabled
     * to false; the merchant turns it on when their plan
     * exposes the endpoint.
     *
     * When refund_enabled is false, the refund is recorded
     * offline by PaymentRefundService without a Pesapal
     * call. The provider returns ok with refund_id =
     * 'offline'.
     *
     * @return array{ok: bool, refund_id?: string,
     *               error?: string}
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array
    {
        $enabled = $this->settings->bool('payments.pesapal.refund_enabled', false);
        if (!$enabled) {
            return ['ok' => true, 'refund_id' => 'offline'];
        }

        // Find the confirmation code from the pending row
        // for this order. Pesapal's refund API takes the
        // confirmation code, not the tracking id.
        $orderNumber = (string) $order->orderNumber;
        $merchantReference = 'ORD-' . $orderNumber;

        // The order's payment_reference carries the
        // confirmation code for a completed Pesapal payment
        // (written by handleWebhook()). For a partial refund
        // initiated from the return flow, the row's
        // confirmation_code is the source.
        $confirmationCode = (string) ($order->paymentReference ?? '');
        if ($confirmationCode === '') {
            return ['ok' => false, 'error' => 'No confirmation code on the order.'];
        }

        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'Pesapal is not configured.'];
        }

        $payload = [
            'confirmation_code' => $confirmationCode,
            'amount'            => round($amount, 2),
            'username'          => $merchantReference,
            'remarks'           => 'Refund for ' . $orderNumber,
        ];

        $response = $this->http->postJson(
            $this->apiBase() . '/api/Transactions/RefundRequest',
            $payload,
            [
                'Authorization: Bearer ' . $token,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }

        if ($response['status'] !== 200 && $response['status'] !== 201) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body'], $response['status']),
            ];
        }

        // Pesapal returns 200 when the refund request is
        // received, not when the refund is processed. The
        // refund row stays pending until Pesapal confirms
        // via the dashboard or a future polling pass.
        return ['ok' => true, 'refund_id' => 'pesapal-pending'];
    }

    /**
     * Verify a Pesapal IPN.
     *
     * Pesapal does not sign its IPNs. The verification is
     * the order_tracking_id lookup: the IPN carries an id
     * that must match a pending row this store initiated.
     * A mismatch is a forgery or a replay and is refused.
     *
     * The body passed in is a synthetic JSON body built by
     * WebhookController::pesapal() from the query
     * parameters. The shape is:
     *   {"order_tracking_id": "...", "merchant_reference":
     *    "...", "notification_type": "CHANGE"}
     *
     * @param array<string,string> $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return false;
        }

        $trackingId = trim((string) ($body['order_tracking_id'] ?? ''));
        if ($trackingId === '') {
            return false;
        }

        $row = $this->pesapalRequests->findByTrackingId($trackingId);
        if ($row === null) {
            return false;
        }

        // A callback for a request that is already
        // completed or failed is a replay. Refuse it.
        if (($row['status'] ?? '') !== 'pending') {
            return false;
        }

        return true;
    }

    /**
     * The Pesapal event id. The order_tracking_id from the
     * synthetic body.
     */
    public function extractEventId(string $rawBody): string
    {
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return '';
        }
        return (string) ($body['order_tracking_id'] ?? '');
    }

    /**
     * Act on a verified Pesapal IPN.
     *
     * Calls GetTransactionStatus with the tracking id.
     * Reads payment_status_description from the response.
     * If COMPLETED, writes the paid state and marks the row
     * completed. Otherwise marks the row failed with the
     * status description.
     *
     * @param array<string,string> $headers
     * @return array{handled: bool, event_type: string,
     *               event_id?: string, order_id?: int,
     *               note?: string}
     */
    public function handleWebhook(string $rawBody, array $headers): array
    {
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return ['handled' => false, 'event_type' => 'pesapal.ipn',
                    'note' => 'Malformed body.'];
        }

        $trackingId = trim((string) ($body['order_tracking_id'] ?? ''));
        if ($trackingId === '') {
            return ['handled' => false, 'event_type' => 'pesapal.ipn',
                    'note' => 'No order_tracking_id in body.'];
        }

        $row = $this->pesapalRequests->findByTrackingId($trackingId);
        if ($row === null) {
            return ['handled' => false, 'event_type' => 'pesapal.ipn',
                    'note' => 'No pending row for this tracking id.'];
        }

        $orderId = (int) ($row['order_id'] ?? 0);

        // Query the transaction status. Same method the return
        // controller calls; the HTTP call logic lives once.
        $statusResult = $this->readTransactionStatus($trackingId);
        if (($statusResult['ok'] ?? false) !== true) {
            return ['handled' => false, 'event_type' => 'pesapal.ipn',
                    'order_id' => $orderId,
                    'note' => 'Status query failed: '
                            . (string) ($statusResult['error'] ?? 'unknown')];
        }

        $statusDesc       = (string) ($statusResult['status'] ?? '');
        $confirmationCode = (string) ($statusResult['confirmation_code'] ?? '');

        if ($statusDesc === 'COMPLETED') {
            $this->pesapalRequests->markCompleted(
                $trackingId,
                $confirmationCode !== '' ? $confirmationCode : null,
                $statusDesc
            );

            if ($orderId > 0) {
                $order = $this->orders->findById($orderId);
                if ($order !== null && $order->paymentStatus !== 'paid') {
                    $this->orders->update($orderId, [
                        'payment_status'    => 'paid',
                        'paid_at'           => date('Y-m-d H:i:s'),
                        'payment_reference' => $confirmationCode !== ''
                                                ? $confirmationCode
                                                : $trackingId,
                    ]);
                }
            }

            return [
                'handled'    => true,
                'event_type' => 'pesapal.ipn',
                'event_id'   => $trackingId,
                'order_id'   => $orderId,
                'note'       => 'Payment completed. Confirmation: '
                              . ($confirmationCode !== '' ? $confirmationCode : '(missing)'),
            ];
        }

        $this->pesapalRequests->markFailed(
            $trackingId,
            $statusDesc !== '' ? $statusDesc : 'Unknown status'
        );

        return [
            'handled'    => true,
            'event_type' => 'pesapal.ipn',
            'event_id'   => $trackingId,
            'order_id'   => $orderId,
            'note'       => 'Payment not completed. Status: ' . $statusDesc,
        ];
    }

    // =================================================================
    // Private helpers
    // =================================================================

    /**
     * Read a merchant-readable error from a Pesapal response
     * body. Pesapal's error shape is inconsistent: sometimes
     * a top-level 'message', sometimes an 'error' object
     * with 'message' and 'code'.
     *
     * @param array<string,mixed> $body
     */
    private function extractError(array $body, int $status): string
    {
        $error = $body['error'] ?? null;
        if (is_array($error)) {
            $message = (string) ($error['message'] ?? '');
            if ($message !== '') {
                return $message;
            }
            $code = (string) ($error['code'] ?? '');
            if ($code !== '') {
                return 'Pesapal error: ' . $code;
            }
        }

        $message = (string) ($body['message'] ?? '');
        if ($message !== '') {
            return $message;
        }

        return 'Pesapal returned HTTP ' . $status . '.';
    }

    private function apiBase(): string
    {
        $mode = trim($this->settings->string('payments.pesapal.mode', 'sandbox'));
        return $mode === 'live' ? self::LIVE_API_BASE : self::SANDBOX_API_BASE;
    }

    /**
     * Fetch a fresh access token. Pesapal's tokens expire
     * after 5 minutes, so no caching. Each request fetches
     * its own token.
     *
     * @return string  the token, or '' on failure
     */
    private function token(): string
    {
        $key    = trim($this->settings->string('payments.pesapal.consumer_key', ''));
        $secret = trim($this->settings->string('payments.pesapal.consumer_secret', ''));
        if ($key === '' || $secret === '') {
            return '';
        }

        $response = $this->http->postJson(
            $this->apiBase() . '/api/Auth/RequestToken',
            [
                'consumer_key'    => $key,
                'consumer_secret' => $secret,
            ]
        );

        if ($response['error'] !== null) {
            return '';
        }
        if ($response['status'] !== 200) {
            return '';
        }

        return trim((string) ($response['body']['token'] ?? ''));
    }
}
