<?php
declare(strict_types=1);

namespace CorporateAid\Platform\Repositories;

use CorporateAid\Core\Database;

/**
 * Read side of core_audit_logs.
 *
 * Every state-changing HTTP request (POST/PUT/PATCH/DELETE)
 * has been written to core_audit_logs since Stage 005 by
 * AuditLoggerMiddleware. Nothing has ever read the table
 * until now.
 *
 * The Activity Log screen consumes this repository. It is a
 * merchant-facing surface: "who changed what, and when". It
 * is distinct from the System Logs screen (which reads the
 * developer log) and from core_activities (a business
 * timeline written explicitly by services).
 *
 * The table is not store-scoped. Every store on the install
 * shares one audit log, and the admin surface filters by
 * user and by path. A future multi-company deployment would
 * filter further.
 *
 * See docs/REMEMBER.md for the three-way split between
 * core_audit_logs (HTTP audit), core_activities (business
 * timeline), and storage/logs/app.log (developer log).
 */
final class AuditLogRepository
{
    public function __construct(private Database $db) {}

    /**
     * Paginated list. Filters:
     *
     *   user_id      exact match against core_audit_logs.user_id
     *   method       POST | PUT | PATCH | DELETE
     *   path         substring match against path
     *   entity_type  exact match
     *   date_from    YYYY-MM-DD (inclusive)
     *   date_to      YYYY-MM-DD (inclusive)
     *   search       substring match against path OR user agent
     *
     * The user name is joined for display. A missing user
     * (deleted account) yields a null user_name and the view
     * shows "User #N" or "System".
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
               FROM `core_audit_logs` al
              WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT al.*, u.`name` AS user_name, s.`name` AS store_name
               FROM `core_audit_logs` al
               LEFT JOIN `core_users` u ON u.`id` = al.`user_id`
               LEFT JOIN `commerce_stores` s ON s.`id` = al.`store_id`
              WHERE {$where}
              ORDER BY al.`created_at` DESC, al.`id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $total === 0 ? 0 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * Distinct users who have at least one audit row, in
     * name order. For the filter dropdown.
     *
     * @return array<int, array{id:int, name:string, count:int}>
     */
    public function activeUsers(): array
    {
        $rows = $this->db->select(
            "SELECT al.`user_id` AS id,
                    COALESCE(u.`name`, CONCAT('User #', al.`user_id`)) AS name,
                    COUNT(*) AS count
               FROM `core_audit_logs` al
               LEFT JOIN `core_users` u ON u.`id` = al.`user_id`
              WHERE al.`user_id` IS NOT NULL
              GROUP BY al.`user_id`, u.`name`
              ORDER BY name ASC"
        );

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'    => (int) $r['id'],
                'name'  => (string) $r['name'],
                'count' => (int) $r['count'],
            ];
        }
        return $out;
    }

    /**
     * Summary counts for the tab bar: total rows, rows
     * today, rows in the last 7 days, rows with a 4xx or
     * 5xx response, and rows whose user_id is null.
     *
     * @return array{total:int, today:int, week:int, failed:int, system:int}
     */
    public function counts(): array
    {
        $row = $this->db->selectOne(
            "SELECT
                COUNT(*) AS total,
                SUM(DATE(`created_at`) = CURDATE()) AS today,
                SUM(`created_at` >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week,
                SUM(`response_status` IS NOT NULL AND `response_status` >= 400) AS failed,
                SUM(`user_id` IS NULL) AS system_count
             FROM `core_audit_logs`"
        );

        if ($row === null) {
            return ['total' => 0, 'today' => 0, 'week' => 0, 'failed' => 0, 'system' => 0];
        }

        return [
            'total'  => (int) ($row['total'] ?? 0),
            'today'  => (int) ($row['today'] ?? 0),
            'week'   => (int) ($row['week'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'system' => (int) ($row['system_count'] ?? 0),
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
            $where   .= " AND al.`user_id` = ?";
            $params[] = $userId;
        }

        $method = strtoupper(trim((string) ($filters['method'] ?? '')));
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $where   .= " AND al.`method` = ?";
            $params[] = $method;
        }

        $path = trim((string) ($filters['path'] ?? ''));
        if ($path !== '') {
            $where   .= " AND al.`path` LIKE ?";
            $params[] = '%' . $path . '%';
        }

        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        if ($entityType !== '') {
            $where   .= " AND al.`entity_type` = ?";
            $params[] = $entityType;
        }

        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where   .= " AND al.`created_at` >= ?";
            $params[] = $from . ' 00:00:00';
        }

        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where   .= " AND al.`created_at` <= ?";
            $params[] = $to . ' 23:59:59';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where   .= " AND (al.`path` LIKE ? OR al.`user_agent` LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return [$where, $params];
    }
}
