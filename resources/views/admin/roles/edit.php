<?php
/**
 * Edit an admin role.
 *
 * Context (from AdminRoleController::showEdit):
 *   $role          array<string,mixed>   raw row from RoleRepository::findById()
 *   $permissionMap array<string,array<int,array<string,mixed>>>
 *   $assignedIds   array<int,int>        currently attached permission ids
 *   $userCount     int                   users assigned to this role
 *   $errors        array<string,string>
 *   $old           array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/roles/{id}.
 * Delete posts to /commerce/ca-admin/roles/{id}/delete (via the modal).
 */
$role          = $role          ?? [];
$permissionMap = $permissionMap ?? [];
$assignedIds   = $assignedIds   ?? [];
$userCount     = (int) ($userCount ?? 0);
$errors        = $errors        ?? [];
$old           = $old           ?? [];

$roleId   = (int) ($role['id'] ?? 0);
$isSystem = ((int) ($role['is_system'] ?? 0)) === 1;
$name     = (string) ($role['name'] ?? '');
$slug     = (string) ($role['slug'] ?? '');
$desc     = (string) ($role['description'] ?? '');
$created  = (string) ($role['created_at'] ?? '');

$val = function (string $key, string $default = '') use ($old, $name, $desc): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    return match ($key) {
        'name'        => $name,
        'description' => $desc,
        default       => $default,
    };
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/roles" class="text-decoration-none">Roles</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($name) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($name) ?></h1>
        <p class="muted mb-0">
            <code><?= e($slug) ?></code>
            <?php if ($isSystem): ?>
                <span class="ms-2 ra-badge ra-badge-warning">System role</span>
            <?php endif; ?>
            <span class="ms-2">
                <?= (int) $userCount ?> user<?= $userCount === 1 ? '' : 's' ?> assigned
            </span>
        </p>
    </div>
</div>

<?php if ($isSystem): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <div class="d-flex gap-3 align-items-start">
                <div style="font-size:1.75rem;color:var(--ra-warning-fg,#d97706);">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i>
                </div>
                <div>
                    <h2 class="h6 mb-1">This is a system role</h2>
                    <p class="muted small mb-0">
                        <?= e($name) ?> is the platform's recovery anchor. Its permissions
                        are locked to every capability in the system, and it cannot be
                        renamed or deleted. This guarantees that at least one account
                        can always restore the platform if a custom role is misconfigured.
                    </p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="/commerce/ca-admin/roles/<?= $roleId ?>" id="ra-role-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Role details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="rf-name">
                        Name<?= $isSystem ? '' : ' <span aria-hidden="true">*</span>' ?>
                    </label>
                    <input type="text" id="rf-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="80"
                           <?= $isSystem ? 'disabled' : 'required' ?>>
                    <?php if ($isSystem): ?>
                        <div class="muted small mt-1">System role name is fixed.</div>
                    <?php endif; ?>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="rf-slug-display">Slug</label>
                    <input type="text" id="rf-slug-display"
                           class="form-control"
                           value="<?= e($slug) ?>"
                           disabled>
                    <div class="muted small mt-1">
                        Slug is the stable identifier and cannot be changed.
                    </div>
                </div>

                <div class="col-12">
                    <label class="muted small" for="rf-description">Description</label>
                    <input type="text" id="rf-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="255"
                           placeholder="Short summary of what this role can do"
                           <?= $isSystem ? 'disabled' : '' ?>>
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($created !== ''): ?>
                    <div class="col-12">
                        <div class="muted small">Created <?= e($created) ?></div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php
    // Shared permission matrix. System roles render read-only.
    $readOnly = $isSystem;
    require __DIR__ . '/_permission-matrix.php';
    ?>
</form>

<?php if (!$isSystem): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h3 class="h6 mb-0">Delete this role</h3>
        </div>
        <div class="ra-card-body">
            <?php if ($userCount === 0): ?>
                <p class="muted small mb-3">
                    This role has no users assigned. Deleting it is permanent
                    and cannot be undone.
                </p>
                <button type="button"
                        class="ra-btn ra-btn-danger"
                        data-ra-delete-role
                        data-role-id="<?= $roleId ?>"
                        data-role-name="<?= e($name) ?>">
                    <i class="bi bi-trash me-1"></i> Delete role
                </button>
            <?php else: ?>
                <p class="muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Cannot delete: <?= (int) $userCount ?>
                    user<?= $userCount === 1 ? ' is' : 's are' ?> still assigned
                    to this role. Reassign them from
                    <a href="/commerce/ca-admin/users">Users</a> first.
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/roles" class="ra-btn ra-btn-outline">Cancel</a>
        <?php if (!$isSystem): ?>
            <button type="submit" form="ra-role-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check2 me-1"></i> Save changes
            </button>
        <?php endif; ?>
    </div>
</div>
<div class="ra-sticky-spacer"></div>

<?php if (!$isSystem && $userCount === 0): ?>
<div id="ra-role-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-close-role-modal></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-role-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-role-delete-title" class="h6 mb-0">Delete role</h3>
            <button type="button" class="ra-icon-btn" data-ra-close-role-modal aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete the role <strong id="ra-role-delete-name">this role</strong>?
            </p>
            <div class="ra-alert ra-alert-warning mb-0" style="font-size:.9rem;">
                This is permanent. Any user with this role must be reassigned
                first - the system will refuse the deletion otherwise.
            </div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-close-role-modal>
                Cancel
            </button>
            <form method="POST"
                  id="ra-role-delete-form"
                  action="/commerce/ca-admin/roles/<?= $roleId ?>/delete"
                  style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete role
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal    = document.getElementById('ra-role-delete-modal');
    var nameEl   = document.getElementById('ra-role-delete-name');
    if (!modal) return;

    function open(name) {
        if (name && nameEl) nameEl.textContent = name;
        modal.style.display = 'flex';
    }
    function close() {
        modal.style.display = 'none';
    }

    document.querySelectorAll('[data-ra-delete-role]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            open(btn.getAttribute('data-role-name') || 'this role');
        });
    });

    modal.querySelectorAll('[data-ra-close-role-modal]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') close();
    });
})();
</script>
<?php endif; ?>
