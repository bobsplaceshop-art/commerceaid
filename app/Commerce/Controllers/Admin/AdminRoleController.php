<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Platform\Repositories\RoleRepository;

/**
 * Admin role management.
 *
 * Routes (all under /commerce/ca-admin/roles):
 *
 *   GET  /                          index
 *   GET  /create                    showCreate
 *   POST /                          store
 *   GET  /{id}/edit                 showEdit
 *   POST /{id}                      update
 *   POST /{id}/delete               destroy
 *   GET  /{id}/delete-info          deleteInfo
 *
 * Permissions:
 *   core.roles.view    index, showEdit
 *   core.roles.create  showCreate, store
 *   core.roles.edit    update
 *   core.roles.delete  destroy, deleteInfo
 *
 * Store scoping:
 *   The list shows global roles (store_id IS NULL) plus roles
 *   owned by the current store. Custom roles created through
 *   this controller are always scoped to the current store.
 *
 * is_system roles:
 *   super-admin is is_system = 1. It cannot be deleted, its
 *   name and description cannot be changed, and its permission
 *   matrix renders read-only. It is the platform's recovery
 *   anchor - if its permission set could be reduced, a merchant
 *   could brick the platform by removing the wrong toggle.
 *
 *   Every other role is fully editable.
 *
 * Guards on delete:
 *   - is_system roles cannot be deleted.
 *   - Roles with active users assigned cannot be deleted.
 *
 * Input convention (matches CategoryController and
 * ProductController):
 *   collectInput() reads the form, resolves the slug, and
 *   normalizes the permissions[] array into a deduplicated
 *   list of positive integers. validate() then validates the
 *   normalized data. store() and update() run collect then
 *   validate then write.
 */
final class AdminRoleController extends AdminController
{
    private const PER_PAGE = 25;

    private const VALID_TABS = ['all', 'system', 'custom'];

    // =================================================================
    // GET /commerce/ca-admin/roles
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $roles   = $this->container->get(RoleRepository::class);
        $context = $this->container->get(StoreContext::class);

        $tab = (string) $request->query('type', 'all');
        if (!in_array($tab, self::VALID_TABS, true)) {
            $tab = 'all';
        }

        $q    = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $filters = ['type' => $tab];
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $storeId = $context->has() ? $context->id() : null;

        $result = $roles->paginate($filters, $page, self::PER_PAGE, $storeId);

        // Counts per tab, store-scoped.
        $allRoles = $roles->all($storeId);
        $counts = [
            'all'    => count($allRoles),
            'system' => 0,
            'custom' => 0,
        ];
        foreach ($allRoles as $r) {
            if ((int) ($r['is_system'] ?? 0) === 1) {
                $counts['system']++;
            } else {
                $counts['custom']++;
            }
        }

        return $this->render('admin.roles.index', [
            'title'  => 'Roles',
            'result' => $result,
            'tab'    => $tab,
            'q'      => $q,
            'counts' => $counts,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/roles/create
    // =================================================================

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $roles   = $this->container->get(RoleRepository::class);
        $context = $this->container->get(StoreContext::class);
        $session = $this->container->get(Session::class);

        $storeId = $context->has() ? $context->id() : null;

        // Copy-from dropdown: every role visible to the current
        // store, excluding is_system roles (their permission set
        // cannot be copied anyway since the target would be a
        // custom role with a different purpose).
        $copyFrom = [];
        foreach ($roles->all($storeId) as $r) {
            if ((int) ($r['is_system'] ?? 0) === 1) {
                continue;
            }
            $copyFrom[] = [
                'id'   => (int) $r['id'],
                'name' => (string) $r['name'],
            ];
        }

        return $this->render('admin.roles.create', [
            'title'         => 'New role',
            'permissionMap' => $roles->permissionsGroupedByModule(),
            'copyFrom'      => $copyFrom,
            'errors'        => $session->getFlash('role_errors', []),
            'old'           => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/roles
    // =================================================================

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $roles   = $this->container->get(RoleRepository::class);
        $context = $this->container->get(StoreContext::class);
        $session = $this->container->get(Session::class);

        $data = $this->collectInput($request);

        $errors = $this->validate($data, $roles, null);
        if ($errors !== []) {
            $session->flash('role_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/roles/create');
        }

        $storeId = $context->has() ? $context->id() : null;

        $roleId = $roles->create(
            (string) $data['name'],
            (string) $data['slug'],
            $data['description'] !== '' ? (string) $data['description'] : null,
            $storeId
        );

        $roles->syncPermissions($roleId, $data['permissions']);

        $session->clearOldInput();
        $session->flash('success', 'Role created.');
        return Response::redirect('/commerce/ca-admin/roles/' . $roleId . '/edit');
    }

    // =================================================================
    // GET /commerce/ca-admin/roles/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid role reference.');
        }

        $roles   = $this->container->get(RoleRepository::class);
        $session = $this->container->get(Session::class);

        $role = $roles->findById($id);
        if ($role === null) {
            return $this->fail('Role not found.');
        }

        return $this->render('admin.roles.edit', [
            'title'         => 'Edit ' . (string) $role['name'],
            'role'          => $role,
            'permissionMap' => $roles->permissionsGroupedByModule(),
            'assignedIds'   => $roles->permissionIdsForRole($id),
            'userCount'     => $roles->countUsersForRole($id),
            'errors'        => $session->getFlash('role_errors', []),
            'old'           => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/roles/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid role reference.');
        }

        $roles   = $this->container->get(RoleRepository::class);
        $session = $this->container->get(Session::class);

        $role = $roles->findById($id);
        if ($role === null) {
            return $this->fail('Role not found.');
        }

        if (((int) ($role['is_system'] ?? 0)) === 1) {
            $session->flash('error', 'This role is protected and cannot be edited.');
            return Response::redirect('/commerce/ca-admin/roles/' . $id . '/edit');
        }

        $data = $this->collectInput($request);
        // Update never touches the slug. Preserve it from the
        // stored role so validation passes the same-shape check
        // and syncPermissions uses the correct identifier.
        $data['slug'] = (string) ($role['slug'] ?? '');

        $errors = $this->validate($data, $roles, $id);
        if ($errors !== []) {
            $session->flash('role_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/roles/' . $id . '/edit');
        }

        $roles->update(
            $id,
            (string) $data['name'],
            $data['description'] !== '' ? (string) $data['description'] : null
        );

        $roles->syncPermissions($id, $data['permissions']);

        $session->clearOldInput();
        $session->flash('success', 'Role updated.');
        return Response::redirect('/commerce/ca-admin/roles/' . $id . '/edit');
    }

    // =================================================================
    // POST /commerce/ca-admin/roles/{id}/delete
    // =================================================================

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid role reference.');
        }

        $roles   = $this->container->get(RoleRepository::class);
        $session = $this->container->get(Session::class);

        $role = $roles->findById($id);
        if ($role === null) {
            return $this->fail('Role not found.');
        }

        if (((int) ($role['is_system'] ?? 0)) === 1) {
            $session->flash('error', 'System roles cannot be deleted.');
            return Response::redirect('/commerce/ca-admin/roles');
        }

        $users = $roles->countUsersForRole($id);
        if ($users > 0) {
            $session->flash(
                'error',
                'Cannot delete this role: ' . $users
                . ' user' . ($users === 1 ? '' : 's')
                . ' still assigned. Reassign them first.'
            );
            return Response::redirect('/commerce/ca-admin/roles/' . $id . '/edit');
        }

        $roles->delete($id);
        $session->flash('success', 'Role deleted.');
        return Response::redirect('/commerce/ca-admin/roles');
    }

