<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\ReturnRequest;

final class ReturnRequestRepository extends CommerceRepository
{
    public function findById(int $id): ?ReturnRequest
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_return_requests`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );
        return $row !== null ? ReturnRequest::fromRow($row) : null;
    }

    public function findByNumber(string $requestNumber): ?ReturnRequest
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_return_requests`
              WHERE `store_id` = ? AND `request_number` = ? LIMIT 1",
            [$this->storeId(), $requestNumber]
        );
        return $row !== null ? ReturnRequest::fromRow($row) : null;
    }

    /**
     * @return array<int, ReturnRequest>
     */
    public function listForCustomer(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_return_requests`
              WHERE `store_id` = ? AND `customer_id` = ?
              ORDER BY `requested_at` DESC, `id` DESC",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => ReturnRequest::fromRow($r), $rows);
    }

    /**
     * @return array<int, ReturnRequest>
     */
    public function listForOrder(int $orderId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_return_requests`
              WHERE `store_id` = ? AND `order_id` = ?
              ORDER BY `requested_at` DESC, `id` DESC",
            [$this->storeId(), $orderId]
        );
        return array_map(fn ($r) => ReturnRequest::fromRow($r), $rows);
    }

    public function hasOpenRequest(int $orderId): bool
    {
        $count = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_return_requests`
              WHERE `store_id` = ? AND `order_id` = ?
                AND `status` IN ('requested','approved','received')",
            [$this->storeId(), $orderId]
        );
        return $count > 0;
    }


    /**
     * Map of order_id => request_number for any order in the
     * given list that has an open return request.
     *
     * Open means status IN (requested, approved, received).
     * Used by the order list to badge orders that have a
     * return in flight without issuing one query per row.
     *
     * When an order has more than one open request (which the
     * service prevents, but a manual DB edit could produce),
     * the most recent one wins.
     *
     * @param array<int, int> $orderIds
     * @return array<int, string> order_id => request_number
     */
    public function openRequestMapForOrders(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
        $params       = [$this->storeId()];
        foreach ($orderIds as $id) {
            $params[] = (int) $id;
        }

        $rows = $this->db->select(
            "SELECT `order_id`, `request_number`
               FROM `commerce_return_requests`
              WHERE `store_id` = ?
                AND `order_id` IN ({$placeholders})
                AND `status` IN ('requested','approved','received')
              ORDER BY `requested_at` DESC, `id` DESC",
            $params
        );

        $map = [];
        foreach ($rows as $r) {
            $orderId = (int) $r['order_id'];
            // First row wins because the query is DESC-ordered.
            if (!isset($map[$orderId])) {
                $map[$orderId] = (string) $r['request_number'];
            }
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: array<int, ReturnRequest>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginateForAdmin(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildFilters($filters);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_return_requests` WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_return_requests`
              WHERE {$where}
              ORDER BY `requested_at` DESC, `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => ReturnRequest::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_return_requests`
              WHERE `store_id` = ? AND `status` = ?",
            [$this->storeId(), $status]
        );
    }

    /**
     * Insert a new return request. Returns the inserted id.
     *
     * The caller (ReturnRequestService::open()) has already
     * computed every value and is inside a transaction. This
     * method does not begin or commit anything.
     *
     * @param array<string,mixed> $data Column => value map.
     */
    public function create(array $data): int
    {
        $data = array_merge(['store_id' => $this->storeId()], $data);

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = '`' . implode('`, `', $columns) . '`';

        return $this->db->insert(
            "INSERT INTO `commerce_return_requests` ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_return_requests`
                SET `status`       = COALESCE(?, `status`),
                    `admin_note`   = COALESCE(?, `admin_note`),
                    `refund_total` = COALESCE(?, `refund_total`),
                    `reviewed_at`  = COALESCE(?, `reviewed_at`),
                    `reviewed_by`  = COALESCE(?, `reviewed_by`),
                    `resolved_at`  = COALESCE(?, `resolved_at`),
                    `updated_at`   = NOW()
              WHERE `store_id` = ? AND `id` = ?",
            [
                $data['status']       ?? null,
                $data['admin_note']   ?? null,
                $data['refund_total'] ?? null,
                $data['reviewed_at']  ?? null,
                $data['reviewed_by']  ?? null,
                $data['resolved_at']  ?? null,
                $this->storeId(),
                $id,
            ]
        );
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
            $where[]  = "`status` = ?";
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $where[]  = "`customer_id` = ?";
            $params[] = (int) $filters['customer_id'];
        }

        if (!empty($filters['order_id'])) {
            $where[]  = "`order_id` = ?";
            $params[] = (int) $filters['order_id'];
        }

        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $where[]  = "`request_number` LIKE ?";
            $params[] = $like;
        }

        // Date-range filter. The two values arrive from
        // <input type="date"> as ISO YYYY-MM-DD strings. Append
        // time bounds so the range is inclusive of both days.
        // The controller validates the format before passing
        // them; this layer assumes they are well-formed when set.
        if (!empty($filters['date_from'])) {
            $where[]  = "`requested_at` >= ?";
            $params[] = (string) $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[]  = "`requested_at` <= ?";
            $params[] = (string) $filters['date_to'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }
}
