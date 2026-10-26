<?php
/**
 * Newsletter block.
 *
 * Editorial dark band with three columns:
 *   - Left:   heading + subheadline.
 *   - Center: inline subscribe form (email + button).
 *   - Right:  "Follow Us" + social icons.
 *
 * The form posts to /newsletter/subscribe (the double opt-in
 * flow from 008I). The socials are store settings and land
 * here as placeholders until the store builder exposes them.
 *
 * @var array<string,mixed> $config
 */
$headline    = trim((string) ($config['headline']    ?? 'Join Our Newsletter'));
$subheadline = trim((string) ($config['subheadline'] ?? 'Get exclusive offers, new arrivals and style inspiration straight to your inbox.'));
$buttonText  = trim((string) ($config['button_text'] ?? 'Subscribe'));

$padMap = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '4rem', 'xl' => '6rem'];
$padTop = $padMap[$config['padding_top'] ?? 'lg'] ?? '4rem';
$padBottom = $padMap[$config['padding_bottom'] ?? 'lg'] ?? '4rem';

$headlineColor = (string) ($config['text_color'] ?? '');
$headlineColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $headlineColor) ? "color: {$headlineColor};" : '';

$subColor = (string) ($config['secondary_text_color'] ?? '');
$subColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $subColor) ? "color: {$subColor};" : '';
?>
<?php
$__newsBgStyle = \CorporateAid\Commerce\Support\Blocks\BlockBackground::style($config, '');
if ($__newsBgStyle === '') { $__newsBgStyle = 'background: #1a2b23;'; }
?>
<section class="sf-newsletter-band" style="padding: <?= e($padTop) ?> 0 <?= e($padBottom) ?>; <?= e($__newsBgStyle) ?> color: #f7f1e8;">
    <?= \CorporateAid\Commerce\Support\Blocks\BlockBackground::overlayStyle($config, 'sf-newsletter-band') ?>
    <div style="max-width: var(--sf-container-w, 1360px); margin: 0 auto; padding: 0 2rem; display: grid; grid-template-columns: 1fr 1.2fr 1fr; gap: 3rem; align-items: center;">

        <!-- Left: heading + subheadline -->
        <div>
            <h2 style="font-family: var(--sf-font-display); font-size: 2rem; line-height: 1.2; margin: 0 0 .75rem; font-weight: 500; <?= e($headlineColorStyle) ?>">
                <?= e($headline) ?>
            </h2>
            <p style="font-family: var(--sf-font-body); font-size: .88rem; line-height: 1.6; color: rgba(247,241,232,0.72); margin: 0; <?= e($subColorStyle) ?>">
                <?= e($subheadline) ?>
            </p>
        </div>

        <!-- Center: subscribe form -->
        <form method="POST" action="/newsletter/subscribe" style="display: flex; gap: .5rem; align-items: stretch;">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="footer">
            <input type="email" name="email" required placeholder="Enter your email address" style="flex: 1; padding: .85rem 1rem; background: transparent; border: 1px solid rgba(247,241,232,0.35); color: #f7f1e8; border-radius: 4px; font-family: var(--sf-font-body); font-size: .9rem;">
            <button type="submit" style="padding: 0 1.4rem; background: var(--sf-gold); color: #0f0f0f; border: 0; font-family: var(--sf-font-body); font-size: .72rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; cursor: pointer; border-radius: 4px;">
                <?= e($buttonText) ?>
            </button>
        </form>

        <!-- Right: Follow Us + socials -->
        <div style="display: flex; flex-direction: column; gap: .75rem; align-items: flex-end; text-align: right;">
            <span style="font-family: var(--sf-font-body); font-size: .72rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--sf-gold);">
                Follow Us
            </span>
            <div style="display: flex; gap: .5rem;">
                <a href="#" aria-label="Facebook"  style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1px solid rgba(247,241,232,0.3); color: #f7f1e8; text-decoration: none;">
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="#" aria-label="Instagram" style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1px solid rgba(247,241,232,0.3); color: #f7f1e8; text-decoration: none;">
                    <i class="bi bi-instagram"></i>
                </a>
                <a href="#" aria-label="TikTok"    style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1px solid rgba(247,241,232,0.3); color: #f7f1e8; text-decoration: none;">
                    <i class="bi bi-tiktok"></i>
                </a>
                <a href="#" aria-label="YouTube"   style="display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 50%; border: 1px solid rgba(247,241,232,0.3); color: #f7f1e8; text-decoration: none;">
                    <i class="bi bi-youtube"></i>
                </a>
            </div>
        </div>

    </div>
</section>

<style>
@media (max-width: 900px) {
    .sf-newsletter-band > div {
        grid-template-columns: 1fr !important;
        text-align: center;
    }
    .sf-newsletter-band > div > div:last-child {
        align-items: center !important;
        text-align: center !important;
    }
}
</style>
