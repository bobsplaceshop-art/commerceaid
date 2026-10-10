<?php
/**
 * Post-logout confirmation.
 *
 * Rendered through layouts.auth (the admin auth shell). The layout
 * provides the outer card; this file is the inner content.
 *
 * No middleware on the route. The user is already signed out by
 * the time this page renders.
 */
?>

<h1 class="h4 mb-1">You have been signed out</h1>
<p class="muted small mb-4">
    Your session has ended. Sign in again to continue managing your store.
</p>

<div class="d-grid gap-2">
    <a href="/login" class="btn btn-primary w-100 py-2">
        <i class="bi bi-box-arrow-in-right me-1"></i>
        Sign in again
    </a>
    <a href="/" class="btn btn-outline-secondary w-100 py-2">
        <i class="bi bi-shop me-1"></i>
        Back to storefront
    </a>
</div>
