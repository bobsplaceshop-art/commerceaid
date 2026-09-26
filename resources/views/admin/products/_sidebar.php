<?php
/**
 * Product editor — right sidebar.
 *
 * Contains: status, save actions, featured flags, danger zone.
 * Shares scope with edit.php.
 */
$currentStatus = (string) $val('status', 'draft');
$isFeatured    = $valBool('is_featured', false);
$isNew         = $valBool('is_new', false);
$isPublished   = $currentStatus === 'published';
$isArchived    = $currentStatus === 'archived';
?>

<div class="ra-card mb-3">
    <div class="ra-card-body">

        <div class="mb-3">
            <label for="status" class="form-label small muted mb-1">Status</label>
            <select id="status" name="status" class="form-select form-select-sm">
                <option value="draft"     <?= $currentStatus === 'draft'     ? 'selected' : '' ?>>Draft</option>
                <option value="published" <?= $currentStatus === 'published' ? 'selected' : '' ?>>Published</option>
                <option value="archived"  <?= $currentStatus === 'archived'  ? 'selected' : '' ?>>Archived</option>
            </select>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="ra-btn ra-btn-primary">
                <i class="bi bi-check-lg me-1"></i>
                <?= $mode === 'edit' ? 'Save changes' : 'Create product' ?>
            </button>
            <a href="/commerce/ca-admin/products" class="ra-btn ra-btn-outline">Cancel</a>
        </div>

    </div>
</div>

<?php if ($mode === 'edit' && $product !== null && $product->isVariable()): ?>
<div class="ra-card mb-3">
    <div class="ra-card-body">
        <div class="muted small mb-2">This is a variable product.</div>
        <a href="/commerce/ca-admin/products/<?= (int) $product->id ?>/variants"
           class="ra-btn ra-btn-primary w-100">
            <i class="bi bi-diagram-3 me-1"></i> Manage variations
        </a>
    </div>
</div>
<?php endif; ?>

<div class="ra-card mb-3">
    <div class="ra-card-header">
        <h3 class="h6 mb-0">Visibility</h3>
    </div>
    <div class="ra-card-body">

        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_featured" value="0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="is_featured" name="is_featured" value="1"
                   <?= $isFeatured ? 'checked' : '' ?>>
            <label class="form-check-label small" for="is_featured">
                <strong>Featured</strong>
                <div class="muted" style="font-size:.75rem;">Highlighted on the storefront.</div>
            </label>
        </div>

        <div class="form-check form-switch">
            <input type="hidden" name="is_new" value="0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="is_new" name="is_new" value="1"
                   <?= $isNew ? 'checked' : '' ?>>
            <label class="form-check-label small" for="is_new">
                <strong>New arrival</strong>
                <div class="muted" style="font-size:.75rem;">Shown in "New arrivals" sections.</div>
            </label>
        </div>

    </div>
</div>

<?php if ($mode === 'edit' && $product !== null): ?>
<div class="ra-card">
    <div class="ra-card-header">
        <h3 class="h6 mb-0">Actions</h3>
    </div>
    <div class="ra-card-body d-grid gap-2">

        <?php if ($isPublished): ?>
            <button type="button" class="ra-btn ra-btn-outline-danger"
                    onclick="document.getElementById('ra-action-unpublish').submit()">
                <i class="bi bi-eye-slash me-1"></i> Unpublish
            </button>
        <?php elseif (!$isArchived): ?>
            <button type="button" class="ra-btn ra-btn-outline"
                    onclick="document.getElementById('ra-action-publish').submit()">
                <i class="bi bi-eye me-1"></i> Publish
            </button>
        <?php endif; ?>

        <?php if (!$isArchived): ?>
            <button type="button" class="ra-btn ra-btn-outline-danger"
                    onclick="document.getElementById('ra-action-archive').submit()">
                <i class="bi bi-archive me-1"></i> Archive
            </button>
        <?php else: ?>
            <button type="button" class="ra-btn ra-btn-outline"
                    onclick="document.getElementById('ra-action-restore').submit()">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Restore to draft
            </button>
        <?php endif; ?>

        <button type="button" class="ra-btn ra-btn-danger"
                onclick='RA_DeleteProduct.open(<?= (int) $product->id ?>, <?= json_encode($product->name) ?>)'>
            <i class="bi bi-trash me-1"></i> Delete
        </button>

    </div>
</div>

<!-- Hidden action forms (posted via the buttons above) -->
<form id="ra-action-publish" method="POST" action="/commerce/ca-admin/products/<?= (int) $product->id ?>/publish" style="display:none;"><?= csrf_field() ?></form>
<form id="ra-action-unpublish" method="POST" action="/commerce/ca-admin/products/<?= (int) $product->id ?>/unpublish" style="display:none;"><?= csrf_field() ?></form>
<form id="ra-action-archive" method="POST" action="/commerce/ca-admin/products/<?= (int) $product->id ?>/archive" style="display:none;"><?= csrf_field() ?></form>
<form id="ra-action-restore" method="POST" action="/commerce/ca-admin/products/<?= (int) $product->id ?>/restore" style="display:none;"><?= csrf_field() ?></form>
<?php endif; ?>
