<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Repositories\FormFieldRepository;
use CorporateAid\Commerce\Repositories\FormRepository;
use CorporateAid\Commerce\Models\Form;
use CorporateAid\Commerce\Models\FormSubmission;
use CorporateAid\Commerce\Repositories\FormSubmissionRepository;
use CorporateAid\Commerce\Services\FormService;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin form management. Stage 009.
 *
 * Routes (all under /commerce/ca-admin/forms):
 *
 *   GET  /                     index
 *   GET  /create               showCreate
 *   POST /                     store
 *   GET  /{id}/edit            showEdit
 *   POST /{id}                 update
 *   POST /{id}/publish         publish
 *   POST /{id}/unpublish       unpublish
 *   POST /{id}/archive         archive
 *   POST /{id}/duplicate       duplicate
 *   POST /{id}/delete          destroy
 *   GET  /{id}/delete-info     deleteInfo
 *
 * Session 2a scope: form metadata only. Field operations
 * (add, update, reorder) ship in Session 2b. The edit
 * page shows a placeholder card for the field editor.
 *
 * See docs/stages/009_forms-plan.md.
 */
final class AdminFormController extends AdminController
{
    /** @var array<int,string> */
    private const VALID_STATUS_TABS = ['all', 'draft', 'published', 'archived'];

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(FormRepository::class);

        $status = (string) $request->query('status', 'all');
        if (!in_array($status, self::VALID_STATUS_TABS, true)) {
            $status = 'all';
        }

        $search = trim((string) $request->query('search', ''));
        $page   = max(1, (int) $request->query('page', 1));

        $result = $repo->paginate(
            [
                'status' => $status === 'all' ? '' : $status,
                'search' => $search,
            ],
            $page,
            20
        );

        // Tab counts reflect the active search but ignore the
        // active status, so switching tabs does not change
        // the numbers shown.
        $statusCounts = $repo->countsForFilters(['search' => $search]);

