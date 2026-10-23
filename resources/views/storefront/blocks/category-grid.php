<?php
/**
 * Home page "Shop by Category" block.
 *
 * Renders the same card layout as the category index page
 * (resources/views/storefront/category/index.php). Uses the
 * shared .sf-category-grid and .sf-category-card CSS classes,
 * so the two surfaces stay visually identical.
 *
 * @var array<string,mixed> $config
 * @var array<int, \CorporateAid\Commerce\Models\Category> $categories
 */
$title = $config['title'] ?? 'Shop by Category';
if (empty($categories)) {
    return;
}
?>
<section class="sf-section sf-category-grid-section" style="padding: 4rem 2rem; background: var(--sf-surface-1);">
    <div class="sf-container">
        <header class="sf-home-section-heading">
            <span class="sf-home-heading-line" aria-hidden="true"></span>
            <h2 class="sf-home-heading-text"><?= e($title) ?></h2>
            <span class="sf-home-heading-line" aria-hidden="true"></span>
        </header>
        <div class="sf-category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="/categories/<?= e(rawurlencode($cat->slug)) ?>"
                   class="sf-category-card"
                   data-sf-category-id="<?= (int) $cat->id ?>">

                    <div class="sf-category-card-image">
                        <?= category_picture_tag($cat, [
                            'variant' => 'md',
                            'sizes'   => '(max-width: 640px) 50vw, (max-width: 900px) 33vw, 400px',
                            'width'   => 500,
                            'height'  => 500,
                        ]) ?>
                    </div>

                    <div class="sf-category-card-footer">
                        <span class="sf-category-card-name"><?= e($cat->name) ?></span>
                        <span class="sf-category-card-arrow" aria-hidden="true">
                            <i class="bi bi-arrow-right"></i>
                        </span>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
