<?php
/**
 * Container block — a nested structural wrapper.
 *
 * Renders a <div> with:
 *   - Horizontal padding (padding_x).
 *   - Content width (max_width).
 *   - Gap between children (gap), using flex column layout.
 *
 * @var array<string,mixed> $config
 * @var string $children_html  Rendered children (may be empty)
 */
$childrenHtml = $children_html ?? '';

$padMap = [
    'none' => '0',
    'sm'   => '1rem',
    'md'   => '2rem',
    'lg'   => '3rem',
];
$maxMap = [
    'full'   => '100%',
    'wide'   => '1200px',
    'narrow' => '800px',
];
$gapMap = [
    'none' => '0',
    'sm'   => '.5rem',
    'md'   => '1rem',
    'lg'   => '1.5rem',
];

$padX     = $padMap[$config['padding_x'] ?? 'md'] ?? '2rem';
$maxWidth = $maxMap[$config['max_width'] ?? 'wide'] ?? '1200px';
$gap      = $gapMap[$config['gap'] ?? 'md'] ?? '1rem';

$alignMap = [
    'stretch' => 'stretch',
    'left'    => 'flex-start',
    'center'  => 'center',
    'right'   => 'flex-end',
];
$justifyMap = [
    'start'         => 'flex-start',
    'center'        => 'center',
    'end'           => 'flex-end',
    'space-between' => 'space-between',
    'space-around'  => 'space-around',
    'space-evenly'  => 'space-evenly',
];
$alignItems     = $alignMap[$config['align_items']       ?? 'stretch'] ?? 'stretch';
$justifyContent = $justifyMap[$config['justify_content'] ?? 'start']   ?? 'flex-start';

$direction = (string) ($config['layout_direction'] ?? 'column');
$flexDirection = $direction === 'row' ? 'row' : 'column';
$flexWrap      = $direction === 'wrap' ? 'wrap' : 'nowrap';

$style = "padding-left: {$padX}; padding-right: {$padX};"
    . " max-width: {$maxWidth}; margin: 0 auto;"
    . " display: flex; flex-direction: {$flexDirection}; flex-wrap: {$flexWrap};"
    . " gap: {$gap};"
    . " align-items: {$alignItems};"
    . " justify-content: {$justifyContent};";
?>
<div class="sf-container-block" style="<?= e($style) ?>">
    <?= $childrenHtml ?>
</div>