        return $this->render('admin.forms.index', [
            'title'        => 'Forms',
            'forms'        => $result['items'],
            'total'        => $result['total'],
            'page'         => $result['page'],
            'pages'        => $result['pages'],
            'status'       => $status,
            'search'       => $search,
            'statusCounts' => $statusCounts,
        ]);
    }

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.forms.create', [
            'title'  => 'New form',
            'errors' => $session->getFlash('form_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectFormInput($request);
        $errors = $this->validateFormInput($data, null);

        if ($errors !== []) {
            $session->flash('form_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/forms/create');
        }

        try {
            $userId = $this->currentUserId();
            $id     = $service->createForm($data, $userId);
        } catch (NewsletterException $e) {
            $session->flash('form_errors', ['slug' => $e->getMessage()]);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/forms/create');
        }

        $session->clearOldInput();
        $session->flash('success', 'Form created. Add fields to make it publishable.');
        return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $repo = $this->container->get(FormRepository::class);
        $form = $repo->findById($id);
        if ($form === null) {
            return $this->fail('Form not found.');
        }

        $fieldsRepo = $this->container->get(FormFieldRepository::class);
        $fields     = $fieldsRepo->listForForm($id);
        $maxStep    = $fieldsRepo->maxStepForForm($id);

        $submissions = $this->container->get(FormSubmissionRepository::class);

        $session = $this->container->get(Session::class);

        return $this->render('admin.forms.edit', [
            'title'        => 'Edit ' . $form->name,
            'form'         => $form,
            'fields'       => $fields,
            'maxStep'      => $maxStep,
            'subCounts'    => $submissions->countsForForm($id),
            'errors'       => $session->getFlash('form_errors', []),
            'old'          => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $repo = $this->container->get(FormRepository::class);
        $form = $repo->findById($id);
        if ($form === null) {
            return $this->fail('Form not found.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectFormInput($request);
        $errors = $this->validateFormInput($data, $id);

        if ($errors !== []) {
            $session->flash('form_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
        }

        try {
            $service->updateForm($id, $data);
        } catch (NewsletterException $e) {
            $session->flash('form_errors', ['slug' => $e->getMessage()]);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
        }

        $session->clearOldInput();
        $session->flash('success', 'Form updated.');
        return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function publish(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        try {
            $service->publish($id);
            $session->flash('success', 'Form published.');
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function unpublish(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        $service->unpublish($id);
        $session->flash('success', 'Form moved to draft.');
        return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        $service->archive($id);
        $session->flash('success', 'Form archived. It is no longer reachable at its public URL.');
        return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function duplicate(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        try {
            $newId = $service->duplicate($id, $this->currentUserId());
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
            return Response::redirect('/commerce/ca-admin/forms');
        }

        $session->flash('success', 'Form duplicated. The copy is a draft.');
        return Response::redirect('/commerce/ca-admin/forms/' . $newId . '/edit');
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $repo = $this->container->get(FormRepository::class);
        $form = $repo->findById($id);
        if ($form === null) {
            return $this->fail('Form not found.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        $service->delete($id);
        $session->flash('success', 'Form deleted.');
        return Response::redirect('/commerce/ca-admin/forms');
    }

    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid id'], 400);
        }

        $repo = $this->container->get(FormRepository::class);
        $form = $repo->findById($id);
        if ($form === null) {
            return Response::json(['ok' => false, 'error' => 'Form not found'], 404);
        }

        $subs = $this->container->get(FormSubmissionRepository::class);
        $counts = $subs->countsForForm($id);
        $blockers = [];

        if ($counts['total'] > 0) {
            $blockers[] = 'This form has ' . $counts['total']
                . ' submission' . ($counts['total'] === 1 ? '' : 's')
                . '. Deleting the form will not delete them.';
        }

        return Response::json([
            'ok'         => true,
            'name'       => $form->name,
            'can_delete' => true,
            'blockers'   => $blockers,
        ]);
    }

    // -----------------------------------------------------------------
    // Fields
    // -----------------------------------------------------------------

    /** @param array<string,string> $params */
    public function fieldStore(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) return $this->fail('Invalid form reference.');

        $repo = $this->container->get(FormRepository::class);
        if ($repo->findById($id) === null) return $this->fail('Form not found.');

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);
        $data    = $this->collectFieldInput($request);

        $errors = $this->validateFieldInput($data);
        if ($errors !== []) {
            $session->flash('form_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit#fields');
        }

        try {
            $service->addField($id, $data);
            $session->flash('success', 'Field added.');
        } catch (NewsletterException $e) {
            $session->flash('form_errors', ['field_key' => $e->getMessage()]);
            $session->flashOldInput($data);
        }

        return Response::redirect('/commerce/ca-admin/forms/' . $id . '/edit#fields');
    }

    /** @param array<string,string> $params */
    public function fieldUpdate(Request $request, array $params = []): Response
    {
        $formId  = (int) ($params['id'] ?? 0);
        $fieldId = (int) ($params['fieldId'] ?? 0);
        if ($formId <= 0 || $fieldId <= 0) {
            return $this->fail('Invalid reference.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);
        $data    = $this->collectFieldInput($request);

        $errors = $this->validateFieldInput($data);
        if ($errors !== []) {
            $session->flash('form_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/edit#fields');
        }

        try {
            $service->updateField($fieldId, $data);
            $session->flash('success', 'Field updated.');
        } catch (NewsletterException $e) {
            $session->flash('form_errors', ['field_key' => $e->getMessage()]);
            $session->flashOldInput($data);
        }

        return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/edit#fields');
    }

    /** @param array<string,string> $params */
    public function fieldDelete(Request $request, array $params = []): Response
    {
        $formId  = (int) ($params['id'] ?? 0);
        $fieldId = (int) ($params['fieldId'] ?? 0);
        if ($formId <= 0 || $fieldId <= 0) {
            return $this->fail('Invalid reference.');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        try {
            $service->deleteField($fieldId);
            $session->flash('success', 'Field deleted.');
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/edit#fields');
    }

    /** @param array<string,string> $params */
    public function fieldMove(Request $request, array $params = []): Response
    {
        $formId  = (int) ($params['id'] ?? 0);
        $fieldId = (int) ($params['fieldId'] ?? 0);
        if ($formId <= 0 || $fieldId <= 0) {
            return $this->fail('Invalid reference.');
        }

        $direction = (string) $request->input('direction', '');
        if (!in_array($direction, ['up', 'down'], true)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Invalid move direction.');
            return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/edit#fields');
        }

        $service = $this->container->get(FormService::class);
        $session = $this->container->get(Session::class);

        try {
            $service->moveField($fieldId, $direction);
        } catch (NewsletterException $e) {
            $session->flash('error', $e->getMessage());
        }

        return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/edit#fields');
    }

    // =================================================================
    // Submissions (Stage 009 Session 4)
    // =================================================================

    /**
     * Submissions list for a form. Filters: status tab,
     * search by email/name, date range. Pagination.
     *
     * @param array<string,string> $params
     */
    public function submissions(Request $request, array $params = []): Response
    {
        $formId = (int) ($params['id'] ?? 0);
        if ($formId <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $forms = $this->container->get(FormRepository::class);
        $form  = $forms->findById($formId);
        if ($form === null) {
            return $this->fail('Form not found.');
        }

        $repo = $this->container->get(FormSubmissionRepository::class);

        $filter   = (string) $request->query('status', 'new');
        if (!in_array($filter, array_merge(['all'], FormSubmissionRepository::STATUSES), true)) {
            $filter = 'new';
        }
        $search   = trim((string) $request->query('q', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo   = (string) $request->query('date_to', '');
        $page     = max(1, (int) $request->query('page', 1));

        $filters = [
            'status'    => $filter === 'all' ? '' : $filter,
            'search'    => $search,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ];

        $result = $repo->paginate($formId, $filters, $page, 25);
        $counts = $repo->countsForForm($formId);

        return $this->render('admin.forms.submissions', [
            'title'     => $form->name . ' — Submissions',
            'form'      => $form,
            'result'    => $result,
            'filter'    => $filter,
            'q'         => $search,
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
            'counts'    => $counts,
        ]);
    }

    /**
     * Single submission detail.
     *
     * @param array<string,string> $params
     */
    public function submissionDetail(Request $request, array $params = []): Response
    {
        $formId = (int) ($params['id'] ?? 0);
        $sid    = (int) ($params['sid'] ?? 0);
        if ($formId <= 0 || $sid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $forms = $this->container->get(FormRepository::class);
        $form  = $forms->findById($formId);
        if ($form === null) {
            return $this->fail('Form not found.');
        }

        $repo       = $this->container->get(FormSubmissionRepository::class);
        $submission = $repo->findById($sid);
        if ($submission === null || $submission->formId !== $formId) {
            return $this->fail('Submission not found.');
        }

        $fields = $this->container->get(FormFieldRepository::class);

        return $this->render('admin.forms.submission', [
            'title'      => 'Submission #' . $submission->id,
            'form'       => $form,
            'submission' => $submission,
            'fields'     => $fields->listForForm($formId),
        ]);
    }

    /**
     * Change one submission's status.
     *
     * @param array<string,string> $params
     */
    public function submissionStatus(Request $request, array $params = []): Response
    {
        $formId = (int) ($params['id'] ?? 0);
        $sid    = (int) ($params['sid'] ?? 0);
        if ($formId <= 0 || $sid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, FormSubmissionRepository::STATUSES, true)) {
            $session = $this->container->get(Session::class);
            $session->flash('error', 'Invalid status.');
            return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/submissions/' . $sid);
        }

        $repo       = $this->container->get(FormSubmissionRepository::class);
        $submission = $repo->findById($sid);
        if ($submission === null || $submission->formId !== $formId) {
            return $this->fail('Submission not found.');
        }

        $repo->updateStatus($sid, $status);

        // The status form also carries a notes field. Saving
        // both in one POST matches the merchant's flow: review
        // the submission, set the status, leave a note, save.
        $notes = (string) $request->input('notes', '');
        if (trim($notes) !== (string) $submission->notes) {
            $repo->updateNotes($sid, trim($notes));
        }

        $session = $this->container->get(Session::class);
        $session->flash('success', 'Submission updated.');

        return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/submissions/' . $sid);
    }

    /**
     * Bulk action on multiple submissions. Accepts the
     * action name and a list of ids from the list view's
     * bulk bar.
     *
     * @param array<string,string> $params
     */
    public function submissionsBulk(Request $request, array $params = []): Response
    {
        $formId = (int) ($params['id'] ?? 0);
        if ($formId <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $action = (string) $request->input('action', '');
        $rawIds = $request->input('ids', []);
        $ids    = is_array($rawIds) ? $rawIds : [];

        $repo    = $this->container->get(FormSubmissionRepository::class);
        $session = $this->container->get(Session::class);

        if ($ids === []) {
            $session->flash('error', 'No submissions selected.');
            return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/submissions');
        }

        $affected = 0;
        $verb     = 'updated';

        switch ($action) {
            case 'mark_new':
                $affected = $repo->bulkUpdateStatus($ids, 'new');
                break;
            case 'mark_read':
                $affected = $repo->bulkUpdateStatus($ids, 'read');
                break;
            case 'mark_spam':
                $affected = $repo->bulkUpdateStatus($ids, 'spam');
                break;
            case 'archive':
                $affected = $repo->bulkUpdateStatus($ids, 'archived');
                break;
            case 'delete':
                $affected = $repo->bulkDelete($ids);
                $verb     = 'deleted';
                break;
            default:
                $session->flash('error', 'Unknown bulk action.');
                return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/submissions');
        }

        $session->flash(
            'success',
            $affected . ' submission' . ($affected === 1 ? '' : 's') . ' ' . $verb . '.'
        );

        return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/submissions');
    }

    /**
     * Delete one submission.
     *
     * @param array<string,string> $params
     */
    public function submissionDelete(Request $request, array $params = []): Response
    {
        $formId = (int) ($params['id'] ?? 0);
        $sid    = (int) ($params['sid'] ?? 0);
        if ($formId <= 0 || $sid <= 0) {
            return $this->fail('Invalid reference.');
        }

        $repo       = $this->container->get(FormSubmissionRepository::class);
        $submission = $repo->findById($sid);
        if ($submission === null || $submission->formId !== $formId) {
            return $this->fail('Submission not found.');
        }

        $repo->delete($sid);

        $session = $this->container->get(Session::class);
        $session->flash('success', 'Submission deleted.');

        return Response::redirect('/commerce/ca-admin/forms/' . $formId . '/submissions');
    }

    /**
     * CSV export of the current filtered set.
     *
     * @param array<string,string> $params
     */
    public function submissionsExport(Request $request, array $params = []): Response
    {
        $formId = (int) ($params['id'] ?? 0);
        if ($formId <= 0) {
            return $this->fail('Invalid form reference.');
        }

        $forms = $this->container->get(FormRepository::class);
        $form  = $forms->findById($formId);
        if ($form === null) {
            return $this->fail('Form not found.');
        }

        $filter   = (string) $request->query('status', 'new');
        if (!in_array($filter, array_merge(['all'], FormSubmissionRepository::STATUSES), true)) {
            $filter = 'new';
        }
        $search   = trim((string) $request->query('q', ''));
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo   = (string) $request->query('date_to', '');

        $filters = [
            'status'    => $filter === 'all' ? '' : $filter,
            'search'    => $search,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ];

        $repo        = $this->container->get(FormSubmissionRepository::class);
        $fieldsRepo  = $this->container->get(FormFieldRepository::class);

        $submissions = $repo->listForExport($formId, $filters, 10000);
        $fields      = $fieldsRepo->listForForm($formId);

        // Build the CSV. Header row: id, date, status,
        // submitter email, submitter name, submitter IP, then
        // one column per field (in sort order), then notes.
        $header = ['id', 'submitted_at', 'status', 'submitter_email', 'submitter_name', 'submitter_ip'];
        foreach ($fields as $f) {
            $header[] = $f->label . ' (' . $f->fieldKey . ')';
        }
        $header[] = 'notes';

        $escapeCsv = static function (?string $v): string {
            $v = (string) $v;
            if (strpbrk($v, ',"' . chr(10) . chr(13)) === false) {
                return $v;
            }
            return '"' . str_replace('"', '""', $v) . '"';
        };

        $csv = implode(',', array_map($escapeCsv, $header)) . PHP_EOL;

        foreach ($submissions as $s) {
            $row = [
                (string) $s->id,
                (string) $s->createdAt,
                $s->status,
                (string) $s->submitterEmail,
                (string) $s->submitterName,
                (string) $s->submitterIp,
            ];
            foreach ($fields as $f) {
                $row[] = $s->value($f->fieldKey);
            }
            $row[] = (string) $s->notes;
            $csv .= implode(',', array_map($escapeCsv, $row)) . PHP_EOL;
        }

        $filename = 'form-' . $form->slug . '-submissions-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        return Response::text($csv);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/forms');
    }

    /**
     * @return array<string,mixed>
     */
    private function collectFormInput(Request $request): array
    {
        return [
            'name'             => trim((string) $request->input('name', '')),
            'slug'             => trim((string) $request->input('slug', '')),
            'description'      => trim((string) $request->input('description', '')),
            'success_message'  => trim((string) $request->input('success_message', '')),
            'notify_email'     => trim((string) $request->input('notify_email', '')),
            'honeypot_enabled' => (bool) $request->input('honeypot_enabled', false),
            'requires_auth'    => (bool) $request->input('requires_auth', false),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectFieldInput(Request $request): array
    {
        // Options arrive as parallel arrays:
        //   option_values[]   = ['us','uk']
        //   option_labels[]   = ['United States','United Kingdom']
        // Zip them into [{value,label}, ...].
        $rawValues = $request->input('option_values', []);
        $rawLabels = $request->input('option_labels', []);
        $options   = [];
        if (is_array($rawValues) && is_array($rawLabels)) {
            foreach ($rawValues as $i => $value) {
                $value = trim((string) $value);
                if ($value === '') continue;
                $label = trim((string) ($rawLabels[$i] ?? ''));
                $options[] = [
                    'value' => $value,
                    'label' => $label !== '' ? $label : $value,
                ];
            }
        }

        // Constraints arrive as a flat associative array:
        //   constraints[max_length] = 100
        //   constraints[min]        = 0
        // Store only non-empty values.
        $rawConstraints = $request->input('constraints', []);
        $constraints    = [];
        if (is_array($rawConstraints)) {
            foreach ($rawConstraints as $k => $v) {
                if ($v === '' || $v === null) continue;
                $constraints[(string) $k] = $v;
            }
        }

        // Conditional rule. All three keys must be set and
        // the target field must be specified. Otherwise we
        // store null (always visible).
        $rawConditional = $request->input('conditional', []);
        $conditional    = null;
        if (is_array($rawConditional)
            && !empty($rawConditional['field'])
            && !empty($rawConditional['operator'])
        ) {
            $conditional = [
                'field'    => (string) $rawConditional['field'],
                'operator' => (string) $rawConditional['operator'],
                'value'    => (string) ($rawConditional['value'] ?? ''),
            ];
        }

        return [
            'field_key'    => trim((string) $request->input('field_key', '')),
            'label'        => trim((string) $request->input('label', '')),
            'input_type'   => trim((string) $request->input('input_type', 'text')),
            'placeholder'  => trim((string) $request->input('placeholder', '')),
            'help_text'    => trim((string) $request->input('help_text', '')),
            'is_required'  => (bool) $request->input('is_required', false),
            'default_value' => trim((string) $request->input('default_value', '')),
            'step'         => max(1, (int) $request->input('step', 1)),
            'options'      => $options,
            'constraints'  => $constraints,
            'conditional'  => $conditional,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateFieldInput(array $data): array
    {
        $errors = [];

        $label = (string) ($data['label'] ?? '');
        if ($label === '') {
            $errors['label'] = 'Label is required.';
        } elseif (mb_strlen($label) > 190) {
            $errors['label'] = 'Label must be 190 characters or fewer.';
        }

        $key = (string) ($data['field_key'] ?? '');
        if ($key === '') {
            $errors['field_key'] = 'Field key is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
            $errors['field_key'] = 'Key must start with a letter and may contain only lowercase letters, digits, and underscores.';
        }

        $type = (string) ($data['input_type'] ?? '');
        if ($type === '') {
            $errors['input_type'] = 'Field type is required.';
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validateFormInput(array $data, ?int $excludeId): array
    {
        $errors = [];

        $name = (string) ($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 120) {
            $errors['name'] = 'Name must be 120 characters or fewer.';
        }

        $slug = (string) ($data['slug'] ?? '');
        if ($slug === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors['slug'] = 'Slug may contain only lowercase letters, digits, and hyphens.';
        }

        $description = (string) ($data['description'] ?? '');
        if (mb_strlen($description) > 500) {
            $errors['description'] = 'Description must be 500 characters or fewer.';
        }

        $notify = (string) ($data['notify_email'] ?? '');
        if ($notify !== '' && !filter_var($notify, FILTER_VALIDATE_EMAIL)) {
            $errors['notify_email'] = 'Enter a valid email address, or leave blank.';
        }

        return $errors;
    }

    private function currentUserId(): ?int
    {
        $auth = $this->container->get(Auth::class);
        $user = $auth->user();
        return $user?->id;
    }
}
