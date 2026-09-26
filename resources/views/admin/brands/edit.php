<?php
/**
 * Brand create/edit form.
 *
 * @var \CorporateAid\Commerce\Models\Brand|null $brand
 * @var array $errors
 * @var array $old
 * @var string $mode
 */
$brand  = $brand  ?? null;
$errors = $errors ?? [];
$old    = $old    ?? [];
$mode   = $mode   ?? 'create';

$val = function (string $key, $default = '') use ($old, $brand) {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($brand === null) {
        return (string) $default;
    }
    return (string) ($brand->$key ?? $default);
};

$err = function (string $key) use ($errors): string {
    return isset($errors[$key])
        ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
        : '';
};

$actionUrl = $mode === 'edit' && $brand !== null
    ? '/commerce/ca-admin/brands/' . (int) $brand->id
    : '/commerce/ca-admin/brands';

$cancelUrl = '/commerce/ca-admin/brands';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $mode === 'edit' ? 'Edit brand' : 'New brand' ?></h1>
        <p class="muted mb-0">
            <?php if ($mode === 'edit' && $brand !== null): ?>
                <?= e($brand->name) ?>
            <?php else: ?>
                Add a brand shoppers can browse by.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<form method="POST" action="<?= e($actionUrl) ?>" novalidate>
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
                           placeholder="e.g. Acme Outdoor">
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
                    <label for="website_url" class="form-label">Website</label>
                    <input type="url" id="website_url" name="website_url" class="form-control"
                           value="<?= e($val('websiteUrl')) ?>"
                           placeholder="https://example.com">
                    <div class="form-text">Optional - link to the brand's site.</div>
                    <?= $err('website_url') ?>
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
                              placeholder="Optional - shown on the brand page"
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
                           maxlength="200"
                           value="<?= e($val('seoTitle')) ?>"
                           placeholder="Defaults to brand name">
                </div>

                <div class="col-12">
                    <label for="seo_description" class="form-label">Meta description</label>
                    <textarea id="seo_description" name="seo_description" class="form-control" rows="2"
                              maxlength="300"
                              placeholder="Up to 300 characters"
                              ><?= e($val('seoDescription')) ?></textarea>
                </div>

                <div class="col-12">
                    <label for="seo_keywords" class="form-label">Keywords</label>
                    <input type="text" id="seo_keywords" name="seo_keywords" class="form-control"
                           maxlength="300"
                           value="<?= e($val('seoKeywords')) ?>"
                           placeholder="Comma-separated">
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">
            <?= $mode === 'edit' ? 'Save changes' : 'Create brand' ?>
        </button>
        <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">Cancel</a>
    </div>
</form>
