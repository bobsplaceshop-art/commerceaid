<?php
/**
 * Storefront header.
 *
 * Structure:
 *   Left   - logo (emblem + wordmark + subtitle with gold lines)
 *   Center - horizontal navigation
 *   Right  - search, account, cart with badge
 *
 * The navigation items are structured as a $navItems array so the
 * store builder (later stage) can supply them from the database
 * without changing this view. For now they are hardcoded.
 *
 * Context (from the layout):
 *   $store     ?Store  the resolved store, or null
 *   $brandName string  display name
 *
 * Not yet functional:
 *   - Cart count is 0 until Stage 008D
 *   - Account link points at /account/login which 404s until 008C
 *   - Search input is a stub
 *   - Language selector is in the utility bar (not here)
 */

$store     = $store     ?? null;
$brandName = $brandName ?? 'Anaya Global';

// Cart count: hardcoded until 008D wires real cart state.
$cartCount = 0;

// Navigation items.
//
// Contract: eventually populated from `commerce_navigation_menu_items`
// via the store builder admin. Today hardcoded. The view treats the
// array as opaque - it just renders whatever it is given.
//
// Note: the design brief listed "Home" twice. The second occurrence
// is replaced with "New In" as a reasonable substitute. Adjust when
// the customer confirms.
$navItems = [
    ['label' => 'Home',               'href' => '/'],
    ['label' => 'Women',              'href' => '/categories/women'],
    ['label' => 'Bags & Accessories', 'href' => '/categories/bags-accessories'],
    ['label' => 'Children',           'href' => '/categories/children'],
    ['label' => 'Beauty & Lifestyle', 'href' => '/categories/beauty-lifestyle'],
    ['label' => 'Gifts',              'href' => '/categories/gifts'],
    ['label' => 'Mobile',             'href' => '/categories/mobile'],
    ['label' => 'New In',             'href' => '/products?sort=newest'],
    ['label' => 'Sale',               'href' => '/products?filter=sale', 'highlight' => true],
];

// Active-state detection: current path prefix-matches an item href.
// Dashboard-style exact-match rule from the sidebar does not apply
// here because nav items are all top-level or category paths.
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isNavActive = function (string $href) use ($currentPath): bool {
    $href = rtrim(explode('?', $href)[0], '/');
    $cur  = rtrim($currentPath, '/');
    if ($href === '') {
        return $cur === '';
    }
    return $cur === $href || str_starts_with($cur, $href . '/');
};
?>
<header class="sf-header" role="banner">
    <div class="sf-container sf-header-inner">

        <a href="/" class="sf-logo" aria-label="<?= e($brandName) ?> - Home">
            <span class="sf-logo-emblem" aria-hidden="true">
                <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" focusable="false">
                    <!-- Stylized A + globe: a monogram-like mark with a
                         circular outline and two cross strokes. Pure
                         SVG so it scales cleanly and can be replaced
                         via the store builder later. -->
                    <circle cx="24" cy="24" r="22" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M10 36 L24 10 L38 36" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M10 36 Q24 28 38 36" fill="none" stroke="currentColor" stroke-width="1" opacity="0.6"/>
                    <ellipse cx="24" cy="24" rx="22" ry="8" fill="none" stroke="currentColor" stroke-width="1" opacity="0.6"/>
                </svg>
            </span>
            <span class="sf-logo-text">
                <span class="sf-logo-name"><?= e($brandName) ?></span>
                <span class="sf-logo-sub">ONLINE SHOP</span>
            </span>
        </a>

        <button type="button"
                class="sf-nav-toggle"
                aria-label="Toggle navigation"
                aria-expanded="false"
                data-sf-nav-toggle>
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <nav class="sf-nav" aria-label="Primary" data-sf-nav>
            <ul class="sf-nav-list">
                <?php foreach ($navItems as $item): ?>
                    <?php $active = $isNavActive($item['href']); ?>
                    <li class="sf-nav-item">
                        <a href="<?= e($item['href']) ?>"
                           class="sf-nav-link<?= $active ? ' is-active' : '' ?><?= !empty($item['highlight']) ? ' is-highlight' : '' ?>">
                            <?= e($item['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="sf-header-actions">
            <button type="button"
                    class="sf-icon-btn"
                    aria-label="Search"
                    data-sf-search-toggle>
                <i class="bi bi-search" aria-hidden="true"></i>
            </button>

            <a href="/account/login" class="sf-icon-btn" aria-label="Account">
                <i class="bi bi-person" aria-hidden="true"></i>
            </a>

            <a href="/cart" class="sf-icon-btn sf-icon-btn-cart" aria-label="Cart">
                <i class="bi bi-bag" aria-hidden="true"></i>
                <?php if ($cartCount > 0): ?>
                    <span class="sf-cart-badge" data-sf-cart-count="<?= (int) $cartCount ?>">
                        <?= (int) $cartCount ?>
                    </span>
                <?php else: ?>
                    <span class="sf-cart-badge is-empty" data-sf-cart-count="0" hidden>0</span>
                <?php endif; ?>
            </a>
        </div>

    </div>
</header>

<script>
(function () {
    'use strict';

    // Mobile navigation toggle. Simple show/hide of the nav list.
    var toggle = document.querySelector('[data-sf-nav-toggle]');
    var nav    = document.querySelector('[data-sf-nav]');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    // Hide cart badge when empty (defensive - backend already omits
    // it when count is 0, but if a template ever renders the empty
    // state we make sure it stays hidden).
    var badge = document.querySelector('[data-sf-cart-count]');
    if (badge) {
        var n = parseInt(badge.getAttribute('data-sf-cart-count') || '0', 10);
        if (n <= 0) {
            badge.hidden = true;
        }
    }
})();
</script>
