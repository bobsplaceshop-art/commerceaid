<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;
use CorporateAid\Commerce\Services\Payment\Providers\PayPalProvider;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Platform\Support\Logger;

/**
 * PayPal Orders v2 return handler.
 *
 * The customer lands here after approving (or cancelling)
 * on PayPal's hosted approval page. The URL carries two
 * parameters:
 *
 *   order   the storefront order number
 *   token   the PayPal order id PayPal assigned when the
 *           charge was created
 *
 * This controller's job is to capture the PayPal order,
 * write the paid state on the storefront order, and send
 * the customer to the confirmation page.
 *
 * PayPal's flow is different from Stripe's. Stripe's
 * Checkout Session captures automatically; the return
 * controller only reads the session status. PayPal's
 * Orders v2 flow requires an explicit capture call. This
 * controller makes that call.
 *
 * The webhook (PAYMENT.CAPTURE.COMPLETED) writes the same
 * order state. The two are idempotent against each other:
 * both check payment_status === 'paid' before writing.
 *
 * If the customer cancelled on PayPal's page, PayPal
 * redirects to the cancel_url instead of the return_url,
 * so this controller is not reached. A missing token on
 * the return path is treated as a defensive cancel: the
 * customer is sent back to confirm to retry.
 *
 * Stage 010 Session 4.
 */
final class PayPalReturnController extends StorefrontController
{
    /** @param array<string,string> $params */
    public function handle(Request $request, array $params = []): Response
    {
        $orderNumber = (string) $request->query('order', '');
        $token       = (string) $request->query('token', '');

        if ($orderNumber === '') {
            return Response::redirect('/');
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findByNumber($orderNumber);
        if ($order === null) {
            return Response::redirect('/');
        }

        // Already paid. The webhook beat us here, or the
        // customer refreshed a completed return. Nothing
        // to capture.
        if ($order->paymentStatus === 'paid') {
            return Response::redirect(
                '/checkout/confirmation/' . $order->orderNumber
            );
        }

        // Defensive cancel. PayPal should have sent the
        // customer to /checkout/confirm on cancel, but if
        // the return URL was reached without a token, the
        // approval did not complete.
        if ($token === '') {
            return Response::redirect('/checkout/confirm');
        }

        $registry = $this->container->get(PaymentProviderRegistry::class);
        $provider = $registry->resolve('paypal');
        if (!$provider instanceof PayPalProvider) {
            $this->flashError(
                'PayPal is not available right now. '
                . 'Please choose another payment method.'
            );
            return Response::redirect('/checkout/confirm');
        }

        $result = $provider->capture($order, $token);
        if (($result['ok'] ?? false) !== true) {
            $error = (string) ($result['error'] ?? 'The PayPal capture did not complete.');

            Logger::info('payment.paypal.capture-failed', [
                'order_id' => $order->id,
                'error'    => $error,
            ]);

            $this->flashError(
                'We could not complete the PayPal payment. '
                . 'Please try again or choose another method.'
            );
            return Response::redirect('/checkout/confirm');
        }

        $captureId = (string) ($result['provider_reference'] ?? '');
        if ($captureId === '') {
            Logger::info('payment.paypal.capture-empty-ref', [
                'order_id' => $order->id,
            ]);
            $this->flashError(
                'The PayPal payment did not return a confirmation. '
                . 'Please contact support if you were charged.'
            );
            return Response::redirect('/checkout/confirm');
        }

        // Write the same state the webhook writes. Both
        // paths guard on payment_status === 'paid' above
        // and in the provider's handleWebhook(), so the
        // second one to run is a no-op.
        $orders->update($order->id, [
            'payment_status'    => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
            'payment_reference' => $captureId,
        ]);

        return Response::redirect(
            '/checkout/confirmation/' . $order->orderNumber
        );
    }

    /**
     * Write a flash the storefront layout will read.
     *
     * Uses Session::class to match AccountController's
     * precedent. The ROADMAP backlog carries a note about
     * a storefront/admin session mismatch; the fix is a
     * separate cleanup. Following the existing precedent
     * keeps the flash visible to the customer.
     */
    private function flashError(string $message): void
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);
        $session->flash('error', $message);
    }
}
