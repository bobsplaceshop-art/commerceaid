<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\ImportBatchRepository;
use CorporateAid\Commerce\Repositories\ImportRowRepository;
use CorporateAid\Commerce\Services\Import\CsvReader;
use CorporateAid\Commerce\Services\Import\Handlers\ImportCustomerHandler;
use CorporateAid\Commerce\Services\Import\Handlers\ImportProductHandler;
use CorporateAid\Commerce\Services\Import\Handlers\ImportSalesHistoryHandler;
use CorporateAid\Commerce\Services\Import\ImportBatchStatus;
use CorporateAid\Commerce\Services\Import\ImportHandler;
use CorporateAid\Commerce\Services\Import\ImportCommitter;
use CorporateAid\Commerce\Services\Import\ImportTemplateGenerator;
use CorporateAid\Commerce\Services\Import\ImportRollback;
use CorporateAid\Commerce\Services\Import\ImportValidator;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin CSV import surface. Stage 016.
 *
 * Session 1 of Stage 016 ships the list screen and the
 * upload flow. The map, validate, preview, commit, and
 * rollback screens ship in Session 2. The routes are
 * declared now so the navigation is complete; the
 * screens that do not yet exist return a "coming in
 * Session 2" placeholder in the batch detail.
 *
 * Routes (all under /commerce/ca-admin/import):
 *
 *   GET  /              index   - the batch list
 *   GET  /upload        showUpload - the entity picker + file input
 *   POST /upload        upload  - store the file, create the batch
 *   GET  /{id}          show    - the batch detail (Session 2 fills this in)
 *
 * Permissions: catalog.import (view and manage)
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportController extends AdminController
{
    private const PER_PAGE = 25;
    private const MAX_FILE_BYTES = 10 * 1024 * 1024; // 10 MB

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);

        $status = (string) $request->query('status', '');
        if ($status !== ''
            && !in_array($status, ImportBatchStatus::all(), true)
        ) {
            $status = '';
        }

        $entity = (string) $request->query('entity', '');
        $page   = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }
        if ($entity !== '') {
            $filters['entity'] = $entity;
        }

        $result = $repo->paginate($filters, $page, self::PER_PAGE);

        return $this->render('admin.import.index', [
            'title'   => 'Import',
            'result'  => $result,
            'status'  => $status,
            'entity'  => $entity,
            'handlers' => $this->handlerLabels(),
        ]);
    }

    /** @param array<string,string> $params */
    public function showUpload(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.import.upload', [
            'title'       => 'Upload CSV',
            'handlers'    => $this->handlerLabels(),
            'errors'      => $session->getFlash('import_errors', []),
            'old'         => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function upload(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(ImportBatchRepository::class);

        $entity   = (string) $request->input('entity', '');
        $handlers = $this->handlerRegistry();

        if (!isset($handlers[$entity])) {
            $session->flash('import_errors', [
                'entity' => 'Choose an entity to import.',
            ]);
            $session->flashOldInput(['entity' => $entity]);
            return Response::redirect('/commerce/ca-admin/import/upload');
        }

        $file = $request->file('csv');
        if ($file === null) {
            $session->flash('import_errors', [
                'csv' => 'Choose a CSV file to upload.',
            ]);
            $session->flashOldInput(['entity' => $entity]);
            return Response::redirect('/commerce/ca-admin/import/upload');
        }

        $name = (string) ($file['name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $tmp  = (string) ($file['tmp_name'] ?? '');

        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            $session->flash('import_errors', [
                'csv' => 'Only .csv files are accepted. Save the file as CSV and retry.',
            ]);
            $session->flashOldInput(['entity' => $entity]);
            return Response::redirect('/commerce/ca-admin/import/upload');
        }
        if ($size <= 0 || $size > self::MAX_FILE_BYTES) {
            $session->flash('import_errors', [
                'csv' => 'File must be larger than 0 bytes and no more than 10 MB.',
            ]);
            $session->flashOldInput(['entity' => $entity]);
            return Response::redirect('/commerce/ca-admin/import/upload');
        }

        // Create the batch row first so the file path uses
        // the batch id.
        $user   = $this->container->get(\CorporateAid\Core\Auth::class)->user();
        $batchId = $repo->create([
            'entity'     => $entity,
            'filename'   => $name,
            'created_by' => $user?->id,
        ]);

        $storageDir = dirname(__DIR__, 4) . '/storage/imports';
        if (!is_dir($storageDir) && !@mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
            $session->flash('import_errors', [
                'csv' => 'Server could not create the import directory.',
            ]);
            return Response::redirect('/commerce/ca-admin/import/upload');
        }

        $dest = $storageDir . '/' . $batchId . '.csv';
        if (!@move_uploaded_file($tmp, $dest)) {
            $session->flash('import_errors', [
                'csv' => 'The uploaded file could not be stored.',
            ]);
            return Response::redirect('/commerce/ca-admin/import/upload');
        }

        $session->flash('import_success',
            'File uploaded. Map the columns to continue.');
        return Response::redirect('/commerce/ca-admin/import/' . $batchId);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $rowRepo = $this->container->get(ImportRowRepository::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }
        $rowCounts = $rowRepo->countByStatus($id);
        $session = $this->container->get(Session::class);
        return $this->render('admin.import.show', [
            'title'      => 'Import batch #' . $id,
            'batch'      => $batch,
            'rowCounts'  => $rowCounts,
            'handlers'   => $this->handlerLabels(),
            'flashOk'    => $session->getFlash('import_success'),
            'flashErr'   => $session->getFlash('import_errors', []),
        ]);
    }

    /**
     * The map screen. Shows one select per handler field,
     * populated from the CSV header. The merchant matches
     * CSV columns to entity fields.
     *
     * @param array<string,string> $params
     */
    public function showMap(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }

        $entity = (string) $batch['entity'];
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity on this batch.');
        }
        $handler = $handlers[$entity];

        $storageDir = dirname(__DIR__, 4) . '/storage/imports';
        $csvPath = $storageDir . '/' . $id . '.csv';
        if (!is_file($csvPath)) {
            return $this->fail('The uploaded CSV file is missing from disk.');
        }

        $reader = $this->container->get(CsvReader::class);
        try {
            [$header, $rows] = $reader->read($csvPath);
        } catch (\RuntimeException $e) {
            return $this->fail('Could not read the CSV: ' . $e->getMessage());
        }

        $session = $this->container->get(Session::class);
        return $this->render('admin.import.map', [
            'title'    => 'Map columns: batch #' . $id,
            'batch'    => $batch,
            'handler'  => $handler,
            'header'   => $header,
            'rowCount' => count($rows),
            'mapping'  => $repo->getMapping($id),
            'errors'   => $session->getFlash('import_errors', []),
        ]);
    }

    /**
     * Persist the merchant's column mapping. Redirects to
     * the batch detail screen where the "Validate" button
     * is waiting.
     *
     * @param array<string,string> $params
     */
    public function saveMap(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $session = $this->container->get(Session::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }

        $entity = (string) $batch['entity'];
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity on this batch.');
        }
        $handler = $handlers[$entity];

        $raw = (array) $request->input('map', []);
        $mapping = [];
        foreach ($handler->fields() as $fieldName => $fieldDef) {
            $csvCol = (string) ($raw[$fieldName] ?? '');
            if ($csvCol === '') {
                if ($fieldDef['required']) {
                    $session->flash('import_errors', [
                        'map' => "Field '{$fieldDef['label']}' is required but not mapped to a CSV column.",
                    ]);
                    return Response::redirect('/commerce/ca-admin/import/' . $id . '/map');
                }
                continue;
            }
            $mapping[$fieldName] = $csvCol;
        }

        $repo->setMapping($id, $mapping);
        $session->flash('import_success', 'Columns mapped. Validate the batch next.');
        return Response::redirect('/commerce/ca-admin/import/' . $id);
    }

    /**
     * Run the validation pass. Parses the CSV, applies
     * the mapping, walks each row, calls the handler's
     * validate(). Writes the per-row records and updates
     * the batch counters. Nothing is committed to the
     * entity tables.
     *
     * @param array<string,string> $params
     */
    public function runValidation(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $session = $this->container->get(Session::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }

        $entity = (string) $batch['entity'];
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity on this batch.');
        }
        $handler = $handlers[$entity];

        $validator = $this->container->get(ImportValidator::class);
        try {
            $result = $validator->validate($id, $handler);
        } catch (\Throwable $e) {
            $session->flash('import_errors', ['validate' => $e->getMessage()]);
            return Response::redirect('/commerce/ca-admin/import/' . $id);
        }

        $msg = sprintf(
            'Validated %d rows: %d valid, %d invalid.',
            $result['total'],
            $result['valid'],
            $result['invalid']
        );
        $session->flash('import_success', $msg);
        return Response::redirect('/commerce/ca-admin/import/' . $id);
    }

    /**
     * The preview screen. Partitions the batch's rows
     * into would-create, would-update, and invalid. The
     * merchant reviews before committing.
     *
     * @param array<string,string> $params
     */
    public function preview(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $rowRepo = $this->container->get(ImportRowRepository::class);
        $session = $this->container->get(Session::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }
        if ((string) $batch['status'] !== ImportBatchStatus::VALIDATED) {
            $session->flash('import_errors', ['preview' => 'The batch must be validated before it can be previewed.']);
            return Response::redirect('/commerce/ca-admin/import/' . $id);
        }

        $entity = (string) $batch['entity'];
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity on this batch.');
        }
        $handler = $handlers[$entity];

        $rows = $rowRepo->listForBatch($id);
        $wouldCreate = [];
        $wouldUpdate = [];
        $invalid = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'invalid') {
                $invalid[] = $row;
                continue;
            }
            if ($status !== 'valid') {
                continue;
            }
            $naturalKey = (string) $row['natural_key'];
            if ($handler->findIdByNaturalKey($naturalKey) !== null) {
                $wouldUpdate[] = $row;
            } else {
                $wouldCreate[] = $row;
            }
        }

        return $this->render('admin.import.preview', [
            'title'       => 'Preview: batch #' . $id,
            'batch'       => $batch,
            'handler'     => $handler,
            'wouldCreate' => $wouldCreate,
            'wouldUpdate' => $wouldUpdate,
            'invalid'     => $invalid,
        ]);
    }

    /**
     * Run the committer. Writes the valid rows to the
     * entity tables. Redirects to the batch detail with
     * the outcome in the flash.
     *
     * @param array<string,string> $params
     */
    public function commit(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $session = $this->container->get(Session::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }

        $entity = (string) $batch['entity'];
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity on this batch.');
        }
        $handler = $handlers[$entity];

        $committer = $this->container->get(ImportCommitter::class);
        try {
            $result = $committer->commit($id, $handler);
        } catch (\Throwable $e) {
            $session->flash('import_errors', ['commit' => $e->getMessage()]);
            return Response::redirect('/commerce/ca-admin/import/' . $id);
        }

        $msg = sprintf(
            'Commit finished: %d created, %d updated, %d failed.',
            $result['created'],
            $result['updated'],
            $result['failed']
        );
        if ($result['failed'] > 0) {
            $session->flash('import_errors', ['commit' => $msg . ' Fix the failed rows and re-run.']);
        } else {
            $session->flash('import_success', $msg);
        }
        return Response::redirect('/commerce/ca-admin/import/' . $id);
    }

    /**
     * Roll back a committed batch. Reverses every
     * committed row. Redirects to the batch detail.
     *
     * @param array<string,string> $params
     */
    public function rollback(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $session = $this->container->get(Session::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }

        $entity = (string) $batch['entity'];
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity on this batch.');
        }
        $handler = $handlers[$entity];

        $rollback = $this->container->get(ImportRollback::class);
        try {
            $result = $rollback->rollback($id, $handler);
        } catch (\Throwable $e) {
            $session->flash('import_errors', ['rollback' => $e->getMessage()]);
            return Response::redirect('/commerce/ca-admin/import/' . $id);
        }

        $msg = sprintf(
            'Rollback finished: %d reversed, %d failed.',
            $result['reversed'],
            $result['failed']
        );
        if ($result['failed'] > 0) {
            $session->flash('import_errors', ['rollback' => $msg]);
        } else {
            $session->flash('import_success', $msg);
        }
        return Response::redirect('/commerce/ca-admin/import/' . $id);
    }

    /**
     * The failed-row export. Emits a CSV of the batch's
     * invalid rows with an appended _error column.
     *
     * @param array<string,string> $params
     */
    public function failedExport(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $rowRepo = $this->container->get(ImportRowRepository::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }

        $invalidRows = $rowRepo->listForBatchByStatus($id, 'invalid');

        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return $this->fail('Could not open a temporary stream.');
        }

        // Header: every distinct key in the payloads plus _error.
        $allKeys = ['_row_number', '_natural_key'];
        foreach ($invalidRows as $row) {
            $payload = json_decode((string) $row['payload_json'], true);
            if (is_array($payload)) {
                foreach (array_keys($payload) as $k) {
                    if (!in_array($k, $allKeys, true)) {
                        $allKeys[] = $k;
                    }
                }
            }
        }
        $allKeys[] = '_error';
        fputcsv($out, $allKeys);

        foreach ($invalidRows as $row) {
            $payload = json_decode((string) $row['payload_json'], true);
            $payload = is_array($payload) ? $payload : [];
            $line = [
                '_row_number'  => (int) $row['row_number'],
                '_natural_key' => (string) $row['natural_key'],
            ];
            foreach ($allKeys as $k) {
                if ($k === '_row_number' || $k === '_natural_key' || $k === '_error') {
                    continue;
                }
                $line[$k] = $payload[$k] ?? '';
            }
            $line['_error'] = (string) ($row['error'] ?? '');
            fputcsv($out, $line);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        $csv = $csv === false ? '' : $csv;

        $filename = 'import-' . $id . '-failed-' . date('Y-m-d') . '.csv';
        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download a template CSV for an entity. The header
     * is the handler's fields() keys, in order. One
     * sample row demonstrates the expected format.
     *
     * @param array<string,string> $params
     */
    public function template(Request $request, array $params = []): Response
    {
        $entity = (string) ($params['entity'] ?? '');
        $handlers = $this->handlerRegistry();
        if (!isset($handlers[$entity])) {
            return $this->fail('Unknown entity.');
        }
        $handler = $handlers[$entity];

        $generator = $this->container->get(ImportTemplateGenerator::class);
        [$header, $sample] = $generator->generate($handler);

        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return $this->fail('Could not open a temporary stream.');
        }
        fputcsv($out, $header);
        fputcsv($out, array_values($sample));
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        $csv = $csv === false ? '' : $csv;

        $filename = 'import-template-' . $entity . '.csv';
        return new Response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // -----------------------------------------------------------------
    // The handler registry
    // -----------------------------------------------------------------

    /**
     * @return array<string, ImportHandler>
     */
    private function handlerRegistry(): array
    {
        return [
            'product'  => $this->container->get(ImportProductHandler::class),
            'customer' => $this->container->get(ImportCustomerHandler::class),
            'sales'    => $this->container->get(ImportSalesHistoryHandler::class),
        ];
    }

    /**
     * The label map the entity picker and the list screen
     * use. Same handler registry; just the labels.
     *
     * @return array<string, string>
     */
    private function handlerLabels(): array
    {
        $labels = [];
        foreach ($this->handlerRegistry() as $key => $handler) {
            $labels[$key] = $handler->entityLabel();
        }
        return $labels;
    }

    /**
     * Render a failure that should send the merchant back
     * to the batch list. Flashes the message and redirects.
     *
     * Same shape as the sibling admin controllers'
     * private fail() methods. Not on AdminController - the
     * codebase's pattern is one per controller.
     */
    private function fail(string $message): Response
    {
        $session = $this->container->get(Session::class);
        $session->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/import');
    }
}
