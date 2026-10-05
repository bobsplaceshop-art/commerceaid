<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\OrderNote;

/**
 * Order internal notes. Append-only.
 *
 * Every note is one row. The admin writes notes; the order
 * detail page reads them newest-first. Nothing updates or
 * deletes.
 *
 * Shape mirrors OrderStatusHistoryRepository.
 */
final class OrderNoteRepository extends CommerceRepository
{
    /**
     * All notes for an order, newest first.
     *
     * @return array<int, OrderNote>
     */
    public function forOrder(int $orderId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_order_notes`
              WHERE `store_id` = ? AND `order_id` = ?
              ORDER BY `id` DESC",
            [$this->storeId(), $orderId]
        );
        return array_map(fn ($r) => OrderNote::fromRow($r), $rows);
    }

    /**
     * Append a note. Returns the inserted id.
     *
     * @param int         $orderId
     * @param string      $note
     * @param int|null    $createdBy
     * @param bool        $customerVisible
     */
    public function append(
        int $orderId,
        string $note,
        ?int $createdBy = null,
        bool $customerVisible = false
    ): int {
        return $this->db->insert(
            "INSERT INTO `commerce_order_notes`
                (`store_id`, `order_id`, `note`, `is_customer_visible`, `created_by`)
             VALUES (?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $orderId,
                $note,
                $customerVisible ? 1 : 0,
                $createdBy,
            ]
        );
    }

    public function countForOrder(int $orderId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_order_notes`
              WHERE `store_id` = ? AND `order_id` = ?",
            [$this->storeId(), $orderId]
        );
    }
}
