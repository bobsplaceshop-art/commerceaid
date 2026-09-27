<?php
/**
 * Storefront product card.
 *
 * Reusable partial. Rendered by:
 *   - catalog/index.php
 *   - category/show.php
 *   - home.php (new arrivals, best sellers)
 *   - product/show.php (related products)
 *
 * Context:
 *   $product  Product  the product to render
 *
 * Interactive elements (wishlist, quick view, add to cart) are
 * rendered as disabled stubs. They light up when their owning
 * sub-stages ship:
 *   - Wishlist   -> a later sub-stage
 *   - Quick view -> a later sub-stage
 *   - Add to cart -> 008D
 *
 * Rating:
 *   The reviews system does not exist yet. The card renders an
 *   empty 5-star row with a "(0)" count. When reviews land, this
 *   block is replaced with real aggregate data.
 *
 * Images:
 *   Uses picsum.photos seeded by product slug. Deterministic per
 *   product. Replaced by real product media when uploads land.
 *   Aspect ratio is 4:5, the industry standard for product cards.
 *
 * Store builder contract:
 *   Interactive actions carry data-sf-card-action attributes, and
 *   optional blocks carry data-sf-card-block attributes. When the
 *   store builder allows toggling these on or off, the JS reads
 *   those attributes. The structure does not change.
 */

$product = $product ?? null;
if ($product === null) {
    return;
}

$cardId = 'sf-product-card-' . (int) $product->id;

$price     = (float) $product->price;
$compareAt = $product->compareAtPrice !== null ? (float) $product->compareAtPrice : null;
$onSale    = $product->isOnSale();
$discount  = $onSale ? (int) $product->discountPercent() : 0;

$stockClass = 'sf-stock-in';
$stockLabel = 'In stock';
if ($product->isOutOfStock()) {
    $stockClass = 'sf-stock-out';
    $stockLabel = 'Out of stock';
} elseif ($product->stockStatus === 'backorder') {
    $stockClass = 'sf-stock-backorder';
    $stockLabel = 'On backorder';
}

$imageUrl   = 'https://picsum.photos/seed/' . rawurlencode($product->slug) . '/600/750';
$detailUrl  = '/products/' . rawurlencode($product->slug);

// Rating stub. Replace with real data when reviews ship.
$ratingAvg   = 0.0;
$ratingCount = 0;
?>

<article class="sf-product-card" id="<?= e($cardId) ?>" aria-labelledby="<?= e($cardId) ?>-name">

    <div class="sf-product-card-media">

        <a href="<?= e($detailUrl) ?>" class="sf-product-card-image-link" tabindex="-1" aria-hidden="true">
            <img src="<?= e($imageUrl) ?>"
                 alt=""
                 loading="lazy"
                 width="600"
                 height="750"
                 class="sf-product-card-image">
        </a>

        <?php if ($onSale): ?>
            <span class="sf-product-card-badge sf-badge-sale">
                <?= $discount > 0 ? '-' . $discount . '%' : 'Sale' ?>
            </span>
        <?php elseif ($product->isNew): ?>
            <span class="sf-product-card-badge sf-badge-new">New</span>
        <?php endif; ?>

        <div class="sf-product-card-quick-actions">
            <button type="button"
                    class="sf-product-card-action"
                    data-sf-card-action="wishlist"
                    aria-label="Add to wishlist"
                    disabled>
                <i class="bi bi-heart" aria-hidden="true"></i>
            </button>
            <button type="button"
                    class="sf-product-card-action"
                    data-sf-card-action="quick-view"
                    aria-label="Quick view"
                    disabled>
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>

    </div>

    <div class="sf-product-card-body">

        <div class="sf-product-card-rating" data-sf-card-block="rating">
            <span class="sf-product-card-stars" aria-hidden="true">
                <i class="bi bi-star"></i>
                <i class="bi bi-star"></i>
                <i class="bi bi-star"></i>
                <i class="bi bi-star"></i>
                <i class="bi bi-star"></i>
            </span>
            <span class="sf-product-card-rating-count">(<?= (int) $ratingCount ?>)</span>
        </div>

        <h3 class="sf-product-card-name" id="<?= e($cardId) ?>-name">
            <a href="<?= e($detailUrl) ?>"><?= e($product->name) ?></a>
        </h3>

        <div class="sf-product-card-pricing">
            <span class="sf-product-card-price">
                <?= e(number_format($price, 2)) ?>
            </span>
            <?php if ($onSale && $compareAt !== null): ?>
                <span class="sf-product-card-compare">
                    <?= e(number_format($compareAt, 2)) ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="sf-product-card-stock <?= e($stockClass) ?>">
            <span class="sf-product-card-stock-dot" aria-hidden="true"></span>
            <?= e($stockLabel) ?>
        </div>

        <button type="button"
                class="sf-product-card-add"
                data-sf-card-action="add-to-cart"
                disabled>
            <i class="bi bi-bag-plus" aria-hidden="true"></i>
            <span>Add to Cart</span>
        </button>

    </div>
</article>
