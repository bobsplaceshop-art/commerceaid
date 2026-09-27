<?php
/**
 * Storefront home page.
 *
 * Renders the storefront merchandising rhythm from the design
 * brief:
 *   hero -> categories -> promo panels -> new arrivals ->
 *   promo strip -> best sellers -> newsletter
 *
 * Context (from HomeController::index):
 *   $featured       array<int, Product>  featured products
 *   $newArrivals    array<int, Product>  newest products
 *   $bestSellers    array<int, Product>  mirrors featured for now
 *   $topCategories  array<int, Category> top-level categories (max 7)
 *
 * Store builder contract:
 *   Every top-level section is wrapped in
 *     <section class="sf-home-{type}" data-sf-section="{type}">
 *   When the store builder lands, section order will be driven by
 *   `commerce_homepage_sections.sort_order`. The wrapper structure
 *   is designed so reordering requires no HTML changes.
 *
 * Images:
 *   Product and category images use picsum.photos seeded by a
 *   stable identifier (slug or name). This produces consistent
 *   placeholder photography during development. Real media
 *   replaces this when uploads land.
 *
 *   Hero is a CSS-composed layout (no external asset). Replacing
 *   it with an uploaded hero image is a one-file change when the
 *   customer supplies the asset.
 */

$featured      = $featured      ?? [];
$newArrivals   = $newArrivals   ?? [];
$bestSellers   = $bestSellers   ?? [];
$topCategories = $topCategories ?? [];

// The layout computes $brandName for the header/footer, but layout
// locals are not visible to the content view. Read it from config
// here so the newsletter (and anything else that needs it) shows
// the correct store name.
$brandName = (string) (
    function_exists('config')
        ? config('branding.product_name', 'Anaya Global')
        : 'Anaya Global'
);

/**
 * Placeholder image URL helper. Deterministic per seed.
 */
$placeholderImage = function (string $seed, int $w = 600, int $h = 750): string {
    return 'https://picsum.photos/seed/' . rawurlencode($seed) . '/' . $w . '/' . $h;
};

// Promotional panels.
//
// Contract: these will come from `commerce_homepage_sections` with
// type='promo_panel' and a JSON config. Today hardcoded.
$promoPanels = [
    [
        'id'       => 'elegant-fashion',
        'eyebrow'  => 'Elegant Fashion',
        'title'    => 'For Every Moment',
        'cta'      => "Shop Women's",
        'href'     => '/categories/women',
    ],
    [
        'id'       => 'bags-accessories',
        'eyebrow'  => 'Stylish Bags',
        'title'    => '& Accessories',
        'cta'      => 'Shop Now',
        'href'     => '/categories/bags-accessories',
    ],
    [
        'id'       => 'trendy-kids',
        'eyebrow'  => 'Trendy Kids',
        'title'    => 'Collection',
        'cta'      => 'Shop Now',
        'href'     => '/categories/children',
    ],
    [
        'id'       => 'home-interiors',
        'eyebrow'  => 'Make Your Home',
        'title'    => 'More Beautiful',
        'cta'      => 'Shop Home',
        'href'     => '/categories/home',
    ],
];
?>

<!-- ============================================================= -->
<!-- HERO                                                          -->
<!-- ============================================================= -->
<section class="sf-home-hero" data-sf-section="hero">
    <div class="sf-home-hero-left">
        <span class="sf-home-hero-scene"></span>
    </div>

    <div class="sf-home-hero-center">
        <span class="sf-home-hero-emblem" aria-hidden="true">
            <svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" focusable="false">
                <circle cx="32" cy="32" r="29" fill="none" stroke="currentColor" stroke-width="1.2"/>
                <path d="M14 46 L32 12 L50 46" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                <path d="M14 46 Q32 38 50 46" fill="none" stroke="currentColor" stroke-width="0.9" opacity="0.65"/>
                <ellipse cx="32" cy="32" rx="29" ry="11" fill="none" stroke="currentColor" stroke-width="0.9" opacity="0.5"/>
            </svg>
        </span>

        <span class="sf-home-hero-brand">Anaya Global</span>
        <span class="sf-home-hero-sub">ONLINE SHOP</span>

        <h1 class="sf-home-hero-slogan">Shop the World, Love Your Style.</h1>

        <ul class="sf-home-hero-trust" aria-label="Service benefits">
            <li class="sf-home-hero-trust-item">
                <i class="bi bi-globe2" aria-hidden="true"></i>
                <span>Worldwide<br>Shipping</span>
            </li>
            <li class="sf-home-hero-trust-item">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
                <span>Secure<br>Payment</span>
            </li>
            <li class="sf-home-hero-trust-item">
                <i class="bi bi-truck" aria-hidden="true"></i>
                <span>Fast &amp; Safe<br>Delivery</span>
            </li>
            <li class="sf-home-hero-trust-item">
                <i class="bi bi-headset" aria-hidden="true"></i>
                <span>24/7<br>Support</span>
            </li>
        </ul>
    </div>

    <div class="sf-home-hero-right">
        <span class="sf-home-hero-scene sf-home-hero-scene-right"></span>
        <span class="sf-home-hero-script">
            Fashion<br>
            Beauty<br>
            Lifestyle<br>
            For a better you &hearts;
        </span>
    </div>
</section>

