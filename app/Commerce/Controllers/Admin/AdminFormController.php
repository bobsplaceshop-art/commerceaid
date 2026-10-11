<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Exceptions\NewsletterException;
use CorporateAid\Commerce\Repositories\FormFieldRepository;
use CorporateAid\Commerce\Repositories\FormRepository;
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
