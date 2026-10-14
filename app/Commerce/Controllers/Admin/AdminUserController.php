<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Support\Validator;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Csrf;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Core\View;
use CorporateAid\Platform\Models\User;
use CorporateAid\Platform\Repositories\PasswordResetRepository;
use CorporateAid\Platform\Repositories\RoleRepository;
use CorporateAid\Platform\Repositories\UserRepository;
use CorporateAid\Platform\Support\EmailService;
use CorporateAid\Platform\Support\AppUrl;

/**
 * Admin user management.
 *
 * Routes (all under /commerce/ca-admin/users):
 *
 *   GET  /                          index
 *   GET  /invite                    showInvite
 *   POST /invite                    invite
 *   GET  /{id}/edit                 showEdit
 *   POST /{id}                      update
 *   POST /{id}/deactivate           deactivate
 *   POST /{id}/reactivate           reactivate
 *   POST /{id}/send-password-reset  sendPasswordReset
 *
 * Public invite acceptance (no auth required):
 *
 *   GET  /accept-invite/{token}     acceptInvite
 *   POST /accept-invite/{token}     completeInvite
 *
 * Permissions:
 *   core.users.view  for index
 *   core.users.edit  for every other admin action
 *
 * Status lifecycle:
 *   pending   invite sent, user cannot log in
 *   active    live account
 *   suspended (not used here; reserved for future auto-lock)
 *   disabled  deactivated by an admin
 *
 * Guards:
 *   - Cannot deactivate the currently logged-in user.
 *   - Cannot deactivate the last remaining super-admin.
 *   - Cannot change the last super-admin's role.
 */
final class AdminUserController extends AdminController
{
    private const PER_PAGE = 25;

    private const VALID_STATUS_TABS = ['active', 'pending', 'disabled', 'all'];

    private const VALID_TIMEZONES = [
        'Africa/Nairobi',
        'Africa/Lagos',
        'Africa/Johannesburg',
        'Africa/Cairo',
        'Europe/London',
        'Europe/Berlin',
        'UTC',
    ];

    // =================================================================
    // GET /commerce/ca-admin/users
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $users = $this->container->get(UserRepository::class);
        $roles = $this->container->get(RoleRepository::class);

        $tab = (string) $request->query('status', 'active');
        if (!in_array($tab, self::VALID_STATUS_TABS, true)) {
            $tab = 'active';
        }

        $q    = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $filters = [];
        if ($tab !== 'all') {
            $filters['status'] = $tab;
        }
        if ($q !== '') {
            $filters['q'] = $q;
        }

        $result = $users->paginate($filters, $page, self::PER_PAGE);

        $counts = [
            'active'   => $this->countByStatus('active'),
            'pending'  => $this->countByStatus('pending'),
            'disabled' => $this->countByStatus('disabled'),
            'all'      => $users->count(),
        ];

        // Role id -> name lookup for the role column in the table.
        $roleNames = [];
        foreach ($roles->all() as $r) {
            $roleNames[(string) $r['slug']] = (string) $r['name'];
        }

