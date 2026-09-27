<?php
/**
 * Storefront footer.
 *
 * Structure:
 *   Top row:    brand + script slogan + social icons
 *   Middle row: four nav columns
 *   Bottom row: legal line
 *
 * Context (from the layout):
 *   $store     ?Store  the resolved store, or null
 *   $brandName string  display name
 *
 * The nav columns and social links are structured as arrays so the
 * store builder (later stage) can supply them from the database
 * without changing this view. Today they are hardcoded.
 *
 * Colors: dark charcoal background, cream text, champagne gold
 * accents. Matches the design brief.
 */

$store     = $store     ?? null;
$brandName = $brandName ?? 'Anaya Global';

$year = (int) date('Y');

// Footer navigation columns.
//
// Contract: eventually populated from `commerce_navigation_menu_items`
// where location = 'footer', ordered by column_id then sort_order.
// Today hardcoded.
$footerColumns = [
    [
        'heading' => 'Customer Care',
        'links'   => [
            ['label' => 'Contact Us',           'href' => '/pages/contact'],
            ['label' => 'Shipping & Delivery',  'href' => '/pages/shipping'],
            ['label' => 'Returns & Refunds',    'href' => '/pages/returns'],
            ['label' => 'FAQ',                  'href' => '/pages/faq'],
        ],
    ],
    [
        'heading' => 'Shopping',
        'links'   => [
            ['label' => "Women's Fashion",     'href' => '/categories/women'],
            ['label' => 'Bags & Accessories',  'href' => '/categories/bags-accessories'],
            ['label' => 'Children',            'href' => '/categories/children'],
            ['label' => 'Beauty & Lifestyle',  'href' => '/categories/beauty-lifestyle'],
            ['label' => 'Gifts',               'href' => '/categories/gifts'],
            ['label' => 'Home',                'href' => '/categories/home'],
            ['label' => 'Mobile',              'href' => '/categories/mobile'],
        ],
    ],
    [
        'heading' => 'Company',
        'links'   => [
            ['label' => 'About ANAYA GLOBAL',  'href' => '/pages/about'],
            ['label' => 'Privacy Policy',      'href' => '/pages/privacy'],
            ['label' => 'Terms & Conditions',  'href' => '/pages/terms'],
            ['label' => 'Cookie Policy',       'href' => '/pages/cookies'],
        ],
    ],
    [
        'heading' => 'Payment & Security',
        'links'   => [
            ['label' => 'Secure Checkout',     'href' => '/pages/security'],
            ['label' => 'Payment Methods',     'href' => '/pages/payment-methods'],
            ['label' => 'Shipping Information', 'href' => '/pages/shipping'],
        ],
    ],
];

// Social links. Stubs until the store builder provides real URLs.
$socialLinks = [
    ['icon' => 'bi-facebook',  'label' => 'Facebook',   'href' => '#'],
    ['icon' => 'bi-instagram', 'label' => 'Instagram',  'href' => '#'],
    ['icon' => 'bi-tiktok',    'label' => 'TikTok',     'href' => '#'],
    ['icon' => 'bi-pinterest', 'label' => 'Pinterest',  'href' => '#'],
    ['icon' => 'bi-youtube',   'label' => 'YouTube',    'href' => '#'],
    ['icon' => 'bi-whatsapp',  'label' => 'WhatsApp',   'href' => '#'],
];
?>
<footer class="sf-footer" role="contentinfo">

    <div class="sf-footer-top">
        <div class="sf-container sf-footer-top-inner">

            <div class="sf-footer-brand">
                <div class="sf-footer-map" aria-hidden="true">
                    <svg viewBox="0 0 120 60" xmlns="http://www.w3.org/2000/svg" focusable="false">
                        <!-- Stylized globe with latitude/longitude lines.
                             Decorative. Replaced by an uploaded map asset
                             when the store builder supports brand assets. -->
                        <circle cx="60" cy="30" r="24" fill="none" stroke="currentColor" stroke-width="0.8" opacity="0.7"/>
                        <ellipse cx="60" cy="30" rx="24" ry="9" fill="none" stroke="currentColor" stroke-width="0.6" opacity="0.5"/>
                        <ellipse cx="60" cy="30" rx="24" ry="18" fill="none" stroke="currentColor" stroke-width="0.6" opacity="0.5"/>
                        <ellipse cx="60" cy="30" rx="9" ry="24" fill="none" stroke="currentColor" stroke-width="0.6" opacity="0.5"/>
                        <line x1="36" y1="30" x2="84" y2="30" stroke="currentColor" stroke-width="0.6" opacity="0.5"/>
                    </svg>
                </div>
                <div class="sf-footer-brand-text">
                    <span class="sf-footer-brand-name"><?= e($brandName) ?></span>
                    <span class="sf-footer-brand-sub">ONLINE SHOP</span>
                </div>
            </div>

            <div class="sf-footer-slogan">
                <span class="sf-footer-slogan-line">Shop the World,</span>
                <span class="sf-footer-slogan-line">
                    Love Your Style
                    <span class="sf-footer-slogan-heart" aria-hidden="true">&hearts;</span>
                </span>
            </div>

            <div class="sf-footer-socials">
                <?php foreach ($socialLinks as $s): ?>
                    <a href="<?= e($s['href']) ?>"
                       class="sf-footer-social"
                       aria-label="<?= e($s['label']) ?>"
                       <?= $s['href'] === '#' ? 'rel="nofollow"' : '' ?>>
                        <i class="bi <?= e($s['icon']) ?>" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <div class="sf-footer-columns">
        <div class="sf-container sf-footer-columns-inner">
            <?php foreach ($footerColumns as $col): ?>
                <div class="sf-footer-column">
                    <h3 class="sf-footer-heading"><?= e($col['heading']) ?></h3>
                    <ul class="sf-footer-list">
                        <?php foreach ($col['links'] as $link): ?>
                            <li>
                                <a href="<?= e($link['href']) ?>" class="sf-footer-link">
                                    <?= e($link['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="sf-footer-bottom">
        <div class="sf-container sf-footer-bottom-inner">
            <span class="sf-footer-copy">
                &copy; <?= e((string) $year) ?> <?= e($brandName) ?>. All rights reserved.
            </span>
            <span class="sf-footer-meta">
                Powered by Anaya Global
            </span>
        </div>
    </div>

</footer>
