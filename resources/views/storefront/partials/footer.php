<?php
/**
 * Storefront footer.
 *
 * Structure:
 *   Top row:    brand + script slogan + social icons
 *   Middle row: four nav columns
 *   Bottom row: legal line + payment method badges
 *
 * Context (from the layout):
 *   $store     ?Store  the resolved store, or null
 *   $brandName string  display name
 *
 * Store builder contract:
 *   Every logical block carries a data-sf-section attribute so the
 *   future theme builder can identify, reorder, toggle, and
 *   reconfigure. Today the blocks render in fixed order; the theme
 *   builder will drive the order from data.
 *
 * Payment badges:
 *   Simplified SVG recreations of common card brands. These are
 *   illustrative, not official brand assets. Real payment method
 *   logos land with Stage 010 (payments) when the store knows
 *   which providers it accepts.
 */

$store     = $store     ?? null;
$brandName = $brandName ?? 'Anaya Global';

$year = (int) date('Y');

// Footer navigation columns.
//
// Contract: eventually populated from commerce_navigation_menu_items
// where location = 'footer'. Today hardcoded.
$footerColumns = [
    [
        'heading' => 'Quick Links',
        'links'   => [
            ['label' => 'Home',                'href' => '/'],
            ['label' => 'Women',               'href' => '/categories/women'],
            ['label' => 'Men',                 'href' => '/categories/men'],
            ['label' => 'Kids',                'href' => '/categories/children'],
            ['label' => 'Bags & Accessories',  'href' => '/categories/bags-accessories'],
            ['label' => 'Beauty & Lifestyle',  'href' => '/categories/beauty-lifestyle'],
            ['label' => 'Gifts',               'href' => '/categories/gifts'],
            ['label' => 'Sale',                'href' => '/products?filter=sale', 'highlight' => true],
        ],
    ],
    [
        'heading' => 'Customer Service',
        'links'   => [
            ['label' => 'Contact Us',           'href' => '/pages/contact'],
            ['label' => 'Shipping Policy',      'href' => '/pages/shipping'],
            ['label' => 'Return & Refunds',     'href' => '/pages/returns'],
            ['label' => 'FAQ',                  'href' => '/pages/faq'],
            ['label' => 'Track Your Order',     'href' => '/account/orders'],
            ['label' => 'Size Guide',           'href' => '/pages/size-guide'],
        ],
    ],
    [
        'heading' => 'Company',
        'links'   => [
            ['label' => 'About ANAYA GLOBAL',  'href' => '/pages/about'],
            ['label' => 'Impressum',           'href' => '/legal/impressum'],
            ['label' => 'Terms & Conditions',  'href' => '/legal/agb'],
            ['label' => 'Withdrawal Policy',   'href' => '/legal/widerruf'],
            ['label' => 'Privacy Policy',      'href' => '/legal/datenschutz'],
            ['label' => 'Cookie Policy',       'href' => '/pages/cookies'],
        ],
    ],
    [
        'heading' => 'Contact',
        'links'   => [
            ['label' => 'hello@anayaglobal.com',  'href' => 'mailto:hello@anayaglobal.com'],
            ['label' => '+254 700 000 000',       'href' => 'tel:+254700000000'],
            ['label' => 'Nairobi, Kenya',         'href' => '#'],
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

// Payment method badges. Simplified SVG recreations - not official
// brand assets. Real logos land with Stage 010 (payments).
$paymentMethods = [
    ['key' => 'visa',       'label' => 'Visa'],
    ['key' => 'mastercard', 'label' => 'Mastercard'],
    ['key' => 'paypal',     'label' => 'PayPal'],
    ['key' => 'verve',      'label' => 'Verve'],
    ['key' => 'secure',     'label' => 'Secure Payments'],
];
?>
<footer class="sf-footer" role="contentinfo">

    <!-- ============================================================= -->
    <!-- Top row: brand + slogan + socials                             -->
    <!-- ============================================================= -->
    <div class="sf-footer-top" data-sf-section="footer-brand">
        <div class="sf-container sf-footer-top-inner">

            <div class="sf-footer-brand">
                <div class="sf-footer-map" aria-hidden="true">
                    <svg viewBox="0 0 120 60" xmlns="http://www.w3.org/2000/svg" focusable="false">
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

            <div class="sf-footer-socials" data-sf-section="footer-socials">
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

    <!-- ============================================================= -->
    <!-- Middle row: nav columns + newsletter placeholder              -->
    <!-- ============================================================= -->
    <div class="sf-footer-columns" data-sf-section="footer-columns">
        <div class="sf-container sf-footer-columns-inner">

            <?php foreach ($footerColumns as $col): ?>
                <div class="sf-footer-column">
                    <h3 class="sf-footer-heading"><?= e($col['heading']) ?></h3>
                    <ul class="sf-footer-list">
                        <?php foreach ($col['links'] as $link): ?>
                            <li>
                                <a href="<?= e($link['href']) ?>"
                                   class="sf-footer-link<?= !empty($link['highlight']) ? ' is-highlight' : '' ?>">
                                    <?= e($link['label']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <!-- Newsletter placeholder column. The theme builder will
                 let the customer reposition, reconfigure, or remove
                 this block. Today it is a stub. -->
            <div class="sf-footer-column sf-footer-newsletter" data-sf-section="footer-newsletter">
                <h3 class="sf-footer-heading">Join Our Newsletter</h3>
                <p class="sf-footer-newsletter-copy">
                    Get exclusive offers, new arrivals and style
                    inspiration straight to your inbox.
                </p>
                <form class="sf-footer-newsletter-form" onsubmit="return false;">
                    <input type="email"
                           name="email"
                           class="sf-footer-newsletter-input"
                           placeholder="Enter your email address"
                           aria-label="Email address"
                           disabled>
                    <button type="button" class="sf-footer-newsletter-btn" disabled>
                        Subscribe
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- ============================================================= -->
    <!-- Bottom row: legal + payment badges                            -->
    <!-- ============================================================= -->
    <div class="sf-footer-bottom" data-sf-section="footer-legal">
        <div class="sf-container sf-footer-bottom-inner">

            <span class="sf-footer-copy">
                &copy; <?= e((string) $year) ?> <?= e($brandName) ?>. All rights reserved.
            </span>

            <div class="sf-footer-payments" data-sf-section="footer-payments" aria-label="Accepted payment methods">
                <?php foreach ($paymentMethods as $pm): ?>
                    <span class="sf-footer-payment sf-footer-payment-<?= e($pm['key']) ?>"
                          title="<?= e($pm['label']) ?>">
                        <?php if ($pm['key'] === 'secure'): ?>
                            <i class="bi bi-shield-lock-fill" aria-hidden="true"></i>
                            <span class="sf-footer-payment-label">Secure Payments</span>
                        <?php else: ?>
                            <span class="sf-footer-payment-badge">
                                <?php
                                // Simplified SVG card with brand text.
                                // Not the official logo. See file header.
                                $brand = e($pm['label']);
                                ?>
                                <svg viewBox="0 0 60 24" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">
                                    <rect x="0.5" y="0.5" width="59" height="23" rx="3"
                                          fill="#FFFFFF" stroke="currentColor" stroke-opacity="0.3"/>
                                    <text x="30" y="15.5" text-anchor="middle"
                                          font-family="Inter, system-ui, sans-serif"
                                          font-size="8" font-weight="700"
                                          fill="#111111"><?= $brand ?></text>
                                </svg>
                            </span>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

</footer>
