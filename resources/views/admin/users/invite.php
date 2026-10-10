<?php
/**
 * Invite new admin user form.
 *
 * Context (from AdminUserController::showInvite):
 *   $roleList  array<int,array<string,mixed>>   from RoleRepository::all()
 *   $timezones array<int,string>                from VALID_TIMEZONES
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/users/invite.
 * The user is created with status='pending', a role, and an
 * invitation email containing a token. Setting a password via
 * that token transitions status to 'active'.
 */
$roleList  = $roleList  ?? [];
$timezones = $timezones ?? [];
$errors    = $errors    ?? [];
$old       = $old       ?? [];

$val = function (string $key, string $default = '') use ($old): string {
    return array_key_exists($key, $old) ? (string) $old[$key] : $default;
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/users" class="text-decoration-none">Users</a>
            <span class="mx-1">&rsaquo;</span>
            <span>Invite user</span>
        </nav>
        <h1 class="h3 mb-1">Invite user</h1>
        <p class="muted mb-0">
            The recipient gets an email with a link to set their password.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="/commerce/ca-admin/users/invite" id="ra-user-invite-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">User details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="uf-name">Name <span aria-hidden="true">*</span></label>
                    <input type="text" id="uf-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="150"
                           required
                           autofocus>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="uf-email">Email <span aria-hidden="true">*</span></label>
                    <input type="email" id="uf-email" name="email"
                           class="form-control <?= $err('email') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('email')) ?>"
                           maxlength="190"
                           required>
                    <div class="muted small mt-1">
                        This is the login email and cannot be changed later.
                    </div>
                    <?php if ($err('email') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('email')) ?></div>
                    <?php endif; ?>
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
                            <option value="<?= (int) $r['id'] ?>" <?= (int) $val('role_id', '0') === (int) $r['id'] ? 'selected' : '' ?>>
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

                <div class="col-12">
                    <div class="muted small">
                        The user is created with a pending status. They cannot log in
                        until they open the invitation link, set a password, and the
                        system marks the account active.
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/users" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-user-invite-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-envelope me-1"></i> Send invitation
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
