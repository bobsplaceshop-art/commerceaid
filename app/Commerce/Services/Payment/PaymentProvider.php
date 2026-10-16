<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Models\Order;

/**
 * The payment provider contract.
 *
 * Every payment provider (Stripe, PayPal, and the six
 * sub-stage providers that follow) implements this
 * interface. The registry resolves a provider key from
 * commerce_store_payment_methods to a concrete instance.
 *
 * Providers are stateful only in the sense that they hold
 * their credentials. They do not hold a cart, an order, or
 * a session. Every method takes what it needs.
 *
 * Return shapes are arrays, not objects. That keeps the
 * interface small and each implementation free of a
 * shared result type. Callers that want a typed result
 * (PaymentRefundService) wrap the array into their own
 * value object.
 *
 * The interface makes no assumption about how the
 * provider's API works. Redirect-based providers
 * (Stripe Checkout, PayPal Orders v2) and direct-charge
 * providers (the M-Pesa STK push, a future card-present
 * terminal) both fit. The charge() return array carries
 * an optional redirect_url for the redirect flow; a
 * direct-charge provider returns the array without it.
 */
interface PaymentProvider
{
    /**
     * The registry key. Matches
     * commerce_orders.payment_provider_key and
     * commerce_store_payment_methods.provider_key.
     *
     * Lowercase, no spaces. Examples: 'stripe', 'paypal',
     * 'mpesa'.
     */
    public function key(): string;

    /**
     * The human label rendered in the checkout method
     * list and in the admin order detail.
     *
     * Examples: 'Stripe', 'PayPal', 'M-Pesa'.
     */
    public function label(): string;

    /**
     * Whether this provider is fully configured for the
     * given store. A provider with missing credentials
     * returns false; the checkout method list skips it.
     */
    public function isConfigured(StoreContext $ctx): bool;

    /**
     * Probe the provider's API with the stored credentials and
     * report whether the connection is live.
     *
     * Unlike isConfigured() (a local "are the fields filled in"
     * check), this makes one real outbound call to the provider.
     * It answers the question the merchant actually asks when
     * they click "Test connection": does the key on file work.
     *
     * The call must be read-only. No charges, no order creation,
     * no side effects. For Stripe: GET /v1/balance. For PayPal:
     * the OAuth token handshake, which is what every subsequent
     * PayPal API call does.
     *
     * Returns a message the admin flash shows verbatim. The
     * message describes what happened, in merchant-readable
     * terms: "Stripe responded with account status: active",
     * "PayPal rejected the credentials (invalid_client)", "The
     * request timed out".
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Charge the customer for the order's grand total.
     *
     * For redirect-based providers (Stripe Checkout,
     * PayPal Orders v2), this creates the session or
     * order on the provider side and returns the URL the
     * customer must be redirected to. The order's
     * payment_status stays 'unpaid' until the webhook
     * confirms.
     *
     * For direct-charge providers, this captures inline
     * and returns the provider reference. The order's
     * payment_status is set by the caller once the
     * response indicates success.
     *
     * @param array<string,mixed> $context  Caller-supplied
     *        context. Recognised keys: 'return_url',
     *        'cancel_url', 'customer_email'. Providers
     *        ignore keys they do not use.
     *
     * @return array{
     *     ok: bool,
     *     redirect_url?: string,
     *     provider_reference?: string,
     *     error?: string
     * }
     */
    public function charge(Order $order, array $context): array;

    /**
     * Capture an authorized charge, when the provider's
     * flow requires an explicit capture step. PayPal's
     * Orders v2 flow requires it. Stripe's default
     * PaymentIntent captures automatically; a Stripe
     * implementation that uses manual capture implements
     * this method and a charge() that requests manual
     * capture.
     *
     * Providers that do not require a separate capture
     * return ok = true with no side effect.
     *
     * @return array{
     *     ok: bool,
     *     provider_reference?: string,
     *     error?: string
     * }
     */
    public function capture(Order $order, string $providerReference): array;

    /**
     * Refund an amount against the original charge.
     *
     * The caller (PaymentRefundService) has already
     * written the intent to the order. This method's job
     * is to move the money. It does not touch the order
     * columns.
     *
     * The idempotency key is passed through to the
     * provider when the provider supports it (Stripe's
     * Idempotency-Key header, PayPal's
     * PayPal-Request-Id). When the provider does not
     * support one, the caller's unique key on
     * commerce_payment_refunds is the deduplication
     * mechanism.
     *
     * @return array{
     *     ok: bool,
     *     refund_id?: string,
     *     error?: string
     * }
     */
    public function refund(Order $order, float $amount, string $idempotencyKey): array;

    /**
     * Read the provider's stable event identifier from a
     * webhook body.
     *
     * Each provider carries the id under a different path:
     *
     *   Stripe  - $event['id']       ("evt_...")
     *   PayPal  - $event['id']       (the event resource id)
     *   M-Pesa  - $event['Body']['stkCallback']['CheckoutRequestID']
     *
     * The value is used as the idempotency key on
     * commerce_webhook_events, paired with the provider
     * key. Two deliveries of the same event carry the same
     * value; two different events never share one.
     *
     * Called by WebhookController after verifyWebhook()
     * returns true. A malformed body returns '' and the
     * controller refuses the request with 400.
     */
    public function extractEventId(string $rawBody): string;

    /**
     * Verify an incoming webhook's signature.
     *
     * Must return false on any mismatch: wrong algorithm,
     * wrong key, expired timestamp, missing header. Must
     * never throw on malformed input; a verification
     * failure is a false return, not an exception.
     *
     * @param array<string,string> $headers  Lowercased
     *        header names. The controller normalises
     *        before calling.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Handle a verified webhook event.
     *
     * Called only after verifyWebhook() returns true.
     * The implementation reads the event type from the
     * body, applies the order-side effect (mark paid,
     * update a refund row), and returns a description
     * of what happened.
     *
     * The caller (WebhookController) is responsible for
     * recording the event in commerce_webhook_events
     * with its provider event id for idempotency. The
     * provider method does not do this itself; it only
     * acts on the event.
     *
     * @param array<string,string> $headers  Lowercased
     *        header names. The controller normalises
     *        before calling.
     *
     * @return array{
     *     handled: bool,
     *     event_type: string,
     *     event_id?: string,
     *     order_id?: int,
     *     note?: string
     * }
     */
    public function handleWebhook(string $rawBody, array $headers): array;
}
