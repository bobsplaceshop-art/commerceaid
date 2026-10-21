<?php
/**
 * @var array<string,mixed> $config
 * @var array<int, \CorporateAid\Commerce\Models\Product> $products
 */
$title = $config['title'] ?? 'Products';
$columns = (int) ($config['columns'] ?? 4);
$showViewAll = !empty($config['show_view_all']);
?>
<section class="sf-section" style="padding: 4rem 2rem;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-family: var(--sf-font-display); font-size: 2rem;"><?= e($title) ?></h2>
            <?php if ($showViewAll): ?>
                <a href="/products" style="color: var(--sf-accent); font-weight: 600;">View All &rarr;</a>
            <?php endif; ?>
        </div>
        <div style="display: grid; grid-template-columns: repeat(<?= $columns ?>, 1fr); gap: 1.5rem;">
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
