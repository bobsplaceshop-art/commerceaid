<?php
/**
 * @var array $blocks
 */
$previewTarget = $_GET['customize_preview'] ?? null;
if ($previewTarget === 'header' || $previewTarget === 'footer') {
    // Only the layout's header/footer will render.
    return;
}
?>
<?php if (!empty($blocks)): ?>
    <?php
    $renderer = app(\CorporateAid\Commerce\Services\Builder\BlockRenderer::class);
    ?>
    <?php foreach ($blocks as $block): ?>
        <?php echo $renderer->render($block); ?>
    <?php endforeach; ?>
    
    <?php if (isset($_GET['customize_preview'])): ?>
        <script src="<?= versioned_asset('js/preview-bridge.js') ?>" defer></script>
    <?php endif; ?>
    
    <?php return; // Stop rendering the old hardcoded home page below ?>
<?php endif; ?>

<?php
/**
 * Storefront home page.
 *
 * Renders the storefront merchandising rhythm from the design
 * brief:
 *   hero -> trust row -> categories -> promo panels ->
 *   new arrivals -> trust strip -> best sellers -> newsletter
 *
 * Context (from HomeController::index):
 *   $featured       array<int, Product>
 *   $newArrivals    array<int, Product>
 *   $bestSellers    array<int, Product>
 *   $topCategories  array<int, Category>
 *
 * Store builder contract:
 *   Every top-level section is wrapped in
 *     <section class="sf-home-{type}" data-sf-section="{type}">
 *   Section order today is fixed. The theme builder will drive
 *   order from commerce_homepage_sections.sort_order. Wrapper
 *   structure is designed so reordering requires no HTML changes.
 *
 * Section collapse rule:
 *   Data-driven sections (categories, promo panels, new arrivals,
 *   best sellers) render NOTHING when their data is empty. Not an
 *   empty state - the section is absent. The section below fills
 *   the gap seamlessly. Brand/messaging sections (hero, trust
 *   row, promo strip, trust strip, newsletter) always render.
 *
 * Image strategy:
 *   Categories and promo panels use loremflickr keyword-driven
 *   photos so the storefront looks like one coherent brand.
 *   Products still use picsum seeded by slug (real product media
 *   comes from the media library in a future stage).
 */

$featured      = $featured      ?? [];
$newArrivals   = $newArrivals   ?? [];
$bestSellers   = $bestSellers   ?? [];
$topCategories = $topCategories ?? [];

// Store-scoped brand name. The resolved store is passed
// into every storefront view by StorefrontController::render().
// The helper reads $store->name first, falls back to
// config('branding.product_name') when no store is bound.
$brandName = brand_name($store ?? null);

/**
 * Deterministic placeholder image per seed.
 *
 * Uses picsum.photos seeded by the identifier, so the same category
 * or promo panel always renders the same photo. Prefix a namespace
 * to the seed ("cat-", "promo-") so different content types do not
 * share photos.
 *
 * Why not keyword-driven services: loremflickr was tried and was
 * unreliable from the development environment (image loads failed
 * silently). picsum is reliable and already allowed by the CSP.
 * When the store builder supports real category media uploads,
 * this placeholder is replaced with the store's own imagery.
 */
$seedImage = function (string $seed, string $namespace = 'x', int $w = 600, int $h = 750): string {
    return 'https://picsum.photos/seed/' . rawurlencode($namespace . '-' . $seed) . '/' . $w . '/' . $h;
};

/**
 * Product image placeholder. Same shape as $seedImage.
 */
$productImage = function (string $slug, int $w = 600, int $h = 750): string {
    return 'https://picsum.photos/seed/' . rawurlencode($slug) . '/' . $w . '/' . $h;
};

