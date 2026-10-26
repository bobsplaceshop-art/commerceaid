<?php
/**
 * Storefront product card.
 *
 * Premium editorial layout, matching the reference design:
 *   - Borderless image area with rounded top corners.
 *   - Badge (sale percent or "New") in the top-left.
 *   - Wishlist heart in the top-right, always visible.
 *   - Product name clamped to two lines.
 *   - Star rating (only when there are reviews).
 *   - Price, bold and large, with a thin strike-through
 *     compare-at when on sale.
 *   - Wide "Add to cart" button, uppercase, gold on hover.
 *
 * Used by:
 *   - catalog/index.php
 *   - category/show.php
 *   - product/show.php (related products)
 *   - blocks/product-grid.php (homepage, category pages)
 *
 * Preserves the existing interactive hooks so wishlist.js
 * and the add-to-cart interceptor keep working:
 *   - data-sf-card-action="wishlist" + data-product-id
 *   - data-sf-add-to-cart on the form
 *
 * @var \CorporateAid\Commerce\Models\Product $product
 * @var array<int,array{avg:float,count:int}> $productRatings
 */

$product        = $product        ?? null;
$productRatings = $productRatings ?? [];

if ($product === null) {
    return;
}

$cardId = 'sf-product-card-' . (int) $product->id;

$price     = (float) $product->price;
$compareAt = $product->compareAtPrice !== null ? (float) $product->compareAtPrice : null;
$onSale    = $product->isOnSale();
$discount  = $onSale ? (int) $product->discountPercent() : 0;

$detailUrl = '/products/' . rawurlencode($product->slug);

$ratingData  = $productRatings[$product->id] ?? null;
$ratingAvg   = $ratingData['avg']   ?? 0.0;
$ratingCount = $ratingData['count'] ?? 0;

$isWished = in_array((int) $product->id, wishlist_product_ids(), true);
?>

<article class="sf-product-card" id="<?= e($cardId) ?>" aria-labelledby="<?= e($cardId) ?>-name">

    <div class="sf-product-card-media">

        <a href="<?= e($detailUrl) ?>" class="sf-product-card-image-link" tabindex="-1" aria-hidden="true">
            <?= product_picture_tag($product, [
                'variant' => 'card',
                'class'   => 'sf-product-card-image',
                'sizes'   => '(max-width: 640px) 50vw, (max-width: 900px) 33vw, 320px',
                'width'   => 400,
                'height'  => 500,
            ]) ?>
        </a>

        <?php if ($onSale && $discount > 0): ?>
            <span class="sf-product-card-badge sf-badge-sale">-<?= $discount ?>%</span>
        <?php elseif ($product->isNew): ?>
            <span class="sf-product-card-badge sf-badge-new">New</span>
        <?php endif; ?>

        <button type="button"
                class="sf-product-card-action<?= $isWished ? ' is-active' : '' ?>"
                data-sf-card-action="wishlist"
                data-product-id="<?= (int) $product->id ?>"
                aria-label="<?= $isWished ? 'Remove from wishlist' : 'Add to wishlist' ?>"
                aria-pressed="<?= $isWished ? 'true' : 'false' ?>">
            <i class="bi <?= $isWished ? 'bi-heart-fill' : 'bi-heart' ?>" aria-hidden="true"></i>
        </button>

    </div>

    <div class="sf-product-card-body">

        <h3 class="sf-product-card-name" id="<?= e($cardId) ?>-name">
            <a href="<?= e($detailUrl) ?>"><?= e($product->name) ?></a>
        </h3>

        <?php if ($ratingCount > 0): ?>
            <div class="sf-product-card-rating" data-sf-card-block="rating">
                <span class="sf-product-card-stars" aria-hidden="true">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= (int) round($ratingAvg) ? 'bi-star-fill' : 'bi-star' ?>"></i>
                    <?php endfor; ?>
                </span>
                <span class="sf-product-card-rating-count">
                    (<?= (int) $ratingCount ?>)
                </span>
            </div>
        <?php endif; ?>

        <div class="sf-product-card-pricing">
            <span class="sf-product-card-price"><?= e(number_format($price, 2)) ?></span>
            <?php if ($onSale && $compareAt !== null): ?>
                <span class="sf-product-card-compare"><?= e(number_format($compareAt, 2)) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$product->isOutOfStock()): ?>
            <form method="POST"
                  action="/cart/add"
                  class="sf-product-card-add-form"
                  data-sf-add-to-cart>
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="sf-product-card-add">
                    <i class="bi bi-bag-plus" aria-hidden="true"></i>
                    <span>Add to cart</span>
                </button>
            </form>
        <?php else: ?>
            <a href="<?= e($detailUrl) ?>"
               class="sf-product-card-add sf-product-card-add-out"
               data-sf-card-action="view-product">
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                <span>View product</span>
            </a>
        <?php endif; ?>

    </div>

</article>
