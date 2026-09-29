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
 *   $product        Product   the product to render
 *   $categoryLabel  ?string   optional category name shown above
 *                             the product name. Callers that know
 *                             their context (home, category page)
 *                             pass it. Mixed listings (catalog)
 *                             leave it null and it does not
 *                             render.
 *
 * Store builder contract:
 *   Interactive elements carry data-sf-card-action attributes,
 *   optional blocks carry data-sf-card-block attributes. The
 *   theme builder will use these to toggle features on/off per
 *   store.
 *
 * Images:
 *   picsum.photos seeded by product slug, deterministic per
 *   product. Replaced by real product media when the media
 *   library ships.
 *
 * Rating:
 *   The reviews system does not exist yet. When $ratingCount is
 *   zero the rating block does not render at all. When it exists
 *   (future), stars and count render here.
 */

$product       = $product       ?? null;
$categoryLabel = $categoryLabel ?? null;
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

$imageUrl  = 'https://picsum.photos/seed/' . rawurlencode($product->slug) . '/600/750';
$detailUrl = '/products/' . rawurlencode($product->slug);

// Rating stub. Replace with real data when reviews ship. When
// zero, the whole rating block is omitted (per the collapse rule).
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
            <?php $isWished = in_array((int) $product->id, $wishlistProductIds ?? [], true); ?>
            <button type="button"
                    class="sf-product-card-action<?= $isWished ? ' is-active' : '' ?>"
                    data-sf-card-action="wishlist"
                    data-product-id="<?= (int) $product->id ?>"
                    aria-label="<?= $isWished ? 'Remove from wishlist' : 'Add to wishlist' ?>"
                    aria-pressed="<?= $isWished ? 'true' : 'false' ?>">
                <i class="bi <?= $isWished ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
            </button>
            <button type="button"
                    class="sf-product-card-action sf-product-card-action-desktop"
                    data-sf-card-action="quick-view"
                    aria-label="Quick view"
                    disabled>
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>

    </div>

    <div class="sf-product-card-body">

        <?php if ($categoryLabel !== null && $categoryLabel !== ''): ?>
            <span class="sf-product-card-category"><?= e($categoryLabel) ?></span>
        <?php endif; ?>

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

        <?php if ($ratingCount > 0): ?>
            <div class="sf-product-card-rating" data-sf-card-block="rating">
                <span class="sf-product-card-stars" aria-hidden="true">
                    <?php
                    // Render filled + empty stars up to 5. For now this
                    // is stubbed to full stars when rating exists.
                    for ($i = 1; $i <= 5; $i++):
                        $cls = $i <= (int) round($ratingAvg) ? 'bi-star-fill' : 'bi-star';
                    ?>
                        <i class="bi <?= $cls ?>"></i>
                    <?php endfor; ?>
                </span>
                <span class="sf-product-card-rating-count">
                    (<?= (int) $ratingCount ?>)
                </span>
            </div>
        <?php endif; ?>

        <div class="sf-product-card-stock <?= e($stockClass) ?>">
            <span class="sf-product-card-stock-dot" aria-hidden="true"></span>
            <?= e($stockLabel) ?>
        </div>

        <a href="<?= e($detailUrl) ?>"
           class="sf-product-card-add sf-product-card-add-link"
           data-sf-card-action="view-product">
            <i class="bi bi-bag-plus" aria-hidden="true"></i>
            <span>View product</span>
        </a>

    </div>
</article>
