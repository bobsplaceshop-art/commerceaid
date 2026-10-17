<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

use CorporateAid\Commerce\Repositories\ImportBatchRepository;
use CorporateAid\Commerce\Repositories\ImportRowRepository;
use RuntimeException;

/**
 * The rollback phase of the import pipeline. Stage 016.
 *
 * Reverses a committed batch. Every row in 'committed'
 * status is walked in reverse row order (last-imported
 * first) - the reverse of the order the committer
 * processed them in. A row that was an update restores
 * its previous values; a row that was a create is
 * deleted.
 *
 * Per-row atomic, chunked at 500 for progress and
 * memory, same as the committer. Each handler's delete()
 * or restore() call is its own transaction (via the
 * handler's repository). The rollback does NOT open a
 * chunk-level transaction.
 *
 * Per-row failure: the handler throws, the row is left
 * in 'committed' status with the rollback error recorded
 * on its error column (via ImportRowRepository's
 * markRollbackFailed()). The batch moves to 'rolled_back'
 * regardless - the terminal state reflects that the
 * rollback action was attempted. The per-row state is
 * the record of what actually reversed. A re-run of the
 * rollback processes only the rows still in 'committed'
 * status, which is the idempotency property: the failed
 * rows get another try.
 *
 * Reverse row order matters for foreign keys. A CSV that
 * imports a category before its parent, or a product
 * before the categories it references, reverses cleanly
 * in reverse order. A CSV that imports in a non-dependency
 * order (parent after child) does not have an order that
 * can reverse cleanly; the handler's delete() throws on
 * the FK and the row's rollback is recorded as failed,
 * the loop continues. The merchant fixes the CSV or
 * accepts the partial rollback.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportRollback
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private ImportBatchRepository $batches,
        private ImportRowRepository $rows,
    ) {
    }

    /**
     * Roll back every committed row in a batch.
     *
     * @return array{
     *     reversed:int,
     *     failed:int,
     *     status:string,
     *     errors:array<int, array{row:int, error:string}>
     * }
     */
    public function rollback(int $batchId, ImportHandler $handler): array
    {
        $batch = $this->batches->findById($batchId);
        if ($batch === null) {
            throw new RuntimeException('Batch not found: ' . $batchId);
        }
        $status = (string) ($batch['status'] ?? '');
        if (!in_array($status, [
            ImportBatchStatus::COMMITTED,
            ImportBatchStatus::PARTIALLY_COMMITTED,
            ImportBatchStatus::ROLLED_BACK,
        ], true)) {
            throw new RuntimeException(
                'Batch is not in a rollback-capable state; current status: ' . $status
            );
        }

        // Committed rows, reversed. Already-rolled-back
        // rows are excluded by the status filter, so a
        // re-run processes only the rows that did not
        // reverse cleanly on the previous attempt.
        $committedRows = $this->rows->listForBatchByStatus(
            $batchId,
            'committed'
        );
        usort($committedRows, function (array $a, array $b): int {
            return (int) $b['row_number'] <=> (int) $a['row_number'];
        });

        $reversed = 0;
        $failed   = 0;
        $errors   = [];

        foreach (array_chunk($committedRows, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $row) {
                $rowId = (int) $row['id'];
                $entityId = (int) ($row['entity_id'] ?? 0);

                if ($entityId <= 0) {
                    $msg = 'No entity id recorded for this row.';
                    $this->rows->markRollbackFailed($rowId, $msg);
                    $failed++;
                    if (count($errors) < 20) {
                        $errors[] = ['row' => (int) $row['row_number'], 'error' => $msg];
                    }
                    continue;
                }

                $previous = [];
                if (!empty($row['previous_values_json'])) {
                    $decoded = json_decode((string) $row['previous_values_json'], true);
                    if (is_array($decoded)) {
                        $previous = $decoded;
                    }
                }

                try {
                    if ($previous !== []) {
                        $handler->restore($entityId, $previous);
                    } else {
                        $handler->delete($entityId);
                    }
                    $this->rows->markRolledBack($rowId);
                    $reversed++;
                } catch (\Throwable $e) {
                    $msg = $e->getMessage() !== '' ? $e->getMessage() : 'Unknown error';
                    $this->rows->markRollbackFailed($rowId, $msg);
                    $failed++;
                    if (count($errors) < 20) {
                        $errors[] = ['row' => (int) $row['row_number'], 'error' => $msg];
                    }
                }
            }
        }

        $this->batches->setStatus($batchId, ImportBatchStatus::ROLLED_BACK, [
            'rolled_back_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'reversed' => $reversed,
            'failed'   => $failed,
            'status'   => ImportBatchStatus::ROLLED_BACK,
            'errors'   => $errors,
        ];
    }
}
