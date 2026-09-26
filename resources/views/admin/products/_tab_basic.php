<?php
/**
 * Product editor — Basic tab.
 * Inherits scope from edit.php ($val, $valBool, $err, $categories, $brands, $categoryIds, $val, $old).
 */
$selectedCategories = array_key_exists('category_ids', $old)
    ? (array) $old['category_ids']
    : $categoryIds;
$currentStatus = (string) $val('status', 'draft');
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'basic' ? ' show active' : '' ?>" id="tab-basic" role="tabpanel">

    <div class="row g-3">

        <div class="col-12">
            <label class="form-label">Product type</label>
            <div class="d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="product_type" id="type_simple"
                           value="simple" <?= $val('productType', 'simple') === 'simple' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="type_simple">
                        <strong>Simple</strong>
                        <div class="muted small">One price, one stock level. Services, single items.</div>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="product_type" id="type_variable"
                           value="variable" <?= $val('productType', 'simple') === 'variable' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="type_variable">
                        <strong>Variable</strong>
                        <div class="muted small">Size, color, or other options with per-variant pricing and stock.</div>
                    </label>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" id="name" name="name" class="form-control" required
                   value="<?= e((string) $val('name')) ?>"
                   placeholder="e.g. Wool Crew Neck Sweater">
            <?= $err('name') ?>
        </div>
        <div class="col-md-4">
            <label for="sku" class="form-label">SKU</label>
            <input type="text" id="sku" name="sku" class="form-control"
                   value="<?= e((string) $val('sku')) ?>"
                   placeholder="e.g. SW-001">
            <div class="form-text">Unique per store.</div>
            <?= $err('sku') ?>
        </div>

        <div class="col-12">
            <label for="slug" class="form-label">Slug</label>
            <input type="text" id="slug" name="slug" class="form-control"
                   pattern="[a-z0-9][a-z0-9-]{0,198}[a-z0-9]"
                   value="<?= e((string) $val('slug')) ?>"
                   placeholder="auto-generated from name">
            <div class="form-text">URL-friendly identifier. Leave blank to auto-generate.</div>
            <?= $err('slug') ?>
        </div>

        <div class="col-12">
            <label for="short_description" class="form-label">Short description</label>
            <textarea id="short_description" name="short_description" class="form-control" rows="2"
                      maxlength="500"
                      placeholder="One-sentence summary shown in product listings"
                      ><?= e((string) $val('shortDescription')) ?></textarea>
            <div class="form-text">Up to 500 characters.</div>
            <?= $err('short_description') ?>
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Full description</label>
            <textarea id="description" name="description" class="form-control" rows="8"
                      placeholder="Detailed product description"
                      ><?= e((string) $val('description')) ?></textarea>
            <div class="form-text">A rich text editor will be added in a later stage.</div>
            <?= $err('description') ?>
        </div>

        <div class="col-md-6">
            <label for="brand_id" class="form-label">Brand</label>
            <select id="brand_id" name="brand_id" class="form-select">
                <option value="">- No brand -</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= (int) $b['id'] ?>"
                        <?= (string) $val('brandId') === (string) $b['id'] ? 'selected' : '' ?>>
                        <?= e($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($brands)): ?>
                <div class="form-text">
                    No brands yet.
                    <a href="/commerce/ca-admin/brands/create" target="_blank" rel="noopener noreferrer">Add one</a>.
                </div>
            <?php endif; ?>
            <?= $err('brand_id') ?>
        </div>

        <div class="col-md-6">
            <label for="primary_category_id" class="form-label">Primary category</label>
            <select id="primary_category_id" name="primary_category_id" class="form-select">
                <option value="">- None -</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"
                        <?= (string) $val('primaryCategoryId') === (string) $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Used for breadcrumbs and default sorting.</div>
            <?= $err('primary_category_id') ?>
        </div>

        <div class="col-12">
            <label class="form-label">Additional categories</label>
            <div class="ra-checkbox-grid">
                <?php foreach ($categories as $c): ?>
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input"
                               name="category_ids[]" value="<?= (int) $c['id'] ?>"
                               <?= in_array((int) $c['id'], array_map('intval', $selectedCategories), true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= e($c['name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-text">A product can appear in multiple categories.</div>
        </div>

        <div class="col-12">
            <label class="form-label">Tags</label>
            <div class="muted small mb-1">
                Tags help with internal filtering and storefront discovery.
            </div>
            <input type="text" class="form-control"
                   placeholder="Tag management UI arrives in a later sub-stage"
                   disabled>
        </div>
    </div>

</div>

<style>
.ra-checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: .35rem .75rem;
    padding: .5rem 0;
}
</style>
