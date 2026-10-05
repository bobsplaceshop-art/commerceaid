<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\ReturnRequestItem;

final class ReturnRequestItemRepository extends CommerceRepository
{
    /**
     * @return array<int, ReturnRequestItem>
     */
    public function listForRequest(int $requestId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_return_request_items`
              WHERE `store_id` = ? AND `request_id` = ?
              ORDER BY `id` ASC",
            [$this->storeId(), $requestId]
        );
        return array_map(fn ($r) => ReturnRequestItem::fromRow($r), $rows);
    }

    /**
     * @param array<int, array<string,mixed>> $items
     */
    public function createMany(array $items): void
    {
        foreach ($items as $item) {
            $this->db->insert('commerce_return_request_items', array_merge(
                ['store_id' => $this->storeId()],
                $item
            ));
        }
    }

    /**
     * Sum the quantity across all requests for a specific order
     * line, excluding rejected and cancelled requests.
     */
    public function sumQuantityForOrderItem(int $orderItemId): int
    {
        $sum = (int) $this->db->scalar(
            "SELECT COALESCE(SUM(i.`quantity`), 0)
               FROM `commerce_return_request_items` i
               INNER JOIN `commerce_return_requests` r
                 ON r.`id` = i.`request_id`
              WHERE i.`store_id` = ?
                AND i.`order_item_id` = ?
                AND r.`status` NOT IN ('rejected','cancelled')",
            [$this->storeId(), $orderItemId]
        );
        return $sum;
    }

    /**
     * Sum the refund amounts across all completed (refunded)
     * requests for a given order line.
     */
    public function sumRefundedForOrderItem(int $orderItemId): float
    {
        $sum = (float) $this->db->scalar(
            "SELECT COALESCE(SUM(i.`line_refund`), 0)
               FROM `commerce_return_request_items` i
               INNER JOIN `commerce_return_requests` r
                 ON r.`id` = i.`request_id`
              WHERE i.`store_id` = ?
                AND i.`order_item_id` = ?
                AND r.`status` = 'refunded'",
            [$this->storeId(), $orderItemId]
        );
        return $sum;
    }
}
