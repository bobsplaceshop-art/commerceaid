<?php
/**
 * Claim your guest orders.
 *
 * Context (from CustomerClaimController::list):
 *   $customer   Customer
 *   $claimable  array<int, Order>
 */
$customer  = $customer  ?? null;
$claimable = $claimable ?? [];

if ($customer === null) {
    return;
}

$count = count($claimable);
?>
<div class="sf-account-panel" data-sf-page="account-claims">

        <h1 class="sf-auth-heading">Claim your orders</h1>
        <p class="sf-auth-subhead">
            Orders placed with your email address before you had an
            account. If they are yours, you can link them to your
            account and they will appear in your order history.
        </p>

        <?php if ($count === 0): ?>
            <div class="sf-alert sf-alert-info">
                <p class="mb-0">
                    No orders to claim. If you believe this is a mistake,
                    contact support.
                </p>
            </div>
            <a href="/account" class="sf-btn sf-btn-outline">Back to account</a>

        <?php else: ?>

            <div class="sf-alert sf-alert-info">
                <strong>About these orders</strong>
                <p class="mb-0 small mt-1">
                    If they are yours, click <em>Link all to my account</em>.
                    They will appear in your order history. If they are not
                    yours, click <em>Leave as guest orders</em>. They will
                    not be linked and you will not see them again.
                </p>
            </div>

            <div class="sf-table-responsive-wrap mb-4">
                <table class="sf-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Date</th>
                            <th style="text-align:right;">Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claimable as $o): ?>
                            <tr>
                                <td>
                                    <a href="/checkout/confirmation/<?= e($o->orderNumber) ?>"
                                       class="text-decoration-none">
                                        <?= e($o->orderNumber) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="muted small">
                                        <?= $o->placedAt !== null
                                            ? e(date('M j, Y', strtotime($o->placedAt)))
                                            : '—' ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?= e(number_format($o->grandTotal, 2)) ?>
                                    <?= e($o->currencyCode) ?>
                                </td>
                                <td>
                                    <span class="muted small">
                                        <?= e(ucfirst(str_replace('_', ' ', $o->status))) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <form method="POST" action="/account/claims/link" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="sf-btn sf-btn-primary">
                        <i class="bi bi-link-45deg me-1"></i>
                        Link all to my account
                    </button>
                </form>
                <form method="POST" action="/account/claims/dismiss" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="sf-btn sf-btn-outline">
                        Leave as guest orders
                    </button>
                </form>
            </div>

        <?php endif; ?>

</div>
