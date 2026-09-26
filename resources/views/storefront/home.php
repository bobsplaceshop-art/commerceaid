<?php
/**
 * Storefront home page content.
 *
 * Rendered inside storefront/layouts/main.php.
 *
 * Context:
 *   $store  - the resolved store, or null
 *   $title  - page title
 *
 * This is a placeholder for 008A. Once 008B ships, the featured
 * products section is populated from real catalog data. For now
 * it renders an empty state that confirms the shell works.
 */

$store = $store ?? null;
$title = $title ?? 'Home';

$storeName = ($store !== null && !empty($store->name))
    ? (string) $store->name
    : 'Our Store';
?>

<section class="sf-hero">
    <div class="sf-container">
        <h1 class="sf-hero-title"><?= e($storeName) ?></h1>
        <p class="sf-hero-subtitle">
            Quality products, fair prices, fast delivery.
        </p>
        <div class="sf-hero-actions">
            <a href="/products" class="sf-btn sf-btn-primary">
                Shop all products
            </a>
            <a href="/categories" class="sf-btn sf-btn-outline">
                Browse categories
            </a>
        </div>
    </div>
</section>

<section class="sf-section">
    <div class="sf-container">
        <div class="sf-section-header">
            <h2 class="sf-section-title">Featured products</h2>
            <a href="/products" class="sf-section-link">View all</a>
        </div>

        <div class="sf-empty">
            <i class="bi bi-box-seam"></i>
            <p class="sf-empty-text">
                Featured products will appear here once the storefront
                catalog is live.
            </p>
        </div>
    </div>
</section>

<section class="sf-section">
    <div class="sf-container">
        <div class="sf-section-header">
            <h2 class="sf-section-title">Shop by category</h2>
            <a href="/categories" class="sf-section-link">View all</a>
        </div>

        <div class="sf-empty">
            <i class="bi bi-grid"></i>
            <p class="sf-empty-text">
                Categories will appear here once the storefront
                catalog is live.
            </p>
        </div>
    </div>
</section>
