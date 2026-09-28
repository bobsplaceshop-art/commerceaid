<?php
/**
 * Search results page.
 *
 * Context (from SearchController::index):
 *   $query      string
 *   $result     array{items,total,page,per_page,pages}
 *   $categories array<int, Category>
 */
$query      = $query      ?? '';
$result     = $result     ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 24, 'pages' => 1];
$categories = $categories ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$pageUrl = function (int $p) use ($query): string {
    return '/search?' . http_build_query(['q' => $query, 'page' => $p]);
};
?>
<div class="sf-search-page" data-sf-page="search">

    <div class="sf-container">

        <header class="sf-search-header">
            <h1 class="sf-search-title">
                <?php if ($total === 0): ?>
                    No results for "<?= e($query) ?>"
                <?php elseif ($total === 1): ?>
                    1 result for "<?= e($query) ?>"
                <?php else: ?>
                    <?= $total ?> results for "<?= e($query) ?>"
                <?php endif; ?>
            </h1>

            <form method="GET" action="/search" class="sf-search-refine">
                <input type="search"
                       name="q"
                       class="sf-search-input"
                       value="<?= e($query) ?>"
                       placeholder="Search products..."
                       required>
                <button type="submit" class="sf-btn sf-btn-primary">
                    Search
                </button>
            </form>
        </header>

        <?php if ($items === []): ?>

            <div class="sf-search-empty">
                <i class="bi bi-search sf-search-empty-icon" aria-hidden="true"></i>
                <h2 class="sf-search-empty-title">No products match "<?= e($query) ?>"</h2>
                <p class="sf-search-empty-body">
                    Try a different spelling, a more general keyword, or browse our categories.
                </p>

                <?php if ($categories !== []): ?>
                    <div class="sf-search-suggestions">
                        <p class="sf-search-suggestions-label">Popular categories</p>
                        <div class="sf-search-suggestions-grid">
                            <?php foreach (array_slice($categories, 0, 6) as $c): ?>
                                <a href="/categories/<?= e($c->slug) ?>" class="sf-search-chip">
                                    <?= e($c->name) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <a href="/products" class="sf-btn sf-btn-outline mt-3">
                    Browse all products
                </a>
            </div>

        <?php else: ?>

            <div class="sf-product-grid">
                <?php foreach ($items as $product): ?>
                    <?= view_partial('storefront.catalog._product-card', [
                        'product'  => $product,
                    ]) ?>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="sf-search-pagination" aria-label="Search results pagination">
                    <?php if ($page > 1): ?>
                        <a href="<?= e($pageUrl($page - 1)) ?>" class="sf-btn sf-btn-outline sf-btn-sm">
                            Previous
                        </a>
                    <?php endif; ?>
                    <span class="sf-search-page-indicator">
                        Page <?= $page ?> of <?= $pages ?>
                    </span>
                    <?php if ($page < $pages): ?>
                        <a href="<?= e($pageUrl($page + 1)) ?>" class="sf-btn sf-btn-outline sf-btn-sm">
                            Next
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

        <?php endif; ?>

    </div>

</div>
