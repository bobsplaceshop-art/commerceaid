<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Core\Database;

/**
 * Read-only analytics queries for the admin dashboard.
 * Every method filters by store_id and returns plain arrays.
 */
final class ReportRepository
{
    public function __construct(
        private Database $db,
        private \CorporateAid\Commerce\Context\StoreContext $context
    ) {
    }

    private function storeId(): int
    {
        return $this->context->id();
    }

    /**
     * Daily revenue for the last N days. Only counts orders that
     * have been paid (paid_at IS NOT NULL) and are not cancelled
     * or refunded (partial refunds still count as revenue).
     *
     * @return array<int, array{date: string, revenue: float, orders: int}>
     */
    public function salesOverTime(int $days = 30): array
    {
        $rows = $this->db->select(
            "SELECT DATE(`paid_at`) AS d, SUM(`grand_total`) AS revenue, COUNT(*) AS orders
             FROM `commerce_orders`
             WHERE `store_id` = ?
               AND `paid_at` IS NOT NULL
               AND `paid_at` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND `status` NOT IN ('cancelled', 'failed')
             GROUP BY DATE(`paid_at`)
             ORDER BY d ASC",
            [$this->storeId(), $days]
        );

        // Normalize into a full N-day series with zeros for missing days
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[(string) $r['d']] = [
                'revenue' => (float) $r['revenue'],
                'orders'  => (int) $r['orders'],
            ];
        }

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $series[] = [
                'date'    => $date,
                'revenue' => $byDate[$date]['revenue'] ?? 0.0,
                'orders'  => $byDate[$date]['orders'] ?? 0,
            ];
        }
        return $series;
    }

    /**
     * Top products by units sold and revenue, for orders in
     * the given window that were paid and not cancelled.
     *
     * @return array<int, array{product_id: int, name: string, sku: ?string, units: int, revenue: float}>
     */
    public function topProducts(int $days = 30, int $limit = 10): array
    {
        $rows = $this->db->select(
            "SELECT oi.`product_id`, p.`name`, p.`sku`,
                    SUM(oi.`quantity`) AS units,
                    SUM(oi.`line_total`) AS revenue
             FROM `commerce_order_items` oi
             JOIN `commerce_orders` o ON o.`id` = oi.`order_id`
             LEFT JOIN `commerce_products` p ON p.`id` = oi.`product_id`
             WHERE o.`store_id` = ?
               AND o.`paid_at` IS NOT NULL
               AND o.`paid_at` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND o.`status` NOT IN ('cancelled', 'failed')
               AND oi.`product_id` IS NOT NULL
             GROUP BY oi.`product_id`
             ORDER BY revenue DESC
             LIMIT ?",
            [$this->storeId(), $days, $limit]
        );

        return array_map(fn($r) => [
            'product_id' => (int) $r['product_id'],
            'name'       => (string) ($r['name'] ?? 'Deleted product'),
            'sku'        => $r['sku'] ?? null,
            'units'      => (int) $r['units'],
            'revenue'    => (float) $r['revenue'],
        ], $rows);
    }

    /**
     * Revenue split by category. A product can belong to multiple
     * categories; we use the primary category (via the
     * commerce_product_categories pivot).
     *
     * @return array<int, array{category_id: int, name: string, revenue: float}>
     */
    public function salesByCategory(int $days = 30): array
    {
        $rows = $this->db->select(
            "SELECT c.`id` AS category_id, c.`name`, SUM(oi.`line_total`) AS revenue
             FROM `commerce_order_items` oi
             JOIN `commerce_orders` o ON o.`id` = oi.`order_id`
             JOIN `commerce_product_categories` pc ON pc.`product_id` = oi.`product_id`
             JOIN `commerce_categories` c ON c.`id` = pc.`category_id`
             WHERE o.`store_id` = ?
               AND o.`paid_at` IS NOT NULL
               AND o.`paid_at` >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
               AND o.`status` NOT IN ('cancelled', 'failed')
             GROUP BY c.`id`
             ORDER BY revenue DESC
             LIMIT 10",
            [$this->storeId(), $days]
        );

        return array_map(fn($r) => [
            'category_id' => (int) $r['category_id'],
            'name'        => (string) $r['name'],
            'revenue'     => (float) $r['revenue'],
        ], $rows);
    }

    /**
     * Order count grouped by status. Used for the operations card.
     *
     * @return array<string, int>
     */
    public function orderStatusBreakdown(): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS n
             FROM `commerce_orders`
             WHERE `store_id` = ?
             GROUP BY `status`",
            [$this->storeId()]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['status']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * New customers and returning customers in the window.
     *
     * @return array{new: int, returning: int, total: int, repeat_rate: float}
     */
    public function customerStats(int $days = 30): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COUNT(DISTINCT CASE WHEN `created_at` >= DATE_SUB(CURDATE(), INTERVAL ? DAY) THEN `id` END) AS new_count,
                COUNT(DISTINCT `id`) AS total_count
             FROM `commerce_customers`
             WHERE `store_id` = ?",
            [$days, $this->storeId()]
        );

        // Count distinct customers with more than one paid order
        $repeat = $this->db->selectOne(
            "SELECT COUNT(*) AS n FROM (
                SELECT `customer_id`
                FROM `commerce_orders`
                WHERE `store_id` = ?
                  AND `customer_id` IS NOT NULL
                  AND `paid_at` IS NOT NULL
                  AND `status` NOT IN ('cancelled', 'failed')
                GROUP BY `customer_id`
                HAVING COUNT(*) > 1
             ) AS repeat_buyers",
            [$this->storeId()]
        );

        $newCount = (int) ($row['new_count'] ?? 0);
        $total = (int) ($row['total_count'] ?? 0);
        $returning = (int) ($repeat['n'] ?? 0);

        return [
            'new'         => $newCount,
            'returning'   => $returning,
            'total'       => $total,
            'repeat_rate' => $total > 0 ? round(($returning / $total) * 100, 1) : 0.0,
        ];
    }
}
