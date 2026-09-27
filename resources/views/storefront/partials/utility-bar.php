<?php
/**
 * Storefront utility bar.
 *
 * Thin black bar at the very top of every storefront page. Shows
 * four trust badges and two non-functional selectors (language,
 * currency). The selectors are display-only stubs until
 * multi-currency and multi-language land in a later stage.
 *
 * Context:
 *   $store  ?Store  the current store, or null
 *
 * Layout:
 *   Left/center: trust badges with vertical dividers
 *   Right:       language + currency selectors
 *
 * Mobile:
 *   The trust badges hide below 900px. The selectors stay visible.
 *   Pure CSS media query, no JS required.
 */

$store = $store ?? null;

$badges = [
    ['icon' => 'bi-globe2',        'label' => 'Worldwide Shipping'],
    ['icon' => 'bi-truck',         'label' => 'Fast & Safe Delivery'],
    ['icon' => 'bi-shield-check',  'label' => 'Secure Payment'],
    ['icon' => 'bi-headset',       'label' => '24/7 Customer Support'],
];
?>
<div class="sf-utility-bar" role="region" aria-label="Site information">
    <div class="sf-container sf-utility-inner">

        <ul class="sf-utility-badges" aria-label="Trust and service information">
            <?php foreach ($badges as $i => $badge): ?>
                <?php if ($i > 0): ?>
                    <li class="sf-utility-divider" aria-hidden="true"></li>
                <?php endif; ?>
                <li class="sf-utility-badge">
                    <i class="bi <?= e($badge['icon']) ?>" aria-hidden="true"></i>
                    <span><?= e($badge['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="sf-utility-selectors">
            <span class="sf-utility-selector" title="Language selector coming soon">
                <i class="bi bi-globe" aria-hidden="true"></i>
                <span>English</span>
                <i class="bi bi-chevron-down sf-utility-chevron" aria-hidden="true"></i>
            </span>

            <span class="sf-utility-selector" title="Currency selector coming soon">
                <span>EUR &euro;</span>
                <i class="bi bi-chevron-down sf-utility-chevron" aria-hidden="true"></i>
            </span>
        </div>

    </div>
</div>
