<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\MpesaRequestRepository;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;

/**
 * Cancels an order and releases everything it held.
 *
 * An order that was placed but never reached the paid
 * state holds:
 *
 *   - An inventory reservation. Every line's units are
 *     counted in commerce_inventory.reserved, reducing
 *     the available quantity for other customers.
 *
 *   - A pending M-Pesa request, if the payment method was
 *     M-Pesa. The commerce_mpesa_requests row sits in the
 *     'pending' state until the callback arrives or the
 *     cleanup cron deletes it.
 *
 * This service releases both and writes the order's
 * terminal state. It is called from two places:
 *
 *   - CheckoutController, when a charge fails before the
 *     customer reaches the provider.
 *
 *   - bin/cancel-abandoned-orders.php, when an order has
 *     been pending for longer than the store's threshold.
 *
 * The two paths produce the same end state. One service,
 * one implementation.
 *
 * Stage 010.1 follow-up. The abandoned-checkout recovery
 * backlog item.
 */
final class OrderCancellationService
{
    public function __construct(
        private InventoryRepository $inventory,
        private OrderItemRepository $orderItems,
        private OrderRepository $orders,
        private MpesaRequestRepository $mpesaRequests,
    ) {
    }

    /**
     * Cancel an order and release its reservations.
     *
     * A reason string is logged for the audit trail. The
     * order row itself carries no reason column; the
     * status-history write, if any, would carry one. This
     * method does not write history today.
     *
     * Idempotent: cancelling an already-cancelled order is
     * a no-op. The release step uses GREATEST(0, ...), so
     * a double release cannot drive reserved below zero.
     * The order update writes the same terminal state
     * twice without side effect.
     *
     * @param string $reason  'Charge failed', 'Abandoned',
     *        or a caller-supplied description. Used only
     *        for logging.
     */
    public function cancel(Order $order, string $reason = 'Cancelled'): void
    {
        // Release each line's reservation.
        foreach ($this->orderItems->forOrder($order->id) as $item) {
            $productId = $item->productId ?? 0;
            $variantId = $item->variantId ?? 0;
            if ($productId <= 0) {
                continue;
            }
            $this->inventory->release(
                $productId,
                $variantId,
                $item->quantity
            );
        }

        // Fail any pending M-Pesa request for this order. A
        // late callback carrying one of these ids is refused
        // by MpesaProvider::verifyWebhook() (the row is no
        // longer pending), so the order cannot flip to paid
        // after cancellation.
        foreach ($this->mpesaRequests->listPendingForOrder($order->id) as $row) {
            $checkoutRequestId = (string) ($row['checkout_request_id'] ?? '');
            if ($checkoutRequestId === '') {
                continue;
            }
            $this->mpesaRequests->markFailed(
                $checkoutRequestId,
                -1,
                $reason
            );
        }

        // Write the terminal state. 'cancelled' is reachable
        // from 'pending_payment' per the Order model's
        // transition map. An order already in a terminal
        // state overwrites to the same values.
        $this->orders->update($order->id, [
            'status'         => 'cancelled',
            'payment_status' => 'failed',
        ]);
    }
}
