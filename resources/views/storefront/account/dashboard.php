<?php
/**
 * Customer account dashboard at /account.
 *
 * Context (from AccountController::dashboard):
 *   $customer      ?Customer      always non-null (route is guarded)
 *   $group         ?CustomerGroup the customer's group, or null
 *   $hasClaimable  bool           true when the customer has guest
 *                                 orders they can claim and has not
 *                                 dismissed the notification
 *
 * The dashboard is the first thing a customer sees after login.
 * It shows:
 *   - A welcome line with their name
 *   - An approval banner if approval_status is not 'approved'
 *   - A group / net-terms block if they have a group
 *   - Quick links to orders, addresses, password
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$customer     = $customer     ?? null;
$group        = $group        ?? null;
$hasClaimable = $hasClaimable ?? false;

if ($customer === null) {
    // Defensive - the route is guarded, but be safe.
    return;
}

$isPending  = $customer->isPending();
$isRejected = $customer->isRejected();
?>

<div class="sf-account-panel" data-sf-page="account-dashboard">

        <h1 class="sf-auth-heading">
            Welcome, <?= e($customer->fullName()) ?>
        </h1>
        <p class="sf-auth-subhead">
            Manage your account details, addresses, and orders from here.
        </p>

        <?php if ($hasClaimable): ?>
            <div class="sf-alert sf-alert-info" style="margin-bottom:1.5rem;">
                <strong>We found orders placed with your email address.</strong>
                <p class="mb-2 mt-1 small">
                    You have guest orders we can link to your account.
                    Once linked, they will appear in your order history.
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="/account/claims" class="sf-btn sf-btn-primary">
                        <i class="bi bi-link-45deg me-1"></i>
                        Review orders
                    </a>
                    <form method="POST" action="/account/claims/dismiss" style="display:inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="sf-btn sf-btn-outline">
                            Not now
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isPending): ?>
            <div class="sf-form-alert" style="background:#fffbeb;border:1px solid #fde68a;color:#92400e;">
                <strong>Account pending review.</strong>
                You can browse and add items to your cart, but you cannot place orders until an administrator approves your account.
            </div>
        <?php elseif ($isRejected): ?>
            <div class="sf-form-alert sf-form-alert-error">
                <strong>Account not approved.</strong>
                Your account is not approved for ordering. Please contact support for details.
            </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin:2rem 0 1rem;">

            <a href="/account/orders" style="display:block;padding:1.25rem;background:var(--sf-cream, #F8F1E8);border:1px solid rgba(198,161,91,0.2);text-decoration:none;color:var(--sf-charcoal, #111111);">
                <div style="font-size:1.5rem;color:var(--sf-gold, #C6A15B);margin-bottom:0.5rem;">
                    <i class="bi bi-receipt"></i>
                </div>
                <div style="font-weight:500;">My Orders</div>
                <div style="font-size:0.8125rem;color:var(--sf-muted, #777777);margin-top:0.25rem;">
                    View your order history
                </div>
            </a>

            <a href="/account/addresses" style="display:block;padding:1.25rem;background:var(--sf-cream, #F8F1E8);border:1px solid rgba(198,161,91,0.2);text-decoration:none;color:var(--sf-charcoal, #111111);">
                <div style="font-size:1.5rem;color:var(--sf-gold, #C6A15B);margin-bottom:0.5rem;">
                    <i class="bi bi-geo-alt"></i>
                </div>
                <div style="font-weight:500;">My Addresses</div>
                <div style="font-size:0.8125rem;color:var(--sf-muted, #777777);margin-top:0.25rem;">
                    Manage billing and shipping
                </div>
            </a>

            <a href="/account/password" style="display:block;padding:1.25rem;background:var(--sf-cream, #F8F1E8);border:1px solid rgba(198,161,91,0.2);text-decoration:none;color:var(--sf-charcoal, #111111);">
                <div style="font-size:1.5rem;color:var(--sf-gold, #C6A15B);margin-bottom:0.5rem;">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div style="font-weight:500;">Change Password</div>
                <div style="font-size:0.8125rem;color:var(--sf-muted, #777777);margin-top:0.25rem;">
                    Update your sign-in credentials
                </div>
            </a>

        </div>

        <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid rgba(198,161,91,0.15);">
            <h2 style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:500;margin:0 0 1rem;color:var(--sf-charcoal, #111111);">
                Account details
            </h2>

            <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0.5rem 1.5rem;font-size:0.9375rem;">
                <dt style="color:var(--sf-muted, #777777);">Email</dt>
                <dd style="margin:0;"><?= e($customer->email) ?></dd>

                <dt style="color:var(--sf-muted, #777777);">Name</dt>
                <dd style="margin:0;"><?= e($customer->fullName()) ?></dd>

                <?php if ($customer->phone !== null): ?>
                    <dt style="color:var(--sf-muted, #777777);">Phone</dt>
                    <dd style="margin:0;"><?= e($customer->phone) ?></dd>
                <?php endif; ?>

                <?php if ($customer->company !== null): ?>
                    <dt style="color:var(--sf-muted, #777777);">Company</dt>
                    <dd style="margin:0;"><?= e($customer->company) ?></dd>
                <?php endif; ?>

                <?php if ($group !== null): ?>
                    <dt style="color:var(--sf-muted, #777777);">Account type</dt>
                    <dd style="margin:0;"><?= e($group->name) ?></dd>

                    <?php if ($group->defaultNetDays > 0): ?>
                        <dt style="color:var(--sf-muted, #777777);">Payment terms</dt>
                        <dd style="margin:0;">Net <?= (int) $group->defaultNetDays ?> days</dd>
                    <?php endif; ?>
                <?php endif; ?>
            </dl>
        </div>

        <form method="POST" action="/account/logout" style="margin-top:2rem;text-align:center;">
            <?= csrf_field() ?>
            <button type="submit" class="sf-btn sf-btn-outline" style="padding:0.5rem 1.5rem;font-size:0.875rem;">
                Sign out
            </button>
        </form>

</div>
