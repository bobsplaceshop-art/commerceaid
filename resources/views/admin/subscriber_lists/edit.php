<?php
/**
 * Subscriber list edit / create. Stage 008I.
 *
 * Context:
 *   $list   ?SubscriberList
 *   $errors array<string,string>
 *   $old    array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/subscriber-lists (create)
 *       or /commerce/ca-admin/subscriber-lists/{id} (update).
 */
$list   = $list   ?? null;
$errors = $errors ?? [];
$old    = $old    ?? [];

$isEdit = $list !== null;
$action = $isEdit
    ? '/commerce/ca-admin/subscriber-lists/' . (int) $list->id
    : '/commerce/ca-admin/subscriber-lists';

$val = function (string $key, string $default = '') use ($old, $list): string {
    if (array_key_exists($key, $old)) return (string) $old[$key];
    if ($list === null) return $default;
    return match ($key) {
        'name'        => (string) $list->name,
        'slug'        => (string) $list->slug,
        'description' => (string) ($list->description ?? ''),
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
            <a href="/commerce/ca-admin/subscriber-lists" class="text-decoration-none">Subscriber lists</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= $isEdit ? 'Edit' : 'New list' ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= $isEdit ? e($list->name) : 'New list' ?></h1>
        <?php if ($isEdit && $list->isDefault): ?>
            <p class="muted mb-0">
                This is the store's default list. It cannot be deleted. The footer signup form joins it.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" id="ra-list-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="muted small" for="lf-name">
                        Name <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="lf-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="80"
                           placeholder="Product updates"
                           required
                           <?= $isEdit && $list->isDefault ? 'readonly' : '' ?>>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="lf-slug">
                        Slug <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="lf-slug" name="slug"
                           class="form-control <?= $err('slug') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('slug')) ?>"
                           maxlength="80"
                           placeholder="product-updates"
                           required
                           <?= $isEdit && $list->isDefault ? 'readonly' : '' ?>>
                    <div class="muted small mt-1">
                        Lowercase letters, digits, and hyphens only. Unique per store.
                    </div>
                    <?php if ($err('slug') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('slug')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="lf-description">Description</label>
                    <input type="text" id="lf-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="255"
                           placeholder="Shown to admins only">
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($isEdit && !$list->isDefault): ?>
        <div class="ra-card mb-4">
            <div class="ra-card-body">
                <h2 class="h6 mb-3">Danger zone</h2>
                <p class="muted small mb-3">
                    Deleting a list removes all its memberships. Subscribers are not
                    deleted; they remain on any other lists they joined. This cannot
                    be undone.
                </p>
                <button type="button" class="ra-btn ra-btn-danger" data-ra-list-delete-open>
                    <i class="bi bi-trash me-1"></i> Delete list
                </button>
            </div>
        </div>
    <?php endif; ?>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/subscriber-lists" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-list-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i> <?= $isEdit ? 'Save changes' : 'Create list' ?>
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>

<?php if ($isEdit && !$list->isDefault): ?>
    <form method="POST"
          action="/commerce/ca-admin/subscriber-lists/<?= (int) $list->id ?>/delete"
          id="ra-list-delete-form"
          style="display:none;">
        <?= csrf_field() ?>
    </form>
    <script>
    (function () {
        'use strict';
        var btn = document.querySelector('[data-ra-list-delete-open]');
        var form = document.getElementById('ra-list-delete-form');
        if (!btn || !form) return;
        btn.addEventListener('click', function () {
            if (!confirm('Delete this list? Members are kept on any other lists they joined.')) return;
            form.submit();
        });
    })();
    </script>
<?php endif; ?>
