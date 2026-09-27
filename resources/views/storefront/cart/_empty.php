<?php
/**
 * Cart empty state.
 *
 * Context (from CartController::index):
 *   No inputs - a static block with a link back to /products.
 */
?>

<div class="sf-cart-empty">
    <div class="sf-cart-empty-icon" aria-hidden="true">
        <i class="bi bi-bag"></i>
    </div>

    <h2 class="sf-cart-empty-heading">Your cart is empty</h2>
    <p class="sf-cart-empty-copy">
        Looks like you haven't added anything yet.
        Browse the collection and come back when you're ready.
    </p>

    <a href="/products" class="sf-btn sf-btn-primary">
        Continue shopping
    </a>
</div>
