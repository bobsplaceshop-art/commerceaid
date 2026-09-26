<?php
/**
 * Storefront footer.
 *
 * Appears at the bottom of every storefront page. Renders a
 * simple multi-column footer with shop links, account links,
 * and a dynamic copyright line.
 *
 * Context (from the layout):
 *   $store     - the resolved store, or null
 *   $brandName - the display name for the site
 *
 * No JavaScript. No external dependencies. Same container width
 * as the header for visual alignment.
 */

$store     = $store     ?? null;
$brandName = $brandName ?? 'Our Store';

$year = date('Y');
?>

<footer class="sf-footer">
    <div class="sf-container">
        <div class="sf-footer-grid">

            <div class="sf-footer-col">
                <div class="sf-footer-brand"><?= e($brandName) ?></div>
                <p class="sf-footer-text">
                    Quality products, fair prices, fast delivery.
                </p>
            </div>

            <div class="sf-footer-col">
                <h3 class="sf-footer-heading">Shop</h3>
                <ul class="sf-footer-links">
                    <li><a href="/products" class="sf-footer-link">All products</a></li>
                    <li><a href="/categories" class="sf-footer-link">Categories</a></li>
                </ul>
            </div>

            <div class="sf-footer-col">
                <h3 class="sf-footer-heading">Account</h3>
                <ul class="sf-footer-links">
                    <li><a href="/account/login" class="sf-footer-link">Sign in</a></li>
                    <li><a href="/account/register" class="sf-footer-link">Create account</a></li>
                    <li><a href="/account/orders" class="sf-footer-link">My orders</a></li>
                </ul>
            </div>

            <div class="sf-footer-col">
                <h3 class="sf-footer-heading">Help</h3>
                <ul class="sf-footer-links">
                    <li><a href="/pages/contact" class="sf-footer-link">Contact</a></li>
                    <li><a href="/pages/shipping" class="sf-footer-link">Shipping</a></li>
                    <li><a href="/pages/returns" class="sf-footer-link">Returns</a></li>
                </ul>
            </div>

        </div>

        <div class="sf-footer-bottom">
            <span class="sf-footer-copy">
                &copy; <?= e((string) $year) ?> <?= e($brandName) ?>. All rights reserved.
            </span>
            <span class="sf-footer-meta">
                Powered by Anaya Global
            </span>
        </div>
    </div>
</footer>
