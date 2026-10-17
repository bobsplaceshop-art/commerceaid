<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment\Providers;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\FlutterwaveRequestRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Commerce\Services\Payment\HttpClient;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;

/**
 * Flutterwave payment provider.
 *
 * Uses Flutterwave API v3 for the charge flow: create a
 * payment, redirect the customer to Flutterwave's hosted
 * page, receive the webhook when the customer completes.
 *
 * Credentials come from commerce_store_settings:
 *   payments.flutterwave.mode            'sandbox' | 'live'
 *   payments.flutterwave.public_key
 *   payments.flutterwave.secret_key
 *   payments.flutterwave.secret_hash
 *   payments.flutterwave.redirect_url
 *   payments.flutterwave.refund_enabled
 *
 * The isConfigured() check reads the secret key and the
 * secret hash. Both are required: the secret key is for
 * every API call (as a Bearer), the secret hash is for
 * the webhook's verif-hash comparison. A store missing
 * either cannot offer Flutterwave at checkout.
 *
 * The webhook signature is a plain shared secret, not
 * an HMAC of the payload. Flutterwave sends the value
 * the merchant configured in the dashboard as the
 * verif-hash header. Comparison is a string equality.
 * The second security layer is the tx_ref lookup in
 * commerce_flutterwave_requests: a webhook whose tx_ref
 * is not in the table is refused even if the header
 * matched.
 *
 * Stage 010, sub-stage 010.3.
 */
final class FlutterwaveProvider implements PaymentProvider
{
    private const API_BASE = 'https://api.flutterwave.com/v3';

    public function __construct(
        private HttpClient $http,
        private StoreSettingsRepository $settings,
        private OrderRepository $orders,
        private FlutterwaveRequestRepository $flutterwaveRequests,
    ) {
    }

    public function key(): string
    {
        return 'flutterwave';
    }

    public function label(): string
    {
        return 'Flutterwave';
    }

    public function isConfigured(StoreContext $ctx): bool
    {
        if (!$ctx->has()) {
            return false;
        }
        $secret = trim($this->settings->string('payments.flutterwave.secret_key', ''));
        $hash   = trim($this->settings->string('payments.flutterwave.secret_hash', ''));
        return $secret !== '' && $hash !== '';
    }

