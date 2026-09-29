<?php
/**
 * Empty state for the recently-viewed page.
 *
 * Rendered when the customer has no history. Same shape as
 * the wishlist empty state.
 */
?>

<div class="sf-account-empty">
    <div class="sf-account-empty-icon" aria-hidden="true">
        <i class="bi bi-clock-history"></i>
    </div>
    <h2 class="sf-account-empty-title">No history yet</h2>
    <p class="sf-account-empty-body">
        Products you view will show up here, newest first. The
        list keeps your last 50 visits.
    </p>
    <div class="sf-account-empty-actions">
        <a href="/products" class="sf-btn sf-btn-primary">
            <i class="bi bi-bag me-2" aria-hidden="true"></i>
            Browse products
        </a>
    </div>
</div>
