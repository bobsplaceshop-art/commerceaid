<?php
/**
 * @var array<string,mixed> $config
 */
$widthMap = ['full' => '100%', 'wide' => '800px', 'narrow' => '600px'];
$widthKey = (string) ($config['max_width'] ?? 'wide');
$maxWidth = $widthMap[$widthKey] ?? '800px';

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
<section class="sf-section" style="padding: 4rem 2rem;">
    <div style="max-width: <?= e($maxWidth) ?>; margin: 0 auto; line-height: 1.8; <?= e($typography) ?>">
        <?= $config['content'] ?? '' ?>
    </div>
</section>
