<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;

/**
 * Read side of core_activities.
 *
 * core_activities is the business-visible timeline of "what
 * happened" - "Esther Chemutai published Product #42" as a
 * human sentence. Distinct from core_audit_logs (raw HTTP
 * audit: method, path, IP, hash) and from storage/logs/app.log
 * (the developer log).
 *
 * Three consumers see the three streams differently:
 *
 *   core_audit_logs        Audit Trail screen - developer
 *   core_activities        Activity Log screen - merchant
 *   storage/logs/app.log   System Logs screen - developer
 *
 * The merchant's Activity Log screen renders core_activities
 * as one sentence per row. No method. No path. No IP. Those
 * belong to the Audit Trail.
 *
 * The table is not store-scoped. All stores share one
 * timeline. A future multi-company install would add scoping.
 *
 * IP addresses are stored as VARBINARY(16) via inet_pton().
 * The read side converts back to a human string with
 * inet_ntop().
 */
final class ActivityRepository
{
    public function __construct(private Database $db) {}

    /**
     * Paginated timeline. Filters:
     *
     *   user_id    exact match against core_activities.user_id
     *   module     exact match
     *   date_from  YYYY-MM-DD (inclusive)
     *   date_to    YYYY-MM-DD (inclusive)
     *   search     substring match against description
     *
     * @param array<string,mixed> $filters
     * @return array{items: array<int,array<string,mixed>>, total:int, page:int, per_page:int, pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM `core_activities` a
              WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT a.*, u.`name` AS user_name, s.`name` AS store_name
               FROM `core_activities` a
               LEFT JOIN `core_users` u ON u.`id` = a.`user_id`
               LEFT JOIN `commerce_stores` s ON s.`id` = a.`store_id`
              WHERE {$where}
              ORDER BY a.`created_at` DESC, a.`id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        // Convert the VARBINARY ip_address back to a string for
        // display. The row mapper does not touch binary columns,
        // so the raw bytes reach the view otherwise.
        foreach ($rows as &$row) {
            $row['ip_address_text'] = null;
            $ip = $row['ip_address'] ?? null;
            if (is_string($ip) && $ip !== '') {
                $human = @inet_ntop($ip);
                $row['ip_address_text'] = $human === false ? null : $human;
            }
        }
        unset($row);

        return [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * Distinct modules with activity counts, for the filter
     * dropdown.
     *
     * @return array<int,array{module:string, count:int}>
     */
    public function activeModules(): array
    {
        $rows = $this->db->select(
            "SELECT `module`, COUNT(*) AS c
               FROM `core_activities`
              GROUP BY `module`
              ORDER BY c DESC, `module` ASC"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'module' => (string) $r['module'],
                'count'  => (int) $r['c'],
            ];
        }
        return $out;
    }

    /**
     * Summary counts for the Overview tab and for the sub-nav
     * badges. Single query, five numbers.
     *
     * @return array{total:int, today:int, week:int}
     */
    public function counts(): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(DATE(`created_at`) = CURDATE()) AS today,
                SUM(`created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week
             FROM `core_activities`"
        );

        if ($row === null) {
            return ['total' => 0, 'today' => 0, 'week' => 0];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'week'  => (int) ($row['week'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string, 1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where  = "1 = 1";
        $params = [];

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where   .= " AND a.`user_id` = ?";
            $params[] = $userId;
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where   .= " AND a.`module` = ?";
            $params[] = $module;
        }

        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where   .= " AND a.`created_at` >= ?";
            $params[] = $from . ' 00:00:00';
        }

        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where   .= " AND a.`created_at` <= ?";
            $params[] = $to . ' 23:59:59';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where   .= " AND a.`description` LIKE ?";
            $params[] = '%' . $search . '%';
        }

        return [$where, $params];
    }
}
