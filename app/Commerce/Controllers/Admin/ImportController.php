<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\ImportBatchRepository;
use CorporateAid\Commerce\Services\Import\Handlers\ImportBrandHandler;
use CorporateAid\Commerce\Services\Import\ImportBatchStatus;
use CorporateAid\Commerce\Services\Import\ImportHandler;
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
            'File uploaded. Session 2 of this stage adds the column-mapping and validation screens.');
        return Response::redirect('/commerce/ca-admin/import');
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(ImportBatchRepository::class);
        $id   = (int) ($params['id'] ?? 0);
        $batch = $repo->findById($id);
        if ($batch === null) {
            return $this->fail('Batch not found.');
        }
        return $this->render('admin.import.show', [
            'title'   => 'Import batch #' . $id,
            'batch'   => $batch,
        ]);
    }

    // -----------------------------------------------------------------
    // The handler registry
    // -----------------------------------------------------------------

    /**
     * Session 1 ships one handler. Session 2 adds the
     * remaining six. The controller resolves the handler
     * instances through the container (each handler's
     * constructor takes a repository, auto-constructed
     * via ADR-018).
     *
     * @return array<string, ImportHandler>
     */
    private function handlerRegistry(): array
    {
        return [
            'brand' => $this->container->get(ImportBrandHandler::class),
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
}
