<?php
/**
 * @var array<string,mixed> $config
 * @var array<int, \CorporateAid\Commerce\Models\Product> $products
 */
$title = $config['title'] ?? 'Products';
$columns = max(1, (int) ($config['columns'] ?? 4)); // Inherit from block config, min 1
$showViewAll = !empty($config['show_view_all']);
?>
<section class="sf-section" style="padding: 4rem 2rem;">
    <div class="sf-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-family: var(--sf-font-display); font-size: 2rem;"><?= e($title) ?></h2>
            <?php if ($showViewAll): ?>
                <a href="/products" style="color: var(--sf-accent); font-weight: 600;">View All &rarr;</a>
            <?php endif; ?>
        </div>
        <div class="sf-block-grid" style="--sf-block-grid-cols: <?= (int) $columns ?>;">
            <?php if (empty($products)): ?>
                <p style="grid-column: span <?= $columns ?>; text-align: center; color: var(--sf-muted); padding: 2rem;">No products found for this configuration.</p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?= view_partial('storefront/catalog/_product-card', ['product' => $product]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