// Promotional panels.
//
// Contract: these will come from commerce_homepage_sections with
// type='promo_panel'. Today hardcoded.
$promoPanels = [
    [
        'id'      => 'elegant-fashion',
        'keyword' => 'woman,elegant',
        'eyebrow' => 'Elegant Fashion',
        'title'   => 'For Every Moment',
        'cta'     => "Shop Women's",
        'href'    => '/categories/women',
    ],
    [
        'id'      => 'bags-accessories',
        'keyword' => 'handbag,luxury',
        'eyebrow' => 'Stylish Bags',
        'title'   => '& Accessories',
        'cta'     => 'Shop Now',
        'href'    => '/categories/bags-accessories',
    ],
    [
        'id'      => 'trendy-kids',
        'keyword' => 'kids,fashion',
        'eyebrow' => 'Trendy Kids',
        'title'   => 'Collection',
        'cta'     => 'Shop Now',
        'href'    => '/categories/children',
    ],
    [
        'id'      => 'home-interiors',
        'keyword' => 'home,interior',
        'eyebrow' => 'Make Your Home',
        'title'   => 'More Beautiful',
        'cta'     => 'Shop Home',
        'href'    => '/categories/home',
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

        <span class="sf-home-hero-brand"><?= e($brandName) ?></span>
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
<!-- SHOP BY CATEGORY (collapses when empty)                       -->
<!-- ============================================================= -->
<?php if (!empty($topCategories)): ?>
    <section class="sf-home-categories" data-sf-section="categories">
        <div class="sf-container">

            <header class="sf-home-section-heading">
                <span class="sf-home-heading-line" aria-hidden="true"></span>
                <h2 class="sf-home-heading-text">Shop by Category</h2>
                <span class="sf-home-heading-line" aria-hidden="true"></span>
            </header>

            <div class="sf-home-category-grid">
                <?php foreach ($topCategories as $c): ?>
                    <a href="/categories/<?= e(rawurlencode($c->slug)) ?>" class="sf-home-category-card">
                        <div class="sf-home-category-image">
                            <?= category_picture_tag($c, [
                                'variant' => 'md',
                                'sizes'   => '(max-width: 640px) 50vw, (max-width: 900px) 33vw, 400px',
                                'width'   => 500,
                                'height'  => 500,
                            ]) ?>
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

        </div>
    </section>
<?php endif; ?>

<!-- ============================================================= -->
<!-- PROMOTIONAL COLLECTIONS (collapses when empty)                -->
<!-- ============================================================= -->
<?php if (!empty($promoPanels)): ?>
    <section class="sf-home-promos" data-sf-section="promo-panels">
        <div class="sf-container">
            <div class="sf-home-promo-grid">
                <?php foreach ($promoPanels as $p): ?>
                    <a href="<?= e($p['href']) ?>" class="sf-home-promo-panel" data-promo-id="<?= e($p['id']) ?>">
                        <div class="sf-home-promo-image">
                            <img src="<?= e($seedImage($p['id'], 'promo', 700, 500)) ?>"
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
<?php endif; ?>

<!-- ============================================================= -->
<!-- PROMOTIONAL STRIP (always renders)                            -->
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
<!-- TRUST STRIP (always renders)                                  -->
<!-- ============================================================= -->
<section class="sf-home-trust-strip" data-sf-section="trust-strip">
    <div class="sf-container">
        <ul class="sf-home-trust-strip-list">
            <?php foreach ($trustRow as $t): ?>
                <li class="sf-home-trust-strip-item">
                    <i class="bi <?= e($t['icon']) ?>" aria-hidden="true"></i>
                    <div class="sf-home-trust-strip-text">
                        <span class="sf-home-trust-strip-title"><?= e($t['title']) ?></span>
                        <span class="sf-home-trust-strip-sub"><?= e($t['sub']) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- ============================================================= -->
<!-- NEWSLETTER (always renders - placeholder for theme builder)   -->
<!-- ============================================================= -->
<section id="newsletter" class="sf-home-newsletter" data-sf-section="newsletter">
    <div class="sf-container sf-home-newsletter-inner">
        <div class="sf-home-newsletter-text">
            <h2 class="sf-home-newsletter-title">Stay in Style</h2>
            <p class="sf-home-newsletter-sub">
                Discover new arrivals, exclusive offers and inspiration from
                <?= e($brandName) ?>.
            </p>
        </div>
        <form class="sf-home-newsletter-form" method="POST" action="/newsletter/subscribe">
            <?= csrf_field() ?>
            <input type="hidden" name="source" value="home">
            <input type="text"
                   name="website"
                   tabindex="-1"
                   autocomplete="off"
                   aria-hidden="true"
                   style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;"
                   value="">
            <input type="email"
                   name="email"
                   class="sf-home-newsletter-input"
                   placeholder="Email Address"
                   aria-label="Email address"
                   required>
            <button type="submit" class="sf-btn sf-btn-primary">
                Subscribe
            </button>
        </form>
    </div>
</section>
