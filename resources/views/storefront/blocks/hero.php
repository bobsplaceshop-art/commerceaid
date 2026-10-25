<?php
/**
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl = $bg_url ?? '';
$style = $bgUrl ? "background-image: url('{$bgUrl}');" : "background-color: var(--sf-surface-2);";

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
<section class="sf-hero" style="<?= e($style) ?> background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; text-align: center;">
    <div class="sf-hero-content" style="max-width: 800px; background: rgba(255,255,255,0.85); padding: 3rem; border-radius: 8px;">
        <h1 style="font-family: var(--sf-font-display); font-size: 3rem; margin-bottom: 1rem; <?= e($typography) ?>"><?= e($config['headline'] ?? '') ?></h1>
        <?php if (!empty($config['subheadline'])): ?>
            <p style="font-size: 1.2rem; margin-bottom: 2rem; <?= e($subheadingTypography) ?>"><?= e($config['subheadline']) ?></p>
        <?php endif; ?>
        <?php if (!empty($config['cta_text'])): ?>
            <a href="<?= e($config['cta_url'] ?? '/products') ?>" class="sf-btn sf-btn-primary" style="padding: 1rem 2rem; font-size: 1.1rem;">
                <?= e($config['cta_text']) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
