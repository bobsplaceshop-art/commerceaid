<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\OrderItem;

/**
 * Order line item read/write.
 *
 * Items are written once at order creation and rarely updated
 * afterwards. The update path is for fulfilment tracking (008F
 * admin flow): incrementing fulfilled_quantity, marking as
 * returned.
 */
final class OrderItemRepository extends CommerceRepository
{
    /**
     * All items for one order, in insertion order.
     *
     * @return array<int, OrderItem>
     */
    public function forOrder(int $orderId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_order_items`
              WHERE `store_id` = ? AND `order_id` = ?
              ORDER BY `id` ASC",
            [$this->storeId(), $orderId]
        );
        return array_map(fn ($r) => OrderItem::fromRow($r), $rows);
    }

    public function findById(int $id): ?OrderItem
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_order_items`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
        return $row === null ? null : OrderItem::fromRow($row);
    }

    /**
     * Bulk insert of all items for one order. Called from the
     * checkout transaction with the full item array. Uses a
     * single multi-row INSERT for efficiency.
     *
     * @param array<int,array<string,mixed>> $items Each element
     *        is a column => value map with store_id and order_id
     *        already set by the caller.
     */
    public function createMany(array $items): void
    {
        if ($items === []) {
            return;
        }

        // Every item has the same columns.
        $columns = array_keys($items[0]);
        $columnList = '`' . implode('`, `', $columns) . '`';

        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($items), $rowPlaceholder));

        $params = [];
        foreach ($items as $item) {
            foreach ($columns as $col) {
                $params[] = $item[$col] ?? null;
            }
        }

        $this->db->execute(
            "INSERT INTO `commerce_order_items` ({$columnList}) VALUES {$allPlaceholders}",
            $params
        );
    }

    /**
     * Update a single item. Used by fulfilment flows.
     *
     * @param array<string,mixed> $data Column => value map.
     */
    public function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`{$col}` = ?";
        }
        $sql = "UPDATE `commerce_order_items` SET " . implode(', ', $set)
             . " WHERE `store_id` = ? AND `id` = ?";
        $params = array_values($data);
        $params[] = $this->storeId();
        $params[] = $id;

        $this->db->execute($sql, $params);
    }

    public function countForOrder(int $orderId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_order_items`
              WHERE `store_id` = ? AND `order_id` = ?",
            [$this->storeId(), $orderId]
        );
    }
}
