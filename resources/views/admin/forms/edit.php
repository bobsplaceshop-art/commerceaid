<?php
/**
 * Edit a form. Stage 009, Session 2a.
 *
 * Context:
 *   $form      Form
 *   $fields    array<int, FormField>   (may be empty; Session 2b UI pending)
 *   $maxStep   int
 *   $subCounts array{new:int,read:int,spam:int,archived:int,total:int}
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *
 * Session 2a ships the metadata editor only. The Fields
 * card is a placeholder that Session 2b replaces with the
 * field editor. The Submissions card links to a route
 * that ships in Session 4.
 */
$form      = $form      ?? null;
$fields    = $fields    ?? [];
$maxStep   = $maxStep   ?? 1;
$subCounts = $subCounts ?? ['new' => 0, 'read' => 0, 'spam' => 0, 'archived' => 0, 'total' => 0];
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$allTypes  = $allTypes  ?? [];

if (empty($allTypes) && class_exists(\CorporateAid\Platform\CustomFields\InputTypeRegistry::class)) {
    $allTypes = \CorporateAid\Platform\CustomFields\InputTypeRegistry::all();
}

if ($form === null) {
    echo '<div class="ra-alert ra-alert-error">Form not found.</div>';
    return;
}

$val = function (string $key, string $default = '') use ($old, $form): string {
    if (array_key_exists($key, $old)) return (string) $old[$key];
    return match ($key) {
        'name'            => (string) $form->name,
        'slug'            => (string) $form->slug,
        'description'     => (string) ($form->description    ?? ''),
        'success_message' => (string) ($form->successMessage ?? ''),
        'notify_email'    => (string) ($form->notifyEmail    ?? ''),
        default           => $default,
    };
};

