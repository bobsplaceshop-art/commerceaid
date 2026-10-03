<?php
/**
 * Storefront product detail at /products/{slug}.
 *
 * Context (from ProductController::show):
 *   $product          Product
 *   $brand            ?Brand
 *   $primaryCategory  ?Category
 *   $parentCategory   ?Category
 *   $stockQty         ?int   available qty for simple products
 *   $variants         array<int, ProductVariant>
 *   $related          array<int, Product>
 *
 * Store builder contract:
 *   Section  data-sf-section="product-detail"
 *   Page     data-sf-page="product"
 *   Product  data-sf-product-id="{id}"
 *   Block    data-sf-block="add-to-cart"
 *
 * Images:
 *   Main image  $gallery[0] as a lg WebP variant, or
 *               /assets/img/product-placeholder.svg when
 *               the product has no images.
 *   Thumbnails  every gallery image as a card WebP
 *               thumbnail with a lg WebP swap target.
 *   Replaced by real product media when uploads land.
 *
 * Variants:
 *   The variant picker reads $v->attributeSummary, populated by
 *   ProductVariantRepository::listForProduct(). The picker is a
 *   disabled stub until cart lands in 008D.
 */

$product = $product ?? null;
if ($product === null) {
    return;
}

$brand           = $brand           ?? null;
$primaryCategory = $primaryCategory ?? null;
$parentCategory  = $parentCategory  ?? null;
$stockQty        = $stockQty        ?? null;
$variants        = $variants        ?? [];
$related         = $related         ?? [];

// The related products come from the same primary category.
// Pass the category name to each card so the label renders.
$relatedCategoryLabel = ($primaryCategory !== null) ? $primaryCategory->name : null;

$price     = (float) $product->price;
$compareAt = $product->compareAtPrice !== null ? (float) $product->compareAtPrice : null;
$onSale    = $product->isOnSale();
$discount  = $onSale ? (int) $product->discountPercent() : 0;

// Live sold-out state comes from the controller (reads
// commerce_inventory, not the cached product.stock_status).
// The cached field can drift; the live check cannot.
$isLiveSoldOut = $isLiveSoldOut ?? false;

$stockClass = 'sf-stock-in';
$stockLabel = 'In stock';
if ($isLiveSoldOut) {
    $stockClass = 'sf-stock-out';
    $stockLabel = 'Sold out';
} elseif ($product->stockStatus === 'backorder') {
    $stockClass = 'sf-stock-backorder';
    $stockLabel = 'Available on backorder';
}

// Product gallery. The controller passes $gallery as an
// array of ['image' => ProductImage, 'media' => MediaItem]
// pairs, ordered by sort_order (primary first when set).
//
// Hero:    the first gallery image as a lg WebP variant.
//          Falls back to the local placeholder SVG.
// Thumbs:  every gallery image as a card WebP thumbnail
//          with a lg WebP swap target.
//
// When the gallery is empty, $thumbnails stays empty and
// the thumbnail strip renders nothing - correct for a
// product with no images.
$gallery = $gallery ?? [];

$mainImage  = '/assets/img/product-placeholder.svg';
$thumbnails = [];

if ($gallery !== []) {
    $mainImage = $gallery[0]['media']->variantUrl('lg', 'webp');

    foreach ($gallery as $entry) {
        $thumbnails[] = [
            'thumb' => $entry['media']->variantUrl('card', 'webp'),
            'large' => $entry['media']->variantUrl('lg', 'webp'),
        ];
    }
}
?>

