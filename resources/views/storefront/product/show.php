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

// Each thumbnail entry carries the MediaItem plus the
// large URL for the gallery swap JS. The <picture>
// markup is built by media_picture_tag() at render
// time; the 'large' URL is what the JS reads on
// thumbnail click.
$thumbnails = [];

if ($gallery !== []) {
    foreach ($gallery as $entry) {
        $thumbnails[] = [
            'media' => $entry['media'],
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
                <?= product_picture_tag($product, [
                    'variant'       => 'lg',
                    'class'         => 'sf-product-main-img',
                    'sizes'         => '(max-width: 900px) 100vw, 500px',
                    'loading'       => 'eager',
                    'fetchpriority' => 'high',
                    'width'         => 500,
                    'height'        => 500,
                    'attrs'         => ['data-sf-gallery-main' => true],
                ]) ?>

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
                        <?= media_picture_tag($thumb['media'], 'product-image', [
                            'variant' => 'card',
                            'sizes'   => '72px',
                            'width'   => 300,
                            'height'  => 375,
                        ]) ?>
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

            <?php
            // Aggregate rating block. Stars + score + count.
            // The link carries data-sf-tab-link="reviews" so
            // product-tabs.js can activate the Reviews tab and
            // scroll to it on click.
            $__revCount = (int) ($reviewAggregate['count'] ?? 0);
            $__revAvg   = (float) ($reviewAggregate['average'] ?? 0);
            $__revWhole = (int) round($__revAvg);
            ?>
            <a href="#reviews"
               class="sf-product-rating-link<?= $__revCount === 0 ? ' is-empty' : '' ?>"
               data-sf-tab-link="reviews">
                <span class="sf-product-rating-stars" aria-hidden="true">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= $__revWhole ? 'bi-star-fill' : 'bi-star' ?>"></i>
                    <?php endfor; ?>
                </span>
                <?php if ($__revCount > 0): ?>
                    <span class="sf-product-rating-text">
                        <span class="sf-product-rating-score"><?= e(number_format($__revAvg, 1)) ?></span>
                        <span class="sf-product-rating-sep">&middot;</span>
                        <span class="sf-product-rating-count">
                            <?= (int) $__revCount ?> review<?= $__revCount === 1 ? '' : 's' ?>
                        </span>
                    </span>
                <?php else: ?>
                    <span class="sf-product-rating-text">Be the first to review</span>
                <?php endif; ?>
            </a>

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

                                                $swapMedia = $swatchImages[$valId]['media'] ?? null;
                                                $imageUrl  = $swapMedia !== null
                                                    ? $swapMedia->variantUrl('card', 'webp')
                                                    : null;
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
                                                    <?php if ($isColor && $swapMedia !== null): ?>
                                                        <?= media_picture_tag($swapMedia, 'product-image', [
                                                            'variant' => 'card',
                                                            'sizes'   => '56px',
                                                            'width'   => 400,
                                                            'height'  => 500,
                                                        ]) ?>
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

    <?php
    // Specifications data. Renders only when at least one
    // field is populated. Values come from data the controller
    // already passes to this view. Attribute values (Color,
    // Size, Care Instructions) are added in a follow-up
    // commit — they need a controller change to load.
    $specs = [];
    if ($brand !== null && !empty($brand->name)) {
        $specs['Brand'] = $brand->name;
    }
    if (!empty($product->sku)) {
        $specs['SKU'] = $product->sku;
    }
    if (!empty($product->barcode)) {
        $specs['Barcode'] = $product->barcode;
    }
    if (!empty($product->weight)) {
        $specs['Weight'] = $product->weight;
    }
    if (!empty($product->dimensions)) {
        $specs['Dimensions'] = $product->dimensions;
    }
    if ($primaryCategory !== null && !empty($primaryCategory->name)) {
        $specs['Category'] = $primaryCategory->name;
    }

    $hasDescription = !empty($product->description);
    $hasSpecs       = $specs !== [];

    // Determine which tab opens by default. Description first,
    // then Specifications, then Reviews as fallbacks.
    $defaultTab = $hasDescription ? 'description'
                : ($hasSpecs    ? 'specs'
                                : 'reviews');
    ?>

    <section class="sf-product-tabs" data-sf-tabs>

        <div class="sf-product-tabs-strip" role="tablist" aria-label="Product details">
            <?php if ($hasDescription): ?>
                <button type="button"
                        class="sf-product-tab<?= $defaultTab === 'description' ? ' is-active' : '' ?>"
                        role="tab"
                        id="sf-tab-description-btn"
                        aria-controls="sf-tab-description"
                        aria-selected="<?= $defaultTab === 'description' ? 'true' : 'false' ?>"
                        data-sf-tab="description">
                    Description
                </button>
            <?php endif; ?>

            <?php if ($hasSpecs): ?>
                <button type="button"
                        class="sf-product-tab<?= $defaultTab === 'specs' ? ' is-active' : '' ?>"
                        role="tab"
                        id="sf-tab-specs-btn"
                        aria-controls="sf-tab-specs"
                        aria-selected="<?= $defaultTab === 'specs' ? 'true' : 'false' ?>"
                        data-sf-tab="specs">
                    Specifications
                </button>
            <?php endif; ?>

            <button type="button"
                    class="sf-product-tab<?= $defaultTab === 'reviews' ? ' is-active' : '' ?>"
                    role="tab"
                    id="sf-tab-reviews-btn"
                    aria-controls="sf-tab-reviews"
                    aria-selected="<?= $defaultTab === 'reviews' ? 'true' : 'false' ?>"
                    data-sf-tab="reviews">
                Reviews <span class="sf-product-tab-count">(<?= (int) ($reviewAggregate['count'] ?? 0) ?>)</span>
            </button>
        </div>

        <?php if ($hasDescription): ?>
            <div class="sf-product-tab-panel<?= $defaultTab === 'description' ? ' is-active' : '' ?>"
                 role="tabpanel"
                 id="sf-tab-description"
                 aria-labelledby="sf-tab-description-btn"
                 data-sf-tab-panel="description">
                <div class="sf-product-description-body">
                    <?= nl2br(e($product->description)) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($hasSpecs): ?>
            <div class="sf-product-tab-panel<?= $defaultTab === 'specs' ? ' is-active' : '' ?>"
                 role="tabpanel"
                 id="sf-tab-specs"
                 aria-labelledby="sf-tab-specs-btn"
                 data-sf-tab-panel="specs">
                <table class="sf-product-specs">
                    <tbody>
                        <?php foreach ($specs as $label => $value): ?>
                            <tr>
                                <th scope="row"><?= e($label) ?></th>
                                <td><?= e((string) $value) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="sf-product-tab-panel<?= $defaultTab === 'reviews' ? ' is-active' : '' ?>"
             role="tabpanel"
             id="sf-tab-reviews"
             aria-labelledby="sf-tab-reviews-btn"
             data-sf-tab-panel="reviews">

            <?= view_partial('storefront/product/_reviews-summary', [
                'reviewAggregate' => $reviewAggregate,
            ]) ?>

            <?= view_partial('storefront/product/_reviews-form', [
                'product'        => $product,
                'customerReview' => $customerReview,
                'customerId'     => $customerId ?? null,
            ]) ?>

            <?= view_partial('storefront/product/_reviews-list', [
                'product'         => $product,
                'reviewAggregate' => $reviewAggregate,
            ]) ?>

        </div>

    </section>

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
<script src="<?= versioned_asset('js/product-tabs.js') ?>" defer></script>
<script src="<?= versioned_asset('js/product-reviews.js') ?>" defer></script>
