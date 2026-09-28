<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\OrderStatusHistory;

/**
 * Order status timeline. Append-only.
 *
 * Every status transition writes one row. Nothing updates or
 * deletes. The full lifecycle of an order is the ordered set of
 * these rows for that order.
 */
final class OrderStatusHistoryRepository extends CommerceRepository
{
    /**
     * Full timeline for an order, newest first.
     *
     * @return array<int, OrderStatusHistory>
     */
    public function forOrder(int $orderId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_order_status_history`
              WHERE `store_id` = ? AND `order_id` = ?
              ORDER BY `id` DESC",
            [$this->storeId(), $orderId]
        );
        return array_map(fn ($r) => OrderStatusHistory::fromRow($r), $rows);
    }

    /**
     * Append a status transition. Called from the checkout
     * transaction (initial creation row) and from every later
     * transition (admin flows).
     *
     * @param int         $orderId
     * @param string|null $fromStatus  NULL for the creation row
     * @param string      $toStatus
     * @param int|null    $changedBy   NULL for customer-initiated
     * @param string|null $note
     */
    public function append(
        int $orderId,
        ?string $fromStatus,
        string $toStatus,
        ?int $changedBy = null,
        ?string $note = null
    ): int {
        return $this->db->insert(
            "INSERT INTO `commerce_order_status_history`
                (`store_id`, `order_id`, `from_status`, `to_status`,
                 `changed_by`, `note`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $orderId,
                $fromStatus,
                $toStatus,
                $changedBy,
                $note,
            ]
        );
    }

    public function countForOrder(int $orderId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_order_status_history`
              WHERE `store_id` = ? AND `order_id` = ?",
            [$this->storeId(), $orderId]
        );
    }
}
