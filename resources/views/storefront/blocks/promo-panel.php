<?php
/**
 * Promo panel block.
 *
 * Editorial dark band with three columns:
 *   - Left:   eyebrow (script), title (display serif), CTA.
 *   - Center: image (cover).
 *   - Right:  "Style Without Borders" caption (display serif).
 *
 * All text comes from the block config. The image comes from
 * $bg_url (resolved from image_file_id by BlockRenderer).
 *
 * @var array<string,mixed> $config
 * @var string|null $bg_url
 */
$bgUrl   = $bg_url ?? '';
$eyebrow = trim((string) ($config['eyebrow'] ?? 'Why Choose Us'));
$title   = trim((string) ($config['title']   ?? 'More Than a Store, It\'s a Lifestyle'));
$ctaText = trim((string) ($config['cta_text'] ?? 'Explore All Products'));
$ctaUrl  = (string) ($config['cta_url'] ?? '/products');
$rightHeadline = trim((string) ($config['right_headline'] ?? 'Style Without Borders'));
$rightSubhead  = trim((string) ($config['right_subheadline'] ?? 'From Nairobi to the world.'));

$padMap = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '4rem', 'xl' => '6rem'];
$padTop = $padMap[$config['padding_top'] ?? 'lg'] ?? '4rem';
$padBottom = $padMap[$config['padding_bottom'] ?? 'lg'] ?? '4rem';

$titleColor = (string) ($config['text_color'] ?? '');
$titleColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $titleColor) ? "color: {$titleColor};" : '';

$subColor = (string) ($config['secondary_text_color'] ?? '');
$subColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $subColor) ? "color: {$subColor};" : '';
?>
<?php
$__promoBgStyle = \CorporateAid\Commerce\Support\Blocks\BlockBackground::style($config, '');
if ($__promoBgStyle === '') { $__promoBgStyle = 'background: #0f0f0f;'; }
?>
<section class="sf-promo-band" style="padding: <?= e($padTop) ?> 0 <?= e($padBottom) ?>; <?= e($__promoBgStyle) ?> color: #ffffff;">
    <?= \CorporateAid\Commerce\Support\Blocks\BlockBackground::overlayStyle($config, 'sf-promo-band') ?>
    <div style="max-width: var(--sf-container-w, 1360px); margin: 0 auto; padding: 0 2rem; display: grid; grid-template-columns: 1fr 1.4fr 1fr; gap: 3rem; align-items: center;">

        <!-- Left column: eyebrow, title, CTA -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <?php if ($eyebrow !== ''): ?>
                <span style="font-family: var(--sf-font-body); font-size: .72rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--sf-gold);">
                    <?= e($eyebrow) ?>
                </span>
            <?php endif; ?>
            <h2 style="font-family: var(--sf-font-display); font-size: 2.4rem; line-height: 1.15; margin: 0; font-weight: 500; <?= e($titleColorStyle) ?>">
                <?= e($title) ?>
            </h2>
            <a href="<?= e($ctaUrl) ?>" style="align-self: flex-start; display: inline-flex; align-items: center; gap: .5rem; padding: .85rem 1.4rem; background: var(--sf-gold); color: #0f0f0f; font-family: var(--sf-font-body); font-size: .72rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; text-decoration: none;">
                <?= e($ctaText) ?> &rarr;
            </a>
        </div>

        <!-- Center column: image -->
        <div style="aspect-ratio: 16 / 10; background-image: url('<?= e($bgUrl) ?>'); background-size: cover; background-position: center; border-radius: 4px; min-height: 320px;"></div>

        <!-- Right column: caption -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <h3 style="font-family: var(--sf-font-display); font-size: 2rem; line-height: 1.15; margin: 0; font-weight: 500; <?= e($titleColorStyle) ?>">
                <?= e($rightHeadline) ?>
            </h3>
            <p style="font-family: var(--sf-font-body); font-size: .9rem; line-height: 1.6; color: rgba(255,255,255,0.72); margin: 0; <?= e($subColorStyle) ?>">
                <?= e($rightSubhead) ?>
            </p>
        </div>

    </div>
</section>
