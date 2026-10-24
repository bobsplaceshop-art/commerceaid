<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment\Providers;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Platform\Support\HttpClient;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;

/**
 * Stripe payment provider.
 *
 * Uses Stripe Checkout (the hosted payment page) for the
 * charge flow and Stripe's REST API for the refund flow.
 * The webhook handler consumes payment_intent.succeeded,
 * payment_intent.payment_failed, charge.refunded, and
 * charge.refund.updated.
 *
 * Credentials come from commerce_store_settings:
 *   payments.stripe.mode            'sandbox' | 'live'
 *   payments.stripe.secret_key      sk_test_... | sk_live_...
 *   payments.stripe.publishable_key pk_test_... | pk_live_...
 *   payments.stripe.webhook_secret  whsec_...
 *
 * The isConfigured() check reads the secret key and the
 * webhook secret. A store with an empty either value cannot
 * offer Stripe at checkout.
 *
 * Stage 010 Session 3.
 */
final class StripeProvider implements PaymentProvider
{
    private const API_BASE        = 'https://api.stripe.com/v1';
    private const WEBHOOK_TOLERANCE = 300; // 5 minutes

    public function __construct(
        private HttpClient $http,
        private StoreSettingsRepository $settings,
        private OrderRepository $orders,
    ) {
    }

    public function key(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function isConfigured(StoreContext $ctx): bool
    {
        if (!$ctx->has()) {
            return false;
        }
        $secret  = trim($this->settings->string('payments.stripe.secret_key', ''));
        $webhook = trim($this->settings->string('payments.stripe.webhook_secret', ''));
        return $secret !== '' && $webhook !== '';
    }

    /**
     * Probe Stripe's API with the stored secret key.
     *
     * GET /v1/balance is the cheapest read-only endpoint
     * that requires authentication and returns data about
     * the account. A 200 means the key is valid, the
     * account is active, and the API is reachable.
     *
     * A 401 means the key is wrong, revoked, or from the
     * wrong account. Any other non-2xx (402 for suspended
     * accounts, 403 for restricted keys) is reported as-is.
     *
     * The message is merchant-readable. The full response
     * is not echoed, because the response could include
     * account metadata the merchant does not need.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $secret = $this->secretKey();
        if ($secret === '') {
            return [
                'ok'      => false,
                'message' => 'Stripe secret key is not set.',
            ];
        }

        $response = $this->http->get(
            self::API_BASE . '/balance',
            [
                'Authorization: Bearer ' . $secret,
            ]
        );

        if ($response['error'] !== null) {
            return [
                'ok'      => false,
                'message' => 'Stripe is unreachable: ' . $response['error'],
            ];
        }

        $status = (int) $response['status'];

        if ($status === 200) {
            $livemode = (bool) ($response['body']['livemode'] ?? false);
            $mode     = $livemode ? 'live' : 'test';

            return [
                'ok'      => true,
                'message' => 'Stripe responded (HTTP 200). Key is valid for '
                           . $mode . ' mode.',
            ];
        }

        if ($status === 401) {
            return [
                'ok'      => false,
                'message' => 'Stripe rejected the secret key (HTTP 401). '
                           . 'Check the key in the credentials below.',
            ];
        }

        if ($status === 403) {
            return [
                'ok'      => false,
                'message' => 'Stripe refused the request (HTTP 403). '
                           . 'The key may be restricted to certain resources.',
            ];
        }

        if ($status === 0) {
            return [
                'ok'      => false,
                'message' => 'Stripe is unreachable: no HTTP response. '
                           . 'Check the server network connection.',
            ];
        }

        return [
            'ok'      => false,
            'message' => 'Stripe returned HTTP ' . $status . '.',
        ];
    }

    /**
     * Create a Stripe Checkout Session for the order. Returns
     * the session's hosted URL, which the caller redirects
     * the customer to. The order's payment_status stays
     * 'unpaid' until payment_intent.succeeded arrives.
     */
    public function charge(Order $order, array $context): array
    {
        $secret = $this->secretKey();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Stripe is not configured.'];
        }

        $currency   = strtolower($order->currencyCode);
        $amountMinor = (int) round($order->grandTotal * 100);
        if ($amountMinor <= 0) {
            return ['ok' => false, 'error' => 'Order total is zero or negative.'];
        }

        $returnUrl = (string) ($context['return_url'] ?? '');
        $cancelUrl = (string) ($context['cancel_url'] ?? '');

        // Stripe expects form-encoded, not JSON. Build the
        // body from key=value pairs. Brackets denote nested
        // keys: metadata[order_id] -> { metadata: { order_id } }.
        $body = http_build_query([
            'mode'                    => 'payment',
            'success_url'             => $returnUrl,
            'cancel_url'              => $cancelUrl,
            'client_reference_id'     => (string) $order->orderNumber,
            'metadata[order_id]'      => (string) $order->id,
            'metadata[order_number]'  => (string) $order->orderNumber,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]'    => $currency,
            'line_items[0][price_data][unit_amount]' => $amountMinor,
            'line_items[0][price_data][product_data][name]' => 'Order ' . $order->orderNumber,
        ]);