        return $this->render('admin.users.index', [
            'title'     => 'Users',
            'result'    => $result,
            'tab'       => $tab,
            'q'         => $q,
            'counts'    => $counts,
            'roleNames' => $roleNames,
        ]);
    }

    // =================================================================
    // GET /commerce/ca-admin/users/invite
    // =================================================================

    /** @param array<string,string> $params */
    public function showInvite(Request $request, array $params = []): Response
    {
        $roles = $this->container->get(RoleRepository::class);
        $session = $this->container->get(Session::class);

        return $this->render('admin.users.invite', [
            'title'     => 'Invite user',
            'roleList'  => $roles->all(),
            'timezones' => self::VALID_TIMEZONES,
            'errors'    => $session->getFlash('user_errors', []),
            'old'       => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/users/invite
    // =================================================================

    /** @param array<string,string> $params */
    public function invite(Request $request, array $params = []): Response
    {
        $users   = $this->container->get(UserRepository::class);
        $roles   = $this->container->get(RoleRepository::class);
        $reset   = $this->container->get(PasswordResetRepository::class);
        $mailer  = $this->container->get(EmailService::class);
        $session = $this->container->get(Session::class);

        $data = [
            'name'     => trim((string) $request->input('name', '')),
            'email'    => strtolower(trim((string) $request->input('email', ''))),
            'phone'    => trim((string) $request->input('phone', '')),
            'timezone' => trim((string) $request->input('timezone', 'Africa/Nairobi')),
            'role_id'  => trim((string) $request->input('role_id', '0')),
        ];

        $errors = $this->validateInvite($data, $users);
        if ($errors !== []) {
            $session->flash('user_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/users/invite');
        }

        // Resolve the role id to a slug.
        $roleSlug = $this->resolveRoleSlug($roles, (int) $data['role_id']);
        if ($roleSlug === null) {
            $session->flash('user_errors', ['role_id' => 'Selected role not found.']);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/users/invite');
        }

        // Create the user with a random placeholder password. The
        // real password is set via the accept-invite flow. The
        // placeholder is 64 hex chars and never disclosed.
        $placeholder = bin2hex(random_bytes(32));

        $userId = $users->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $placeholder,
            'phone'    => $data['phone'] !== '' ? $data['phone'] : null,
            'timezone' => $data['timezone'],
            'status'   => 'pending',
        ]);

        $users->attachRoleBySlug($userId, $roleSlug);

        // Generate a token for the acceptance link. Reuses the
        // password-reset infrastructure (hashed token, 60-min TTL).
        $token = $reset->createToken($data['email']);
        $acceptUrl = AppUrl::absolute('/commerce/ca-admin/users/accept-invite/' . $token);

        $mailer->sendTemplate(
            $data['email'],
            'Your ' . brand_name($this->resolveStore()) . ' account invitation',
            'user-invite',
            [
                'user_name'   => $data['name'],
                'accept_url'  => $acceptUrl,
                'app_name'    => brand_name($this->resolveStore()),
                'role_name'   => $this->roleDisplayName($roles, $roleSlug),
            ]
        );

        $session->clearOldInput();
        $session->flash('success', 'Invitation sent to ' . $data['email'] . '.');
        return Response::redirect('/commerce/ca-admin/users');
    }

    // =================================================================
    // GET /commerce/ca-admin/users/{id}/edit
    // =================================================================

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return $this->fail('Invalid user reference.');
        }

        $users = $this->container->get(UserRepository::class);
        $roles = $this->container->get(RoleRepository::class);
        $session = $this->container->get(Session::class);

        $user = $users->findById($id);
        if ($user === null) {
            return $this->fail('User not found.');
        }

        $user = $users->loadRoles($user);

        // The view receives $targetUser (not $user). AdminController::render()
        // injects $user unconditionally with the logged-in admin for the
        // topbar. Passing the target as $user would be silently overwritten
        // by that injection - which was the source of a display bug where
        // the edit page showed the admin's details instead of the target's.
        return $this->render('admin.users.edit', [
            'title'      => 'Edit ' . $user->name,
            'targetUser' => $user,
            'roleList'   => $roles->all(),
            'userRoles'  => $users->rolesWithIdsForUser($id),
            'timezones'  => self::VALID_TIMEZONES,
            'errors'     => $session->getFlash('user_errors', []),
            'old'        => $_SESSION['_old'] ?? [],
        ]);
    }

    // =================================================================
    // POST /commerce/ca-admin/users/{id}
    // =================================================================

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid user reference.');
        }

        $users = $this->container->get(UserRepository::class);
        $roles = $this->container->get(RoleRepository::class);
        $auth  = $this->container->get(Auth::class);

        $user = $users->findById($id);
        if ($user === null) {
            return $this->fail('User not found.');
        }

        $data = [
            'name'     => trim((string) $request->input('name', '')),
            'phone'    => trim((string) $request->input('phone', '')),
            'timezone' => trim((string) $request->input('timezone', $user->timezone)),
            'role_id'  => trim((string) $request->input('role_id', '0')),
        ];

        $errors = $this->validateUpdate($data);
        if ($errors !== []) {
            $session->flash('user_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
        }

        // Role change: detach all and attach the selected role.
        $newRoleSlug = $this->resolveRoleSlug($roles, (int) $data['role_id']);
        if ($newRoleSlug === null) {
            $session->flash('user_errors', ['role_id' => 'Selected role not found.']);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
        }

        // Guard: cannot demote the last super-admin.
        $currentRoles = $users->loadRoles($user)->roleSlugs;
        $isSuperAdmin = in_array('super-admin', $currentRoles, true);
        if ($isSuperAdmin && $newRoleSlug !== 'super-admin' && $this->isLastSuperAdmin($users, $id)) {
            $session->flash('user_errors', ['role_id' => 'Cannot change the role of the last remaining super-admin.']);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
        }

        // Update the base fields.
        $user->name     = $data['name'];
        $user->phone    = $data['phone'] !== '' ? $data['phone'] : null;
        $user->timezone = $data['timezone'];
        $users->update($user);

        // Update the role only if it actually changed.
        if (!in_array($newRoleSlug, $currentRoles, true)) {
            $users->detachAllRoles($id);
            $users->attachRoleBySlug($id, $newRoleSlug);
        }

        $session->clearOldInput();
        $session->flash('success', 'User updated.');
        return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
    }

    // =================================================================
    // POST /commerce/ca-admin/users/{id}/deactivate
    // =================================================================

    /** @param array<string,string> $params */
    public function deactivate(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);
        $auth    = $this->container->get(Auth::class);

        if ($id <= 0) {
            return $this->fail('Invalid user reference.');
        }

        $users = $this->container->get(UserRepository::class);
        $user  = $users->findById($id);
        if ($user === null) {
            return $this->fail('User not found.');
        }

        // Guard: cannot deactivate yourself.
        if ($auth->id() === $id) {
            $session->flash('error', 'You cannot deactivate your own account.');
            return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
        }

        // Guard: cannot deactivate the last super-admin.
        $user = $users->loadRoles($user);
        if ($user->isSuperAdmin() && $this->isLastSuperAdmin($users, $id)) {
            $session->flash('error', 'Cannot deactivate the last remaining super-admin.');
            return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
        }

        $users->setStatus($id, 'disabled');
        $session->flash('success', 'User deactivated.');
        return Response::redirect('/commerce/ca-admin/users');
    }

    // =================================================================
    // POST /commerce/ca-admin/users/{id}/reactivate
    // =================================================================

    /** @param array<string,string> $params */
    public function reactivate(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid user reference.');
        }

        $users = $this->container->get(UserRepository::class);
        $user  = $users->findById($id);
        if ($user === null) {
            return $this->fail('User not found.');
        }

        $users->setStatus($id, 'active');
        $session->flash('success', 'User reactivated.');
        return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
    }

    // =================================================================
    // POST /commerce/ca-admin/users/{id}/send-password-reset
    // =================================================================

    /** @param array<string,string> $params */
    public function sendPasswordReset(Request $request, array $params = []): Response
    {
        $id      = (int) ($params['id'] ?? 0);
        $session = $this->container->get(Session::class);

        if ($id <= 0) {
            return $this->fail('Invalid user reference.');
        }

        $users = $this->container->get(UserRepository::class);
        $reset = $this->container->get(PasswordResetRepository::class);
        $mailer = $this->container->get(EmailService::class);

        $user = $users->findById($id);
        if ($user === null) {
            return $this->fail('User not found.');
        }

        $token  = $reset->createToken($user->email);
        $resetUrl = AppUrl::absolute('/login/reset/' . $token);

        $mailer->sendTemplate(
            $user->email,
            'Reset your ' . brand_name($this->resolveStore()) . ' password',
            'password-reset',
            [
                'user_name'  => $user->name,
                'reset_url'  => $resetUrl,
                'app_name'   => brand_name($this->resolveStore()),
                'expires_in' => '1 hour',
            ]
        );

        $session->flash('success', 'Password reset link sent to ' . $user->email . '.');
        return Response::redirect('/commerce/ca-admin/users/' . $id . '/edit');
    }

    // =================================================================
    // GET /commerce/ca-admin/users/accept-invite/{token}
    // PUBLIC - no auth middleware
    // =================================================================

    /** @param array<string,string> $params */
    public function acceptInvite(Request $request, array $params = []): Response
    {
        $token = (string) ($params['token'] ?? '');
        $reset = $this->container->get(PasswordResetRepository::class);
        $users = $this->container->get(UserRepository::class);
        $view  = $this->container->get(View::class);
        $csrf  = $this->container->get(Csrf::class);
        $session = $this->container->get(Session::class);

        $email = $token !== '' ? $reset->findEmailByToken($token) : null;
        $user  = $email !== null ? $users->findByEmail($email) : null;

        if ($user === null || $user->status !== 'pending') {
            return Response::html(
                $view->render('admin.users.accept-invite-invalid', [
                    'title' => 'Invitation expired',
                ], 'layouts.auth')
            );
        }

        return Response::html(
            $view->render('admin.users.accept-invite', [
                'title'  => 'Set your password',
                'csrf'   => $csrf->token(),
                'token'  => $token,
                'name'   => $user->name,
                'email'  => $user->email,
                'errors' => $session->getFlash('invite_errors', []),
            ], 'layouts.auth')
        );
    }

    // =================================================================
    // POST /commerce/ca-admin/users/accept-invite/{token}
    // PUBLIC - no auth middleware
    // =================================================================

    /** @param array<string,string> $params */
    public function completeInvite(Request $request, array $params = []): Response
    {
        $token   = (string) ($params['token'] ?? '');
        $reset   = $this->container->get(PasswordResetRepository::class);
        $users   = $this->container->get(UserRepository::class);
        $session = $this->container->get(Session::class);
        $csrf    = $this->container->get(Csrf::class);

        $email = $token !== '' ? $reset->findEmailByToken($token) : null;
        $user  = $email !== null ? $users->findByEmail($email) : null;

        if ($user === null || $user->status !== 'pending') {
            $session->flash('error', 'This invitation is no longer valid. Ask an administrator to send a new one.');
            return Response::redirect('/login');
        }

        $password = (string) $request->input('password', '');
        $confirm  = (string) $request->input('password_confirmation', '');

        $errors = [];
        if (Validator::required($password, 'Password') !== null) {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 200) {
            $errors['password'] = 'Password is too long.';
        }
        if ($password !== $confirm) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            $session->flash('invite_errors', $errors);
            return Response::redirect('/commerce/ca-admin/users/accept-invite/' . $token);
        }

        $users->updatePassword($user->id, $password);
        $users->setStatus($user->id, 'active');
        $reset->consumeToken($token);

        // Sign out any session currently active in this browser.
        // The invite-accept flow is designed for an invitee on a
        // fresh device with no session, but an admin clicking
        // their own invite link (or a forwarded one) can reach
        // this point while still authenticated as an admin. The
        // logout guarantees the invitee sees the login form
        // after activation, matching the flow's intent: this is
        // a new account, and the browser should not hold a
        // session for a different one.
        $this->container->get(Auth::class)->logout();

        $csrf->rotate();
        $session->flash('success', 'Your account is ready. Sign in to continue.');
        return Response::redirect('/login');
    }

    // =================================================================
    // Helpers
    // =================================================================

    private function fail(string $message): Response
    {
        $this->container->get(Session::class)->flash('error', $message);
        return Response::redirect('/commerce/ca-admin/users');
    }

    /**
     * Count users by status. Bypasses the repository's paginate()
     * because we only need four numbers and a full query per tab
     * is wasteful.
     */
    private function countByStatus(string $status): int
    {
        return (int) $this->container->get(\CorporateAid\Core\Database::class)
            ->scalar(
                "SELECT COUNT(*) FROM `core_users` WHERE `status` = ? AND `deleted_at` IS NULL",
                [$status]
            );
    }

    /**
     * @param array<string,string> $data
     * @return array<string,string>
     */
    private function validateInvite(array $data, UserRepository $users): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($data['name']) > 150) {
            $errors['name'] = 'Name must be 150 characters or fewer.';
        }

        if ($data['email'] === '') {
            $errors['email'] = 'Email is required.';
        } elseif (Validator::email($data['email'], 'Email') !== null) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (mb_strlen($data['email']) > 190) {
            $errors['email'] = 'Email must be 190 characters or fewer.';
        } elseif ($users->findByEmail($data['email']) !== null) {
            $errors['email'] = 'A user with that email already exists.';
        }

        if ($data['phone'] !== '' && mb_strlen($data['phone']) > 40) {
            $errors['phone'] = 'Phone must be 40 characters or fewer.';
        }

        if (!in_array($data['timezone'], self::VALID_TIMEZONES, true)) {
            $errors['timezone'] = 'Select a valid timezone.';
        }

        if ((int) $data['role_id'] <= 0) {
            $errors['role_id'] = 'Select a role.';
        }

        return $errors;
    }

    /**
     * @param array<string,string> $data
     * @return array<string,string>
     */
    private function validateUpdate(array $data): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($data['name']) > 150) {
            $errors['name'] = 'Name must be 150 characters or fewer.';
        }

        if ($data['phone'] !== '' && mb_strlen($data['phone']) > 40) {
            $errors['phone'] = 'Phone must be 40 characters or fewer.';
        }

        if (!in_array($data['timezone'], self::VALID_TIMEZONES, true)) {
            $errors['timezone'] = 'Select a valid timezone.';
        }

        if ((int) $data['role_id'] <= 0) {
            $errors['role_id'] = 'Select a role.';
        }

        return $errors;
    }

    private function resolveRoleSlug(RoleRepository $roles, int $roleId): ?string
    {
        if ($roleId <= 0) {
            return null;
        }
        foreach ($roles->all() as $r) {
            if ((int) $r['id'] === $roleId) {
                return (string) $r['slug'];
            }
        }
        return null;
    }

    private function roleDisplayName(RoleRepository $roles, string $slug): string
    {
        foreach ($roles->all() as $r) {
            if ((string) $r['slug'] === $slug) {
                return (string) $r['name'];
            }
        }
        return $slug;
    }

    /**
     * Returns true if the given user is the only super-admin left.
     * Loads all super-admins and checks the count.
     */
    private function isLastSuperAdmin(UserRepository $users, int $userId): bool
    {
        $superAdminIds = $users->findActiveUserIdsByRole('super-admin');
        return count($superAdminIds) === 1 && $superAdminIds[0] === $userId;
    }

}
