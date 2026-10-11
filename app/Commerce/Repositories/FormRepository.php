<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Form;

/**
 * Forms. Stage 009.
 *
 * Standard repository for commerce_forms. Every read
 * filters store_id and (unless explicitly asking for
 * deleted rows) deleted_at IS NULL.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormRepository extends CommerceRepository
{
    public function findById(int $id): ?Form
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_forms`
              WHERE `store_id` = ? AND `id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? Form::fromRow($row) : null;
    }

    /**
     * Public lookup. Only published forms resolve here;
     * a draft or archived form returns null so the
     * public route 404s.
     */
    public function findPublishedBySlug(string $slug): ?Form
    {
        if ($slug === '') return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_forms`
              WHERE `store_id` = ? AND `slug` = ? AND `status` = 'published'
                AND `deleted_at` IS NULL
              LIMIT 1",
            [$this->storeId(), $slug]
        );

        return $row !== null ? Form::fromRow($row) : null;
    }

    /**
     * Admin lookup. Any status, still hides soft-deleted.
     */
    public function findBySlug(string $slug): ?Form
    {
        if ($slug === '') return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_forms`
              WHERE `store_id` = ? AND `slug` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$this->storeId(), $slug]
        );

        return $row !== null ? Form::fromRow($row) : null;
    }

    /**
     * @return array<int,Form>
     */
    public function listForStore(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_forms`
              WHERE `store_id` = ? AND `deleted_at` IS NULL
              ORDER BY `name` ASC, `id` ASC",
            [$this->storeId()]
        );

        return array_map(fn ($r) => Form::fromRow($r), $rows);
    }

    /**
     * Paginated admin list with filters.
     *
     * Filters: status (draft|published|archived|''),
     * search (name or slug substring).
     *
     * @param array<string,mixed> $filters
     * @return array{items: array<int,Form>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_forms` WHERE {$where}",
            $params
        );

        $rows = $this->db->select(
            "SELECT * FROM `commerce_forms`
              WHERE {$where}
              ORDER BY `updated_at` DESC, `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'items'    => array_map(fn ($r) => Form::fromRow($r), $rows),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) FROM `commerce_forms`
                    WHERE `store_id` = ? AND `slug` = ? AND `deleted_at` IS NULL";
        $params = [$this->storeId(), $slug];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        return ((int) $this->db->scalar($sql, $params)) > 0;
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
            "INSERT INTO `commerce_forms` ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        if ($id <= 0 || $data === []) return;

        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]   = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $this->storeId();
        $params[] = $id;

        $this->db->execute(
            "UPDATE `commerce_forms`
                SET " . implode(', ', $sets) . "
              WHERE `store_id` = ? AND `id` = ?",
            $params
        );
    }

    /**
     * Soft delete. The row is preserved for audit; every
     * read in this repository filters it out.
     */
    public function softDelete(int $id): void
    {
        if ($id <= 0) return;

        $this->db->execute(
            "UPDATE `commerce_forms`
                SET `deleted_at` = NOW()
              WHERE `store_id` = ? AND `id` = ? AND `deleted_at` IS NULL",
            [$this->storeId(), $id]
        );
    }

    public function restore(int $id): void
    {
        if ($id <= 0) return;

        $this->db->execute(
            "UPDATE `commerce_forms`
                SET `deleted_at` = NULL
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    /**
     * Whether a form has any submissions. Used to gate
     * deletion: a form with submissions cannot be
     * hard-deleted without first archiving them.
     */
    public function hasSubmissions(int $id): bool
    {
        if ($id <= 0) return false;

        return ((int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_form_submissions`
              WHERE `store_id` = ? AND `form_id` = ? LIMIT 1",
            [$this->storeId(), $id]
        )) > 0;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string, 1:array<int,mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $where  = "`store_id` = ? AND `deleted_at` IS NULL";
        $params = [$this->storeId()];

        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $where   .= " AND `status` = ?";
            $params[] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where   .= " AND (`name` LIKE ? OR `slug` LIKE ?)";
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        return [$where, $params];
    }
}
