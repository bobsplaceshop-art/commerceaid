<?php
/**
 * @var array<string,mixed> $config
 * @var array<int, \CorporateAid\Commerce\Models\Product> $products
 */
$title = (string) ($config['title'] ?? 'Products');

// Read the three grid fields. The $config['columns']
// fallback preserves blocks saved before this commit
// (columns was an int, now it is columns_desktop).
$columnsDesktop = (int) ($config['columns_desktop'] ?? $config['columns'] ?? 4);
$columnsMobile  = (int) ($config['columns_mobile']  ?? 2);
$gridAlignment  = (string) ($config['grid_alignment'] ?? 'justify');

$validColsDesktop = [3, 4, 5, 6, 7, 8];
if (!in_array($columnsDesktop, $validColsDesktop, true)) { $columnsDesktop = 4; }
$validColsMobile = [1, 2];
if (!in_array($columnsMobile, $validColsMobile, true)) { $columnsMobile = 2; }
$validGridAlign = ['left', 'center', 'right', 'justify'];
if (!in_array($gridAlignment, $validGridAlign, true)) { $gridAlignment = 'justify'; }

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
        <div class="sf-block-grid sf-block-grid-align-<?= e($gridAlignment) ?>"
             style="--sf-block-grid-cols: <?= (int) $columnsDesktop ?>; --sf-block-grid-cols-mobile: <?= (int) $columnsMobile ?>;">
            <?php if (empty($products)): ?>
                <p style="grid-column: 1 / -1; text-align: center; color: var(--sf-muted); padding: 2rem;">No products found for this configuration.</p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <?= view_partial('storefront/catalog/_product-card', ['product' => $product]) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