    /**
     * Probe Flutterwave's API with the stored secret key.
     *
     * GET /transactions is the cheapest read-only endpoint
     * that requires authentication. A 200 means the key is
     * valid and the API is reachable. A 401 means the key
     * is wrong or revoked.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $secret = $this->secretKey();
        if ($secret === '') {
            return [
                'ok'      => false,
                'message' => 'Flutterwave secret key is not set.',
            ];
        }

        $response = $this->http->get(
            self::API_BASE . '/transactions?page=1',
            [
                'Authorization: Bearer ' . $secret,
            ]
        );

        if ($response['error'] !== null) {
            return [
                'ok'      => false,
                'message' => 'Flutterwave is unreachable: ' . $response['error'],
            ];
        }

        $status = (int) $response['status'];
        $mode   = trim($this->settings->string('payments.flutterwave.mode', 'sandbox'));

        if ($status === 200) {
            return [
                'ok'      => true,
                'message' => 'Flutterwave authenticated (HTTP 200). Credentials are valid for '
                           . $mode . ' mode.',
            ];
        }

        if ($status === 401) {
            return [
                'ok'      => false,
                'message' => 'Flutterwave rejected the credentials (HTTP 401). '
                           . 'Check the secret key below.',
            ];
        }

        if ($status === 0) {
            return [
                'ok'      => false,
                'message' => 'Flutterwave is unreachable: no HTTP response. '
                           . 'Check the server network connection.',
            ];
        }

        return [
            'ok'      => false,
            'message' => 'Flutterwave returned HTTP ' . $status . '.',
        ];
    }

    /**
     * Create a Flutterwave payment and return the hosted
     * checkout URL.
     *
     * The customer is redirected to Flutterwave's hosted
     * page, pays, and Flutterwave sends them back to the
     * redirect URL the platform supplied. The order's
     * payment_status stays 'unpaid' until the webhook (or
     * the return controller's verify call) confirms the
     * payment.
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
        $secret = $this->secretKey();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Flutterwave is not configured.'];
        }

        $returnUrl = (string) ($context['return_url'] ?? '');
        if ($returnUrl === '') {
            return ['ok' => false, 'error' => 'Missing return URL for Flutterwave.'];
        }

        // The tx_ref is the platform's merchant reference
        // for this payment. It is unique per order and is
        // the idempotency key on commerce_flutterwave_requests.
        $txRef = 'ORD-' . $order->orderNumber;

        // Build the customer block. The order carries the
        // shipping address; Flutterwave expects name and
        // email. The email falls back to the guest email
        // or a placeholder (Flutterwave requires the field
        // to be present).
        $email = (string) ($order->guestEmail ?? '');
        if ($email === '') {
            $email = 'customer@example.invalid';
        }

        $payload = [
            'tx_ref'           => $txRef,
            'amount'           => round($order->grandTotal, 2),
            'currency'         => strtoupper($order->currencyCode),
            'redirect_url'     => $returnUrl,
            'payment_options'  => 'card,mobilemoney,ussd',
            'customer'         => [
                'email'       => $email,
                'name'        => 'Customer',
                'phonenumber' => '',
            ],
            'customizations'   => [
                'title'       => 'Order ' . $order->orderNumber,
                'description' => 'Order ' . $order->orderNumber,
            ],
            'meta'             => [
                'order_id'     => (string) $order->id,
                'order_number' => (string) $order->orderNumber,
            ],
        ];

        $response = $this->http->postJson(
            self::API_BASE . '/payments',
            $payload,
            [
                'Authorization: Bearer ' . $secret,
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

        $link = trim((string) ($response['body']['data']['link'] ?? ''));
        if ($link === '') {
            return ['ok' => false, 'error' => 'Flutterwave returned no checkout link.'];
        }

        // Record the pending row. The transaction_id is
        // not present in this response; it comes from the
        // webhook body or the return controller's verify
        // call. setTransactionId() writes it later.
        $this->flutterwaveRequests->record([
            'order_id'      => $order->id,
            'tx_ref'        => $txRef,
            'amount'        => (float) $order->grandTotal,
            'currency_code' => (string) $order->currencyCode,
        ]);

        return [
            'ok'                 => true,
            'redirect_url'       => $link,
            'provider_reference' => $txRef,
        ];
    }

    /**
     * Flutterwave has no explicit capture step. The
     * payment completes on Flutterwave's hosted page; the
     * webhook or the return controller's verify call
     * writes the paid state. The method exists to satisfy
     * the interface.
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
     * Refund an amount against the original Flutterwave
     * payment.
     *
     * Flutterwave supports partial refunds by amount.
     * The minimum refund is KES 10.
     *
     * The plan defaults refund_enabled to false. When
     * disabled, the refund is recorded offline by
     * PaymentRefundService without a Flutterwave call.
     * When enabled, the provider calls POST
     * /transactions/{id}/refund with the amount.
     *
     * @return array{ok: bool, refund_id?: string,
     *               error?: string}
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array
    {
        $enabled = $this->settings->bool('payments.flutterwave.refund_enabled', false);
        if (!$enabled) {
            return ['ok' => true, 'refund_id' => 'offline'];
        }

        $secret = $this->secretKey();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Flutterwave is not configured.'];
        }

        // The order's payment_reference carries the
        // Flutterwave transaction_id for a completed
        // payment (written by handleWebhook()).
        $transactionId = (string) ($order->paymentReference ?? '');
        if ($transactionId === '') {
            return ['ok' => false, 'error' => 'No transaction id on the order.'];
        }

        $payload = [
            'amount' => round($amount, 2),
        ];

        $response = $this->http->postJson(
            self::API_BASE . '/transactions/' . rawurlencode($transactionId) . '/refund',
            $payload,
            [
                'Authorization: Bearer ' . $secret,
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

        // Flutterwave returns 200 when the refund request
        // is received. The refund row stays pending until
        // Flutterwave confirms via the dashboard or a
        // future polling pass.
        $refundId = (string) ($response['body']['data']['id'] ?? '');
        return [
            'ok'        => true,
            'refund_id' => $refundId !== '' ? $refundId : 'flutterwave-pending',
        ];
    }

    /**
     * Read the current status of a Flutterwave
     * transaction.
     *
     * Public entry point for the return controller. The
     * webhook handler uses the same query internally;
     * this method exposes it without the repository
     * write.
     *
     * @return array{ok: bool, status?: string,
     *               transaction_id?: string,
     *               error?: string}
     */
    public function readTransactionStatus(string $transactionId): array
    {
        if ($transactionId === '') {
            return ['ok' => false, 'error' => 'Missing transaction id.'];
        }

        $secret = $this->secretKey();
        if ($secret === '') {
            return ['ok' => false, 'error' => 'Flutterwave is not configured.'];
        }

        $response = $this->http->get(
            self::API_BASE . '/transactions/'
                . rawurlencode($transactionId) . '/verify',
            [
                'Authorization: Bearer ' . $secret,
            ]
        );

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }
        if ($response['status'] !== 200) {
            return [
                'ok'    => false,
                'error' => 'Flutterwave verify returned HTTP ' . $response['status'],
            ];
        }

