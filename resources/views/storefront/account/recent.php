<?php
/**
 * Recently-viewed page. Rendered inside the account shell by
 * RecentViewController::index().
 *
 * Context:
 *   $rows     array<int, array{view: RecentView, product: ?Product}>
 *   $count    int
 *   $customer ?Customer
 *   $flashOk  ?string  optional success flash from a previous
 *                      clear action
 *
 * Each row pairs the RecentView (id + product_id + viewed_at)
 * with a live Product lookup. When the product is null
 * (archived or hard-deleted), the row renders a "no longer
 * available" state and no action link.
 *
 * CSS note: the .sf-wishlist-grid class is reused here. It is
 * a generic account-page product grid - the class name is
 * historical. A future refactor will rename it when a third
 * account page needs the same layout (008G.3 message center).
 *
 * See docs/stages/008g.2_recently-viewed-plan.md.
 */
$rows    = $rows    ?? [];
$count   = (int) ($count ?? 0);
$flashOk = $flashOk ?? null;
?>

<div class="sf-account-page-header">
    <div class="sf-recent-header-row">
        <div class="sf-recent-header-text">
            <h1 class="sf-account-page-title">Recently Viewed</h1>
            <p class="sf-account-page-subtitle">
                <?php if ($count > 0): ?>
                    The last <?= (int) $count ?>
                    <?= $count === 1 ? 'product' : 'products' ?>
                    you looked at.
                <?php else: ?>
                    Products you view will show up here.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($count > 0): ?>
            <form method="POST"
                  action="/account/recent/clear"
                  class="sf-recent-clear-form"
                  onsubmit="return confirm('Clear your entire recently-viewed history? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit"
                        class="sf-recent-clear-btn"
                        aria-label="Clear recently-viewed history">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span>Clear history</span>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($flashOk !== null && $flashOk !== ''): ?>
    <div class="sf-account-flash sf-account-flash-ok" role="status">
        <?= e($flashOk) ?>
    </div>
<?php endif; ?>

<?php if ($rows === []): ?>

    <?= view_partial('storefront.account._recent-empty') ?>

<?php else: ?>

    <ul class="sf-wishlist-grid">
        <?php foreach ($rows as $row): ?>
            <?php
            /** @var \CorporateAid\Commerce\Models\RecentView $view */
            $view    = $row['view'];
            $product = $row['product'];
            ?>

            <li class="sf-wishlist-card"
                data-recent-product-id="<?= (int) $view->productId ?>">

                <?php if ($product === null): ?>

                    <div class="sf-wishlist-card-unavailable">
                        <div class="sf-wishlist-card-unavailable-icon" aria-hidden="true">
                            <i class="bi bi-x-circle"></i>
                        </div>
                        <div class="sf-wishlist-card-unavailable-body">
                            <strong>Product no longer available</strong>
                            <span class="muted small">
                                It has been removed from the catalog.
                            </span>
                        </div>
                    </div>

                <?php else: ?>

                    <?php
                    $detailUrl = '/products/' . rawurlencode($product->slug);
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
                        <?= product_picture_tag($product, [
                            'variant' => 'card',
                            'class'   => 'sf-wishlist-card-image',
                            'sizes'   => '(max-width: 640px) 50vw, (max-width: 900px) 33vw, 320px',
                            'width'   => 400,
                            'height'  => 500,
                        ]) ?>
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
                        </div>
                    </div>

                <?php endif; ?>

            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>
