<?php
/**
 * Empty state for the wishlist page.
 *
 * Rendered when the customer has no wishlisted products. Same
 * shape as the other account empty states (orders, addresses).
 */
?>

<div class="sf-account-empty">
    <div class="sf-account-empty-icon" aria-hidden="true">
        <i class="bi bi-heart"></i>
    </div>
    <h2 class="sf-account-empty-title">Your wishlist is empty</h2>
    <p class="sf-account-empty-body">
        Tap the heart on any product to save it here. Come back
        whenever you're ready to buy.
    </p>
    <div class="sf-account-empty-actions">
        <a href="/products" class="sf-btn sf-btn-primary">
            <i class="bi bi-bag me-2" aria-hidden="true"></i>
            Browse products
        </a>
    </div>
</div>
