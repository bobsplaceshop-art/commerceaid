<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\Order;
use CorporateAid\Commerce\Repositories\InventoryRepository;
use CorporateAid\Commerce\Repositories\OrderItemRepository;
use CorporateAid\Commerce\Repositories\OrderRepository;

/**
 * Handles unpaid order cleanup and stock release.
 *
 * Two entry points:
 *   - releaseStockForOrder(Order) — called by the admin when an
 *     order is cancelled or failed. Releases the reserved
 *     inventory for every line item.
 *   - cancelStale(int $minutes) — called by the cleanup CLI. Finds
 *     unpaid orders older than the cutoff, releases stock, and
 *     cancels them.
 *
 * Both paths share the same release logic so an admin cancel and
 * an automatic cancel leave the ledger in the same state.
 */
final class AbandonedOrderService
{
    public function __construct(
        private readonly OrderRepository      $orders,
        private readonly OrderItemRepository  $orderItems,
        private readonly InventoryRepository  $inventory
    ) {
    }

    /**
     * Releases the reserved stock for every line item of an order.
     *
     * Uses the `cancel` movement type so the ledger records why
     * the quantity came back. Safe to call on an already-cancelled
     * order: the inventory repository uses `SELECT ... FOR UPDATE`
     * and the delta is non-negative for the cancel path only when
     * the order was previously holding stock. To be safe against
     * double-release, we skip if the order is already cancelled or
     * failed.
     */
    public function releaseStockForOrder(Order $order): int
    {
        if (in_array($order->status, ['cancelled', 'failed', 'refunded', 'partially_refunded'], true)) {
            return 0;
        }

        $items = $this->orderItems->forOrder($order->id);
        $released = 0;

        foreach ($items as $item) {
            $quantity = (int) $item->quantity;
            if ($quantity <= 0) {
                continue;
            }

            // The order item carries the product and variant id.
            // A simple product uses variant_id = 0 (the sentinel
            // documented in CommerceRepository).
            $this->inventory->adjust(
                (int) $item->productId,
                (int) ($item->variantId ?? 0),
                $quantity,
                'cancel',
                [
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                    'reason'         => 'Order cancelled or failed',
                ]
            );
            $released += $quantity;
        }

        return $released;
    }

    /**
     * Cancels unpaid orders older than $minutes.
     *
     * Returns a summary array for the CLI to print:
     *   ['cancelled' => int, 'released' => int, 'order_ids' => int[]]
     *
     * @return array{cancelled:int, released:int, order_ids:array<int,int>}
     */
    public function cancelStale(int $minutes): array
    {
        if ($minutes < 1) {
            return ['cancelled' => 0, 'released' => 0, 'order_ids' => []];
        }

        $stale = $this->orders->findAbandoned($minutes);
        $cancelled = 0;
        $released  = 0;
        $orderIds  = [];

        foreach ($stale as $order) {
            $this->releaseStockForOrder($order);
            $released += count($this->orderItems->forOrder($order->id));

            $this->orders->transitionStatus(
                $order->id,
                'pending_payment',
                'cancelled',
                null,
                'Auto-cancelled after ' . $minutes . ' minutes of inactivity'
            );

            $cancelled++;
            $orderIds[] = $order->id;
        }

        return [
            'cancelled' => $cancelled,
            'released'  => $released,
            'order_ids' => $orderIds,
        ];
    }
}
