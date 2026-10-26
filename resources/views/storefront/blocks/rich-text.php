<?php
/**
 * @var array<string,mixed> $config
 */
$widthMap = ['full' => '100%', 'wide' => '800px', 'narrow' => '600px'];
$widthKey = (string) ($config['max_width'] ?? 'wide');
$maxWidth = $widthMap[$widthKey] ?? '800px';

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

$bodyT = is_array($config['body_typography'] ?? null) ? $config['body_typography'] : [];
$typography = $__typographyStyle($bodyT);
?>
<section class="sf-section" style="padding: 4rem 2rem;">
<?php
$textColor = (string) ($config['text_color'] ?? '');
$textColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $textColor) ? "color: {$textColor};" : '';
?>
    <div style="max-width: <?= e($maxWidth) ?>; margin: 0 auto; line-height: 1.8; <?= e($typography) ?> <?= e($textColorStyle) ?>">
        <?= $config['content'] ?? '' ?>
    </div>
</section>
