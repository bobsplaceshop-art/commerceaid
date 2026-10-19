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

    // =================================================================
    // Admin order tabs (Stage 008K.1)
    // =================================================================

    /**
     * Counts of orders in each admin-facing status group. One
     * query, five conditional SUMs. Used by the admin orders
     * list for the tab badges.
     *
     * The groups mirror the admin list tabs:
     *   new        - pending_payment, paid
     *   processing - processing, packed, on_hold
     *   shipped    - shipped, delivered
     *   done       - completed, cancelled, failed,
     *                refunded, partially_refunded
     *   all        - every order
     *
     * @return array<string, int>
     */
    /**
     * Orders stuck in 'pending_payment' with an unpaid
     * payment_status, older than the given threshold.
     *
     * These are orders a customer placed but never paid:
     * an abandoned Stripe Checkout session, a closed PayPal
     * approval page, an ignored M-Pesa PIN prompt. The
     * inventory reservation holds the stock. The
     * abandoned-order cron cancels them.
     *
     * The threshold is in minutes. 60 is the industry
     * default for "abandoned". A merchant can override it
     * per store with general.orders.abandoned_after_minutes.
     *
     * @return array<int, Order>
     */
    public function findAbandoned(int $minutes): array
    {
        if ($minutes < 1) {
            return [];
        }

        $rows = $this->db->select(
            "SELECT * FROM `commerce_orders`
              WHERE `store_id` = ?
                AND `status` = 'pending_payment'
                AND `payment_status` = 'unpaid'
                AND `created_at` < (NOW() - INTERVAL ? MINUTE)
              ORDER BY `id` ASC",
            [$this->storeId(), $minutes]
        );

        return array_map(fn ($r) => Order::fromRow($r), $rows);
    }

    /**
     * Order counts grouped by the admin list's status tabs.
     *
     * @return array{new:int, processing:int, shipped:int,
     *               done:int, all:int}
     */
    public function countByStatusGroups(): array
    {
        $rows = $this->db->select(
            "SELECT
                SUM(CASE WHEN `status` IN ('pending_payment', 'paid')
                     THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN `status` IN ('processing', 'packed', 'on_hold')
                     THEN 1 ELSE 0 END) AS processing_count,
                SUM(CASE WHEN `status` IN ('shipped', 'delivered')
                     THEN 1 ELSE 0 END) AS shipped_count,
                SUM(CASE WHEN `status` IN ('completed', 'cancelled', 'failed',
                                           'refunded', 'partially_refunded')
                     THEN 1 ELSE 0 END) AS done_count,
                COUNT(*) AS all_count
               FROM `commerce_orders`
              WHERE `store_id` = ?",
            [$this->storeId()]
        );

        $r = $rows[0] ?? [];
        return [
            'new'        => (int) ($r['new_count'] ?? 0),
            'processing' => (int) ($r['processing_count'] ?? 0),
            'shipped'    => (int) ($r['shipped_count'] ?? 0),
            'done'       => (int) ($r['done_count'] ?? 0),
            'all'        => (int) ($r['all_count'] ?? 0),
        ];
    }

    // =================================================================
    // Admin order status transitions (Stage 008K.1)
    // =================================================================

    /**
     * Apply a status transition. Updates commerce_orders.status
     * and appends a row to commerce_order_status_history inside
     * one transaction.
     *
     * The caller is responsible for validating that the
     * transition is allowed (via Order::canTransitionTo()).
     * This method trusts its caller for the business rule and
     * only guarantees the two writes succeed or fail together.
     *
     * Transaction shape mirrors InventoryRepository::adjust().
     * The history row is written with a direct insert rather
     * than through OrderStatusHistoryRepository because
     * cross-repository calls inside a transaction are the
     * anti-pattern the codebase avoids.
     *
     * @param int         $orderId
     * @param string      $fromStatus   current status before update
     * @param string      $toStatus     target status
     * @param int|null    $changedBy    acting admin user id, or
     *                                  null for system transitions
     * @param string|null $note         optional note on the
     *                                  transition
     */
    public function transitionStatus(
        int $orderId,
        string $fromStatus,
        string $toStatus,
        ?int $changedBy = null,
        ?string $note = null
    ): void {
        $this->db->beginTransaction();
        try {
            // 1) Update the order's status column.
            $this->db->execute(
                "UPDATE `commerce_orders`
                    SET `status` = ?
                  WHERE `store_id` = ? AND `id` = ?",
                [$toStatus, $this->storeId(), $orderId]
            );

            // 2) Append the history row.
            $this->db->insert(
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

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // =================================================================
    // Customer order tabs (Stage 008F)
    // =================================================================

    /**
     * Paginated orders for a customer, filtered by tab.
     *
     * Tabs are a customer-facing classification across the
     * three underlying status tracks (status, payment_status,
     * fulfilment_status). Not a stored field.
     *
     * @param string $tab  unpaid | to_ship | shipped | completed | cancelled | all
     * @return array{items:array<int,Order>,total:int,page:int,per_page:int,pages:int}
     */
    public function paginateForCustomerTab(
        int $customerId,
        string $tab,
        int $page = 1,
        int $perPage = 20
    ): array {
        $page    = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset  = ($page - 1) * $perPage;

        $where  = ["`store_id` = ?", "`customer_id` = ?"];
        $params = [$this->storeId(), $customerId];

        // Tab-specific classification. See the plan doc for the
        // reasoning behind each condition.
        $tabWhere = match ($tab) {
            'unpaid' => "`payment_status` = 'unpaid' "
                      . "AND `status` NOT IN ('cancelled', 'failed')",
            'to_ship' => "`payment_status` = 'paid' "
                       . "AND `fulfilment_status` IN ('unfulfilled', 'partially_fulfilled') "
                       . "AND `status` NOT IN ('cancelled', 'completed')",
            'shipped' => "`fulfilment_status` = 'fulfilled' "
                       . "AND `status` NOT IN ('cancelled', 'completed')",
            'completed' => "`status` = 'completed'",
            'cancelled' => "`status` IN ('cancelled', 'failed')",
            default => null, // 'all' or unknown - no additional filter
        };

        if ($tabWhere !== null) {
            $where[] = '(' . $tabWhere . ')';
        }

        $whereSql = implode(' AND ', $where);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_orders` WHERE {$whereSql}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_orders`
              WHERE {$whereSql}
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
     * Count orders in each tab for the badge display. Returns
     * an associative array with keys unpaid, to_ship, shipped,
     * completed, cancelled, all.
     *
     * @return array<string, int>
     */
    public function countForCustomerTabs(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT
                SUM(CASE WHEN `payment_status` = 'unpaid'
                          AND `status` NOT IN ('cancelled', 'failed')
                     THEN 1 ELSE 0 END) AS unpaid,
                SUM(CASE WHEN `payment_status` = 'paid'
                          AND `fulfilment_status` IN ('unfulfilled', 'partially_fulfilled')
                          AND `status` NOT IN ('cancelled', 'completed')
                     THEN 1 ELSE 0 END) AS to_ship,
                SUM(CASE WHEN `fulfilment_status` = 'fulfilled'
                          AND `status` NOT IN ('cancelled', 'completed')
                     THEN 1 ELSE 0 END) AS shipped,
                SUM(CASE WHEN `status` = 'completed'
                     THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN `status` IN ('cancelled', 'failed')
                     THEN 1 ELSE 0 END) AS cancelled,
                COUNT(*) AS total_count
               FROM `commerce_orders`
              WHERE `store_id` = ? AND `customer_id` = ?",
            [$this->storeId(), $customerId]
        );

        $r = $rows[0] ?? [];
        return [
            'unpaid'    => (int) ($r['unpaid'] ?? 0),
            'to_ship'   => (int) ($r['to_ship'] ?? 0),
            'shipped'   => (int) ($r['shipped'] ?? 0),
            'completed' => (int) ($r['completed'] ?? 0),
            'cancelled' => (int) ($r['cancelled'] ?? 0),
            'all'       => (int) ($r['total_count'] ?? 0),
        ];
    }

    /**
     * Find an order by number, restricted to a specific customer.
     * Used by the customer-facing order detail page. Returns null
     * when the order belongs to a different customer or does not
     * exist - the caller redirects to the order list on null.
     */
    public function findForCustomer(string $orderNumber, int $customerId): ?Order
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_orders`
              WHERE `store_id` = ?
                AND `order_number` = ?
                AND `customer_id` = ?",
            [$this->storeId(), $orderNumber, $customerId]
        );
        return $row === null ? null : Order::fromRow($row);
    }

    // =================================================================
    // Guest order claim (Stage 008E.2)
    // =================================================================

    /**
     * Orders marked as claimable by this customer. A claimable
     * order has claimable_by_customer_id set to the customer id
     * and has not yet been claimed.
     *
     * @return array<int, Order>
     */
    public function listClaimableForCustomer(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_orders`
              WHERE `store_id` = ?
                AND `claimable_by_customer_id` = ?
                AND `claimed_at` IS NULL
              ORDER BY `id` DESC",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => Order::fromRow($r), $rows);
    }

    // =================================================================
    // Returns eligibility (Stage 008H)
    // =================================================================

    /**
     * Orders belonging to the customer that have at least one order
     * line with outstanding returnable quantity.
     *
     * "Outstanding" means quantity > returned_quantity on the order
     * item row. In-flight return requests are not considered here -
     * ReturnRequestService enforces the per-line cap when a request
     * is opened. This method is a coarse eligibility filter.
     *
     * Ordered newest first. Capped at 50 to keep the Apply tab
     * readable and the query bounded; a customer with more than 50
     * eligible orders is an edge case.
     *
     * @return array<int, Order>
     */
    public function listEligibleForReturn(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT o.* FROM `commerce_orders` o
              WHERE o.`store_id` = ?
                AND o.`customer_id` = ?
                AND o.`status` NOT IN ('cancelled', 'failed', 'refunded')
                AND EXISTS (
                    SELECT 1 FROM `commerce_order_items` i
                     WHERE i.`order_id` = o.`id`
                       AND i.`store_id` = o.`store_id`
                       AND i.`quantity` > i.`returned_quantity`
                )
              ORDER BY o.`id` DESC
              LIMIT 50",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => Order::fromRow($r), $rows);
    }

    /**
     * Fast check: does this customer have any unclaimed orders?
     * Cheaper than listClaimableForCustomer when we only need a
     * boolean for the dashboard notification.
     */
    /**
     * The id of a qualifying order for the verified-purchase
     * badge on a product review.
     *
     * A qualifying order:
     *   - belongs to the customer
     *   - has status 'delivered' or 'completed'
     *   - contains an order item for the given product
     *
     * Returns the most recent qualifying order id, or null
     * when none exists. Used by ProductReviewService at
     * review submission time.
     */
    public function findVerifiedPurchaseOrderId(int $customerId, int $productId): ?int
    {
        if ($customerId <= 0 || $productId <= 0) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT o.`id`
               FROM `commerce_orders` o
               INNER JOIN `commerce_order_items` oi
                 ON oi.`order_id` = o.`id`
              WHERE o.`store_id` = ?
                AND o.`customer_id` = ?
                AND oi.`product_id` = ?
                AND o.`status` IN ('delivered', 'completed')
              ORDER BY o.`id` DESC
              LIMIT 1",
            [$this->storeId(), $customerId, $productId]
        );

        return $row !== null ? (int) $row['id'] : null;
    }

    public function hasClaimableOrders(int $customerId): bool
    {
        $count = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_orders`
              WHERE `store_id` = ?
                AND `claimable_by_customer_id` = ?
                AND `claimed_at` IS NULL",
            [$this->storeId(), $customerId]
        );
        return $count > 0;
    }

    /**
     * Link claimable orders to a customer. Each order must:
     *   - be in this store
     *   - be claimable by this exact customer
     *   - not have been claimed already
     *
     * On success:
     *   customer_id              <- claimable_by_customer_id
     *   claimed_at               <- NOW()
     *   claimable_by_customer_id <- NULL
     *
     * The WHERE clauses guard against races: two concurrent
     * requests both calling this method, only the first wins.
     * Returns the number of orders actually claimed.
     *
     * @param array<int, int> $orderIds
     */
    public function claimOrders(array $orderIds, int $customerId): int
    {
        if ($orderIds === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));

        // Parameter order matches the placeholder order in the
        // SQL below:
        //   1. customer_id        (SET)
        //   2. store_id           (WHERE)
        //   3..N. id IN (...)     (WHERE)
        //   N+1. claimable_by     (WHERE)
        $params = [$customerId, $this->storeId()];
        foreach ($orderIds as $id) {
            $params[] = (int) $id;
        }
        $params[] = $customerId;

        $stmt = $this->db->execute(
            "UPDATE `commerce_orders`
                SET `customer_id` = ?,
                    `claimed_at` = NOW(),
                    `claimable_by_customer_id` = NULL
              WHERE `store_id` = ?
                AND `id` IN ({$placeholders})
                AND `claimable_by_customer_id` = ?
                AND `claimed_at` IS NULL",
            $params
        );

        return $stmt;
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

        // List variant of the status filter. Used by the admin
        // orders queue to filter by a group of statuses (e.g.
        // the "New" tab groups pending_payment and paid).
        // Non-string or empty entries are dropped.
        if (!empty($filters['statuses']) && is_array($filters['statuses'])) {
            $statuses = array_values(array_filter(
                $filters['statuses'],
                fn ($s) => is_string($s) && $s !== ''
            ));
            if ($statuses !== []) {
                $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
                $where[]  = "`status` IN ({$placeholders})";
                foreach ($statuses as $s) {
                    $params[] = $s;
                }
            }
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

    /**
     * Search orders by number or customer email.
     * @return array<int, \CorporateAid\Commerce\Models\Order>
     */
    public function search(string $q, int $limit = 10): array
    {
        $stmt = $this->db->select(
            "SELECT o.*, c.first_name, c.last_name 
             FROM `commerce_orders` o 
             LEFT JOIN `commerce_customers` c ON c.id = o.customer_id 
             WHERE o.`store_id` = ? 
               AND (o.`order_number` LIKE ? OR c.`email` LIKE ?) 
             ORDER BY o.`created_at` DESC 
             LIMIT ?",
            [$this->storeId(), "%{$q}%", "%{$q}%", $limit]
        );
        return array_map([\CorporateAid\Commerce\Models\Order::class, 'fromRow'], $stmt);
    }

}
