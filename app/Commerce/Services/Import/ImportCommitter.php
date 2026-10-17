<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

use CorporateAid\Commerce\Repositories\ImportBatchRepository;
use CorporateAid\Commerce\Repositories\ImportRowRepository;
use RuntimeException;

/**
 * The commit phase of the import pipeline. Stage 016.
 *
 * Reads a validated batch's rows in 'valid' status,
 * walks them in chunks of 500, calls the handler's
 * create() or update() for each, records the outcome on
 * the row, and updates the batch counters. The batch
 * moves to 'committed' or 'partially_committed'.
 *
 * Per-row atomic, chunked for progress and memory.
 * Each handler write is its own transaction (via the
 * handler's repository). The committer does NOT open a
 * chunk-level transaction - the handlers' repositories
 * already manage their own transaction boundaries, and
 * nesting a chunk-level transaction on top would throw
 * "There is already an active transaction" from PDO on
 * the first handler that wraps its writes (customer
 * creation, inventory adjust).
 *
 * The distinction matters for failure semantics: a
 * per-row failure records a failure on that row and
 * the loop continues. A "chunk failure" is not a thing
 * in this design - the chunk is a batching unit, not a
 * transactional unit. If the handler's own transaction
 * fails on row 47 of a chunk, rows 1-46 have already
 * committed (their own transactions were successful)
 * and row 48's call proceeds. The batch ends in
 * 'partially_committed' and the merchant decides
 * whether to rollback the successful rows or fix the
 * CSV and re-run.
 *
 * Re-running the committer processes only the rows
 * still in 'valid' status. The rows already 'committed'
 * from a prior run are skipped. That is the idempotency
 * property: the batch's state accumulates progress, and
 * a partial run can be finished by another run.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportCommitter
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private ImportBatchRepository $batches,
        private ImportRowRepository $rows,
    ) {
    }

    /**
     * Commit every valid row in a batch.
     *
     * @return array{
     *     committed:int,
     *     failed:int,
     *     created:int,
     *     updated:int,
     *     status:string,
     *     errors:array<int, array{row:int, error:string}>
     * }
     */
    public function commit(int $batchId, ImportHandler $handler): array
    {
        $batch = $this->batches->findById($batchId);
        if ($batch === null) {
            throw new RuntimeException('Batch not found: ' . $batchId);
        }
        if (($batch['status'] ?? '') !== ImportBatchStatus::VALIDATED) {
            throw new RuntimeException(
                'Batch is not in the validated state; cannot commit.'
            );
        }

        $validRows = $this->rows->listForBatchByStatus($batchId, 'valid');

        $created    = 0;
        $updated    = 0;
        $failed     = 0;
        $errors     = [];
        $processed  = 0;

        foreach (array_chunk($validRows, self::CHUNK_SIZE) as $chunk) {
            foreach ($chunk as $row) {
                $processed++;
                $rowId = (int) $row['id'];
                $naturalKey = (string) $row['natural_key'];

                $payload = json_decode((string) $row['payload_json'], true);
                if (!is_array($payload)) {
                    $this->rows->markCommitFailed($rowId, 'Row payload is not valid JSON.');
                    $failed++;
                    if (count($errors) < 20) {
                        $errors[] = ['row' => (int) $row['row_number'], 'error' => 'Row payload is not valid JSON.'];
                    }
                    continue;
                }

                try {
                    $existingId = $handler->findIdByNaturalKey($naturalKey);

                    if ($existingId !== null) {
                        $previous = $handler->update($naturalKey, $payload);
                        $this->rows->markCommitted($rowId, $existingId, $previous);
                        $updated++;
                    } else {
                        $newId = $handler->create($payload);
                        $this->rows->markCommitted($rowId, $newId, []);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $msg = $e->getMessage() !== '' ? $e->getMessage() : 'Unknown error';
                    $this->rows->markCommitFailed($rowId, $msg);
                    $failed++;
                    if (count($errors) < 20) {
                        $errors[] = ['row' => (int) $row['row_number'], 'error' => $msg];
                    }
                }
            }
        }

        $status = $failed === 0
            ? ImportBatchStatus::COMMITTED
            : ImportBatchStatus::PARTIALLY_COMMITTED;

        $this->batches->setStatus($batchId, $status, [
            'rows_created'       => $created,
            'rows_updated'       => $updated,
            'rows_failed_commit' => $failed,
            'committed_at'       => date('Y-m-d H:i:s'),
        ]);

        return [
            'committed' => $created + $updated,
            'failed'    => $failed,
            'created'   => $created,
            'updated'   => $updated,
            'status'    => $status,
            'errors'    => $errors,
        ];
    }
}
