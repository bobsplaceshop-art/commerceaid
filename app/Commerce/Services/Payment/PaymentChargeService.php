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
    /**
     * @param array<string,mixed> $context  Caller-supplied
     *        data the provider needs beyond the order. The
     *        M-Pesa flow passes 'mpesa_phone' here. Other
     *        callers pass nothing and get the default.
     */
    public function charge(Order $order, array $context = []): ChargeResult
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

        // Build the return and cancel URLs the provider
        // will use. The return URL differs per provider.
        //
        //   Stripe: /checkout/stripe/return. Stripe
        //           substitutes {CHECKOUT_SESSION_ID} on
        //           the way back.
        //   PayPal: /checkout/paypal/return. PayPal
        //           appends its own token and PayerID
        //           query parameters.
        //   Other:  the confirmation page. A provider
        //           that captures inline has nothing
        //           to return to.
        //
        // The cancel URL is the storefront's confirm
        // step for every provider.
        $appUrl   = rtrim((string) config('app.url', ''), '/');
        $orderRef = rawurlencode((string) $order->orderNumber);

        $returnUrl = match ($providerKey) {
            'stripe'      => $appUrl . '/checkout/stripe/return?order='
                           . $orderRef . '&session_id={CHECKOUT_SESSION_ID}',
            'paypal'      => $appUrl . '/checkout/paypal/return?order=' . $orderRef,
            'pesapal'     => $appUrl . '/checkout/pesapal/return?order=' . $orderRef,
            'flutterwave' => $appUrl . '/checkout/flutterwave/return?order=' . $orderRef,
            default       => $appUrl . '/checkout/confirmation/' . $orderRef,
        };

        $cancelUrl = $appUrl . '/checkout/confirm';

        // The provider context carries the return and cancel
        // URLs plus any caller-supplied keys. The M-Pesa
        // phone arrives as $context['mpesa_phone']. Stripe,
        // PayPal, and the offline methods ignore unknown keys.
        $providerContext = array_merge($context, [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
        ]);

        $response = $provider->charge($order, $providerContext);

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

        // No redirect URL means the provider accepted the
        // charge inline. A provider that requires an
        // out-of-band action (M-Pesa STK push: the customer
        // enters a PIN on their phone) sets
        // requires_polling = true. The controller then
        // renders a waiting page instead of the
        // confirmation page.
        $requiresPolling = (bool) ($response['requires_polling'] ?? false);

        return ChargeResult::charged(
            $order->id,
            $providerKey,
            $providerReference,
            $requiresPolling
        );
    }
}