    // =================================================================
    // GET /commerce/ca-admin/roles/{id}/delete-info
    // =================================================================

    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['ok' => false, 'error' => 'Invalid id'], 400);
        }

        $roles = $this->container->get(RoleRepository::class);
        $role  = $roles->findById($id);

        if ($role === null) {
            return Response::json(['ok' => false, 'error' => 'Role not found'], 404);
        }

        $isSystem  = ((int) ($role['is_system'] ?? 0)) === 1;
        $userCount = $roles->countUsersForRole($id);

        $blockers = [];
        if ($isSystem) {
            $blockers[] = 'This is a system role and cannot be deleted.';
        }
        if ($userCount > 0) {
            $blockers[] = $userCount . ' user' . ($userCount === 1 ? '' : 's')
                . ' currently assigned to this role.';
        }

        return Response::json([
            'ok'         => true,
            'name'       => (string) $role['name'],
            'slug'       => (string) $role['slug'],
            'is_system'  => $isSystem,
            'user_count' => $userCount,
            'can_delete' => count($blockers) === 0,
            'blockers'   => $blockers,
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/roles');
    }

    /**
     * Read the form, resolve the slug, normalize the permissions
     * array. Same shape as CategoryController::collectInput() and
     * ProductController::collectInput().
     *
     * @return array<string,mixed>
     */
    private function collectInput(Request $request): array
    {
        $slug = strtolower(trim((string) $request->input('slug', '')));
        if ($slug === '') {
            $slug = $this->slugify((string) $request->input('name', ''));
        }

        return [
            'name'        => trim((string) $request->input('name', '')),
            'slug'        => $slug,
            'description' => trim((string) $request->input('description', '')),
            'permissions' => $this->collectPermissionIds($request),
        ];
    }

    /**
     * Read the posted permissions[] array and return a clean list
     * of integer permission IDs, deduplicated, filtered to
     * positive values.
     *
     * @return array<int,int>
     */
    private function collectPermissionIds(Request $request): array
    {
        $raw = $request->input('permissions', []);
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            $v = (int) $id;
            if ($v > 0) {
                $ids[$v] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data, RoleRepository $roles, ?int $excludeId): array
    {
        $errors = [];

        $name = (string) ($data['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = 'Name must be 80 characters or fewer.';
        }

        // Slug is validated on create only. Update preserves the
        // stored slug and never posts a new one.
        if ($excludeId === null) {
            $slug = (string) ($data['slug'] ?? '');
            if ($slug === '') {
                $errors['slug'] = 'Could not generate a slug from the name. Provide one.';
            } elseif (mb_strlen($slug) > 80) {
                $errors['slug'] = 'Slug must be 80 characters or fewer.';
            } elseif (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug)) {
                $errors['slug'] = 'Slug must be lowercase letters, digits, and hyphens only.';
            } elseif ($roles->slugExists($slug, null)) {
                $errors['slug'] = 'A role with that slug already exists.';
            }
        }

        $description = (string) ($data['description'] ?? '');
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        return $errors;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
