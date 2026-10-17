<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Reads and writes commerce_import_rows. Stage 016.
 *
 * One row per CSV row in a batch. The row carries the
 * parsed payload, the previous values (for rollback of an
 * update), the entity id it created or updated, and its
 * status (pending, valid, invalid, committed,
 * rolled_back).
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportRowRepository extends CommerceRepository
{
    /**
     * Bulk-insert the parsed rows for a batch. One INSERT
     * per batch, N value tuples. Returns nothing: the
     * rows are anonymous until a commit assigns them an
     * entity id.
     *
     * @param array<int, array{row_number: int, natural_key: string,
     *                        payload_json: array<string, string>}> $rows
     */
    public function insertBatch(int $batchId, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $chunkSize = 500;
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $placeholders = [];
            $params       = [];
            foreach ($chunk as $r) {
                $placeholders[] = '(?, ?, ?, ?, ?)';
                $params[]       = $this->storeId();
                $params[]       = $batchId;
                $params[]       = (int) $r['row_number'];
                $params[]       = (string) $r['natural_key'];
                $params[]       = json_encode(
                    $r['payload_json'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }
            $this->db->execute(
                "INSERT INTO `commerce_import_rows`
                    (`store_id`, `batch_id`, `row_number`,
                     `natural_key`, `payload_json`)
                 VALUES " . implode(', ', $placeholders),
                $params
            );
        }
    }

    /**
     * Set the status of every row in a batch. Used to move
     * from 'pending' to 'valid' or 'invalid' after
     * validation, without writing per-row.
     */
    public function setStatusForBatch(int $batchId, string $from, string $to): void
    {
        $this->db->execute(
            "UPDATE `commerce_import_rows`
                SET `status` = ?
              WHERE `store_id` = ? AND `batch_id` = ? AND `status` = ?",
            [$to, $this->storeId(), $batchId, $from]
        );
    }

    /**
     * Mark one row valid or invalid. Called by the
     * validation phase as it walks each row.
     */
    public function setRowStatus(int $rowId, string $status, ?string $error = null): void
    {
        $this->db->execute(
            "UPDATE `commerce_import_rows`
                SET `status` = ?, `error` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [$status, $error, $this->storeId(), $rowId]
        );
    }

    /**
     * Mark a row committed after a successful create or
     * update. Records the entity id (for rollback of a
     * create) and the previous values (for rollback of an
     * update).
     *
     * @param array<string, mixed> $previousValues
     */
    public function markCommitted(
        int $rowId,
        int $entityId,
        array $previousValues = []
    ): void {
        $this->db->execute(
            "UPDATE `commerce_import_rows`
                SET `status` = 'committed',
                    `entity_id` = ?,
                    `previous_values_json` = ?,
                    `error` = NULL
              WHERE `store_id` = ? AND `id` = ?",
            [
                $entityId,
                $previousValues === []
                    ? null
                    : json_encode(
                        $previousValues,
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    ),
                $this->storeId(),
                $rowId,
            ]
        );
    }

    /**
     * Mark a row failed during commit (a uniqueness
     * conflict that validation did not foresee, a
     * foreign-key miss). The chunk rolls back; the row
     * stays as a record.
     */
    public function markCommitFailed(int $rowId, string $error): void
    {
        $this->db->execute(
            "UPDATE `commerce_import_rows`
                SET `status` = 'invalid', `error` = ?
              WHERE `store_id` = ? AND `id` = ?",
            [$error, $this->storeId(), $rowId]
        );
    }

    /**
     * Mark a row rolled back after a batch is reversed.
     */
    public function markRolledBack(int $rowId): void
    {
        $this->db->execute(
            "UPDATE `commerce_import_rows`
                SET `status` = 'rolled_back'
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $rowId]
        );
    }

    /**
     * Read every row in a batch, ordered by row number.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listForBatch(int $batchId): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_import_rows`
              WHERE `store_id` = ? AND `batch_id` = ?
              ORDER BY `row_number` ASC",
            [$this->storeId(), $batchId]
        );
    }

    /**
     * Read rows for a batch in a specific status.
     *
     * @return array<int, array<string,mixed>>
     */
    public function listForBatchByStatus(int $batchId, string $status): array
    {
        return $this->db->select(
            "SELECT * FROM `commerce_import_rows`
              WHERE `store_id` = ? AND `batch_id` = ? AND `status` = ?
              ORDER BY `row_number` ASC",
            [$this->storeId(), $batchId, $status]
        );
    }

    /**
     * Count rows per status for a batch. Used by the
     * preview screen's summary and by the rollback.
     *
     * @return array<string, int>  status => count
     */
    public function countByStatus(int $batchId): array
    {
        $rows = $this->db->select(
            "SELECT `status`, COUNT(*) AS c
               FROM `commerce_import_rows`
              WHERE `store_id` = ? AND `batch_id` = ?
              GROUP BY `status`",
            [$this->storeId(), $batchId]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['c'];
        }
        return $counts;
    }

    /**
     * Delete every row in a batch. Called when the batch
     * itself is deleted (the FK cascade would do it
     * anyway; explicit is safer).
     */
    public function deleteForBatch(int $batchId): void
    {
        $this->db->execute(
            "DELETE FROM `commerce_import_rows`
              WHERE `store_id` = ? AND `batch_id` = ?",
            [$this->storeId(), $batchId]
        );
    }
}
