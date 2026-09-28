<?php
/**
 * Cart page at /cart.
 *
 * Context (from CartController::index):
 *   $lines     array<int, CartLineItem>
 *   $subtotal  float
 *   $count     int
 *   $isEmpty   bool
 *
 * The layout renders flash messages globally. This view does
 * not render flash.
 *
 * See docs/stages/008d_cart-plan.md.
 */

$lines    = $lines    ?? [];
$subtotal = $subtotal ?? 0.0;
$count    = $count    ?? 0;
$isEmpty  = $isEmpty  ?? true;
?>

<div class="sf-cart-page" data-sf-page="cart">

    <div class="sf-container">

        <header class="sf-cart-heading">
            <h1 class="sf-cart-title">Your Cart</h1>
            <?php if (!$isEmpty): ?>
                <p class="sf-cart-count">
                    <?= (int) $count ?> item<?= $count === 1 ? '' : 's' ?>
                </p>
            <?php endif; ?>
        </header>

        <?php if ($isEmpty): ?>

            <?= view_partial('storefront.cart._empty') ?>

        <?php else: ?>

            <div class="sf-cart-layout">

                <div class="sf-cart-items">
                    <?php foreach ($lines as $line): ?>
                        <?= view_partial('storefront.cart._line', ['line' => $line]) ?>
                    <?php endforeach; ?>
                </div>

                <aside class="sf-cart-summary">
                    <h2 class="sf-cart-summary-title">Order summary</h2>

                    <dl class="sf-cart-summary-list">
                        <dt>Subtotal</dt>
                        <dd><?= e(number_format($subtotal, 2)) ?></dd>

                        <dt class="sf-cart-summary-note-label">Shipping</dt>
                        <dd class="sf-cart-summary-note-value">
                            Calculated at checkout
                        </dd>

                        <dt class="sf-cart-summary-note-label">Tax</dt>
                        <dd class="sf-cart-summary-note-value">
                            Calculated at checkout
                        </dd>
                    </dl>

                    <div class="sf-cart-summary-total">
                        <span>Total</span>
                        <span><?= e(number_format($subtotal, 2)) ?></span>
                    </div>

                    <?php
                    // Checkout link — disabled until Stage 008E.
                    // The button is present so the layout is complete
                    // but it does not link anywhere yet.
                    ?>

                    <div class="sf-cart-summary-actions">
                        <a href="/products" class="sf-btn sf-btn-outline sf-btn-block">
                            Continue shopping
                        </a>
                        <a href="/checkout" class="sf-btn sf-btn-primary sf-btn-block">
                            Proceed to Checkout
                        </a>
                    </div>

                    <form method="POST" action="/cart/clear"
                          class="sf-cart-clear-form"
                          onsubmit="return confirm('Remove all items from your cart?');">
                        <?= csrf_field() ?>
                        <button type="submit" class="sf-cart-clear-btn">
                            Empty cart
                        </button>
                    </form>
                </aside>

            </div>

        <?php endif; ?>

    </div>

</div>
