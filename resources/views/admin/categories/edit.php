<?php
/**
 * Category create/edit form.
 *
 * In edit mode, also shows the attribute mapping section.
 *
 * @var \CorporateAid\Commerce\Models\Category|null $category
 * @var array $parents
 * @var array $errors
 * @var array $old
 * @var string $mode
 * @var array $mappedAttributes     Attributes currently mapped (edit only)
 * @var array $availableAttributes  Attributes available to add (edit only)
 */
$category            = $category            ?? null;
$parents             = $parents             ?? [];
$errors              = $errors              ?? [];
$old                 = $old                 ?? [];
$mode                = $mode                ?? 'create';
$mappedAttributes    = $mappedAttributes    ?? [];
$availableAttributes = $availableAttributes ?? [];

$val = function (string $key, $default = '') use ($old, $category) {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($category === null) {
        return (string) $default;
    }
    return (string) ($category->$key ?? $default);
};

$err = function (string $key) use ($errors): string {
    return isset($errors[$key])
        ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
        : '';
};

$actionUrl = $mode === 'edit' && $category !== null
    ? '/commerce/ca-admin/categories/' . (int) $category->id
    : '/commerce/ca-admin/categories';

$cancelUrl = '/commerce/ca-admin/categories';
$categoryId = $category !== null ? (int) $category->id : 0;
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $mode === 'edit' ? 'Edit category' : 'New category' ?></h1>
        <p class="muted mb-0">
            <?php if ($mode === 'edit' && $category !== null): ?>
                <?= e($category->name) ?>
            <?php else: ?>
                Create a new category to organize your products.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<form method="POST" action="<?= e($actionUrl) ?>" novalidate id="ra-category-main-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Basic information</h2>
        </div>
        <div class="ra-card-body">

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= e($val('name')) ?>"
                           placeholder="e.g. Men's Clothing">
                    <?= $err('name') ?>
                </div>

                <div class="col-md-4">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control"
                           pattern="[a-z0-9][a-z0-9-]{0,118}[a-z0-9]"
                           value="<?= e($val('slug')) ?>"
                           placeholder="auto-generated">
                    <div class="form-text">Leave blank to auto-generate from name.</div>
                    <?= $err('slug') ?>
                </div>

                <div class="col-md-8">
                    <label for="parent_id" class="form-label">Parent category</label>
                    <select id="parent_id" name="parent_id" class="form-select">
                        <option value="">- Top level -</option>
                        <?php foreach ($parents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"
                                <?= (string) $val('parentId') === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Nest under another category for a hierarchy.</div>
                    <?= $err('parent_id') ?>
                </div>

                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control"
                           min="0" max="9999"
                           value="<?= e($val('sortOrder', '0')) ?>">
                    <div class="form-text">Lower numbers appear first.</div>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Optional - shown on the category page"
                              ><?= e($val('description')) ?></textarea>
                </div>
            </div>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">SEO <span class="muted fw-normal">(optional)</span></h2>
        </div>
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="seo_title" class="form-label">Page title</label>
                    <input type="text" id="seo_title" name="seo_title" class="form-control"
                           maxlength="200" value="<?= e($val('seoTitle')) ?>">
                </div>
                <div class="col-12">
                    <label for="seo_description" class="form-label">Meta description</label>
                    <textarea id="seo_description" name="seo_description" class="form-control" rows="2"
                              maxlength="300"><?= e($val('seoDescription')) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="seo_keywords" class="form-label">Keywords</label>
                    <input type="text" id="seo_keywords" name="seo_keywords" class="form-control"
                           maxlength="300" value="<?= e($val('seoKeywords')) ?>">
                </div>
            </div>
        </div>
    </div>

</form>

<?php if ($mode === 'edit' && $category !== null): ?>

<!-- ============================================================= -->
<!-- Attribute mapping — only shown on edit                        -->
<!-- ============================================================= -->

<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Attributes for this category</h2>
        <span class="muted small"><?= count($mappedAttributes) ?> mapped</span>
    </div>
    <div class="ra-card-body">

        <p class="muted small mb-3">
            These attributes appear in the variant generator for products
            in this category. Attributes are global vocabulary; other
            categories can use the same ones.
        </p>

        <?php if (empty($mappedAttributes)): ?>
            <p class="muted small mb-3">No attributes mapped yet. Add one below.</p>
        <?php else: ?>
            <div class="ra-mapped-attrs mb-3">
                <?php foreach ($mappedAttributes as $attr): ?>
                    <div class="ra-mapped-attr">
                        <i class="bi bi-input-cursor-text" style="color:var(--ra-muted,#94a3b8);"></i>
                        <span class="ra-mapped-attr-name"><?= e($attr['name']) ?></span>
                        <?php if (!empty($attr['is_variant_axis'])): ?>
                            <span class="ra-badge ra-badge-success" style="font-size:.62rem;">Variant</span>
                        <?php else: ?>
                            <span class="ra-badge ra-badge-muted" style="font-size:.62rem;">Info</span>
                        <?php endif; ?>
                        <code class="muted small"><?= e($attr['slug']) ?></code>
                        <button type="button" class="ra-icon-btn ms-auto"
                                onclick='RA_DetachAttribute.open(<?= $categoryId ?>, <?= (int) $attr['id'] ?>, <?= json_encode($attr['name']) ?>)'
                                title="Remove from this category">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($availableAttributes)): ?>
            <form method="POST"
                  action="/commerce/ca-admin/categories/<?= $categoryId ?>/attributes"
                  class="ra-add-attr-form">
                <?= csrf_field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="attribute_id" class="form-label small mb-1">Add attribute</label>
                        <select id="attribute_id" name="attribute_id" class="form-select form-select-sm" required>
                            <option value="">- Select -</option>
                            <?php
                            $variantAxes = array_filter($availableAttributes, fn($a) => !empty($a['is_variant_axis']));
                            $informational = array_filter($availableAttributes, fn($a) => empty($a['is_variant_axis']));
                            ?>
                            <?php if (!empty($variantAxes)): ?>
                                <optgroup label="Variant axes (generate variants)">
                                    <?php foreach ($variantAxes as $attr): ?>
                                        <option value="<?= (int) $attr['id'] ?>">
                                            <?= e($attr['name']) ?>
                                            <?php if (!empty($attr['values'])): ?>
                                                (<?= count($attr['values']) ?> values)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (!empty($informational)): ?>
                                <optgroup label="Informational (filtering only)">
                                    <?php foreach ($informational as $attr): ?>
                                        <option value="<?= (int) $attr['id'] ?>">
                                            <?= e($attr['name']) ?>
                                            <?php if (!empty($attr['values'])): ?>
                                                (<?= count($attr['values']) ?> values)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="ra-btn ra-btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <p class="muted small mb-0">
                All attributes are already mapped.
                <a href="/commerce/ca-admin/attributes/create">Create a new attribute</a> to add more.
            </p>
        <?php endif; ?>

    </div>
