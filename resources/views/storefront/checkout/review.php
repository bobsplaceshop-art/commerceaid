<?php
/**
 * Checkout step 1 - cart review.
 *
 * Context (from CheckoutController::review):
 *   $lines     array<int, CartLineItem>
 *   $subtotal  float
 *   $blocking  array<int, string>  blocking warnings
 *
 * Legal links are in the page footer (rendered by the layout).
 * The customer cannot proceed to the next step until any
 * blocking warnings are resolved.
 */
$lines    = $lines    ?? [];
$subtotal = $subtotal ?? 0.0;
$blocking = $blocking ?? [];
?>
<div class="sf-checkout" data-sf-page="checkout-review">

    <div class="sf-container">
        <?= view_partial('storefront.checkout._steps', ['current' => 1]) ?>

        <h1 class="sf-checkout-title">Review your cart</h1>

        <?php if ($blocking !== []): ?>
            <div class="sf-alert sf-alert-error">
                <strong>Some items need your attention:</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($blocking as $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="mb-0 mt-2">
                    <a href="/cart" class="sf-link">Return to cart</a>
                </p>
            </div>
        <?php endif; ?>

        <div class="sf-checkout-review-grid">
            <div class="sf-checkout-review-lines">
                <?php foreach ($lines as $line): ?>
                    <?php
                    $item = $line->item;
                    $live = $line->liveProduct;
                    $lineSubtotal = $line->effectiveSubtotal();
                    ?>
                    <div class="sf-checkout-line">
                        <div class="sf-checkout-line-info">
                            <div class="sf-checkout-line-name">
                                <?= e($item->productName) ?>
                            </div>
                            <?php if ($item->variantLabel !== null && $item->variantLabel !== ''): ?>
                                <div class="sf-checkout-line-variant muted small">
                                    <?= e($item->variantLabel) ?>
                                </div>
                            <?php endif; ?>
                            <div class="sf-checkout-line-qty muted small">
                                Qty <?= (int) $item->quantity ?>
                                &middot;
                                <?= e(number_format($line->effectiveUnitPrice(), 2)) ?> each
                            </div>
                        </div>
                        <div class="sf-checkout-line-total">
                            <?= e(number_format($lineSubtotal, 2)) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <aside class="sf-checkout-summary">
                <h2 class="sf-checkout-summary-title">Order summary</h2>
                <dl class="sf-checkout-summary-list">
                    <dt>Subtotal</dt>
                    <dd><?= e(number_format($subtotal, 2)) ?></dd>
                    <dt>Shipping</dt>
                    <dd class="muted">Calculated next</dd>
                    <dt>VAT (incl.)</dt>
                    <dd class="muted">Included in price</dd>
                </dl>
                <div class="sf-checkout-summary-total">
                    <span>Total</span>
                    <span><?= e(number_format($subtotal, 2)) ?></span>
                </div>

                <?php if ($blocking === []): ?>
                    <a href="/checkout/info" class="sf-btn sf-btn-primary sf-btn-block mt-3">
                        Continue to details
                    </a>
                <?php else: ?>
                    <a href="/cart" class="sf-btn sf-btn-outline sf-btn-block mt-3">
                        Return to cart
                    </a>
                <?php endif; ?>
            </aside>
        </div>
    </div>

</div>
