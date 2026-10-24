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
$title = (string) ($config['title'] ?? 'Shop by Category');
if (empty($categories)) {
    return;
}

$columnsDesktop = (int) ($config['columns_desktop'] ?? 5);
$columnsMobile  = (int) ($config['columns_mobile']  ?? 2);
$alignment      = (string) ($config['alignment']    ?? 'center');
$gridAlignment  = (string) ($config['grid_alignment'] ?? 'justify');
$paddingTop     = (string) ($config['padding_top']  ?? 'md');
$paddingBottom  = (string) ($config['padding_bottom'] ?? 'md');
$divider        = !empty($config['divider']);
$spacerAbove    = (string) ($config['spacer_above'] ?? 'none');

// Whitelist the string values so the class attribute cannot
// be polluted by a stale value in the database.
$validAlign = ['left', 'center', 'right'];
if (!in_array($alignment, $validAlign, true)) { $alignment = 'center'; }
$validGridAlign = ['left', 'center', 'right', 'justify'];
if (!in_array($gridAlignment, $validGridAlign, true)) { $gridAlignment = 'justify'; }
$validPad = ['none', 'sm', 'md', 'lg', 'xl'];
if (!in_array($paddingTop, $validPad, true)) { $paddingTop = 'md'; }
if (!in_array($paddingBottom, $validPad, true)) { $paddingBottom = 'md'; }
$validSpacer = ['none', 'sm', 'md', 'lg'];
if (!in_array($spacerAbove, $validSpacer, true)) { $spacerAbove = 'none'; }
if (!in_array($columnsDesktop, [3, 4, 5, 6, 7, 8], true)) { $columnsDesktop = 5; }
if (!in_array($columnsMobile, [1, 2], true)) { $columnsMobile = 2; }

$sectionClasses = [
    'sf-section',
    'sf-category-grid-section',
    'sf-cat-align-' . $alignment,
    'sf-cat-pt-' . $paddingTop,
    'sf-cat-pb-' . $paddingBottom,
];
if ($spacerAbove !== 'none') {
    $sectionClasses[] = 'sf-cat-spacer-' . $spacerAbove;
}
if ($divider) {
    $sectionClasses[] = 'sf-cat-has-divider';
}
?>
<section class="<?= e(implode(' ', $sectionClasses)) ?>">
    <div class="sf-container">
        <header class="sf-home-section-heading">
            <span class="sf-home-heading-line" aria-hidden="true"></span>
            <h2 class="sf-home-heading-text"><?= e($title) ?></h2>
            <span class="sf-home-heading-line" aria-hidden="true"></span>
        </header>
        <div class="sf-category-grid sf-cat-grid-align-<?= e($gridAlignment) ?>"
             style="--sf-cat-cols: <?= (int) $columnsDesktop ?>; --sf-cat-cols-mobile: <?= (int) $columnsMobile ?>;">
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
