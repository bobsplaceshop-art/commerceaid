<?php
/**
 * Cart line partial.
 *
 * Context (from CartController::index -> $lines loop):
 *   $line  CartLineItem  snapshot + live state, with warnings
 *
 * The line renders:
 *   - Product image placeholder (real images land with the media
 *     library; today a decorative icon)
 *   - Product name + variant label
 *   - Warning badge if priceChanged, outOfStock, or productRemoved
 *   - Quantity input (form POST to /cart/update)
 *   - Line subtotal (effective price * quantity)
 *   - Remove button (form POST to /cart/remove)
 *
 * Every mutation is a form POST with CSRF, so the cart works
 * without JavaScript. The mini-cart JS enhances it, but does
 * not replace it.
 */

/** @var \CorporateAid\Commerce\Models\CartLineItem $line */
$item = $line->item;

// Live name: prefer live product name if the product still exists,
// otherwise the snapshot. Same for variant label.
$displayName = $line->liveProduct !== null
    ? $line->liveProduct->name
    : $item->productName;

$displayVariant = $item->variantLabel;
$displaySku     = $line->liveProduct !== null && $line->liveProduct->sku !== null
    ? $line->liveProduct->sku
    : $item->sku;

// Warning badge text and class.
$warningLabel = null;
$warningClass = '';
if ($line->productRemoved) {
    $warningLabel = 'No longer available';
    $warningClass = 'sf-cart-line-warning-danger';
} elseif ($line->outOfStock) {
    $warningLabel = 'Out of stock';
    $warningClass = 'sf-cart-line-warning-danger';
} elseif ($line->priceChanged) {
    $warningLabel = 'Price changed';
    $warningClass = 'sf-cart-line-warning-info';
}
?>

<div class="sf-cart-line" data-sf-cart-line
     data-product-id="<?= (int) $item->productId ?>"
     data-variant-id="<?= (int) $item->variantId ?>">

    <div class="sf-cart-line-media" aria-hidden="true">
        <i class="bi bi-image"></i>
    </div>

    <div class="sf-cart-line-info">

        <h3 class="sf-cart-line-name"><?= e($displayName) ?></h3>

        <?php if ($displayVariant !== null): ?>
            <p class="sf-cart-line-variant"><?= e($displayVariant) ?></p>
        <?php endif; ?>

        <?php if ($displaySku !== null): ?>
            <p class="sf-cart-line-sku">
                <span class="sf-cart-line-sku-label">SKU:</span>
                <?= e($displaySku) ?>
            </p>
        <?php endif; ?>

        <?php if ($warningLabel !== null): ?>
            <p class="sf-cart-line-warning <?= e($warningClass) ?>">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <?= e($warningLabel) ?>
                <?php if ($line->priceChanged && $line->liveProduct !== null): ?>
                    &mdash; now
                    <?= e(number_format($line->liveProduct->price, 2)) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

    </div>

    <div class="sf-cart-line-quantity">
        <form method="POST" action="/cart/update" class="sf-cart-quantity-form">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $item->productId ?>">
            <input type="hidden" name="variant_id" value="<?= (int) $item->variantId ?>">

            <label class="sf-cart-quantity-label" for="qty-<?= (int) $item->productId ?>-<?= (int) $item->variantId ?>">
                Qty
            </label>
            <input type="number"
                   id="qty-<?= (int) $item->productId ?>-<?= (int) $item->variantId ?>"
                   name="quantity"
                   class="sf-cart-quantity-input"
                   value="<?= (int) $item->quantity ?>"
                   min="0"
                   max="999"
                   inputmode="numeric"
                   data-sf-cart-quantity>
            <button type="submit"
                    class="sf-cart-quantity-update"
                    title="Update quantity">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i>
                <span class="sf-visually-hidden">Update</span>
            </button>
        </form>
    </div>

    <div class="sf-cart-line-price">
        <span class="sf-cart-line-price-value">
            <?= e(number_format($line->effectiveUnitPrice(), 2)) ?>
        </span>
    </div>

    <div class="sf-cart-line-subtotal">
        <?= e(number_format($line->effectiveSubtotal(), 2)) ?>
    </div>

    <div class="sf-cart-line-remove">
        <form method="POST" action="/cart/remove" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $item->productId ?>">
            <input type="hidden" name="variant_id" value="<?= (int) $item->variantId ?>">
            <button type="submit"
                    class="sf-cart-remove-btn"
                    aria-label="Remove <?= e($displayName) ?> from cart"
                    onclick="return confirm('Remove this item from your cart?');">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </form>
    </div>

</div>
