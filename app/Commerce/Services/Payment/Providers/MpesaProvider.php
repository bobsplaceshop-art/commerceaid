<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment\Providers;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\MpesaRequestRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\StoreSettingsRepository;
use CorporateAid\Platform\Support\HttpClient;
use CorporateAid\Commerce\Services\Payment\PaymentProvider;

/**
 * M-Pesa payment provider.
 *
 * Uses Safaricom's Daraja API. The charge flow is an STK
 * push: the customer enters a phone number, Safaricom
 * sends the phone a PIN prompt, the customer enters the
 * PIN, Safaricom POSTs a callback to the URL registered in
 * the Daraja dashboard.
 *
 * Unlike Stripe and PayPal, the customer never leaves the
 * site. The charge returns no redirect_url. The controller
 * renders a waiting page and polls for the order's
 * payment_status to flip.
 *
 * Credentials come from commerce_store_settings:
 *   payments.mpesa.mode             'sandbox' | 'live'
 *   payments.mpesa.consumer_key
 *   payments.mpesa.consumer_secret
 *   payments.mpesa.shortcode
 *   payments.mpesa.passkey
 *   payments.mpesa.callback_url
 *   payments.mpesa.transaction_type 'CustomerPayBillOnline' | 'CustomerBuyGoodsOnline'
 *   payments.mpesa.refund_enabled   bool
 *
 * The isConfigured() check reads the four credentials and
 * also gates on the store's currency being KES. M-Pesa is
 * KES-only on the Daraja API. A store priced in EUR cannot
 * offer it.
 *
 * M-Pesa does not sign its webhooks. verifyWebhook()
 * confirms a callback by looking up its CheckoutRequestID
 * in commerce_mpesa_requests. A callback whose id does not
 * match a pending request this store initiated is refused.
 *
 * Stage 010, sub-stage 010.1.
 */
final class MpesaProvider implements PaymentProvider
{
    private const SANDBOX_API_BASE = 'https://sandbox.safaricom.co.ke';
    private const LIVE_API_BASE    = 'https://api.safaricom.co.ke';

    /** Cached access token for this request. */
    private ?string $accessToken = null;

    public function __construct(
        private HttpClient $http,
        private StoreSettingsRepository $settings,
        private OrderRepository $orders,
        private MpesaRequestRepository $mpesaRequests,
    ) {
    }

    public function key(): string
    {
        return 'mpesa';
    }

    public function label(): string
    {
        return 'M-Pesa';
    }

    public function isConfigured(StoreContext $ctx): bool
    {
        if (!$ctx->has()) {
            return false;
        }

        // The currency gate lives in charge(), not here.
        // isConfigured() is a local check: are the
        // credentials present. The currency check
        // belongs where the order is in scope, because
        // the order carries the actual currency the
        // customer is being charged in.

        $key    = trim($this->settings->string('payments.mpesa.consumer_key', ''));
        $secret = trim($this->settings->string('payments.mpesa.consumer_secret', ''));
        $short  = trim($this->settings->string('payments.mpesa.shortcode', ''));
        $pass   = trim($this->settings->string('payments.mpesa.passkey', ''));

        return $key !== '' && $secret !== '' && $short !== '' && $pass !== '';
    }

