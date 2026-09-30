<?php
/**
 * Wishlist page. Rendered inside the account shell by
 * WishlistController::index().
 *
 * Context:
 *   $rows     array<int, array{wishlist: WishlistItem, product: ?Product}>
 *   $count    int
 *   $customer ?Customer
 *
 * Each row pairs the WishlistItem (which just has the
 * product_id) with a live Product lookup. When the product is
 * null (archived or hard-deleted), the row renders a
 * "no longer available" state and offers a remove button only.
 *
 * See docs/stages/008g.1_wishlist-plan.md.
 */
$rows  = $rows  ?? [];
$count = (int) ($count ?? 0);
?>

<div class="sf-account-page-header">
    <h1 class="sf-account-page-title">My Wishlist</h1>
    <p class="sf-account-page-subtitle">
        <?php if ($count > 0): ?>
            <?= (int) $count ?> <?= $count === 1 ? 'item' : 'items' ?> saved for later.
        <?php else: ?>
            Save products you love and come back to them later.
        <?php endif; ?>
    </p>
</div>

<?php if ($rows === []): ?>

    <?= view_partial('storefront.account._wishlist-empty') ?>

<?php else: ?>

    <ul class="sf-wishlist-grid">
        <?php foreach ($rows as $row): ?>
            <?php
            /** @var \CorporateAid\Commerce\Models\WishlistItem $item */
            $item    = $row['wishlist'];
            $product = $row['product'];
            ?>

            <li class="sf-wishlist-card" data-wishlist-product-id="<?= (int) $item->productId ?>">

                <?php if ($product === null): ?>

                    <div class="sf-wishlist-card-unavailable">
                        <div class="sf-wishlist-card-unavailable-icon" aria-hidden="true">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="sf-wishlist-card-unavailable-body">
                            <strong>Product no longer available</strong>
                            <span class="muted small">It has been removed from the catalog.</span>
                        </div>
                        <form method="POST"
                              action="/account/wishlist/remove"
                              class="sf-wishlist-card-remove-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="product_id" value="<?= (int) $item->productId ?>">
                            <button type="submit"
                                    class="sf-btn sf-btn-outline sf-btn-sm"
                                    aria-label="Remove from wishlist">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                                Remove
                            </button>
                        </form>
                    </div>

                <?php else: ?>

                    <?php
                    $detailUrl = '/products/' . rawurlencode($product->slug);
                    $imageUrl  = product_picture_url($product, 'card');
                    $onSale    = $product->isOnSale();
                    $stockClass = 'sf-stock-in';
                    $stockLabel = 'In stock';
                    if ($product->isOutOfStock()) {
                        $stockClass = 'sf-stock-out';
                        $stockLabel = 'Out of stock';
                    } elseif ($product->stockStatus === 'backorder') {
                        $stockClass = 'sf-stock-backorder';
                        $stockLabel = 'On backorder';
                    }
                    ?>

                    <a href="<?= e($detailUrl) ?>" class="sf-wishlist-card-media">
                        <img src="<?= e($imageUrl) ?>"
                             alt=""
                             loading="lazy"
                             width="600"
                             height="750"
                             class="sf-wishlist-card-image">
                        <?php if ($onSale): ?>
                            <span class="sf-wishlist-card-badge sf-badge-sale">
                                Sale
                            </span>
                        <?php endif; ?>
                    </a>

                    <div class="sf-wishlist-card-body">
                        <h2 class="sf-wishlist-card-name">
                            <a href="<?= e($detailUrl) ?>"><?= e($product->name) ?></a>
                        </h2>

                        <div class="sf-wishlist-card-pricing">
                            <span class="sf-wishlist-card-price">
                                <?= e(number_format((float) $product->price, 2)) ?>
                            </span>
                            <?php if ($onSale && $product->compareAtPrice !== null): ?>
                                <span class="sf-wishlist-card-compare">
                                    <?= e(number_format((float) $product->compareAtPrice, 2)) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="sf-product-card-stock <?= e($stockClass) ?>">
                            <span class="sf-product-card-stock-dot" aria-hidden="true"></span>
                            <?= e($stockLabel) ?>
                        </div>

                        <div class="sf-wishlist-card-actions">
                            <a href="<?= e($detailUrl) ?>"
                               class="sf-btn sf-btn-primary sf-btn-sm sf-wishlist-card-view">
                                View product
                            </a>
                            <form method="POST"
                                  action="/account/wishlist/remove"
                                  class="sf-wishlist-card-remove-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="product_id" value="<?= (int) $item->productId ?>">
                                <button type="submit"
                                        class="sf-btn sf-btn-outline sf-btn-sm"
                                        aria-label="Remove from wishlist">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                <?php endif; ?>

            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>
