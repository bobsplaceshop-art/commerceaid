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

// Cart count. Resolved by the layout from CartService::count()
// once per request and passed to this partial. Falls back to 0
// if the layout doesn't provide it (defensive).
$cartCount = $cartCount ?? 0;

// Current customer, resolved once for both the bell and the
// account dropdown. Guests get null. Any resolution failure
// also yields null so the header still renders.
$__customer = null;
try {
    $__auth = \CorporateAid\Core\App::instance()
        ->container()
        ->get(\CorporateAid\Commerce\Services\CustomerAuthService::class);
    $__customer = $__auth->user();
} catch (\Throwable) {
    $__customer = null;
}

// Unread message count for the bell. Returns null for guests
// (bell is hidden), int for customers (bell shows only when
// count > 0).
$__unread = customer_unread_message_count();

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

        <?php
        // Uploaded logo takes precedence. When the merchant
        // uploads a logo in Store Settings, the entire lockup
        // (emblem + wordmark + subtitle) is replaced by the
        // image. That matches how Shopify, WooCommerce, and
        // BigCommerce handle merchant logos: the image is the
        // mark. If a text wordmark is wanted, it is designed
        // into the uploaded image.
        //
        // With no logo uploaded, the original inline SVG
        // monogram and the brand wordmark render as before.
        $__logoUrl = store_logo_url($store ?? null, 'lg', 'png');
        ?>
        <a href="/" class="sf-logo<?= $__logoUrl !== null ? ' sf-logo-custom' : '' ?>" aria-label="<?= e($brandName) ?> - Home">
            <?php if ($__logoUrl !== null): ?>
                <img src="<?= e($__logoUrl) ?>"
                     alt="<?= e($brandName) ?>"
                     class="sf-logo-image">
            <?php else: ?>
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
            <?php endif; ?>
        </a>

        <button type="button"
                class="sf-nav-toggle"
                aria-label="Toggle navigation"
                aria-expanded="false"
                data-sf-nav-toggle>
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="sf-header-actions">
            <form method="GET"
                  action="/search"
                  class="sf-header-search sf-hide-sm"
                  role="search">
                <input type="search"
                       name="q"
                       class="sf-header-search-input"
                       placeholder="Search products..."
                       aria-label="Search products"
                       required>
                <button type="submit"
                        class="sf-header-search-submit"
                        aria-label="Search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                </button>
            </form>

            <a href="/search"
               class="sf-icon-btn sf-show-sm"
               aria-label="Search">
                <i class="bi bi-search" aria-hidden="true"></i>
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

            <?php if ($__unread !== null && $__unread > 0): ?>
                <?php
                // Bell dropdown. Shows the most recent unread
                // messages as a quick snapshot. Renders only
                // when the customer has at least one unread
                // message - an empty bell has nothing to show.
                $__previewMessages = customer_recent_messages(5);
                ?>
                <div class="sf-bell-menu" data-sf-bell-menu>
                    <button type="button"
                            class="sf-icon-btn sf-icon-btn-bell sf-bell-menu-toggle"
                            aria-label="Notifications (<?= (int) $__unread ?> unread)"
                            aria-haspopup="true"
                            aria-expanded="false"
                            data-sf-bell-toggle>
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <span class="sf-cart-badge sf-bell-badge"><?= (int) $__unread ?></span>
                    </button>

                    <div class="sf-bell-menu-panel" role="menu" hidden>
                        <div class="sf-bell-menu-header">
                            <span>Notifications</span>
                            <a href="/account/messages" class="sf-bell-menu-see-all">See all</a>
                        </div>

                        <?php if ($__previewMessages === []): ?>
                            <div class="sf-bell-menu-empty">
                                You're all caught up.
                            </div>
                        <?php else: ?>
                            <ul class="sf-bell-menu-list" role="none">
                                <?php foreach ($__previewMessages as $msg): ?>
                                    <li role="none">
                                        <a href="<?= e($msg['link'] ?? '/account/messages') ?>"
                                           class="sf-bell-menu-item"
                                           role="menuitem">
                                            <span class="sf-bell-menu-icon" aria-hidden="true">
                                                <i class="bi <?= e($msg['icon']) ?>"></i>
                                            </span>
                                            <span class="sf-bell-menu-body">
                                                <span class="sf-bell-menu-title"><?= e($msg['title']) ?></span>
                                                <span class="sf-bell-menu-time"><?= e($msg['time']) ?></span>
                                            </span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <div class="sf-bell-menu-footer">
                            <a href="/account/messages" class="sf-bell-menu-footer-link">
                                View all messages
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($__customer === null): ?>
                <a href="/account/login" class="sf-icon-btn sf-icon-btn-labeled" aria-label="Sign in">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    <span class="sf-icon-btn-label">Sign in</span>
                </a>
            <?php else: ?>
                <?php
                $__displayName = $__customer->fullName();
                if ($__displayName === '') {
                    $__displayName = $__customer->email;
                }

                $__accountItems = [
                    ['label' => 'Dashboard',       'href' => '/account',          'icon' => 'bi-house'],
                    ['label' => 'Orders',          'href' => '/account/orders',   'icon' => 'bi-receipt'],
                    ['label' => 'Messages',        'href' => '/account/messages', 'icon' => 'bi-envelope', 'badge' => $__unread],
                    ['label' => 'Wishlist',        'href' => '/account/wishlist', 'icon' => 'bi-heart'],
                    ['label' => 'Recently Viewed', 'href' => '/account/recent',   'icon' => 'bi-clock-history'],
                    ['label' => 'Addresses',       'href' => '/account/addresses','icon' => 'bi-geo-alt'],
                ];
                ?>
                <div class="sf-account-menu" data-sf-account-menu>
                    <button type="button"
                            class="sf-icon-btn sf-account-menu-toggle"
                            aria-label="Account menu"
                            aria-haspopup="true"
                            aria-expanded="false"
                            data-sf-account-toggle>
                        <i class="bi bi-person" aria-hidden="true"></i>
                    </button>

                    <div class="sf-account-menu-panel" role="menu" hidden>
                        <div class="sf-account-menu-header">
                            <div class="sf-account-menu-name"><?= e($__displayName) ?></div>
                            <div class="sf-account-menu-email muted"><?= e($__customer->email) ?></div>
                        </div>

                        <ul class="sf-account-menu-list" role="none">
                            <?php foreach ($__accountItems as $item): ?>
                                <?php $badge = (int) ($item['badge'] ?? 0); ?>
                                <li role="none">
                                    <a href="<?= e($item['href']) ?>"
                                       class="sf-account-menu-link"
                                       role="menuitem">
                                        <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                                        <span><?= e($item['label']) ?></span>
                                        <?php if ($badge > 0): ?>
                                            <span class="sf-account-menu-badge"><?= $badge ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="sf-account-menu-divider" role="separator"></div>

                        <form method="POST" action="/account/logout" style="margin:0;">
                            <?= csrf_field() ?>
                            <button type="submit" class="sf-account-menu-link sf-account-menu-logout" role="menuitem">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                <span>Sign out</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <nav class="sf-nav-row" aria-label="Primary" data-sf-nav>
        <div class="sf-container">
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
        </div>
    </nav>
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

    // Account dropdown. Same pattern as the admin topbar: a
    // click toggles the panel, click-outside and Escape close it.
    var accountMenu = document.querySelector('[data-sf-account-menu]');
    if (accountMenu) {
        var toggle = accountMenu.querySelector('[data-sf-account-toggle]');
        var panel  = accountMenu.querySelector('.sf-account-menu-panel');

        function openPanel() {
            if (!panel || !toggle) return;
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            // Small delay so the panel can transition in.
            requestAnimationFrame(function () {
                accountMenu.classList.add('is-open');
            });
        }

        function closePanel() {
            if (!panel || !toggle) return;
            accountMenu.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
            // Wait for transition before hiding (or hide immediately
            // if there is no transition).
            setTimeout(function () {
                panel.hidden = true;
            }, 120);
        }

        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (accountMenu.classList.contains('is-open')) {
                    closePanel();
                } else {
                    openPanel();
                }
            });
        }

        // Click outside closes
        document.addEventListener('click', function (e) {
            if (!accountMenu.classList.contains('is-open')) return;
            if (e.target.closest('[data-sf-account-menu]')) return;
            closePanel();
        });

        // Escape closes
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (!accountMenu.classList.contains('is-open')) return;
            closePanel();
        });
    }

    // Bell dropdown. Same open/close pattern as the account
    // menu. Only one dropdown open at a time - opening the
    // bell closes the account menu and vice versa.
    var bellMenu = document.querySelector('[data-sf-bell-menu]');
    if (bellMenu) {
        var bellToggle = bellMenu.querySelector('[data-sf-bell-toggle]');
        var bellPanel  = bellMenu.querySelector('.sf-bell-menu-panel');

        function bellOpen() {
            if (!bellPanel || !bellToggle) return;

            // Close the account menu if it is open.
            if (accountMenu && accountMenu.classList.contains('is-open')) {
                var accPanel  = accountMenu.querySelector('.sf-account-menu-panel');
                var accToggle = accountMenu.querySelector('[data-sf-account-toggle]');
                accountMenu.classList.remove('is-open');
                if (accToggle) accToggle.setAttribute('aria-expanded', 'false');
                if (accPanel) setTimeout(function () { accPanel.hidden = true; }, 120);
            }

            bellPanel.hidden = false;
            bellToggle.setAttribute('aria-expanded', 'true');
            requestAnimationFrame(function () {
                bellMenu.classList.add('is-open');
            });
        }

        function bellClose() {
            if (!bellPanel || !bellToggle) return;
            bellMenu.classList.remove('is-open');
            bellToggle.setAttribute('aria-expanded', 'false');
            setTimeout(function () {
                bellPanel.hidden = true;
            }, 120);
        }

        if (bellToggle) {
            bellToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (bellMenu.classList.contains('is-open')) {
                    bellClose();
                } else {
                    bellOpen();
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!bellMenu.classList.contains('is-open')) return;
            if (e.target.closest('[data-sf-bell-menu]')) return;
            bellClose();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (!bellMenu.classList.contains('is-open')) return;
            bellClose();
        });
    }
})();
</script>
