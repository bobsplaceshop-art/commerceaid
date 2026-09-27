<?php
/**
 * Storefront category detail at /categories/{slug}.
 *
 * Context (from CategoryController::show):
 *   $category  Category            the current category
 *   $parent    ?Category           parent, for breadcrumb
 *   $children  array<int,Category> direct children
 *   $result    array{items, total, page, per_page, pages}
 *
 * Store builder contract:
 *   Section  data-sf-section="category-detail"
 *   Page     data-sf-page="category"
 *   Category data-sf-category-id="{id}"
 *
 * Images:
 *   Hero banner uses picsum.photos seeded by "category-{slug}" so
 *   it is deterministic per category. Replaced by real banner
 *   media when uploads land.
 */

$category = $category ?? null;
if ($category === null) {
    return;
}

$parent   = $parent   ?? null;
$children = $children ?? [];
$result   = $result   ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 24, 'pages' => 0];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$heroImage = 'https://picsum.photos/seed/' . rawurlencode('category-' . $category->slug) . '/1200/400';

/**
 * Page URL preserving only the page param.
 */
$pageUrl = function (int $p): string {
    return '?page=' . $p;
};
?>

<div class="sf-container sf-category-show"
     data-sf-page="category"
     data-sf-category-id="<?= (int) $category->id ?>">

    <nav class="sf-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
        <a href="/categories">Categories</a>
        <?php if ($parent !== null): ?>
            <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
            <a href="/categories/<?= e(rawurlencode($parent->slug)) ?>"><?= e($parent->name) ?></a>
        <?php endif; ?>
        <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
        <span class="sf-breadcrumb-current" aria-current="page"><?= e($category->name) ?></span>
    </nav>

    <section class="sf-category-hero" data-sf-section="category-detail">
        <div class="sf-category-hero-image">
            <img src="<?= e($heroImage) ?>" alt="" loading="lazy" width="1200" height="400">
        </div>
        <div class="sf-category-hero-body">
            <h1 class="sf-category-hero-title"><?= e($category->name) ?></h1>
            <?php if (!empty($category->description)): ?>
                <p class="sf-category-hero-description"><?= e($category->description) ?></p>
            <?php endif; ?>
            <p class="sf-category-hero-count">
                <?= $total ?> product<?= $total === 1 ? '' : 's' ?>
            </p>
        </div>
    </section>

    <?php if (!empty($children)): ?>
        <nav class="sf-subcategory-strip" aria-label="Subcategories">
            <?php foreach ($children as $child): ?>
                <a href="/categories/<?= e(rawurlencode($child->slug)) ?>" class="sf-subcategory-chip">
                    <?= e($child->name) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div class="sf-empty">
            <i class="bi bi-box-seam"></i>
            <p class="sf-empty-text">
                No products in this category yet.
            </p>
            <a href="/products" class="sf-btn sf-btn-outline">Browse all products</a>
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

</div>