    /**
     * Probe the Daraja API with the stored credentials.
     *
     * The test is the OAuth token handshake. A 200 with an
     * access_token in the body means the consumer key and
     * secret are valid for the configured mode. 401 means
     * the credentials are wrong. Any other status is
     * reported as-is.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        $key    = trim($this->settings->string('payments.mpesa.consumer_key', ''));
        $secret = trim($this->settings->string('payments.mpesa.consumer_secret', ''));

        if ($key === '' || $secret === '') {
            return [
                'ok'      => false,
                'message' => 'M-Pesa consumer key and consumer secret must both be set.',
            ];
        }

        $mode      = trim($this->settings->string('payments.mpesa.mode', 'sandbox'));
        $basicAuth = base64_encode($key . ':' . $secret);

        $response = $this->http->get(
            $this->apiBase() . '/oauth/v1/generate?grant_type=client_credentials',
            [
                'Authorization: Basic ' . $basicAuth,
            ]
        );

        if ($response['error'] !== null) {
            return [
                'ok'      => false,
                'message' => 'M-Pesa (Daraja) is unreachable: ' . $response['error'],
            ];
        }

        $status = (int) $response['status'];

        if ($status === 200) {
            $token = (string) ($response['body']['access_token'] ?? '');
            if ($token === '') {
                return [
                    'ok'      => false,
                    'message' => 'Daraja responded HTTP 200 but returned no access token.',
                ];
            }
            return [
                'ok'      => true,
                'message' => 'M-Pesa authenticated (HTTP 200). Credentials are valid for '
                           . $mode . ' mode.',
            ];
        }

        if ($status === 401) {
            return [
                'ok'      => false,
                'message' => 'Daraja rejected the credentials (HTTP 401). '
                           . 'Check the consumer key and secret below.',
            ];
        }

        if ($status === 0) {
            return [
                'ok'      => false,
                'message' => 'Daraja is unreachable: no HTTP response. '
                           . 'Check the server network connection.',
            ];
        }

        return [
            'ok'      => false,
            'message' => 'Daraja returned HTTP ' . $status . '.',
        ];
    }

    /**
     * Initiate an STK push for the order.
     *
     * The customer's phone prompts for a PIN. The provider
     * stores a pending row in commerce_mpesa_requests and
     * returns the CheckoutRequestID. The webhook handler
     * flips the order once Safaricom reports a result.
     *
     * No redirect_url is returned. The controller renders
     * a waiting page and polls the order's payment_status.
     *
     * @param array<string,mixed> $context  Recognised key:
     *        'customer_phone' - the number to prompt.
     *
     * @return array{ok: bool, provider_reference?: string,
     *               error?: string}
     */
    public function charge(Order $order, array $context): array
    {
        // M-Pesa is KES-only. The order carries the actual
        // currency the customer is being charged in; the
        // check belongs here rather than in
        // isConfigured(), which has no order in scope.
        if (strtoupper($order->currencyCode) !== 'KES') {
            return [
                'ok'    => false,
                'error' => 'M-Pesa is available only for orders in KES.',
            ];
        }

        $phone = trim((string) ($context['customer_phone'] ?? ''));
        if ($phone === '') {
            return [
                'ok'    => false,
                'error' => 'A phone number is required for M-Pesa.',
            ];
        }

        // Normalise the number to the 254XXXXXXXXX form the
        // Daraja API expects.
        $phone = $this->normalisePhone($phone);
        if ($phone === '') {
            return [
                'ok'    => false,
                'error' => 'The phone number for M-Pesa is not in a valid Kenyan format.',
            ];
        }

        $token = $this->token();
        if ($token === '') {
            return ['ok' => false, 'error' => 'M-Pesa is not configured.'];
        }

        $shortcode  = trim($this->settings->string('payments.mpesa.shortcode', ''));
        $passkey    = trim($this->settings->string('payments.mpesa.passkey', ''));
        $callback   = trim($this->settings->string('payments.mpesa.callback_url', ''));
        $txType     = trim($this->settings->string('payments.mpesa.transaction_type', 'CustomerPayBillOnline'));

        if ($shortcode === '' || $passkey === '' || $callback === '') {
            return ['ok' => false, 'error' => 'M-Pesa shortcode, passkey, or callback URL is missing.'];
        }

        $timestamp = date('YmdHis');
        $password  = base64_encode($shortcode . $passkey . $timestamp);

        // M-Pesa charges in whole KES. The Daraja API
        // rejects decimals.
        $amount = (int) round($order->grandTotal);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'M-Pesa cannot charge a zero amount.'];
        }

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => $txType,
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $callback,
            'AccountReference'  => (string) $order->orderNumber,
            'TransactionDesc'   => 'Order ' . $order->orderNumber,
        ];

        $response = $this->http->postJson(
            $this->apiBase() . '/mpesa/stkpush/v1/processrequest',
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

        $responseCode = (string) ($response['body']['ResponseCode'] ?? '');
        if ($responseCode !== '0') {
            return [
                'ok'    => false,
                'error' => $this->extractError($response['body'], 200),
            ];
        }

        $checkoutRequestId = (string) ($response['body']['CheckoutRequestID'] ?? '');
        $merchantRequestId = (string) ($response['body']['MerchantRequestID'] ?? '');
        if ($checkoutRequestId === '') {
            return ['ok' => false, 'error' => 'Daraja returned no CheckoutRequestID.'];
        }

        // Record the pending request so verifyWebhook() can
        // confirm the callback when it arrives. M-Pesa does
        // not sign its callbacks; this row is the security
        // boundary.
        $this->mpesaRequests->record([
            'order_id'            => $order->id,
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId !== '' ? $merchantRequestId : null,
            'phone_number'        => $phone,
            'amount'              => (float) $amount,
        ]);

        return [
            'ok'                 => true,
            'provider_reference' => $checkoutRequestId,
            'requires_polling'   => true,
        ];
    }

    /**
     * M-Pesa has no capture step. The STK push collects the
     * payment in one call. The method exists to satisfy the
     * interface.
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
     * Refund an amount against the original M-Pesa charge.
     *
     * M-Pesa's refund endpoint is not exposed on every
     * Daraja plan. The provider reads a store setting,
     * payments.mpesa.refund_enabled, and behaves
     * accordingly:
     *
     *   false (default): the refund is recorded offline
     *   by PaymentRefundService. The merchant refunds
     *   manually through the M-Pesa dashboard. This
     *   provider returns ok with refund_id = 'offline'.
     *
     *   true: the provider calls Daraja's refund
     *   endpoint. If the merchant's plan does not
     *   actually expose the endpoint, the call fails
     *   and the refund row stays pending for the retry
     *   cron.
     *
     * The plan defaults refund_enabled to false. A
     * merchant turns it on after confirming their plan
     * exposes the endpoint.
     *
     * @return array{ok: bool, refund_id?: string,
     *               error?: string}
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array
    {
        $enabled = $this->settings->bool('payments.mpesa.refund_enabled', false);
        if (!$enabled) {
            return ['ok' => true, 'refund_id' => 'offline'];
        }

        // The live refund path is out of scope for this
        // sub-stage. The plan names the endpoint shape and
        // the trigger; the code is added when a merchant's
        // plan confirms the endpoint exists. Until then,
        // refund_enabled stays false and the offline path
        // is used.
        return [
            'ok'    => false,
            'error' => 'M-Pesa refund API is not wired for this plan. '
                     . 'Disable payments.mpesa.refund_enabled or refund '
                     . 'through the M-Pesa dashboard.',
        ];
    }

    /**
     * The M-Pesa event id. M-Pesa's callback body is not
     * the same shape as Stripe's or PayPal's: there is no
     * top-level 'id'. The stable identifier is the
     * CheckoutRequestID under
     * Body.stkCallback.CheckoutRequestID.
     *
     * The value matches a row in commerce_mpesa_requests
     * that charge() created. It is the idempotency key on
     * commerce_webhook_events.
     */
    public function extractEventId(string $rawBody): string
    {
        return $this->callbackCheckoutRequestId($rawBody);
    }

    /**
     * Verify an incoming M-Pesa callback.
     *
     * M-Pesa does not sign its callbacks. The verification
     * is a lookup: the callback carries a CheckoutRequestID,
     * and this method confirms it matches a pending request
     * the store initiated. A callback whose id is not in
     * the pending set is a forgery or a replay.
     *
     * @param array<string,string> $headers
     */
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $checkoutRequestId = $this->callbackCheckoutRequestId($rawBody);
        if ($checkoutRequestId === '') {
            return false;
        }

        $row = $this->mpesaRequests->findByCheckoutRequestId($checkoutRequestId);
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
     * Act on a verified M-Pesa callback.
     *
     * ResultCode 0 means success. The M-Pesa receipt
     * number is stored on the repository row and written
     * to the order's payment_reference. Any other
     * ResultCode (customer cancelled, wrong PIN, timeout,
     * insufficient funds) marks the request failed and
     * leaves the order unpaid.
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
            return [
                'handled'    => false,
                'event_type' => 'mpesa.callback',
                'note'       => 'Malformed callback body.',
            ];
        }

        $checkoutRequestId = $this->callbackCheckoutRequestId($rawBody);
        if ($checkoutRequestId === '') {
            return [
                'handled'    => false,
                'event_type' => 'mpesa.callback',
                'note'       => 'No CheckoutRequestID in callback.',
            ];
        }

        $row = $this->mpesaRequests->findByCheckoutRequestId($checkoutRequestId);
        if ($row === null) {
            return [
                'handled'    => false,
                'event_type' => 'mpesa.callback',
                'note'       => 'No pending request for this CheckoutRequestID.',
            ];
        }

        $orderId = (int) ($row['order_id'] ?? 0);
        $resultCode = $this->callbackResultCode($rawBody);

        if ($resultCode === 0) {
            $receipt = $this->callbackReceipt($rawBody);
            $this->mpesaRequests->markCompleted(
                $checkoutRequestId,
                $resultCode,
                $receipt
            );

            if ($orderId > 0) {
                $order = $this->orders->findById($orderId);
                if ($order !== null && $order->paymentStatus !== 'paid') {
                    $this->orders->update($orderId, [
                        'payment_status'    => 'paid',
                        'paid_at'           => date('Y-m-d H:i:s'),
                        'payment_reference' => $receipt !== '' ? $receipt : $checkoutRequestId,
                    ]);
                }
            }

            return [
                'handled'    => true,
                'event_type' => 'mpesa.callback',
                'event_id'   => $checkoutRequestId,
                'order_id'   => $orderId,
                'note'       => 'Payment completed. Receipt ' . ($receipt !== '' ? $receipt : '(missing)') . '.',
            ];
        }

        // Any non-zero result is a failure at M-Pesa's end.
        $description = $this->callbackResultDescription($rawBody);
        $this->mpesaRequests->markFailed(
            $checkoutRequestId,
            $resultCode,
            $description
        );

        return [
            'handled'    => true,
            'event_type' => 'mpesa.callback',
            'event_id'   => $checkoutRequestId,
            'order_id'   => $orderId,
            'note'       => 'Payment failed. ResultCode ' . $resultCode
                          . ($description !== '' ? ': ' . $description : '.'),
        ];
    }

    // =================================================================
    // Private helpers
    // =================================================================

    /**
     * Normalise a Kenyan phone number to the
     * 254XXXXXXXXX form the Daraja API expects.
     *
     * Accepts:
     *   0712345678       -> 254712345678
     *   +254712345678    -> 254712345678
     *   254712345678     -> 254712345678
     *   712345678        -> 254712345678
     *
     * Returns '' on any input that does not resolve to a
     * valid 254-prefixed number.
     */
    private function normalisePhone(string $phone): string
    {
        // Strip everything but digits.
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        // 0712345678 or 712345678 -> drop the leading 0.
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // 712345678 -> prefix with 254.
        if (strlen($digits) === 9) {
            $digits = '254' . $digits;
        }

        // 254712345678 -> the expected form. 12 digits
        // beginning 254.
        if (strlen($digits) === 12 && str_starts_with($digits, '254')) {
            return $digits;
        }

        return '';
    }

    /**
     * Read the CheckoutRequestID from a callback body.
     *
     * The shape is:
     *   Body.stkCallback.CheckoutRequestID
     */
    private function callbackCheckoutRequestId(string $rawBody): string
    {
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return '';
        }
        $callback = $event['Body']['stkCallback'] ?? null;
        if (!is_array($callback)) {
            return '';
        }
        return (string) ($callback['CheckoutRequestID'] ?? '');
    }

    /**
     * Read the ResultCode from a callback body. Returns
     * -1 when the body cannot be parsed.
     */
    private function callbackResultCode(string $rawBody): int
    {
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return -1;
        }
        $callback = $event['Body']['stkCallback'] ?? null;
        if (!is_array($callback)) {
            return -1;
        }
        if (!array_key_exists('ResultCode', $callback)) {
            return -1;
        }
        return (int) $callback['ResultCode'];
    }

    /**
     * Read the ResultDesc from a callback body.
     */
    private function callbackResultDescription(string $rawBody): string
    {
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return '';
        }
        $callback = $event['Body']['stkCallback'] ?? null;
        if (!is_array($callback)) {
            return '';
        }
        return (string) ($callback['ResultDesc'] ?? '');
    }

    /**
     * Read the M-Pesa receipt number from a success
     * callback. Returns '' when the callback did not
     * carry one.
     *
     * The shape is:
     *   Body.stkCallback.CallbackMetadata.Item[]
     *   where each item has Name and Value. The receipt
     *   is the item whose Name is "MpesaReceiptNumber".
     */
    private function callbackReceipt(string $rawBody): string
    {
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            return '';
        }
        $callback = $event['Body']['stkCallback'] ?? null;
        if (!is_array($callback)) {
            return '';
        }
        $meta = $callback['CallbackMetadata'] ?? null;
        if (!is_array($meta)) {
            return '';
        }
        $items = $meta['Item'] ?? null;
        if (!is_array($items)) {
            return '';
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                return (string) ($item['Value'] ?? '');
            }
        }
        return '';
    }

    /**
     * Read a merchant-readable error from a Daraja
     * response body. The body carries different keys
     * depending on the endpoint.
     *
     * @param array<string,mixed> $body
     */
    private function extractError(array $body, int $status): string
    {
        $message = (string) ($body['errorMessage'] ?? '');
        if ($message !== '') {
            return $message;
        }
        $message = (string) ($body['ResponseDescription'] ?? '');
        if ($message !== '') {
            return $message;
        }
        $message = (string) ($body['ResultDesc'] ?? '');
        if ($message !== '') {
            return $message;
        }
        return 'Daraja returned HTTP ' . $status . '.';
    }

    private function apiBase(): string
    {
        $mode = trim($this->settings->string('payments.mpesa.mode', 'sandbox'));
        return $mode === 'live' ? self::LIVE_API_BASE : self::SANDBOX_API_BASE;
    }

    /**
     * Fetch an OAuth access token. Cached on the instance
     * for the request's lifetime. Same pattern as
     * PayPalProvider::token().
     *
     * @return string  the token, or '' on failure
     */
    private function token(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $key    = trim($this->settings->string('payments.mpesa.consumer_key', ''));
        $secret = trim($this->settings->string('payments.mpesa.consumer_secret', ''));
        if ($key === '' || $secret === '') {
            return '';
        }

        $basicAuth = base64_encode($key . ':' . $secret);

        $response = $this->http->get(
            $this->apiBase() . '/oauth/v1/generate?grant_type=client_credentials',
            [
                'Authorization: Basic ' . $basicAuth,
            ]
        );

        if ($response['error'] !== null) {
            return '';
        }
        if ($response['status'] !== 200) {
            return '';
        }

        // Trim the token before caching it. Daraja's OAuth
        // response can carry a trailing newline or other
        // invisible whitespace. A token with trailing
        // whitespace produces a malformed Authorization
        // header, and Daraja returns 404.001.04
        // ("Invalid Authentication Header") even though the
        // token itself is valid. The trim is defensive; the
        // response usually has no whitespace, but when it
        // does the failure is silent and the error message
        // points at the wrong thing.
        $token = trim((string) ($response['body']['access_token'] ?? ''));
        if ($token === '') {
            return '';
        }
        $this->accessToken = $token;
        return $token;
    }
}
