<?php
/**
 * Storefront filter sidebar.
 *
 * Shared between the /products catalog page and the
 * /categories/{slug} category detail page.
 *
 * Callers pass:
 *   $currentFilters    array<string,string>  the currently-applied filters
 *   $sortOptions       array<string,string>  sort key => label
 *   $categoryOptions   array<int,array{slug,name}>   categories (may be [])
 *   $brandOptions      array<int,array{slug,name}>   brands
 *   $formAction        string                '/products' or '/categories/{slug}'
 *   $categoryLocked    bool                  true on the category detail page
 *   $hasFilters        bool                  true when any filter is applied
 *   $showSearch        bool                  true to render the search box
 *
 * On the category detail page, $categoryLocked = true hides
 * the category dropdown. The page IS a category; picking
 * another one belongs on the /products page.
 */

$currentFilters  = $currentFilters  ?? [];
$sortOptions     = $sortOptions     ?? [];
$categoryOptions = $categoryOptions ?? [];
$brandOptions    = $brandOptions    ?? [];
$formAction      = $formAction      ?? '/products';
$categoryLocked  = $categoryLocked  ?? false;
$hasFilters      = $hasFilters      ?? false;
$showSearch      = $showSearch      ?? true;
?>

<aside class="sf-catalog-filters" data-sf-catalog-block="filters">
    <form method="GET"
          action="<?= e($formAction) ?>"
          id="sf-catalog-filter-form">

        <?php if ($showSearch): ?>
            <div class="sf-filter-block">
                <label for="sf-filter-q" class="sf-filter-label">Search</label>
                <input type="search"
                       id="sf-filter-q"
                       name="q"
                       class="sf-filter-input"
                       value="<?= e((string) ($currentFilters['q'] ?? '')) ?>"
                       placeholder="Name, SKU, keyword">
            </div>
        <?php endif; ?>

        <?php if (!$categoryLocked && !empty($categoryOptions)): ?>
            <div class="sf-filter-block">
                <label for="sf-filter-category" class="sf-filter-label">Category</label>
                <select id="sf-filter-category" name="category" class="sf-filter-input">
                    <option value="">All categories</option>
                    <?php foreach ($categoryOptions as $c): ?>
                        <option value="<?= e($c['slug']) ?>"
                            <?= ($currentFilters['category'] ?? '') === $c['slug'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="sf-filter-block">
            <label for="sf-filter-brand" class="sf-filter-label">Brand</label>
            <select id="sf-filter-brand" name="brand" class="sf-filter-input">
                <option value="">All brands</option>
                <?php foreach ($brandOptions as $b): ?>
                    <option value="<?= e($b['slug']) ?>"
                        <?= ($currentFilters['brand'] ?? '') === $b['slug'] ? 'selected' : '' ?>>
                        <?= e($b['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sf-filter-block">
            <label class="sf-filter-label">Price range</label>
            <div class="sf-filter-price">
                <input type="number"
                       name="price_min"
                       class="sf-filter-input"
                       placeholder="Min"
                       min="0"
                       step="0.01"
                       value="<?= e((string) ($currentFilters['price_min'] ?? '')) ?>">
                <span class="sf-filter-price-sep" aria-hidden="true">&ndash;</span>
                <input type="number"
                       name="price_max"
                       class="sf-filter-input"
                       placeholder="Max"
                       min="0"
                       step="0.01"
                       value="<?= e((string) ($currentFilters['price_max'] ?? '')) ?>">
            </div>
        </div>

        <div class="sf-filter-block">
            <label class="sf-filter-check">
                <input type="checkbox"
                       name="in_stock"
                       value="1"
                       <?= !empty($currentFilters['in_stock']) ? 'checked' : '' ?>>
                <span>In stock only</span>
            </label>
        </div>

        <input type="hidden" name="sort" value="<?= e((string) ($currentFilters['sort'] ?? 'newest')) ?>">

        <div class="sf-filter-actions">
            <button type="submit" class="sf-btn sf-btn-primary sf-btn-block">Apply filters</button>
            <?php if ($hasFilters): ?>
                <a href="<?= e($formAction) ?>" class="sf-btn sf-btn-outline sf-btn-block">Clear all</a>
            <?php endif; ?>
        </div>
    </form>
</aside>
