<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\FormSubmission;

/**
 * Form submissions. Stage 009.
 *
 * Every read filters store_id. Submissions are hard-deleted
 * (the merchant removes the row when they decide a
 * submission is not worth keeping); there is no soft-delete
 * column. The status enum carries the archival state.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormSubmissionRepository extends CommerceRepository
{
    /**
     * The four states the admin tab bar shows. The order
     * matches the tab order in the UI.
     *
     * @var array<int,string>
     */
    public const STATUSES = ['new', 'read', 'spam', 'archived'];

    public function findById(int $id): ?FormSubmission
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_form_submissions`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? FormSubmission::fromRow($row) : null;
    }

    /**
     * Paginated admin list, scoped to a form.
     *
     * Filters:
     *   status      'new'|'read'|'spam'|'archived'|''
     *               (empty string = all statuses)
     *   search      Substring matched against submitter_email
     *               and submitter_name
     *   date_from   YYYY-MM-DD (inclusive)
     *   date_to     YYYY-MM-DD (inclusive)
     *
     * @param array<string,mixed> $filters
     * @return array{items: array<int,FormSubmission>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(int $formId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if ($formId <= 0) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];
        }

        [$where, $params] = $this->buildFilters($formId, $filters);

        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_form_submissions` WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_form_submissions`
              WHERE {$where}
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => FormSubmission::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Every matching submission, unpaginated, capped at a
     * sane maximum. Used by the CSV export. The cap exists
     * so a merchant cannot accidentally export 5 million
     * rows and lock the process.
     *
     * @param array<string,mixed> $filters
     * @return array<int,FormSubmission>
     */
    public function listForExport(int $formId, array $filters = [], int $limit = 10000): array
    {
        if ($formId <= 0) return [];

        [$where, $params] = $this->buildFilters($formId, $filters);

        $limit = max(1, min(50000, $limit));

        $rows = $this->db->select(
            "SELECT * FROM `commerce_form_submissions`
              WHERE {$where}
              ORDER BY `created_at` ASC, `id` ASC
              LIMIT {$limit}",
            $params
        );

        return array_map(fn ($r) => FormSubmission::fromRow($r), $rows);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $data = array_merge(['store_id' => $this->storeId()], $data);

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = '`' . implode('`, `', $columns) . '`';

        return (int) $this->db->insert(
            "INSERT INTO `commerce_form_submissions` ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        if ($id <= 0) return;
        if (!in_array($status, self::STATUSES, true)) return;

        $this->db->execute(
            "UPDATE `commerce_form_submissions`
                SET `status` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [$status, $this->storeId(), $id]
        );
    }

    public function updateNotes(int $id, string $notes): void
    {
        if ($id <= 0) return;

        $this->db->execute(
            "UPDATE `commerce_form_submissions`
                SET `notes` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [$notes, $this->storeId(), $id]
        );
    }

    /**
     * Bulk status update. Ids are filtered to the current
     * store; an id from another store is silently ignored.
     *
     * @param array<int,int> $ids
     * @return int  Rows actually changed.
     */
    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if ($ids === []) return 0;
        if (!in_array($status, self::STATUSES, true)) return 0;

        $ids = array_values(array_filter(
            array_map('intval', $ids),
            static fn ($id) => $id > 0
        ));
        if ($ids === []) return 0;

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $params       = array_merge([$status, $this->storeId()], $ids);

        $affected = $this->db->execute(
            "UPDATE `commerce_form_submissions`
                SET `status` = ?
              WHERE `store_id` = ? AND `id` IN ({$placeholders})",
            $params
        );

        return $affected;
    }

    /**
     * Bulk delete. Same scoping rules as bulkUpdateStatus.
     *
     * @param array<int,int> $ids
     * @return int  Rows actually deleted.
     */
    public function bulkDelete(array $ids): int
    {
        if ($ids === []) return 0;

        $ids = array_values(array_filter(
            array_map('intval', $ids),
            static fn ($id) => $id > 0
        ));
        if ($ids === []) return 0;

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $params       = array_merge([$this->storeId()], $ids);

        return $this->db->execute(
            "DELETE FROM `commerce_form_submissions`
              WHERE `store_id` = ? AND `id` IN ({$placeholders})",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;

        $this->db->execute(
            "DELETE FROM `commerce_form_submissions`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    public function countByStatus(int $formId, string $status): int
    {
        if ($formId <= 0) return 0;
        if (!in_array($status, self::STATUSES, true)) return 0;

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_form_submissions`
              WHERE `store_id` = ? AND `form_id` = ? AND `status` = ?",
            [$this->storeId(), $formId, $status]
        );
    }

    /**
     * The four-status summary for the admin tab bar. Always
     * returns all four keys, even when the count is zero.
     *
     * @return array{new:int, read:int, spam:int, archived:int, total:int}
     */
    public function countsForForm(int $formId): array
    {
        $counts = ['new' => 0, 'read' => 0, 'spam' => 0, 'archived' => 0, 'total' => 0];
        if ($formId <= 0) return $counts;

        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS `c`
               FROM `commerce_form_submissions`
              WHERE `store_id` = ? AND `form_id` = ?
              GROUP BY `status`",
            [$this->storeId(), $formId]
        );

        foreach ($rows as $r) {
            $status = (string) $r['status'];
            $c      = (int) $r['c'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = $c;
            }
            $counts['total'] += $c;
        }

        return $counts;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string, 1:array<int,mixed>}
     */
    private function buildFilters(int $formId, array $filters): array
    {
        $where  = "`store_id` = ? AND `form_id` = ?";
        $params = [$this->storeId(), $formId];

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, self::STATUSES, true)) {
            $where   .= " AND `status` = ?";
            $params[] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where   .= " AND (`submitter_email` LIKE ? OR `submitter_name` LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $from = trim((string) ($filters['date_from'] ?? ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where   .= " AND `created_at` >= ?";
            $params[] = $from . ' 00:00:00';
        }

        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where   .= " AND `created_at` <= ?";
            $params[] = $to . ' 23:59:59';
        }

        return [$where, $params];
    }
}