        return [
            'ok'             => true,
            'status'         => (string) ($response['body']['data']['status'] ?? ''),
            'transaction_id' => (string) ($response['body']['data']['id'] ?? ''),
        ];
    }

    /**
     * Verify a Flutterwave webhook.
     *
     * The verif-hash header carries the plain shared
     * secret the merchant configured in the Flutterwave
     * dashboard. This is NOT an HMAC of the payload.
     * Comparison is a string equality against the store's
     * payments.flutterwave.secret_hash setting.
     *
     * After the header matches, the tx_ref from the
     * body is confirmed against commerce_flutterwave_requests.
     * A webhook whose tx_ref is not in the table is
     * refused even if the header matched: an attacker
     * with the secret hash cannot forge a transaction
     * id for an order the store never initiated.
     *
     * @param array<string,string> $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $received = (string) ($headers['verif-hash'] ?? '');
        $expected = trim($this->settings->string('payments.flutterwave.secret_hash', ''));

        if ($received === '' || $expected === '') {
            return false;
        }

        if (!hash_equals($expected, $received)) {
            return false;
        }

        // Second layer: the tx_ref must match a pending row.
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return false;
        }

        $txRef = (string) ($body['data']['tx_ref'] ?? '');
        if ($txRef === '') {
            return false;
        }

        $row = $this->flutterwaveRequests->findByTxRef($txRef);
        if ($row === null) {
            return false;
        }

        if (($row['status'] ?? '') !== 'pending') {
            return false;
        }

        return true;
    }

    /**
     * The Flutterwave event id. The tx_ref from the
     * webhook body's data block.
     */
    public function extractEventId(string $rawBody): string
    {
        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return '';
        }
        return (string) ($body['data']['tx_ref'] ?? '');
    }

    /**
     * Act on a verified Flutterwave webhook.
     *
     * Reads the tx_ref and the Flutterwave transaction id
     * from the body. Calls GET /verify to read the
     * authoritative payment state. If 'successful', writes
     * the transaction id to the row and flips the order
     * to paid. Otherwise marks the row failed.
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
            return ['handled' => false, 'event_type' => 'flutterwave.webhook',
                    'note' => 'Malformed body.'];
        }

        $txRef = (string) ($body['data']['tx_ref'] ?? '');
        if ($txRef === '') {
            return ['handled' => false, 'event_type' => 'flutterwave.webhook',
                    'note' => 'No tx_ref in body.'];
        }

        $row = $this->flutterwaveRequests->findByTxRef($txRef);
        if ($row === null) {
            return ['handled' => false, 'event_type' => 'flutterwave.webhook',
                    'note' => 'No pending row for this tx_ref.'];
        }

        $orderId = (int) ($row['order_id'] ?? 0);

        // The transaction id comes from the body. Write
        // it to the row even before the verify call, so
        // a failed verify leaves a record.
        $transactionIdFromBody = (string) ($body['data']['id'] ?? '');
        if ($transactionIdFromBody !== '') {
            $this->flutterwaveRequests->setTransactionId($txRef, $transactionIdFromBody);
        }

        // Use the body's transaction id for the verify
        // call, or the one already stored if the body is
        // somehow missing it.
        $transactionId = $transactionIdFromBody !== ''
            ? $transactionIdFromBody
            : (string) ($row['transaction_id'] ?? '');

        if ($transactionId === '') {
            return ['handled' => false, 'event_type' => 'flutterwave.webhook',
                    'order_id' => $orderId,
                    'note' => 'No Flutterwave transaction id available.'];
        }

        $statusResult = $this->readTransactionStatus($transactionId);
        if (($statusResult['ok'] ?? false) !== true) {
            return ['handled' => false, 'event_type' => 'flutterwave.webhook',
                    'order_id' => $orderId,
                    'note' => 'Verify query failed: '
                            . (string) ($statusResult['error'] ?? 'unknown')];
        }

        $status = (string) ($statusResult['status'] ?? '');

        if ($status === 'successful') {
            $this->flutterwaveRequests->markCompleted($txRef, $transactionId);

            if ($orderId > 0) {
                $order = $this->orders->findById($orderId);
                if ($order !== null && $order->paymentStatus !== 'paid') {
                    $this->orders->update($orderId, [
                        'payment_status'    => 'paid',
                        'paid_at'           => date('Y-m-d H:i:s'),
                        'payment_reference' => $transactionId,
                    ]);
                }
            }

            return [
                'handled'    => true,
                'event_type' => 'flutterwave.webhook',
                'event_id'   => $txRef,
                'order_id'   => $orderId,
                'note'       => 'Payment completed. Transaction: ' . $transactionId,
            ];
        }

        $this->flutterwaveRequests->markFailed(
            $txRef,
            $status !== '' ? $status : 'Unknown status'
        );

        return [
            'handled'    => true,
            'event_type' => 'flutterwave.webhook',
            'event_id'   => $txRef,
            'order_id'   => $orderId,
            'note'       => 'Payment not successful. Status: ' . $status,
        ];
    }

    // =================================================================
    // Private helpers
    // =================================================================

    /**
     * Read a merchant-readable error from a Flutterwave
     * response body. Flutterwave's error shape is usually:
     *   {"status":"error","message":"...","data":null}
     *
     * @param array<string,mixed> $body
     */
    private function extractError(array $body, int $status): string
    {
        $message = (string) ($body['message'] ?? '');
        if ($message !== '') {
            return $message;
        }

        $data = $body['data'] ?? null;
        if (is_array($data)) {
            $innerMessage = (string) ($data['message'] ?? '');
            if ($innerMessage !== '') {
                return $innerMessage;
            }
        }

        return 'Flutterwave returned HTTP ' . $status . '.';
    }

    private function secretKey(): string
    {
        return trim($this->settings->string('payments.flutterwave.secret_key', ''));
    }
}
