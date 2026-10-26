<?php
/**
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl = $bg_url ?? '';
$bgStyle = \CorporateAid\Commerce\Support\Blocks\BlockBackground::style($config, $bgUrl);
if ($bgStyle === '') {
    $bgStyle = $bgUrl !== '' ? "background-image: url('{$bgUrl}');" : "background-color: var(--sf-accent); color: white;";
}

$__typographyStyle = function (array $t): string {
    $s = '';
    $font = (string) ($t['font'] ?? 'inherit');
    if ($font !== '' && $font !== 'inherit') {
        $entry = \CorporateAid\Commerce\Support\Fonts\FontCatalog::find($font);
        if ($entry !== null) {
            $family   = addcslashes((string) $entry['family'], "'\\");
            $category = (string) ($entry['category'] ?? 'sans-serif');
            $generic  = in_array($category, ['serif', 'sans-serif', 'cursive'], true) ? $category : 'sans-serif';
            $s .= "font-family: '{$family}', {$generic}; ";
        }
    }
    $size = match ((string) ($t['size'] ?? 'inherit')) {
        'sm' => '1.5rem', 'md' => '2rem', 'lg' => '2.5rem', 'xl' => '3rem', '2xl' => '4rem',
        default => '',
    };
    if ($size !== '') $s .= "font-size: {$size}; ";
    $weight = match ((string) ($t['weight'] ?? 'inherit')) {
        'normal' => '400', 'medium' => '500', 'bold' => '700', default => '',
    };
    if ($weight !== '') $s .= "font-weight: {$weight}; ";
    $align = (string) ($t['alignment'] ?? 'inherit');
    if (in_array($align, ['left', 'center', 'right'], true)) {
        $s .= "text-align: {$align}; ";
    }
    return $s;
};

$headingT = is_array($config['heading_typography'] ?? null) ? $config['heading_typography'] : [];
$subheadingT = is_array($config['subheading_typography'] ?? null) ? $config['subheading_typography'] : [];
$typography = $__typographyStyle($headingT);
$subheadingTypography = $__typographyStyle($subheadingT);
?>
<section class="sf-banner" style="<?= e($bgStyle) ?> background-size: cover; background-position: center; padding: 5rem 2rem; text-align: center;">
    <?= \CorporateAid\Commerce\Support\Blocks\BlockBackground::overlayStyle($config, 'sf-banner') ?>
    <?php
$textColor = (string) ($config['text_color'] ?? '');
$textColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $textColor) ? "color: {$textColor};" : '';
?>
    <div style="max-width: 800px; margin: 0 auto; <?= e($textColorStyle) ?>">
        <h2 style="font-family: var(--sf-font-display); font-size: 2.5rem; margin-bottom: 1rem; <?= e($typography) ?>"><?= e($config['headline'] ?? '') ?></h2>
        <p style="font-size: 1.2rem; margin-bottom: 2rem; <?= e($subheadingTypography) ?>"><?= e($config['subheadline'] ?? '') ?></p>
        <?php if (!empty($config['cta_text'])): ?>
            <a href="<?= e($config['cta_url'] ?? '/products') ?>" class="sf-btn sf-btn-outline" style="border-color: currentColor; color: inherit;">
                <?= e($config['cta_text']) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
