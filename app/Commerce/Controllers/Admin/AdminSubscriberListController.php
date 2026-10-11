<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Repositories\SubscriberListRepository;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Admin subscriber list management. Stage 008I.
 *
 * Routes (all under /commerce/ca-admin/subscriber-lists):
 *
 *   GET  /                     index
 *   GET  /create               showCreate
 *   POST /                     store
 *   GET  /{id}/edit            showEdit
 *   POST /{id}                 update
 *   POST /{id}/delete          destroy
 *   GET  /{id}/delete-info     deleteInfo
 *
 * The default list cannot be deleted. The controller
 * redirects to the list with a message.
 *
 * See docs/stages/008i_newsletter-plan.md.
 */
final class AdminSubscriberListController extends AdminController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo  = $this->container->get(SubscriberListRepository::class);
        $lists = $repo->listForStore();

        $counts = [];
        foreach ($lists as $l) {
            $counts[$l->id] = $repo->memberCount($l->id);
        }

        return $this->render('admin.subscriber_lists.index', [
            'title'  => 'Subscriber lists',
            'lists'  => $lists,
            'counts' => $counts,
        ]);
    }

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.subscriber_lists.edit', [
            'title'  => 'New list',
            'list'   => null,
            'errors' => $session->getFlash('list_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(SubscriberListRepository::class);
        $session = $this->container->get(Session::class);

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, $repo, null);

        if ($errors !== []) {
            $session->flash('list_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/subscriber-lists/create');
        }

        $id = $repo->create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'is_default'  => 0,
        ]);

        $session->clearOldInput();
        $session->flash('success', 'List created.');
        return Response::redirect('/commerce/ca-admin/subscriber-lists/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid list reference.');
        }

        $repo = $this->container->get(SubscriberListRepository::class);
        $list = $repo->findById($id);
        if ($list === null) {
            return $this->fail('List not found.');
        }

        $session = $this->container->get(Session::class);

        return $this->render('admin.subscriber_lists.edit', [
            'title'  => 'Edit ' . $list->name,
            'list'   => $list,
            'errors' => $session->getFlash('list_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid list reference.');
        }

        $repo = $this->container->get(SubscriberListRepository::class);
        $list = $repo->findById($id);
        if ($list === null) {
            return $this->fail('List not found.');
        }

        $session = $this->container->get(Session::class);
        $data    = $this->collectInput($request);
        $errors  = $this->validate($data, $repo, $id);

        if ($errors !== []) {
            $session->flash('list_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/subscriber-lists/' . $id . '/edit');
        }

        $repo->update($id, [
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
        ]);

        $session->clearOldInput();
        $session->flash('success', 'List updated.');
        return Response::redirect('/commerce/ca-admin/subscriber-lists/' . $id . '/edit');
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid list reference.');
        }

        $repo    = $this->container->get(SubscriberListRepository::class);
        $session = $this->container->get(Session::class);

        $list = $repo->findById($id);
        if ($list === null) {
            return $this->fail('List not found.');
        }
        if ($list->isDefault) {
            $session->flash('error', 'The default list cannot be deleted.');
            return Response::redirect('/commerce/ca-admin/subscriber-lists/' . $id . '/edit');
        }

        $repo->delete($id);
        $session->flash('success', 'List deleted.');
        return Response::redirect('/commerce/ca-admin/subscriber-lists');
    }

    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid id'], 400);
        }

        $repo = $this->container->get(SubscriberListRepository::class);
        $list = $repo->findById($id);
        if ($list === null) {
            return Response::json(['ok' => false, 'error' => 'List not found'], 404);
        }

        $blockers = [];
        if ($list->isDefault) {
            $blockers[] = 'The default list cannot be deleted.';
        }

        return Response::json([
            'ok'         => true,
            'name'       => $list->name,
            'can_delete' => $blockers === [],
            'blockers'   => $blockers,
        ]);
    }

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/subscriber-lists');
    }

    /** @return array<string,mixed> */
    private function collectInput(Request $request): array
    {
        return [
            'name'        => trim((string) $request->input('name', '')),
            'slug'        => trim((string) $request->input('slug', '')),
            'description' => trim((string) $request->input('description', '')),
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data, SubscriberListRepository $repo, ?int $excludeId): array
    {
        $errors = [];

        $name = (string) ($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = 'Name must be 80 characters or fewer.';
        }

        $slug = (string) ($data['slug'] ?? '');
        if ($slug === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors['slug'] = 'Slug may contain only lowercase letters, digits, and hyphens.';
        } elseif ($repo->slugExists($slug, $excludeId)) {
            $errors['slug'] = 'This slug is already used.';
        }

        $description = (string) ($data['description'] ?? '');
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        return $errors;
    }
}
