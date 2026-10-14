<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;
use CorporateAid\Commerce\Services\Payment\Providers\StripeProvider;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Stripe Checkout return handler.
 *
 * The customer lands here after paying (or cancelling) on
 * Stripe's hosted Checkout page. The URL carries two
 * parameters:
 *
 *   order      the storefront order number
 *   session_id the Stripe Checkout Session id (Stripe
 *              substitutes {CHECKOUT_SESSION_ID})
 *
 * This controller's job is to give the customer a
 * confirmation page quickly. It reads the Checkout Session
 * status from Stripe's API and, when the session is paid,
 * writes the same order state the webhook writes. The
 * webhook and this controller are idempotent against each
 * other: both check payment_status === 'paid' before
 * writing.
 *
 * The write here is defensive. Stripe's webhook arrives
 * within seconds of the payment, but occasionally a
 * webhook is delayed. A customer who lands on the return
 * URL before the webhook has fired should see a
 * confirmation page that shows the paid state.
 *
 * Stage 010 Session 3.
 */
final class StripeReturnController extends StorefrontController
{
    /** @param array<string,string> $params */
    public function handle(Request $request, array $params = []): Response
    {
        $orderNumber = (string) $request->query('order', '');
        $sessionId   = (string) $request->query('session_id', '');

        if ($orderNumber === '') {
            return Response::redirect('/');
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findByNumber($orderNumber);
        if ($order === null) {
            return Response::redirect('/');
        }

        // If the customer cancelled, Stripe still redirects to
        // the success_url unless a cancel_url is set. The
        // session_id query parameter is absent on a cancel.
        // Send them back to confirm so they can retry.
        if ($sessionId === '') {
            return Response::redirect('/checkout/confirm');
        }

        // Verify the session against Stripe. If the session
        // is paid and the order is still unpaid, write the
        // same state the webhook writes.
        $registry = $this->container->get(PaymentProviderRegistry::class);
        $provider = $registry->resolve('stripe');
        if ($provider instanceof StripeProvider) {
            $this->maybeMarkPaid($provider, $order, $sessionId, $orders);
        }

        return Response::redirect('/checkout/confirmation/' . $order->orderNumber);
    }

    /**
     * Read the Checkout Session from Stripe. When the session
     * is paid and the order is still unpaid, write the paid
     * state. The webhook does the same write; whichever runs
     * first wins, the second is a no-op.
     */
    private function maybeMarkPaid(
        StripeProvider $provider,
        \CorporateAid\Commerce\Models\Order $order,
        string $sessionId,
        OrderRepository $orders
    ): void {
        // Already paid. Nothing to do.
        if ($order->paymentStatus === 'paid') {
            return;
        }

        $session = $provider->readCheckoutSession($sessionId);
        if (($session['ok'] ?? false) !== true) {
            return;
        }

        $paymentStatus = (string) ($session['payment_status'] ?? '');
        if ($paymentStatus !== 'paid') {
            return;
        }

        $intentId = (string) ($session['payment_intent_id'] ?? '');

        $orders->update($order->id, [
            'payment_status'    => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
            'payment_reference' => $intentId,
        ]);
    }
}