$bool = function (string $key, bool $default) use ($old, $form): bool {
    if (array_key_exists($key, $old)) return !empty($old[$key]);
    return match ($key) {
        'honeypot_enabled' => $form->honeypotEnabled,
        'requires_auth'    => $form->requiresAuth,
        default            => $default,
    };
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$slugLocked = !$form->isDraft();
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/forms" class="text-decoration-none">Forms</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($form->name) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($form->name) ?></h1>
        <p class="muted mb-0">
            <code><?= e($form->slug) ?></code>
            <span class="mx-1">·</span>
            <span class="ra-badge ra-badge-<?= e($form->statusBadgeVariant()) ?>">
                <?= e($form->statusLabel()) ?>
            </span>
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Status</h2>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($form->isDraft()): ?>
                <form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/publish" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-primary">
                        <i class="bi bi-rocket-takeoff me-1"></i> Publish
                    </button>
                </form>
                <form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/archive" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline"
                            onclick="return confirm('Archive this form? The public URL will stop working.');">
                        <i class="bi bi-archive me-1"></i> Archive
                    </button>
                </form>
            <?php elseif ($form->isPublished()): ?>
                <a href="/forms/<?= e($form->slug) ?>" target="_blank" rel="noopener" class="ra-btn ra-btn-outline">
                    <i class="bi bi-box-arrow-up-right me-1"></i> View public form
                </a>
                <form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/unpublish" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline"
                            onclick="return confirm('Move back to draft? The public URL will stop working until re-published.');">
                        <i class="bi bi-file-earmark me-1"></i> Unpublish
                    </button>
                </form>
                <form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/archive" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline"
                            onclick="return confirm('Archive this form? The public URL will stop working.');">
                        <i class="bi bi-archive me-1"></i> Archive
                    </button>
                </form>
            <?php else: /* archived */ ?>
                <form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/unpublish" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline">
                        <i class="bi bi-file-earmark me-1"></i> Move to draft
                    </button>
                </form>
            <?php endif; ?>

            <form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/duplicate" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-outline"
                        onclick="return confirm('Duplicate this form? The copy will be a draft.');">
                    <i class="bi bi-files me-1"></i> Duplicate
                </button>
            </form>
        </div>
        <?php if ($form->isPublished()): ?>
            <div class="muted small mt-3">
                Public URL: <code>/forms/<?= e($form->slug) ?></code>
            </div>
        <?php endif; ?>
        <?php if ($slugLocked): ?>
            <div class="muted small mt-2">
                The slug is locked because the form is published. Move it to draft to change the URL.
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="/commerce/ca-admin/forms/<?= (int) $form->id ?>" id="ra-form-main-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Basic</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="muted small" for="ff-name">
                        Name <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="ff-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="120" required>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="ff-slug">
                        Slug <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="ff-slug" name="slug"
                           class="form-control <?= $err('slug') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('slug')) ?>"
                           maxlength="120"
                           <?= $slugLocked ? 'readonly' : '' ?>
                           required>
                    <div class="muted small mt-1">
                        <?= $slugLocked
                            ? 'Locked while the form is not a draft.'
                            : 'Lowercase letters, digits, and hyphens only.' ?>
                    </div>
                    <?php if ($err('slug') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('slug')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="ff-description">Description</label>
                    <input type="text" id="ff-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="500"
                           placeholder="Shown to admins only">
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="ff-success">Success message</label>
                    <textarea id="ff-success"
                              name="success_message"
                              class="form-control"
                              rows="2"
                              maxlength="1000"><?= e($val('success_message')) ?></textarea>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="ff-notify">Notify email</label>
                    <input type="email" id="ff-notify" name="notify_email"
                           class="form-control <?= $err('notify_email') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('notify_email')) ?>"
                           maxlength="190"
                           placeholder="sales@example.com">
                    <?php if ($err('notify_email') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('notify_email')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Protection</h2>

            <div class="form-check mb-2">
                <input class="form-check-input"
                       type="checkbox"
                       id="ff-honeypot"
                       name="honeypot_enabled"
                       value="1"
                       <?= $bool('honeypot_enabled', true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ff-honeypot">
                    Enable honeypot spam protection
                </label>
            </div>

            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="ff-auth"
                       name="requires_auth"
                       value="1"
                       <?= $bool('requires_auth', false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ff-auth">
                    Require login to view this form
                </label>
            </div>
        </div>
    </div>
</form>

<div class="ra-card mb-4" id="fields">
    <div class="ra-card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h6 mb-1">Fields</h2>
                <p class="muted small mb-0">
                    The order of fields here is the order customers see them.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="ra-badge ra-badge-muted">
                    <?= count($fields) ?> <?= count($fields) === 1 ? 'field' : 'fields' ?>
                </span>
                <button type="button" class="ra-btn ra-btn-primary" data-ra-field-add>
                    <i class="bi bi-plus-lg me-1"></i> Add field
                </button>
            </div>
        </div>

        <?php if (empty($fields)): ?>
            <div class="text-center py-4" style="border:1px dashed var(--ra-border,#cbd5e1);border-radius:8px;">
                <i class="bi bi-input-cursor-text" style="font-size:2rem;color:var(--ra-muted,#94a3b8);"></i>
                <p class="muted small mb-0 mt-2">
                    No fields yet. Add at least one field before publishing the form.
                </p>
            </div>
        <?php else: ?>
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Label</th>
                            <th class="ra-hide-sm" style="width:150px;">Key</th>
                            <th class="ra-hide-sm" style="width:140px;">Type</th>
                            <th style="width:70px;">Step</th>
                            <th style="width:80px;">Required</th>
                            <th style="width:180px;text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $i => $field): ?>
                            <tr>
                                <td class="muted small"><?= (int) ($i + 1) ?></td>
                                <td>
                                    <span style="font-weight:600;"><?= e($field->label) ?></span>
                                    <?php if ($field->helpText !== null && $field->helpText !== ''): ?>
                                        <div class="muted small"><?= e($field->helpText) ?></div>
                                    <?php endif; ?>
                                    <?php if ($field->isConditional()): ?>
                                        <div class="muted small">
                                            <i class="bi bi-funnel" aria-hidden="true"></i>
                                            Only shown when <code><?= e($field->conditional['field']) ?></code>
                                            <?= e(str_replace('_', ' ', $field->conditional['operator'])) ?>
                                            <?php if ($field->conditional['value'] !== null && $field->conditional['value'] !== ''): ?>
                                                <code><?= e((string) $field->conditional['value']) ?></code>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="ra-hide-sm"><code><?= e($field->fieldKey) ?></code></td>
                                <td class="ra-hide-sm muted small">
                                    <?= e(isset($allTypes[$field->inputType]) ? $allTypes[$field->inputType]->label() : $field->inputType) ?>
                                </td>
                                <td><?= (int) $field->step ?></td>
                                <td>
                                    <?php if ($field->isRequired): ?>
                                        <span class="ra-badge ra-badge-warning">Yes</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end" style="white-space:nowrap;">
                                    <form method="POST"
                                          action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/fields/<?= (int) $field->id ?>/move"
                                          style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="ra-icon-btn" title="Move up"
                                                <?= $i === 0 ? 'disabled' : '' ?>>
                                            <i class="bi bi-arrow-up"></i>
                                        </button>
                                    </form>
                                    <form method="POST"
                                          action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/fields/<?= (int) $field->id ?>/move"
                                          style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="ra-icon-btn" title="Move down"
                                                <?= $i === count($fields) - 1 ? 'disabled' : '' ?>>
                                            <i class="bi bi-arrow-down"></i>
                                        </button>
                                    </form>
                                    <button type="button"
                                            class="ra-icon-btn"
                                            title="Edit"
                                            data-ra-field-edit
                                            data-field-id="<?= (int) $field->id ?>"
                                            data-field-key="<?= e($field->fieldKey) ?>"
                                            data-field-label="<?= e($field->label) ?>"
                                            data-field-type="<?= e($field->inputType) ?>"
                                            data-field-placeholder="<?= e((string) ($field->placeholder ?? '')) ?>"
                                            data-field-help="<?= e((string) ($field->helpText ?? '')) ?>"
                                            data-field-required="<?= $field->isRequired ? '1' : '0' ?>"
                                            data-field-step="<?= (int) $field->step ?>"
                                            data-field-default="<?= e((string) ($field->defaultValue ?? '')) ?>"
                                            data-field-options='<?= e((string) json_encode($field->options)) ?>'
                                            data-field-constraints='<?= e((string) json_encode($field->constraints)) ?>'
                                            data-field-conditional='<?= e((string) json_encode($field->conditional)) ?>'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST"
                                          action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/fields/<?= (int) $field->id ?>/delete"
                                          style="display:inline;"
                                          data-ra-field-delete-form>
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn" title="Delete"
                                                onclick="return confirm('Delete this field? Existing submissions keep their value, but the field will be gone from the form.');">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?= view_partial('admin.forms._field-modal', [
    'form'     => $form,
    'fields'   => $fields,
    'allTypes' => $allTypes,
]) ?>

<script src="<?= versioned_asset('js/forms-field-editor-v2.js') ?>"></script>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">Submissions</h2>
            <button type="button" class="ra-btn ra-btn-outline" disabled
                    title="Submissions viewer ships in Session 4.">
                View all (coming soon)
            </button>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="muted small">New</div>
                <div style="font-size:1.5rem;font-weight:600;"><?= (int) $subCounts['new'] ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="muted small">Read</div>
                <div style="font-size:1.5rem;font-weight:600;"><?= (int) $subCounts['read'] ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="muted small">Spam</div>
                <div style="font-size:1.5rem;font-weight:600;"><?= (int) $subCounts['spam'] ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="muted small">Total</div>
                <div style="font-size:1.5rem;font-weight:600;"><?= (int) $subCounts['total'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="ra-card mb-4" style="border-color:var(--ra-error-border,#fecaca);">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Danger zone</h2>
        <p class="muted small mb-3">
            Deleting a form hides it from every list and stops the public URL.
            Any submissions it has already collected are kept.
        </p>
        <button type="button" class="ra-btn ra-btn-danger" data-ra-form-delete-open>
            <i class="bi bi-trash me-1"></i> Delete form
        </button>
    </div>
</div>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/forms" class="ra-btn ra-btn-outline">Back to forms</a>
        <button type="submit" form="ra-form-main-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i> Save changes
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>

<form method="POST"
      action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/delete"
      id="ra-form-delete-form"
      style="display:none;">
    <?= csrf_field() ?>
</form>

<script>
(function () {
    'use strict';

    var btn = document.querySelector('[data-ra-form-delete-open]');
    var form = document.getElementById('ra-form-delete-form');
    if (!btn || !form) return;

    btn.addEventListener('click', function () {
        var total = <?= (int) $subCounts['total'] ?>;
        var msg = total > 0
            ? 'Delete this form? It has ' + total + ' submission' + (total === 1 ? '' : 's') + '. Submissions are kept but the form will be hidden everywhere.'
            : 'Delete this form?';
        if (!confirm(msg)) return;
        form.submit();
    });
})();
</script>