        $response = $this->http->post(
            self::API_BASE . '/checkout/sessions',
            $body,
            [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body']),
            ];
        }

        $sessionId = (string) ($response['body']['id'] ?? '');
        $url       = (string) ($response['body']['url'] ?? '');
        if ($sessionId === '' || $url === '') {
            return ['ok' => false, 'error' => 'Stripe returned no session URL.'];
        }

        return [
            'ok'                 => true,
            'redirect_url'       => $url,
            'provider_reference' => $sessionId,
        ];
    }

    /**
     * Stripe Checkout captures automatically. Nothing to do.
     * The interface requires the method; the implementation
     * returns a no-op success.
     */
    public function capture(Order $order, string $providerReference): array
    {
        return ['ok' => true, 'provider_reference' => $providerReference];
    }

    /**
     * Refund an amount against the order's original charge.
     * The order's payment_reference holds the PaymentIntent id
     * (set by the webhook when payment_intent.succeeded
     * arrived). Stripe accepts the PaymentIntent id on the
     * refund endpoint.
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array
    {
        $secret = $this->secretKey();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Stripe is not configured.'];
        }

        $paymentIntentId = (string) ($order->paymentReference ?? '');
        if ($paymentIntentId === '') {
            return [
                'ok'    => false,
                'error' => 'No payment reference on the order.',
            ];
        }

        $amountMinor = (int) round($amount * 100);
        if ($amountMinor <= 0) {
            return ['ok' => false, 'error' => 'Refund amount is zero or negative.'];
        }

        $body = http_build_query([
            'payment_intent' => $paymentIntentId,
            'amount'         => $amountMinor,
        ]);

        $response = $this->http->post(
            self::API_BASE . '/refunds',
            $body,
            [
                'Authorization: Bearer ' . $secret,
                'Content-Type: application/x-www-form-urlencoded',
                'Idempotency-Key: ' . $idempotencyKey,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body']),
            ];
        }

        $refundId = (string) ($response['body']['id'] ?? '');
        if ($refundId === '') {
            return ['ok' => false, 'error' => 'Stripe returned no refund id.'];
        }
        return ['ok' => true, 'refund_id' => $refundId];
    }

    /**
     * Read a Checkout Session from Stripe.
     *
     * Called by StripeReturnController after the customer
     * lands on the return URL. Reads the session's
     * payment_status and, when the session is paid, returns
     * the PaymentIntent id. The caller writes the paid state
     * on the order when the webhook has not already done so.
     *
     * Stripe returns the session with payment_intent as
     * either a string id (default) or an expanded object
     * (when expand[]=payment_intent is used). This method
     * accepts both shapes.
     *
     * @return array{ok: bool, payment_status: string,
     *               payment_intent_id: string, error?: string}
     */
    public function readCheckoutSession(string $sessionId): array
    {
        if ($sessionId === '') {
            return [
                'ok'                 => false,
                'payment_status'     => '',
                'payment_intent_id'  => '',
                'error'              => 'Missing session id.',
            ];
        }

        $secret = $this->secretKey();
        if ($secret === '') {
            return [
                'ok'                 => false,
                'payment_status'     => '',
                'payment_intent_id'  => '',
                'error'              => 'Stripe is not configured.',
            ];
        }

        $response = $this->http->get(
            self::API_BASE . '/checkout/sessions/' . rawurlencode($sessionId),
            [
                'Authorization: Bearer ' . $secret,
            ]
        );

        if ($response['error'] !== null) {
            return [
                'ok'                 => false,
                'payment_status'     => '',
                'payment_intent_id'  => '',
                'error'              => $response['error'],
            ];
        }
        if ($response['status'] !== 200) {
            return [
                'ok'                 => false,
                'payment_status'     => '',
                'payment_intent_id'  => '',
                'error'              => $this->extractError($response['body']),
            ];
        }

        $paymentStatus = (string) ($response['body']['payment_status'] ?? '');

        $intent = $response['body']['payment_intent'] ?? '';
        $intentId = '';
        if (is_string($intent)) {
            $intentId = $intent;
        } elseif (is_array($intent) && isset($intent['id'])) {
            $intentId = (string) $intent['id'];
        }

        return [
            'ok'                 => true,
            'payment_status'     => $paymentStatus,
            'payment_intent_id'  => $intentId,
        ];
    }

    /**
     * Verify a Stripe-Signature header.
     *
     * Stripe sends: Stripe-Signature: t=<timestamp>,v1=<hex>
     * where <hex> is HMAC-SHA256 of "{timestamp}.{raw_body}"
     * using the webhook signing secret.
     *
     * The timestamp must be within 5 minutes of now. An
     * older signature is rejected as a replay.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $signatureHeader = $headers['stripe-signature'] ?? '';
        if ($signatureHeader === '') {
            return false;
        }

        $secret = trim($this->settings->string('payments.stripe.webhook_secret', ''));
        if ($secret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $pair) {
            $kv = explode('=', $pair, 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }
        $timestamp = (int) ($parts['t'] ?? 0);
        $expected  = $parts['v1'] ?? '';

        if ($timestamp <= 0 || $expected === '') {
            return false;
        }
        if (abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE) {
            return false;
        }

        $signedPayload = $timestamp . '.' . $rawBody;
        $computed = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($computed, $expected);
    }

    /**
     * The Stripe event id. Top-level 'id' key on the body.
     */
    public function extractEventId(string $rawBody): string
    {
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return '';
        }
        return (string) ($event['id'] ?? '');
    }

    /**
     * Act on a verified Stripe webhook event.
     *
     * Reads the event type and applies the order-side
     * effect. The caller (WebhookController) records the
     * event row before calling this; this method does not
     * touch commerce_webhook_events.
     *
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
        $eventType = (string) ($event['type'] ?? '');
        $object    = $event['data']['object'] ?? [];
        if (!is_array($object)) {
            $object = [];
        }

        $orderId = $this->orderIdFromObject($object);

        switch ($eventType) {
            case 'payment_intent.succeeded':
                return $this->handleSucceeded($orderId, $object, $eventId, $eventType);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentFailed($orderId, $object, $eventId, $eventType);

            case 'charge.refunded':
            case 'charge.refund.updated':
                // The refund row is already updated by
                // PaymentRefundService when it calls the
                // refund endpoint. The webhook is the
                // confirmation that Stripe processed the
                // refund. Nothing to write.
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

    private function secretKey(): string
    {
        return trim($this->settings->string('payments.stripe.secret_key', ''));
    }

    /**
     * The order id from a Stripe event object's metadata.
     * The charge flow sets metadata[order_id] on the
     * Checkout Session; Stripe carries it through to the
     * PaymentIntent and the charge.
     *
     * @param array<string,mixed> $object
     */
    private function orderIdFromObject(array $object): int
    {
        $meta = $object['metadata'] ?? [];
        if (is_array($meta) && isset($meta['order_id'])) {
            return (int) $meta['order_id'];
        }
        return 0;
    }

    /**
     * @param array<string,mixed> $object
     * @return array{handled: bool, event_type: string,
     *               event_id?: string, order_id?: int,
     *               note?: string}
     */
    private function handleSucceeded(int $orderId, array $object, string $eventId, string $eventType): array
    {
        if ($orderId <= 0) {
            return ['handled' => false, 'event_type' => $eventType,
                    'event_id' => $eventId, 'note' => 'No order id in metadata.'];
        }
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            return ['handled' => false, 'event_type' => $eventType,
                    'event_id' => $eventId, 'order_id' => $orderId,
                    'note' => 'Order not found.'];
        }

        // Only transition an order that is still unpaid.
        // Idempotency is at the row level; the row exists
        // once, but a re-delivered webhook with the same
        // event id is a no-op in the controller. The extra
        // guard here protects against a later event
        // overwriting a terminal state.
        if ($order->paymentStatus === 'paid') {
            return ['handled' => true, 'event_type' => $eventType,
                    'event_id' => $eventId, 'order_id' => $orderId,
                    'note' => 'Order already marked paid.'];
        }

        $paymentIntentId = (string) ($object['id'] ?? '');

        $this->orders->update($orderId, [
            'payment_status'    => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
            'payment_reference' => $paymentIntentId,
        ]);

        return ['handled' => true, 'event_type' => $eventType,
                'event_id' => $eventId, 'order_id' => $orderId,
                'note' => 'Order marked paid.'];
    }

    /**
     * @param array<string,mixed> $object
     * @return array{handled: bool, event_type: string,
     *               event_id?: string, order_id?: int,
     *               note?: string}
     */
    private function handlePaymentFailed(int $orderId, array $object, string $eventId, string $eventType): array
    {
        if ($orderId <= 0) {
            return ['handled' => false, 'event_type' => $eventType,
                    'event_id' => $eventId, 'note' => 'No order id in metadata.'];
        }
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            return ['handled' => false, 'event_type' => $eventType,
                    'event_id' => $eventId, 'order_id' => $orderId,
                    'note' => 'Order not found.'];
        }
        // A payment_failed event does not change the order's
        // payment_status to failed at the order level. The
        // order stays unpaid; the customer can retry. Only
        // the payment_reference and a note record the attempt.
        // The order's status is still 'pending_payment'.
        return ['handled' => true, 'event_type' => $eventType,
                'event_id' => $eventId, 'order_id' => $orderId,
                'note' => 'Payment failed. Order remains unpaid for retry.'];
    }

    /**
     * The provider's error message from a non-2xx response
     * body. Stripe returns { error: { message: "..." } } on
     * a failed call. Falls back to the HTTP status when the
     * body has no recognisable error object.
     *
     * @param array<string,mixed> $body
     */
    private function extractError(array $body): string
    {
        $err = $body['error'] ?? null;
        if (is_array($err) && isset($err['message'])) {
            return (string) $err['message'];
        }
        return 'Stripe returned status ' . (string) ($body['status'] ?? 'unknown');
    }
}
