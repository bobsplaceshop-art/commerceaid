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

$textColor = (string) ($config['text_color'] ?? '');
$textColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $textColor) ? "color: {$textColor};" : '';

$subColor = (string) ($config['secondary_text_color'] ?? '');
$subColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $subColor) ? "color: {$subColor};" : '';
?>
<?php
$padMap = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '4rem', 'xl' => '6rem'];
$padTop = $padMap[$config['padding_top'] ?? 'lg'] ?? '4rem';
$padBottom = $padMap[$config['padding_bottom'] ?? 'lg'] ?? '4rem';
?>
<section class="sf-section" style="padding: <?= e($padTop) ?> 2rem <?= e($padBottom) ?>;">
    <div class="sf-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-family: var(--sf-font-display); font-size: 2rem; <?= e($textColorStyle) ?>"><?= e($title) ?></h2>
            <?php if ($showViewAll): ?>
                <a href="/products" style="color: var(--sf-accent); font-weight: 600; <?= e($subColorStyle) ?>">View All &rarr;</a>
            <?php endif; ?>
        </div>
        <?php
        // The autofill grid needs a minimum card width, not a
        // fixed column count. Derive it from columns_desktop
        // against a 1440px reference viewport. A merchant who
        // sets 8 columns gets ~160px cards (smaller, more per
        // row). A merchant who sets 3 columns gets ~460px cards
        // (larger, fewer per row). The grid still caps at the
        // exact count when products are plentiful.
        $minWidth = max(160, (int) floor(1440 / max(1, $columnsDesktop)));
        ?>
        <div class="sf-block-grid sf-block-grid-align-<?= e($gridAlignment) ?>"
             style="--sf-block-grid-cols: <?= (int) $columnsDesktop ?>; --sf-block-grid-cols-mobile: <?= (int) $columnsMobile ?>; --sf-block-grid-min: <?= (int) $minWidth ?>px;">
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
