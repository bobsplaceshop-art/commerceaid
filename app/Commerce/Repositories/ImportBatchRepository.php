<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Services\Import\ImportBatchStatus;

/**
 * Reads and writes commerce_import_batches. Stage 016.
 *
 * One row per CSV upload. The status lifecycle lives on
 * the row. The repositories are the only writers.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportBatchRepository extends CommerceRepository
{
    /**
     * Insert a new batch row in the 'uploaded' state.
     * Returns the new id.
     *
     * @param array{entity: string, filename: string, created_by: ?int} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_import_batches`
                (`store_id`, `entity`, `filename`, `status`, `created_by`)
             VALUES (?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                (string) $data['entity'],
                (string) $data['filename'],
                ImportBatchStatus::UPLOADED,
                $data['created_by'],
            ]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_import_batches`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: array<int, array<string,mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        [$where, $params] = $this->buildFilters($filters);

        $totalRow = $this->db->selectOne(
            "SELECT COUNT(*) AS c FROM `commerce_import_batches`
              WHERE {$where}",
            $params
        );
        $total = (int) ($totalRow['c'] ?? 0);

        $offset = ($page - 1) * $perPage;
        $items  = $this->db->select(
            "SELECT * FROM `commerce_import_batches`
              WHERE {$where}
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $pages = max(1, (int) ceil($total / $perPage));

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildFilters(array $filters): array
    {
        $clauses = ['`store_id` = ?'];
        $params  = [$this->storeId()];

        if (!empty($filters['status'])
            && in_array((string) $filters['status'], ImportBatchStatus::all(), true)
        ) {
            $clauses[] = '`status` = ?';
            $params[]  = (string) $filters['status'];
        }

        if (!empty($filters['entity'])) {
            $clauses[] = '`entity` = ?';
            $params[]  = (string) $filters['entity'];
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Write the column mapping JSON.
     *
     * @param array<string, string> $mapping  entity_field => csv_header
     */
    public function setMapping(int $id, array $mapping): void
    {
        $this->db->execute(
            "UPDATE `commerce_import_batches`
                SET `mapping_json` = ?, `status` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [
                json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ImportBatchStatus::MAPPED,
                $this->storeId(),
                $id,
            ]
        );
    }

    /**
     * Read back the mapping. Returns an empty array when
     * unset or unparseable.
     *
     * @return array<string, string>
     */
    public function getMapping(int $id): array
    {
        $row = $this->findById($id);
        if ($row === null || !is_string($row['mapping_json'] ?? null)) {
            return [];
        }
        $decoded = json_decode((string) $row['mapping_json'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Update the status and optionally the counters.
     *
     * @param array{
     *     rows_total?: int, rows_valid?: int,
     *     rows_invalid?: int, rows_created?: int,
     *     rows_updated?: int, rows_failed_commit?: int,
     *     committed_at?: string, rolled_back_at?: string
     * } $counters
     */
    public function setStatus(int $id, string $status, array $counters = []): void
    {
        $set  = ['`status` = ?'];
        $bind = [$status];

        foreach (['rows_total','rows_valid','rows_invalid',
                  'rows_created','rows_updated','rows_failed_commit'] as $c) {
            if (array_key_exists($c, $counters)) {
                $set[]  = "`{$c}` = ?";
                $bind[] = (int) $counters[$c];
            }
        }
        foreach (['committed_at','rolled_back_at'] as $d) {
            if (array_key_exists($d, $counters)) {
                $set[]  = "`{$d}` = ?";
                $bind[] = $counters[$d];
            }
        }

        $bind[] = $this->storeId();
        $bind[] = $id;

        $this->db->execute(
            "UPDATE `commerce_import_batches`
                SET " . implode(', ', $set) . "
              WHERE `store_id` = ? AND `id` = ?",
            $bind
        );
    }
}
