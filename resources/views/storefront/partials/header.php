<?php
/**
 * Storefront header.
 *
 * Appears at the top of every storefront page. Renders the brand,
 * primary navigation, search stub, account link, and cart badge.
 *
 * Context (from the layout):
 *   $store     - the resolved store, or null
 *   $brandName - the display name for the site
 *
 * Placeholders:
 *   - Search input is disabled. Full search ships in 008G.
 *   - Account link points at /account/login which 404s until
 *     008C ships. The link is present but inert.
 *   - Cart icon shows an empty badge. Cart functionality ships
 *     in 008D.
 */

$store     = $store     ?? null;
$brandName = $brandName ?? 'Our Store';

// For 008A the cart is always empty. 008D will replace this with
// a session-backed count read from CartService.
$cartCount = 0;
?>

<header class="sf-header">
    <div class="sf-container">
        <nav class="sf-nav">
            <a href="/" class="sf-brand">
                <span class="sf-brand-mark"></span>
                <span class="sf-brand-name"><?= e($brandName) ?></span>
            </a>

            <button type="button"
                    class="sf-nav-toggle"
                    aria-label="Toggle navigation"
                    aria-expanded="false"
                    data-sf-nav-toggle>
                <i class="bi bi-list"></i>
            </button>

            <div class="sf-nav-links" data-sf-nav-links>
                <a href="/" class="sf-nav-link">Home</a>
                <a href="/products" class="sf-nav-link">Products</a>
                <a href="/categories" class="sf-nav-link">Categories</a>
            </div>

            <div class="sf-nav-actions">
                <div class="sf-search">
                    <i class="bi bi-search sf-search-icon"></i>
                    <input type="search"
                           class="sf-search-input"
                           placeholder="Search products"
                           disabled
                           aria-label="Search products">
                </div>

                <a href="/account/login" class="sf-icon-btn" title="Account">
                    <i class="bi bi-person"></i>
                </a>

                <a href="/cart" class="sf-icon-btn sf-icon-btn-cart" title="Cart">
                    <i class="bi bi-bag"></i>
                    <span class="sf-cart-badge" data-sf-cart-count="<?= (int) $cartCount ?>">
                        <?= (int) $cartCount ?>
                    </span>
                </a>
            </div>
        </nav>
    </div>
</header>

<script>
(function () {
    'use strict';

    // Mobile nav toggle. Bootstrap's collapse is overkill for a
    // simple show/hide of the nav links.
    var toggle = document.querySelector('[data-sf-nav-toggle]');
    var links  = document.querySelector('[data-sf-nav-links]');

    if (toggle && links) {
        toggle.addEventListener('click', function () {
            var isOpen = links.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    // Hide cart badge when count is zero.
    var badge = document.querySelector('[data-sf-cart-count]');
    if (badge) {
        var n = parseInt(badge.getAttribute('data-sf-cart-count') || '0', 10);
        if (n <= 0) {
            badge.style.display = 'none';
        }
    }
})();
</script>
