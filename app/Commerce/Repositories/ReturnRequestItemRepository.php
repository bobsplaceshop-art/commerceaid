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
     * Insert one row per return line. Called from
     * ReturnRequestService::open() inside the create transaction.
     *
     * Each $item is a column => value map. The store_id is
     * stamped here; the caller does not pass it.
     *
     * @param array<int, array<string,mixed>> $items
     */
    public function createMany(array $items): void
    {
        if ($items === []) {
            return;
        }

        foreach ($items as $item) {
            $data = array_merge(['store_id' => $this->storeId()], $item);

            $columns      = array_keys($data);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $columnList   = '`' . implode('`, `', $columns) . '`';

            $this->db->insert(
                "INSERT INTO `commerce_return_request_items` ({$columnList}) VALUES ({$placeholders})",
                array_values($data)
            );
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

    /**
     * Map of order_item_id => in-flight return quantity across
     * all open or processing return requests for the given lines.
     *
     * "In flight" means the request is in status requested,
     * approved, or received. Rejected and cancelled requests are
     * excluded. Refunded requests are excluded - those units
     * have already been counted in returned_quantity on the
     * order item and must not be double-counted by the customer
     * view.
     *
     * Used by AccountController::orderDetail() to annotate each
     * order line with "N being returned" when the customer has
     * an active return in progress on that line.
     *
     * @param array<int, int> $orderItemIds
     * @return array<int, int> order_item_id => quantity
     */
    public function inFlightMapForOrderItems(array $orderItemIds): array
    {
        if ($orderItemIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($orderItemIds), '?'));
        $params       = [$this->storeId()];
        foreach ($orderItemIds as $id) {
            $params[] = (int) $id;
        }

        $rows = $this->db->select(
            "SELECT i.`order_item_id`, SUM(i.`quantity`) AS qty
               FROM `commerce_return_request_items` i
               INNER JOIN `commerce_return_requests` r
                 ON r.`id` = i.`request_id`
              WHERE i.`store_id` = ?
                AND i.`order_item_id` IN ({$placeholders})
                AND r.`status` IN ('requested','approved','received')
              GROUP BY i.`order_item_id`",
            $params
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['order_item_id']] = (int) $r['qty'];
        }
        return $map;
    }
}
