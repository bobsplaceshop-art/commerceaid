<?php
/**
 * Create a new admin role.
 *
 * Context (from AdminRoleController::showCreate):
 *   $permissionMap array<string,array<int,array<string,mixed>>>
 *   $copyFrom      array<int,array{id:int,name:string}>  (unused for now)
 *   $errors        array<string,string>
 *   $old           array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/roles.
 */
$permissionMap = $permissionMap ?? [];
$errors        = $errors        ?? [];
$old           = $old           ?? [];

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
            <a href="/commerce/ca-admin/roles" class="text-decoration-none">Roles</a>
            <span class="mx-1">&rsaquo;</span>
            <span>New role</span>
        </nav>
        <h1 class="h3 mb-1">New role</h1>
        <p class="muted mb-0">
            Give the role a name and toggle the permissions it should grant.
            Users assigned to this role gain access to the selected areas.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="/commerce/ca-admin/roles" id="ra-role-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Role details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="rf-name">
                        Name <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="rf-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="80"
                           required
                           autofocus>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="rf-slug">Slug</label>
                    <input type="text" id="rf-slug" name="slug"
                           class="form-control <?= $err('slug') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('slug')) ?>"
                           maxlength="80"
                           pattern="[a-z0-9]+(-[a-z0-9]+)*"
                           placeholder="auto-generated from name">
                    <div class="muted small mt-1">
                        Leave blank to auto-generate. Lowercase letters,
                        digits, and hyphens only.
                    </div>
                    <?php if ($err('slug') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('slug')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="rf-description">Description</label>
                    <input type="text" id="rf-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="255"
                           placeholder="Short summary of what this role can do">
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <?php
    // Shared permission matrix. On create, nothing is pre-checked.
    $assignedIds = [];
    $readOnly    = false;
    require __DIR__ . '/_permission-matrix.php';
    ?>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/roles" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-role-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-shield-check me-1"></i> Create role
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
