<?php
/**
 * Edit admin user.
 *
 * Context (from AdminUserController::showEdit):
 *   $targetUser User                 with roleSlugs loaded
 *   $roleList  array<int,array<string,mixed>>
 *   $userRoles array<int,array{id:int,slug:string,name:string}>
 *   $timezones array<int,string>
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/users/{id}.
 * The email is read-only: it is the login identifier.
 *
 * Side actions:
 *   POST /commerce/ca-admin/users/{id}/send-password-reset
 *   POST /commerce/ca-admin/users/{id}/deactivate
 *   POST /commerce/ca-admin/users/{id}/reactivate
 */
$targetUser = $targetUser ?? null;
$roleList  = $roleList  ?? [];
$userRoles = $userRoles ?? [];
$timezones = $timezones ?? [];
$errors    = $errors    ?? [];
$old       = $old       ?? [];

if ($targetUser === null) {
    return;
}

$currentRoleId = 0;
if (!empty($userRoles) && isset($userRoles[0]['id'])) {
    $currentRoleId = (int) $userRoles[0]['id'];
}

$val = function (string $key, string $default = '') use ($old, $targetUser): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    $prop = match ($key) {
        'name'     => $targetUser->name,
        'phone'    => $targetUser->phone,
        'timezone' => $targetUser->timezone,
        default    => $default,
    };
    return (string) ($prop ?? $default);
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$statusBadge = match ($targetUser->status) {
    'active'    => '<span class="ra-badge ra-badge-success">Active</span>',
    'pending'   => '<span class="ra-badge ra-badge-warning">Pending</span>',
    'disabled'  => '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Disabled</span>',
    'suspended' => '<span class="ra-badge ra-badge-muted">Suspended</span>',
    default     => '<span class="ra-badge ra-badge-muted">' . e($targetUser->status) . '</span>',
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/users" class="text-decoration-none">Users</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($targetUser->name) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($targetUser->name) ?></h1>
        <p class="muted mb-0">
            <?= e($targetUser->email) ?>
            <span class="ms-2"><?= $statusBadge ?></span>
        </p>
    </div>
</div>

<!-- ============================ STATE ACTIONS ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex flex-wrap gap-2">

            <form method="POST"
                  action="/commerce/ca-admin/users/<?= (int) $targetUser->id ?>/send-password-reset"
                  class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-outline"
                        onclick="return confirm('Send a password reset link to this user?');">
                    <i class="bi bi-envelope me-1"></i> Send password reset
                </button>
            </form>

            <?php if ($targetUser->status === 'active'): ?>
                <form method="POST"
                      action="/commerce/ca-admin/users/<?= (int) $targetUser->id ?>/deactivate"
                      class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-danger"
                            onclick="return confirm('Deactivate this user? They will be signed out and cannot log in until reactivated.');">
                        <i class="bi bi-slash-circle me-1"></i> Deactivate
                    </button>
                </form>
            <?php elseif ($targetUser->status === 'disabled'): ?>
                <form method="POST"
                      action="/commerce/ca-admin/users/<?= (int) $targetUser->id ?>/reactivate"
                      class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Reactivate
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST"
      action="/commerce/ca-admin/users/<?= (int) $targetUser->id ?>"
      id="ra-user-edit-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Profile</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="uf-name">Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="uf-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="150"
                           required>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="uf-email">Email</label>
                    <input type="email" id="uf-email"
                           class="form-control"
                           value="<?= e($targetUser->email) ?>"
                           disabled>
                    <div class="muted small mt-1">
                        Email is the login identifier and cannot be changed.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="uf-phone">Phone</label>
                    <input type="tel" id="uf-phone" name="phone"
                           class="form-control <?= $err('phone') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('phone')) ?>"
                           maxlength="40">
                    <?php if ($err('phone') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('phone')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="uf-tz">Timezone</label>
                    <select id="uf-tz" name="timezone"
                            class="form-select <?= $err('timezone') !== '' ? 'is-invalid' : '' ?>">
                        <?php foreach ($timezones as $tz): ?>
                            <option value="<?= e($tz) ?>" <?= $val('timezone', 'Africa/Nairobi') === $tz ? 'selected' : '' ?>>
                                <?= e($tz) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('timezone') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('timezone')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="uf-role">Role <span aria-hidden="true">*</span></label>
                    <select id="uf-role" name="role_id"
                            class="form-select <?= $err('role_id') !== '' ? 'is-invalid' : '' ?>"
                            required>
                        <option value="">Select a role…</option>
                        <?php foreach ($roleList as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= $currentRoleId === (int) $r['id'] ? 'selected' : '' ?>>
                                <?= e((string) $r['name']) ?>
                                <?php if (!empty($r['description'])): ?>
                                    — <?= e((string) $r['description']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('role_id') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('role_id')) ?></div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/users" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-user-edit-form" class="ra-btn ra-btn-primary">
            Save changes
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
