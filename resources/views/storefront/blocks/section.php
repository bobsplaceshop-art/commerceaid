<?php
/**
 * Section block — a full-width structural container.
 *
 * Renders a <section> with:
 *   - Vertical padding (padding_top / padding_bottom).
 *   - Optional background color (background_color).
 *   - An inner content wrapper constrained by max_width.
 *
 * Children render inside the inner wrapper so a full-bleed
 * background can span the viewport while content stays within
 * the 1200px (or 800px) column.
 *
 * @var array<string,mixed> $config
 * @var string $children_html  Rendered children (may be empty)
 */
$childrenHtml = $children_html ?? '';

$padMap = [
    'none' => '0',
    'sm'   => '1rem',
    'md'   => '2rem',
    'lg'   => '4rem',
    'xl'   => '6rem',
];
$maxMap = [
    'full'   => '100%',
    'wide'   => '1200px',
    'narrow' => '800px',
];

$padTop    = $padMap[$config['padding_top']    ?? 'md'] ?? '2rem';
$padBottom = $padMap[$config['padding_bottom'] ?? 'md'] ?? '2rem';
$maxWidth  = $maxMap[$config['max_width']      ?? 'wide'] ?? '1200px';
$bg        = (string) ($config['background_color'] ?? '');

$style = "padding-top: {$padTop}; padding-bottom: {$padBottom};";
if ($bg !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) {
    $style .= " background-color: {$bg};";
}
?>
<section class="sf-section-block" style="<?= e($style) ?>">
    <div class="sf-section-inner" style="max-width: <?= e($maxWidth) ?>; margin: 0 auto; padding-left: 1rem; padding-right: 1rem;">
        <?= $childrenHtml ?>
    </div>
</section>
