<?php
/**
 * Customer order history at /account/orders.
 *
 * Context (from AccountController::orders):
 *   $customer  ?Customer
 *
 * STUB. Order history ships in Stage 008E (checkout creates the
 * first orders) and 008F (customer-facing order view). Until then
 * this page shows a friendly empty state.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$customer = $customer ?? null;
?>

<div class="sf-auth-wrap" data-sf-page="account-orders">
    <div class="sf-auth-card sf-auth-card-wide">

        <h1 class="sf-auth-heading">My Orders</h1>
        <p class="sf-auth-subhead">
            Your order history will appear here once you place an order.
        </p>

        <div style="text-align:center;padding:3rem 1rem;">
            <div style="font-size:3rem;color:var(--sf-gold, #C6A15B);opacity:0.5;">
                <i class="bi bi-receipt"></i>
            </div>
            <h2 style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:500;margin:1rem 0 0.5rem;color:var(--sf-charcoal, #111111);">
                No orders yet
            </h2>
            <p style="color:var(--sf-muted, #777777);font-size:0.9375rem;margin:0 0 1.5rem;">
                When you place your first order, it will appear here.
            </p>
            <a href="/products" class="sf-btn sf-btn-primary">
                Browse products
            </a>
        </div>

        <p class="sf-auth-footer">
            <a href="/account">Back to account</a>
        </p>

    </div>
</div>
