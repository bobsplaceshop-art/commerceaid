<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\PesapalRequestRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;
use CorporateAid\Commerce\Services\Payment\Providers\PesapalProvider;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Pesapal return handler.
 *
 * The customer lands here after paying (or cancelling)
 * on Pesapal's hosted page. The URL carries the order
 * number the platform supplied as the callback URL's
 * order parameter.
 *
 * Pesapal's flow: the customer leaves the site, pays,
 * and Pesapal redirects to the callback_url. The
 * authoritative payment confirmation is the IPN, but
 * the IPN can arrive after the customer lands on this
 * page. The return controller queries
 * GetTransactionStatus and writes the paid state
 * defensively. The IPN handler does the same write; the
 * two are idempotent against each other.
 *
 * The tracking id is not in the return URL. It is on
 * the pending commerce_pesapal_requests row for the
 * order. The controller reads the row, gets the
 * tracking id, calls GetTransactionStatus, and writes
 * the order state if COMPLETED.
 *
 * Stage 010, sub-stage 010.2 Session 3.
 */
final class PesapalReturnController extends StorefrontController
{
    /** @param array<string,string> $params */
    public function handle(Request $request, array $params = []): Response
    {
        $orderNumber = (string) $request->query('order', '');

        if ($orderNumber === '') {
            return Response::redirect('/');
        }

        $orders = $this->container->get(OrderRepository::class);
        $order  = $orders->findByNumber($orderNumber);
        if ($order === null) {
            return Response::redirect('/');
        }

        // Already paid. The IPN beat us here, or the
        // customer refreshed a completed return.
        if ($order->paymentStatus === 'paid') {
            return Response::redirect(
                '/checkout/confirmation/' . $order->orderNumber
            );
        }

        // Find the tracking id from the pending row.
        $trackingId = $this->trackingIdForOrder($order->id);
        if ($trackingId === '') {
            // No pending row: the charge never reached
            // Pesapal, or the IPN deleted the row. Send
            // the customer back to confirm.
            return Response::redirect('/checkout/confirm');
        }

        // Resolve the provider and query the status.
        $registry = $this->container->get(PaymentProviderRegistry::class);
        $provider = $registry->resolve('pesapal');
        if (!$provider instanceof PesapalProvider) {
            return Response::redirect('/checkout/confirm');
        }

        $status = $provider->readTransactionStatus($trackingId);
        if (($status['ok'] ?? false) !== true) {
            return Response::redirect('/checkout/confirm');
        }

        if ((string) ($status['status'] ?? '') !== 'COMPLETED') {
            // Not yet complete. The IPN will arrive.
            return Response::redirect('/checkout/confirm');
        }

        // Write the paid state. Idempotent against the
        // IPN handler, which writes the same columns.
        $confirmationCode = (string) ($status['confirmation_code'] ?? '');

        $orders->update($order->id, [
            'payment_status'    => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
            'payment_reference' => $confirmationCode !== ''
                                    ? $confirmationCode
                                    : $trackingId,
        ]);

        return Response::redirect(
            '/checkout/confirmation/' . $order->orderNumber
        );
    }

    private function trackingIdForOrder(int $orderId): string
    {
        $repo = $this->container->get(PesapalRequestRepository::class);
        $rows = $repo->listPendingForOrder($orderId);
        if ($rows === []) {
            return '';
        }
        return (string) ($rows[0]['order_tracking_id'] ?? '');
    }
}
