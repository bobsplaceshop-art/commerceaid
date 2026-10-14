<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Payment;

use CorporateAid\Commerce\Models\Order;
use CorporateAid\Platform\Support\Logger;

/**
 * The charge wrapper.
 *
 * Symmetric to PaymentRefundService. Called after
 * CheckoutService::placeOrder() creates the order. Resolves
 * the provider from the order's payment_provider_key and
 * calls Provider::charge(). Returns a ChargeResult the
 * controller reads to decide the next redirect.
 *
 * The service does not write to the database. The order is
 * already placed. The webhook is what writes the payment
 * state (payment_status = 'paid', paid_at, payment_reference)
 * once the provider confirms.
 *
 * Three outcomes beyond the offline case:
 *
 *   1. Offline method. cod, bank_transfer, purchase_order,
 *      manual, or empty. No provider call. The controller
 *      redirects straight to confirmation.
 *
 *   2. Provider resolves, redirect flow. The provider
 *      returns a redirect_url (Stripe Checkout, PayPal
 *      approval page). The controller sends the customer
 *      there.
 *
 *   3. Provider resolves, direct-charge flow. The provider
 *      returns a provider_reference without a redirect_url.
 *      The order's payment_status is set by the caller
 *      based on the provider response.
 *
 * A provider that fails to resolve (its class has not
 * shipped, or its credentials are missing) produces a
 * ChargeResult::failed(). The order stays 'unpaid'; the
 * customer can retry on the confirm page.
 *
 * Stage 010 Session 3.
 */
final class PaymentChargeService
{
    /**
     * Payment provider keys handled without a provider call.
     * The order was not charged through an external API; the
     * confirmation page is the record.
     *
     * @var array<int, string>
     */
    private const OFFLINE_KEYS = [
        'cod',
        'bank_transfer',
        'purchase_order',
        'manual',
        '',
    ];

    public function __construct(
        private PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * Dispatch the charge for a placed order.
     *
     * The order is already created with payment_status =
     * 'unpaid'. This method's job is to obtain a redirect
     * URL, a provider reference, or a failure reason.
     */
    public function charge(Order $order): ChargeResult
    {
        $providerKey = (string) ($order->paymentProviderKey ?? '');

        // Offline path: no provider, no call.
        if (in_array($providerKey, self::OFFLINE_KEYS, true)) {
            return ChargeResult::offline(
                $order->id,
                $providerKey !== '' ? $providerKey : 'offline'
            );
        }

        // Resolve the provider.
        $provider = $this->providers->resolve($providerKey);
        if ($provider === null) {
            Logger::info('payment.charge.provider-missing', [
                'order_id'     => $order->id,
                'provider_key' => $providerKey,
            ]);
            return ChargeResult::failed(
                $order->id,
                $providerKey,
                'The selected payment method is not available right now. '
                . 'Please choose another method.'
            );
        }

        // Build the return and cancel URLs the provider will
        // use. Both target the storefront, both carry the
        // order number so the return controller can resolve
        // the order when the customer comes back.
        //
        // The success URL also carries the Stripe Checkout
        // session-id placeholder. Stripe substitutes the
        // actual session id when the customer lands. The
        // return controller reads it to verify the intent
        // against Stripe's API.
        $appUrl    = rtrim((string) config('app.url', ''), '/');
        $returnUrl = $appUrl . '/checkout/stripe/return?order='
                   . rawurlencode((string) $order->orderNumber)
                   . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $appUrl . '/checkout/confirm';

        $response = $provider->charge($order, [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ]);

        if (($response['ok'] ?? false) !== true) {
            $error = (string) ($response['error'] ?? 'The payment provider could not start a charge.');
            Logger::info('payment.charge.failed', [
                'order_id' => $order->id,
                'provider' => $providerKey,
                'error'    => $error,
            ]);
            return ChargeResult::failed($order->id, $providerKey, $error);
        }

        $redirectUrl       = (string) ($response['redirect_url'] ?? '');
        $providerReference = (string) ($response['provider_reference'] ?? '');

        if ($redirectUrl !== '') {
            return ChargeResult::redirected(
                $order->id,
                $providerKey,
                $redirectUrl,
                $providerReference
            );
        }

        // No redirect URL means the provider captured inline.
        // The order's payment_status is set by the caller.
        return ChargeResult::charged(
            $order->id,
            $providerKey,
            $providerReference
        );
    }
}
