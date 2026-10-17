<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Commerce\Repositories\FlutterwaveRequestRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Services\Payment\PaymentProviderRegistry;
use CorporateAid\Commerce\Services\Payment\Providers\FlutterwaveProvider;
use CorporateAid\Commerce\Support\StorefrontController;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Flutterwave return handler.
 *
 * The customer lands here after paying (or cancelling)
 * on Flutterwave's hosted page. The URL carries the
 * order number the platform supplied as the redirect_url
 * order parameter.
 *
 * The authoritative payment confirmation is the webhook.
 * The webhook can arrive after the customer lands on this
 * page. The return controller reads the transaction_id
 * from the query (Flutterwave appends it), calls
 * GET /transactions/{id}/verify, and writes the paid
 * state if 'successful'. The webhook handler does the
 * same write; the two are idempotent against each other.
 *
 * If the transaction_id is absent from the query, the
 * controller falls back to the row's stored transaction_id
 * (set by the webhook body's data.id before the verify
 * call). If neither is present, the customer is sent back
 * to confirm.
 *
 * Stage 010, sub-stage 010.3 Session 3.
 */
final class FlutterwaveReturnController extends StorefrontController
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

        // Already paid. The webhook beat us here, or the
        // customer refreshed a completed return.
        if ($order->paymentStatus === 'paid') {
            return Response::redirect(
                '/checkout/confirmation/' . $order->orderNumber
            );
        }

        $txRef         = 'ORD-' . $order->orderNumber;
        $transactionId = (string) $request->query('transaction_id', '');

        // Fall back to the row's stored transaction_id if
        // the query did not carry one. Flutterwave usually
        // sends transaction_id on the redirect but the
        // parameter name can vary by plan.
        $repo = $this->container->get(FlutterwaveRequestRepository::class);
        if ($transactionId === '') {
            $row = $repo->findByTxRef($txRef);
            if ($row !== null) {
                $transactionId = (string) ($row['transaction_id'] ?? '');
            }
        }

        if ($transactionId === '') {
            return Response::redirect('/checkout/confirm');
        }

        $registry = $this->container->get(PaymentProviderRegistry::class);
        $provider = $registry->resolve('flutterwave');
        if (!$provider instanceof FlutterwaveProvider) {
            return Response::redirect('/checkout/confirm');
        }

        $status = $provider->readTransactionStatus($transactionId);
        if (($status['ok'] ?? false) !== true) {
            return Response::redirect('/checkout/confirm');
        }

        if ((string) ($status['status'] ?? '') !== 'successful') {
            return Response::redirect('/checkout/confirm');
        }

        // Write the paid state. Idempotent against the
        // webhook handler, which writes the same columns.
        $orders->update($order->id, [
            'payment_status'    => 'paid',
            'paid_at'           => date('Y-m-d H:i:s'),
            'payment_reference' => $transactionId,
        ]);

        return Response::redirect(
            '/checkout/confirmation/' . $order->orderNumber
        );
    }
}
