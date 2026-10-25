<?php
/**
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl = $bg_url ?? '';
$eyebrow = trim((string) ($config['eyebrow'] ?? ''));
$heightMap = ['sm' => '300px', 'md' => '400px', 'lg' => '500px', 'xl' => '600px'];
$heightKey = (string) ($config['height'] ?? 'md');
$minHeight = $heightMap[$heightKey] ?? '400px';

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
$typography = $__typographyStyle($headingT);
?>
<section class="sf-promo" style="padding: 4rem 2rem;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; background: var(--sf-surface-2); border-radius: 8px; overflow: hidden; min-height: <?= e($minHeight) ?>;">
        <div style="flex: 1; padding: 4rem; display: flex; flex-direction: column; justify-content: center;">
            <?php if ($eyebrow !== ''): ?>
                <span style="font-family: var(--sf-font-script); font-size: 1.5rem; color: var(--sf-accent); margin-bottom: .5rem;">
                    <?= e($eyebrow) ?>
                </span>
            <?php endif; ?>
            <h2 style="font-family: var(--sf-font-display); font-size: 2.5rem; margin-bottom: 2rem; <?= e($typography) ?>"><?= e($config['title'] ?? '') ?></h2>
            <a href="<?= e($config['cta_url'] ?? '#') ?>" class="sf-btn sf-btn-primary" style="align-self: flex-start;"><?= e($config['cta_text'] ?? 'Shop Now') ?></a>
        </div>
        <div style="flex: 1; background-image: url('<?= e($bgUrl) ?>'); background-size: cover; background-position: center;"></div>
    </div>
</section>