<div class="sf-container sf-product-detail"
     data-sf-page="product"
     data-sf-product-id="<?= (int) $product->id ?>">

    <nav class="sf-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
        <a href="/products">Products</a>
        <?php if ($parentCategory !== null): ?>
            <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
            <a href="/categories/<?= e(rawurlencode($parentCategory->slug)) ?>"><?= e($parentCategory->name) ?></a>
        <?php endif; ?>
        <?php if ($primaryCategory !== null): ?>
            <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
            <a href="/categories/<?= e(rawurlencode($primaryCategory->slug)) ?>"><?= e($primaryCategory->name) ?></a>
        <?php endif; ?>
        <span class="sf-breadcrumb-sep" aria-hidden="true">/</span>
        <span class="sf-breadcrumb-current" aria-current="page"><?= e($product->name) ?></span>
    </nav>

    <section class="sf-product-layout" data-sf-section="product-detail">

        <!-- ========================================================= -->
        <!-- Media column                                              -->
        <!-- ========================================================= -->
        <div class="sf-product-media" data-sf-gallery>
            <div class="sf-product-image-large">
                <img src="<?= e($mainImage) ?>"
                     alt=""
                     width="900"
                     height="1125"
                     class="sf-product-main-img"
                     data-sf-gallery-main>

                <?php if ($onSale): ?>
                    <span class="sf-product-badge sf-badge-sale">
                        <?= $discount > 0 ? '-' . $discount . '%' : 'Sale' ?>
                    </span>
                <?php elseif ($product->isNew): ?>
                    <span class="sf-product-badge sf-badge-new">New</span>
                <?php endif; ?>
            </div>

            <div class="sf-product-thumbnails" role="group" aria-label="Product images">
                <?php foreach ($thumbnails as $i => $thumb): ?>
                    <button type="button"
                            class="sf-product-thumb <?= $i === 0 ? 'is-active' : '' ?>"
                            data-sf-gallery-thumb
                            data-large="<?= e($thumb['large']) ?>"
                            aria-label="Show image <?= $i + 1 ?>">
                        <img src="<?= e($thumb['thumb']) ?>" alt="" width="300" height="375" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- Info column                                               -->
        <!-- ========================================================= -->
        <div class="sf-product-info">

            <?php if ($brand !== null): ?>
                <a href="/products?brand=<?= e(rawurlencode($brand->slug)) ?>" class="sf-product-brand">
                    <?= e($brand->name) ?>
                </a>
            <?php endif; ?>

            <h1 class="sf-product-title"><?= e($product->name) ?></h1>

            <?php if (!empty($product->shortDescription)): ?>
                <p class="sf-product-short"><?= e($product->shortDescription) ?></p>
            <?php endif; ?>

            <div class="sf-product-price-block">
                <span class="sf-product-price"><?= e(number_format($price, 2)) ?></span>
                <?php if ($onSale && $compareAt !== null): ?>
                    <span class="sf-product-compare"><?= e(number_format($compareAt, 2)) ?></span>
                    <span class="sf-product-save">Save <?= $discount > 0 ? $discount . '%' : 'now' ?></span>
                <?php endif; ?>
            </div>

            <div class="sf-product-stock <?= e($stockClass) ?>">
                <span class="sf-product-stock-dot" aria-hidden="true"></span>
                <?= e($stockLabel) ?>
                <?php if ($stockQty !== null && $stockQty > 0 && $stockQty <= 10): ?>
                    <span class="muted">&middot; only <?= (int) $stockQty ?> left</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($product->minOrderQuantity) && $product->minOrderQuantity > 1): ?>
                <p class="sf-product-moq muted">
                    Minimum order: <?= (int) $product->minOrderQuantity ?> units
                </p>
            <?php endif; ?>

            <div class="sf-product-actions" data-sf-block="add-to-cart">
                <?php
                // Variable products: the customer picks a variant from
                // a <select>. Simple products: variant_id stays 0.
                $isVariableWithVariants = $product !== null
                    && $product->isVariable()
                    && !empty($variants);
                ?>

                <?php if ($isLiveSoldOut): ?>
                    <div class="sf-product-sold-out" role="status">
                        <i class="bi bi-slash-circle me-1" aria-hidden="true"></i>
                        Currently unavailable
                    </div>
                    <p class="sf-product-sold-out-hint">
                        This product is out of stock. It will reappear in our listings
                        when stock is replenished.
                    </p>
                <?php elseif ($product !== null && (!$product->isVariable() || $isVariableWithVariants)): ?>
                    <form method="POST"
                          action="/cart/add"
                          class="sf-add-to-cart-form"
                          data-sf-add-to-cart>

                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $product->id ?>">

                        <?php if ($isVariableWithVariants): ?>
                            <?php
                            // The swatch selector. One row per
                            // variant axis. The first axis renders
                            // as image swatches when is_color_axis
                            // is true, otherwise as text chips.
                            //
                            // The hidden variant_id input carries the
                            // resolved variant id. product-swatches.js
                            // sets it on click.
                            //
                            // The combinations map lets the JS
                            // resolve a selected (value_id, value_id)
                            // pair to a variant id without a
                            // network round-trip.
                            $swatchAxes         = $swatchAxes         ?? [];
                            $combinations       = $combinations       ?? [];
                            $swatchImages       = $swatchImages       ?? [];
                            $sellableVariantIds = $sellableVariantIds ?? [];

                            $combinationsJson = json_encode(
                                $combinations,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            );
                            ?>
                            <div class="sf-product-variants"
                                 data-sf-swatches
                                 data-sf-combinations="<?= e($combinationsJson) ?>"
                                 data-sf-sellable="<?= e(implode(',', $sellableVariantIds)) ?>">

                                <input type="hidden"
                                       name="variant_id"
                                       value=""
                                       data-sf-variant-input>

                                <?php foreach ($swatchAxes as $axis): ?>
                                    <?php
                                    $axisSlug = (string) ($axis['slug'] ?? '');
                                    $axisName = (string) ($axis['name'] ?? '');
                                    $isColor  = !empty($axis['is_color_axis']);
                                    ?>
                                    <div class="sf-swatch-axis"
                                         data-sf-axis="<?= e($axisSlug) ?>">
                                        <div class="sf-swatch-axis-label">
                                            <?= e($axisName) ?>:
                                            <span class="sf-swatch-axis-value"
                                                  data-sf-axis-value></span>
                                        </div>

                                        <div class="sf-swatch-row">
                                            <?php foreach (($axis['values'] ?? []) as $val): ?>
                                                <?php
                                                $valId      = (int) ($val['attribute_value_id'] ?? 0);
                                                $variantIds = $val['variant_ids'] ?? [];
                                                $hex        = $val['color_hex'] ?? '';
                                                $label      = (string) ($val['value'] ?? '');

                                                $imageUrl = null;
                                                if (isset($swatchImages[$valId])) {
                                                    $imageUrl = $swatchImages[$valId]['media']
                                                        ->variantUrl('card', 'webp');
                                                }
                                                ?>
                                                <button type="button"
                                                        class="sf-swatch<?= $isColor ? ' sf-swatch-color' : ' sf-swatch-chip' ?>"
                                                        data-sf-swatch
                                                        data-axis="<?= e($axisSlug) ?>"
                                                        data-value-id="<?= $valId ?>"
                                                        data-variant-ids="<?= e(implode(',', $variantIds)) ?>"
                                                        <?php if ($imageUrl !== null): ?>
                                                            data-swap-image="<?= e($imageUrl) ?>"
                                                        <?php endif; ?>>
                                                    <?php if ($imageUrl !== null): ?>
                                                        <img src="<?= e($imageUrl) ?>"
                                                             alt=""
                                                             loading="lazy">
                                                    <?php elseif ($isColor && $hex !== ''): ?>
                                                        <span class="sf-swatch-fill"
                                                              style="background-color: <?= e($hex) ?>"></span>
                                                    <?php else: ?>
                                                        <span class="sf-swatch-label"><?= e($label) ?></span>
                                                    <?php endif; ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <noscript>
                                <div class="sf-product-variants">
                                    <label for="sf-variant-select" class="sf-field-label">
                                        Choose an option <span class="req">*</span>
                                    </label>
                                    <select name="variant_id"
                                            id="sf-variant-select"
                                            class="sf-select"
                                            required>
                                        <option value="">Select&hellip;</option>
                                        <?php foreach ($variants as $v): ?>
                                            <option value="<?= (int) $v->id ?>">
                                                <?php
                                                $label = trim((string) $v->attributeSummary);
                                                if ($label === '') {
                                                    $label = $v->sku ?: 'Variant #' . (int) $v->id;
                                                }
                                                echo e($label . ' — ' . number_format($v->price, 2));
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </noscript>
                        <?php else: ?>
                            <input type="hidden" name="variant_id" value="0">
                        <?php endif; ?>

                        <div class="sf-quantity-row">
                            <label for="sf-product-qty" class="sf-quantity-label">
                                Quantity
                            </label>
                            <input type="number"
                                   id="sf-product-qty"
                                   name="quantity"
                                   class="sf-quantity-input"
                                   value="1"
                                   min="1"
                                   max="99"
                                   step="1"
                                   inputmode="numeric">
                        </div>

                        <button type="submit"
                                class="sf-btn sf-btn-primary sf-btn-lg sf-btn-block">
                            <i class="bi bi-bag-plus me-2" aria-hidden="true"></i>
                            Add to Cart
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button"
                            class="sf-btn sf-btn-primary sf-btn-lg sf-btn-block"
                            disabled
                            title="This product is not available">
                        <i class="bi bi-bag-plus me-2" aria-hidden="true"></i>
                        Not available
                    </button>
                <?php endif; ?>
                <?php $detailIsWished = in_array((int) $product->id, wishlist_product_ids(), true); ?>
                <button type="button"
                        class="sf-btn sf-btn-outline sf-btn-lg sf-btn-block sf-wishlist-toggle<?= $detailIsWished ? ' is-active' : '' ?>"
                        data-sf-card-action="wishlist"
                        data-product-id="<?= (int) $product->id ?>"
                        aria-pressed="<?= $detailIsWished ? 'true' : 'false' ?>">
                    <i class="bi <?= $detailIsWished ? 'bi-heart-fill' : 'bi-heart' ?> me-2" aria-hidden="true"></i>
                    <span class="sf-wishlist-label"><?= $detailIsWished ? 'Saved to Wishlist' : 'Add to Wishlist' ?></span>
                </button>
            </div>

            <ul class="sf-product-trust" aria-label="Purchase guarantees">
                <li><i class="bi bi-truck" aria-hidden="true"></i> Fast &amp; safe delivery</li>
                <li><i class="bi bi-shield-check" aria-hidden="true"></i> Secure payment</li>
                <li><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Easy returns</li>
            </ul>

        </div>
    </section>

    <?php if (!empty($product->description)): ?>
        <section class="sf-product-description">
            <h2 class="sf-section-title">Description</h2>
            <div class="sf-product-description-body">
                <?= nl2br(e($product->description)) ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($related)): ?>
        <section class="sf-product-related">
            <header class="sf-home-section-heading sf-home-section-heading-left">
                <h2 class="sf-home-heading-text">You may also like</h2>
            </header>
            <div class="sf-product-grid">
                <?php foreach ($related as $p): ?>
                    <?= view_partial('storefront/catalog/_product-card', [
                        'product'       => $p,
                        'categoryLabel' => $relatedCategoryLabel,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>

<script src="/assets/js/storefront-gallery.js" defer></script>
<script src="<?= versioned_asset('js/product-swatches.js') ?>" defer></script>