<!-- ============================================================= -->
<!-- SHOP BY CATEGORY                                              -->
<!-- ============================================================= -->
<section class="sf-home-categories" data-sf-section="categories">
    <div class="sf-container">

        <header class="sf-home-section-heading">
            <span class="sf-home-heading-line" aria-hidden="true"></span>
            <h2 class="sf-home-heading-text">Shop by Category</h2>
            <span class="sf-home-heading-line" aria-hidden="true"></span>
        </header>

        <?php if (empty($topCategories)): ?>
            <div class="sf-empty">
                <i class="bi bi-grid"></i>
                <p class="sf-empty-text">Categories will appear here once the catalog is live.</p>
            </div>
        <?php else: ?>
            <div class="sf-home-category-grid">
                <?php foreach ($topCategories as $c): ?>
                    <a href="/categories/<?= e(rawurlencode($c->slug)) ?>" class="sf-home-category-card">
                        <div class="sf-home-category-image">
                            <img src="<?= e($placeholderImage($c->slug, 400, 500)) ?>"
                                 alt=""
                                 loading="lazy"
                                 width="400"
                                 height="500">
                        </div>
                        <div class="sf-home-category-footer">
                            <span class="sf-home-category-name"><?= e($c->name) ?></span>
                            <span class="sf-home-category-arrow" aria-hidden="true">
                                <i class="bi bi-arrow-right"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- ============================================================= -->
<!-- PROMOTIONAL COLLECTIONS                                       -->
<!-- ============================================================= -->
<section class="sf-home-promos" data-sf-section="promo-panels">
    <div class="sf-container">
        <div class="sf-home-promo-grid">
            <?php foreach ($promoPanels as $p): ?>
                <a href="<?= e($p['href']) ?>" class="sf-home-promo-panel" data-promo-id="<?= e($p['id']) ?>">
                    <div class="sf-home-promo-image">
                        <img src="<?= e($placeholderImage($p['id'], 700, 500)) ?>"
                             alt=""
                             loading="lazy"
                             width="700"
                             height="500">
                    </div>
                    <div class="sf-home-promo-body">
                        <span class="sf-home-promo-eyebrow"><?= e($p['eyebrow']) ?></span>
                        <span class="sf-home-promo-title"><?= $p['title'] /* contains & */ ?></span>
                        <span class="sf-home-promo-cta">
                            <?= e($p['cta']) ?>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================================================= -->
<!-- NEW ARRIVALS                                                  -->
<!-- ============================================================= -->
<section class="sf-home-products" data-sf-section="new-arrivals">
    <div class="sf-container">

        <header class="sf-home-section-heading sf-home-section-heading-left">
            <h2 class="sf-home-heading-text">New Arrivals</h2>
            <a href="/products?sort=newest" class="sf-home-heading-link">
                View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </header>

        <?php if (empty($newArrivals)): ?>
            <div class="sf-empty">
                <i class="bi bi-box-seam"></i>
                <p class="sf-empty-text">New arrivals will appear here once products are published.</p>
            </div>
        <?php else: ?>
            <div class="sf-product-grid">
                <?php foreach ($newArrivals as $p): ?>
                    <?= view_partial('storefront/catalog/_product-card', ['product' => $p]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- ============================================================= -->
<!-- PROMOTIONAL STRIP                                             -->
<!-- ============================================================= -->
<section class="sf-home-strip" data-sf-section="promo-strip">
    <div class="sf-container sf-home-strip-inner">
        <div class="sf-home-strip-text">
            <span class="sf-home-strip-eyebrow">Discover Your Next Favorite Look</span>
            <h2 class="sf-home-strip-title">Global Style. Delivered to Your Door.</h2>
            <p class="sf-home-strip-sub">Worldwide shipping available.</p>
        </div>
        <a href="/products" class="sf-btn sf-btn-gold">
            Explore Collection
            <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
        </a>
    </div>
</section>

<!-- ============================================================= -->
<!-- BEST SELLERS                                                  -->
<!-- ============================================================= -->
<section class="sf-home-products" data-sf-section="best-sellers">
    <div class="sf-container">

        <header class="sf-home-section-heading sf-home-section-heading-left">
            <h2 class="sf-home-heading-text">Best Sellers</h2>
            <a href="/products" class="sf-home-heading-link">
                View all <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </header>

        <?php if (empty($bestSellers)): ?>
            <div class="sf-empty">
                <i class="bi bi-star"></i>
                <p class="sf-empty-text">Best sellers will appear here once orders start flowing.</p>
            </div>
        <?php else: ?>
            <div class="sf-product-grid">
                <?php foreach ($bestSellers as $p): ?>
                    <?= view_partial('storefront/catalog/_product-card', ['product' => $p]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<!-- ============================================================= -->
<!-- NEWSLETTER                                                    -->
<!-- ============================================================= -->
<section class="sf-home-newsletter" data-sf-section="newsletter">
    <div class="sf-container sf-home-newsletter-inner">
        <div class="sf-home-newsletter-text">
            <h2 class="sf-home-newsletter-title">Stay in Style</h2>
            <p class="sf-home-newsletter-sub">
                Discover new arrivals, exclusive offers and inspiration from
                <?= e($brandName ?? 'Anaya Global') ?>.
            </p>
        </div>
        <form class="sf-home-newsletter-form" onsubmit="return false;">
            <input type="email"
                   name="email"
                   class="sf-home-newsletter-input"
                   placeholder="Email Address"
                   aria-label="Email address"
                   disabled>
            <button type="button" class="sf-btn sf-btn-primary" disabled>
                Subscribe
            </button>
        </form>
        <p class="sf-home-newsletter-note">Newsletter signup coming soon.</p>
    </div>
</section>