</div>

<?php endif; ?>


<!-- ============================================================= -->
<!-- Sticky action bar — always the last element on the page       -->
<!-- ============================================================= -->
<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">
            Cancel
        </a>
        <button type="submit" form="ra-category-main-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            <?= $mode === 'edit' ? 'Save changes' : 'Create category' ?>
        </button>
    </div>
</div>

<!-- Spacer so content does not hide behind fixed action bar -->
<div class="ra-sticky-spacer"></div>

<style>
.ra-mapped-attrs {
    display: flex;
    flex-direction: column;
    gap: .35rem;
}
.ra-mapped-attr {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .55rem .75rem;
    background: var(--ra-hover, rgba(148, 163, 184, 0.08));
    border-radius: 8px;
}
.ra-mapped-attr-name {
    color: var(--ra-fg, #0f172a);
    font-weight: 500;
}

.ra-sticky-actions {
    position: sticky;
    bottom: 0;
    margin-top: 2rem;
    padding: 1rem 0;
    background: linear-gradient(to top, var(--ra-bg, #f4f6fb) 60%, transparent);
    z-index: 10;
}
.ra-sticky-actions-inner {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    background: var(--ra-card-bg, #ffffff);
    border: 1px solid var(--ra-border, #e5e9f2);
    border-radius: 12px;
    padding: .75rem 1rem;
    box-shadow: 0 -4px 20px -8px rgba(15, 23, 42, 0.1);
}
</style>

<!-- Detach confirmation modal -->
<div id="ra-detach-attr-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_DetachAttribute.close()"></div>
    <div class="ra-modal-panel" role="dialog">
        <div class="ra-modal-header">
            <h3 class="h6 mb-0">Remove attribute from category</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_DetachAttribute.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Remove <strong id="ra-detach-name">this attribute</strong> from this category?
            </p>
            <div id="ra-detach-info" class="muted small">Loading...</div>
            <div id="ra-detach-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DetachAttribute.close()">
                Cancel
            </button>
            <form method="POST" id="ra-detach-form" action="" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-x-lg me-1"></i> Remove
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.RA_DetachAttribute = (function () {
    var modalId = 'ra-detach-attr-modal';
    var nameEl, infoEl, warnEl, formEl;
    var pendingCategoryId, pendingAttributeId;

    function init() {
        nameEl = document.getElementById('ra-detach-name');
        infoEl = document.getElementById('ra-detach-info');
        warnEl = document.getElementById('ra-detach-warning');
        formEl = document.getElementById('ra-detach-form');
    }

    function open(categoryId, attributeId, name) {
        if (!nameEl) init();
        pendingCategoryId = categoryId;
        pendingAttributeId = attributeId;

        document.getElementById(modalId).style.display = 'flex';
        nameEl.textContent = name;
        infoEl.textContent = 'Checking usage...';
        warnEl.style.display = 'none';

        // Point the form at the detach endpoint
        formEl.action = '/commerce/ca-admin/categories/' + categoryId
                      + '/attributes/' + attributeId + '/detach';

        fetch('/commerce/ca-admin/categories/' + categoryId
              + '/attributes/' + attributeId + '/usage', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var count = data.usage_count || 0;
            if (count > 0) {
                infoEl.textContent = count + ' product' + (count === 1 ? '' : 's')
                    + ' in this category currently use this attribute.';
                warnEl.textContent = 'Existing variant data will remain, but this attribute '
                    + 'will no longer appear in the variant generator for products in this category. '
                    + 'Re-add it later if needed.';
                warnEl.style.display = 'block';
            } else {
                infoEl.textContent = 'No products in this category currently use this attribute.';
            }
        })
        .catch(function () {
            infoEl.textContent = 'Could not load usage information.';
        });
    }

    function close() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    return { open: open, close: close };
})();
</script>
