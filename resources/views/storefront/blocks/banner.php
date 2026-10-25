<?php
/**
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl = $bg_url ?? '';
$style = $bgUrl ? "background-image: url('{$bgUrl}');" : "background-color: var(--sf-accent); color: white;";

$textFontFamily = match ((string) ($config['text_font'] ?? 'inherit')) {
    'display' => 'var(--sf-font-display)',
    'body'    => 'var(--sf-font-body)',
    'script'  => 'var(--sf-font-script)',
    default   => '',
};
$textSize = match ((string) ($config['text_size'] ?? 'inherit')) {
    'sm'  => '1.5rem',
    'md'  => '2rem',
    'lg'  => '2.5rem',
    'xl'  => '3rem',
    '2xl' => '4rem',
    default => '',
};
$textWeight = match ((string) ($config['text_weight'] ?? 'inherit')) {
    'normal' => '400',
    'medium' => '500',
    'bold'   => '700',
    default  => '',
};
$textAlign = in_array((string) ($config['text_alignment'] ?? 'inherit'), ['left', 'center', 'right'], true)
    ? (string) $config['text_alignment']
    : '';

$typography = '';
if ($textFontFamily !== '') $typography .= "font-family: {$textFontFamily}; ";
if ($textSize !== '')      $typography .= "font-size: {$textSize}; ";
if ($textWeight !== '')    $typography .= "font-weight: {$textWeight}; ";
if ($textAlign !== '')     $typography .= "text-align: {$textAlign}; ";
?>
<section class="sf-banner" style="<?= e($style) ?> background-size: cover; background-position: center; padding: 5rem 2rem; text-align: center;">
    <div style="max-width: 800px; margin: 0 auto;">
        <h2 style="font-family: var(--sf-font-display); font-size: 2.5rem; margin-bottom: 1rem; <?= e($typography) ?>"><?= e($config['headline'] ?? '') ?></h2>
        <p style="font-size: 1.2rem; margin-bottom: 2rem;"><?= e($config['subheadline'] ?? '') ?></p>
        <?php if (!empty($config['cta_text'])): ?>
            <a href="<?= e($config['cta_url'] ?? '/products') ?>" class="sf-btn sf-btn-outline" style="border-color: currentColor; color: inherit;">
                <?= e($config['cta_text']) ?>
            </a>
        <?php endif; ?>
    </div>
</section>
