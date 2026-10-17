<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import;

use CorporateAid\Commerce\Repositories\ImportBatchRepository;
use CorporateAid\Commerce\Repositories\ImportRowRepository;
use RuntimeException;

/**
 * The validation phase of the import pipeline. Stage 016.
 *
 * Reads a batch's CSV file, applies the merchant's column
 * mapping, walks each data row, calls the handler's
 * validate(), and writes the per-row records. The batch's
 * counters are updated and its status moves to
 * 'validated'. Nothing is written to the entity tables -
 * validation is a pure read against the file plus the
 * handler's in-memory checks.
 *
 * The caller resolves the handler and passes it in.
 * ImportController::handlerRegistry() is the source; the
 * validator does not know about the registry. That keeps
 * the validator pure and makes it independently testable.
 *
 * The validator is idempotent. Re-running it deletes the
 * batch's existing rows (via ImportRowRepository's
 * deleteForBatch) and re-inserts them. That lets the
 * merchant re-map and re-validate without orphaning rows
 * from an earlier run.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportValidator
{
    public function __construct(
        private ImportBatchRepository $batches,
        private ImportRowRepository $rows,
        private CsvReader $reader,
    ) {
    }

    /**
     * Validate every row in a batch.
     *
     * @return array{total:int, valid:int, invalid:int, errors: array<int, array{row:int, error:string}>}
     *         The first 10 errors are in the errors list, for a flash preview.
     *         The full per-row errors live on commerce_import_rows.
     */
    public function validate(int $batchId, ImportHandler $handler): array
    {
        $batch = $this->batches->findById($batchId);
        if ($batch === null) {
            throw new RuntimeException('Batch not found: ' . $batchId);
        }

        $mapping = $this->batches->getMapping($batchId);
        if ($mapping === []) {
            throw new RuntimeException('Columns have not been mapped yet.');
        }

        $storageDir = dirname(__DIR__, 3) . '/storage/imports';
        $csvPath = $storageDir . '/' . $batchId . '.csv';
        if (!is_file($csvPath)) {
            throw new RuntimeException('The uploaded CSV file is missing from disk.');
        }

        [$header, $csvRows] = $this->reader->read($csvPath);

        // Idempotent re-run: clear any rows from a previous
        // validation pass before writing the fresh set.
        $this->rows->deleteForBatch($batchId);

        $total   = 0;
        $valid   = 0;
        $invalid = 0;
        $errors  = [];

        $rowsToInsert = [];
        $rowNumber    = 0;

        foreach ($csvRows as $csvRow) {
            $rowNumber++;
            $total++;

            $mapped = $this->applyMapping($mapping, $csvRow);

            $error = $handler->validate($mapped);
            $status = $error === null ? 'valid' : 'invalid';

            if ($error === null) {
                $valid++;
            } else {
                $invalid++;
                if (count($errors) < 10) {
                    $errors[] = ['row' => $rowNumber, 'error' => $error];
                }
            }

            $naturalKey = $handler->naturalKey($mapped);

            $rowsToInsert[] = [
                'row_number'   => $rowNumber,
                'natural_key'  => $naturalKey,
                'payload_json' => $mapped,
                'status'       => $status,
                'error'        => $error,
            ];
        }

        $this->rows->insertBatch($batchId, $rowsToInsert);

        $this->batches->setStatus($batchId, ImportBatchStatus::VALIDATED, [
            'rows_total'   => $total,
            'rows_valid'   => $valid,
            'rows_invalid' => $invalid,
        ]);

        return [
            'total'   => $total,
            'valid'   => $valid,
            'invalid' => $invalid,
            'errors'  => $errors,
        ];
    }

    /**
     * Apply the mapping to a CSV row. The mapping is
     * field_name => csv_header. For each handler field,
     * read the CSV's value from the mapped header. A
     * field that is in the mapping but the header is
     * missing produces an empty string; the handler's
     * validate() catches the missing-required case.
     *
     * @param array<string, string> $mapping
     * @param array<string, string> $csvRow
     * @return array<string, string>
     */
    private function applyMapping(array $mapping, array $csvRow): array
    {
        $out = [];
        foreach ($mapping as $field => $csvHeader) {
            $out[$field] = $csvRow[$csvHeader] ?? '';
        }
        return $out;
    }
}
