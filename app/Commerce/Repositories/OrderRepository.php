<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Order;

/**
 * Order read/write.
 *
 * Every query filters by storeId(). findById, findByNumber,
 * paginate, and the insert/update paths are the core surface.
 *
 * The order is created inside the checkout transaction (see
 * CheckoutService). This repository only owns the SQL - the
 * transaction shape lives in the service.
 */
final class OrderRepository extends CommerceRepository
{
    public function findById(int $id): ?Order
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_orders`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
        return $row === null ? null : Order::fromRow($row);
    }

    public function findByNumber(string $orderNumber): ?Order
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_orders`
              WHERE `store_id` = ? AND `order_number` = ?",
            [$this->storeId(), $orderNumber]
        );
        return $row === null ? null : Order::fromRow($row);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items:array<int,Order>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_orders` WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_orders`
              WHERE {$where}
              ORDER BY `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => Order::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Insert a new order. Returns the inserted id.
     *
     * The caller (CheckoutService) has already computed every
     * value and is inside a transaction. This method does not
     * begin or commit anything.
     *
     * @param array<string,mixed> $data Column => value map.
     */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = '`' . implode('`, `', $columns) . '`';

        return $this->db->insert(
            "INSERT INTO `commerce_orders` ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    /**
     * Update mutable fields. Used by admin order flows in later
     * stages; checkout only writes on insert.
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
        $sql = "UPDATE `commerce_orders` SET " . implode(', ', $set)
             . " WHERE `store_id` = ? AND `id` = ?";
        $params = array_values($data);
        $params[] = $this->storeId();
        $params[] = $id;

        $this->db->execute($sql, $params);
    }

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_orders` WHERE `store_id` = ?",
            [$this->storeId()]
        );
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_orders`
              WHERE `store_id` = ? AND `status` = ?",
            [$this->storeId(), $status]
        );
    }

    public function countPendingPayment(): int
    {
        return $this->countByStatus('pending_payment');
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where  = ["`store_id` = ?"];
        $params = [$this->storeId()];

        if (!empty($filters['status'])) {
            $where[] = "`status` = ?";
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $where[] = "`payment_status` = ?";
            $params[] = (string) $filters['payment_status'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = "`customer_id` = ?";
            $params[] = (int) $filters['customer_id'];
        }

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[] = "(`order_number` LIKE ? OR `guest_email` LIKE ?)";
            $params[] = $like;
            $params[] = $like;
        }

        return [implode(' AND ', $where), $params];
    }
}
