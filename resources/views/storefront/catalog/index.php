<?php
/**
 * Public product listing at /products.
 *
 * Context (from CatalogController::index):
 *   $result           array{items, total, page, per_page, pages}
 *   $currentFilters   array{q, category, brand, in_stock, price_min, price_max, sort}
 *   $sortOptions      array{sort_key => label}
 *   $categoryOptions  array<{slug, name}>
 *   $brandOptions     array<{slug, name}>
 *   $resolvedCategory ?Category
 *   $resolvedBrand    ?Brand
 *
 * Store builder contract:
 *   The catalog page exposes three named blocks via data attributes:
 *     data-sf-catalog-block="active-filters"
 *     data-sf-catalog-block="filters"
 *     data-sf-catalog-block="sort"
 *   And a page-level marker: data-sf-page="catalog"
 *   The store builder will use these hooks to toggle or reorder
 *   blocks per store without changing the HTML.
 */

$result           = $result           ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 24, 'pages' => 0];
$currentFilters   = $currentFilters   ?? [];
$sortOptions      = $sortOptions      ?? ['newest' => 'Newest'];
$categoryOptions  = $categoryOptions  ?? [];
$brandOptions     = $brandOptions     ?? [];
$resolvedCategory = $resolvedCategory ?? null;
$resolvedBrand    = $resolvedBrand    ?? null;

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$hasFilters = !empty($currentFilters['q'])
    || !empty($currentFilters['category'])
    || !empty($currentFilters['brand'])
    || !empty($currentFilters['in_stock'])
    || !empty($currentFilters['price_min'])
    || !empty($currentFilters['price_max']);

/**
 * Build a URL for a given page, preserving all active filters.
 */
$pageUrl = function (int $p) use ($currentFilters): string {
    $q = array_filter($currentFilters, fn ($v) => $v !== '' && $v !== null && $v !== 0);
    $q['page'] = $p;
    return '/products?' . http_build_query($q);
};

/**
 * Build a URL that clears one specific filter but keeps the rest.
 */
$clearFilterUrl = function (string $key) use ($currentFilters): string {
    $q = array_filter($currentFilters, fn ($v) => $v !== '' && $v !== null && $v !== 0);
    unset($q[$key]);
    $qs = http_build_query($q);
    return $qs === '' ? '/products' : '/products?' . $qs;
};

/**
 * Human label for an active filter chip.
 */
$activeChips = [];
if (!empty($currentFilters['q'])) {
    $activeChips[] = ['key' => 'q', 'label' => 'Search: ' . $currentFilters['q']];
}
if (!empty($currentFilters['category'])) {
    $activeChips[] = ['key' => 'category', 'label' => 'Category: ' . $currentFilters['category']];
}
if (!empty($currentFilters['brand'])) {
    $activeChips[] = ['key' => 'brand', 'label' => 'Brand: ' . $currentFilters['brand']];
}
if (!empty($currentFilters['in_stock'])) {
    $activeChips[] = ['key' => 'in_stock', 'label' => 'In stock only'];
}
if (!empty($currentFilters['price_min']) || !empty($currentFilters['price_max'])) {
    $min = $currentFilters['price_min'] ?: '0';
    $max = $currentFilters['price_max'] ?: '∞';
    $activeChips[] = ['key' => 'price_min', 'label' => 'Price: ' . $min . ' - ' . $max];
}
?>

<div class="sf-container sf-catalog" data-sf-page="catalog">

    <nav class="sf-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
        <?php if ($resolvedCategory !== null): ?>
            <a href="/products">Products</a>
            <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
            <span class="sf-breadcrumb-current" aria-current="page"><?= e($resolvedCategory->name) ?></span>
        <?php else: ?>
            <span class="sf-breadcrumb-current" aria-current="page">Products</span>
        <?php endif; ?>
    </nav>

    <header class="sf-catalog-header">
        <h1 class="sf-catalog-title"><?= e($title ?? 'Shop all products') ?></h1>
        <p class="sf-catalog-count">
            <?= $total ?> product<?= $total === 1 ? '' : 's' ?>
            <?php if ($hasFilters): ?>
                <span class="muted">&middot; filtered</span>
            <?php endif; ?>
        </p>
    </header>

    <?php if (!empty($activeChips)): ?>
        <div class="sf-catalog-active-filters" data-sf-catalog-block="active-filters">
            <?php foreach ($activeChips as $chip): ?>
                <a href="<?= e($clearFilterUrl($chip['key'])) ?>" class="sf-chip">
                    <span><?= e($chip['label']) ?></span>
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
            <a href="/products" class="sf-chip sf-chip-clear-all">
                Clear all
            </a>
        </div>
    <?php endif; ?>

    <div class="sf-catalog-layout">

        <!-- ========================================================= -->
        <!-- Filter sidebar                                            -->
        <!-- ========================================================= -->
        <?= view_partial('storefront/catalog/_filters', [
            'currentFilters'  => $currentFilters,
            'sortOptions'     => $sortOptions,
            'categoryOptions' => $categoryOptions,
            'brandOptions'    => $brandOptions,
            'formAction'      => '/products',
            'categoryLocked'  => false,
            'hasFilters'      => $hasFilters,
            'showSearch'      => true,
        ]) ?>

        <!-- ========================================================= -->
        <!-- Product grid                                              -->
        <!-- ========================================================= -->
        <section class="sf-catalog-main">

            <?= view_partial('storefront/catalog/_toolbar', [
                'result'         => $result,
                'currentFilters' => $currentFilters,
                'sortOptions'    => $sortOptions,
                'formId'         => 'sf-catalog-filter-form',
            ]) ?>

            <?php if (empty($items)): ?>
                <div class="sf-empty">
                    <i class="bi bi-search"></i>
                    <p class="sf-empty-text">
                        <?php if ($hasFilters): ?>
                            No products match your filters.
                        <?php else: ?>
                            No products available yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($hasFilters): ?>
                        <a href="/products" class="sf-btn sf-btn-outline">Clear filters</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="sf-product-grid">
                    <?php foreach ($items as $p): ?>
                        <?= view_partial('storefront/catalog/_product-card', ['product' => $p]) ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="sf-pagination" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?= e($pageUrl($page - 1)) ?>" class="sf-pagination-link">
                                <i class="bi bi-chevron-left"></i> Prev
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end   = min($pages, $page + 2);

                        if ($start > 1): ?>
                            <a href="<?= e($pageUrl(1)) ?>" class="sf-pagination-link">1</a>
                            <?php if ($start > 2): ?>
                                <span class="sf-pagination-ellipsis">&hellip;</span>
                            <?php endif;
                        endif;

                        for ($p = $start; $p <= $end; $p++): ?>
                            <a href="<?= e($pageUrl($p)) ?>"
                               class="sf-pagination-link <?= $p === $page ? 'is-active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor;

                        if ($end < $pages): ?>
                            <?php if ($end < $pages - 1): ?>
                                <span class="sf-pagination-ellipsis">&hellip;</span>
                            <?php endif; ?>
                            <a href="<?= e($pageUrl($pages)) ?>" class="sf-pagination-link"><?= $pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $pages): ?>
                            <a href="<?= e($pageUrl($page + 1)) ?>" class="sf-pagination-link">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        </section>
    </div>
</div>
