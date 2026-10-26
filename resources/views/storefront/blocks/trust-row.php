<?php
/**
 * Trust bar block.
 *
 * Renders the trust items from the header menu's trust_bar
 * column (edited from Design > Header Builder). The block's
 * own config provides the visible heading, background color,
 * and vertical padding.
 *
 * @var array<string,mixed> $config
 */
$navRepo = \CorporateAid\Core\App::instance()->container()->get(
    \CorporateAid\Commerce\Repositories\NavigationRepository::class
);
$trustItems = $navRepo->getTrustBarItems('header');

if (empty($trustItems)) {
    return;
}

$heading = trim((string) ($config['heading'] ?? ''));

$bg = (string) ($config['background_color'] ?? '');
if ($bg === '' || !preg_match('/^#[0-9a-fA-F]{6}$/', $bg)) {
    $bg = 'var(--sf-cream)';
}

$padMap = ['none' => '0', 'sm' => '1rem', 'md' => '2rem', 'lg' => '4rem', 'xl' => '6rem'];
$padTop = $padMap[$config['padding_top'] ?? 'md'] ?? '2rem';
$padBottom = $padMap[$config['padding_bottom'] ?? 'md'] ?? '2rem';

$labelColor = (string) ($config['text_color'] ?? '');
$labelColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $labelColor) ? "color: {$labelColor};" : '';

$subColor = (string) ($config['secondary_text_color'] ?? '');
$subColorStyle = preg_match('/^#[0-9a-fA-F]{6}$/', $subColor) ? "color: {$subColor};" : '';
?>
<section class="sf-home-trust-row" data-sf-section="trust-row" style="padding: <?= e($padTop) ?> 0 <?= e($padBottom) ?>; background: <?= e($bg) ?>;">
    <div class="sf-container">
        <?php if ($heading !== ''): ?>
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <span style="font-family: var(--sf-font-body); font-size: .72rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase; color: var(--sf-gold-dark);">
                    <?= e($heading) ?>
                </span>
            </div>
        <?php endif; ?>
        <ul class="sf-home-trust-row-list">
            <?php foreach ($trustItems as $t): ?>
                <li class="sf-home-trust-row-item">
                    <i class="bi <?= e($t->icon ?? 'bi-star') ?>" aria-hidden="true"></i>
                    <div class="sf-home-trust-row-text">
                        <span class="sf-home-trust-row-title" style="<?= e($labelColorStyle) ?>"><?= e($t->label) ?></span>
                        <span class="sf-home-trust-row-sub" style="<?= e($subColorStyle) ?>"><?= e($t->subtitle ?? '') ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
