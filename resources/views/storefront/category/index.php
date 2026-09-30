<?php
/**
 * Storefront category index at /categories.
 *
 * Context (from CategoryController::index):
 *   $categories  array<int, Category>  top-level categories only
 *   $totalCount  int                   total active categories (all levels)
 *
 * Store builder contract:
 *   Section carries data-sf-section="categories-index".
 *   Page carries data-sf-page="categories".
 *   Each card carries data-sf-category-id="{id}".
 *
 * Images:
 *   The category's uploaded image, via category_picture_url().
 *   Falls back to a local placeholder SVG when the category has
 *   no uploaded image. Never null.
 */

$categories = $categories ?? [];
$totalCount = $totalCount ?? count($categories);

?>

<div class="sf-container sf-categories-index" data-sf-page="categories">

    <nav class="sf-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
        <span class="sf-breadcrumb-current" aria-current="page">Categories</span>
    </nav>

    <header class="sf-home-section-heading">
        <span class="sf-home-heading-line" aria-hidden="true"></span>
        <h1 class="sf-home-heading-text">Shop by Category</h1>
        <span class="sf-home-heading-line" aria-hidden="true"></span>
    </header>

    <?php if (!empty($categories)): ?>
        <p class="sf-categories-count">
            <?= count($categories) ?> categor<?= count($categories) === 1 ? 'y' : 'ies' ?>
            <?php if ($totalCount > count($categories)): ?>
                <span class="muted">&middot; <?= (int) $totalCount ?> total including subcategories</span>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (empty($categories)): ?>
        <div class="sf-empty">
            <i class="bi bi-grid"></i>
            <p class="sf-empty-text">No categories available yet.</p>
            <a href="/products" class="sf-btn sf-btn-outline">Browse all products</a>
        </div>
    <?php else: ?>
        <div class="sf-category-grid">
            <?php foreach ($categories as $c): ?>
                <a href="/categories/<?= e(rawurlencode($c->slug)) ?>"
                   class="sf-category-card"
                   data-sf-category-id="<?= (int) $c->id ?>">

                    <div class="sf-category-card-image">
                        <img src="<?= e(category_picture_url($c, 'md')) ?>"
                             alt=""
                             loading="lazy"
                             width="500"
                             height="650">
                    </div>

                    <div class="sf-category-card-footer">
                        <span class="sf-category-card-name"><?= e($c->name) ?></span>
                        <span class="sf-category-card-arrow" aria-hidden="true">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
