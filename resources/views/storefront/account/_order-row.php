<?php
/**
 * One row in the customer's order list.
 *
 * @var \CorporateAid\Commerce\Models\Order $o
 * @var array<int,string> $openReturnMap  order_id => request_number
 *                                        for orders with an open
 *                                        return request. Optional;
 *                                        defaults to empty.
 */
$o             = $o             ?? null;
$openReturnMap = $openReturnMap ?? [];

if ($o === null) {
    return;
}

// If this order has an open return request, show a badge in the
// header. The map is built once per page load by
// AccountController::orders().
$returnRequestNo = $openReturnMap[$o->id] ?? null;

$statusLabels = [
    'pending_payment'    => 'Pending payment',
    'paid'               => 'Paid',
    'processing'         => 'Processing',
    'packed'             => 'Packed',
    'shipped'            => 'Shipped',
    'delivered'          => 'Delivered',
    'completed'          => 'Completed',
    'cancelled'          => 'Cancelled',
    'failed'             => 'Failed',
    'refunded'           => 'Refunded',
    'partially_refunded' => 'Partially refunded',
    'on_hold'            => 'On hold',
];
$statusLabel = $statusLabels[$o->status] ?? ucfirst(str_replace('_', ' ', $o->status));

$statusClass = match ($o->status) {
    'completed', 'delivered' => 'sf-order-badge-success',
    'cancelled', 'failed'    => 'sf-order-badge-danger',
    'pending_payment'        => 'sf-order-badge-warning',
    default                  => 'sf-order-badge-muted',
};
?>
<div class="sf-order-row">
    <div class="sf-order-row-main">
        <div class="sf-order-row-header">
            <a href="/account/orders/<?= e($o->orderNumber) ?>"
               class="sf-order-row-number">
                <?= e($o->orderNumber) ?>
            </a>
            <span class="sf-order-badge <?= e($statusClass) ?>">
                <?= e($statusLabel) ?>
            </span>
            <?php if ($returnRequestNo !== null): ?>
                <a href="/account/returns/<?= e($returnRequestNo) ?>"
                   class="sf-order-badge sf-order-badge-muted">
                    Return in progress
                </a>
            <?php endif; ?>
        </div>
        <div class="sf-order-row-meta">
            <span>
                <?= $o->placedAt !== null
                    ? e(date('M j, Y', strtotime($o->placedAt)))
                    : '—' ?>
            </span>
            <span class="sf-order-row-dot" aria-hidden="true">&middot;</span>
            <span>
                <?= e(number_format($o->grandTotal, 2)) ?> <?= e($o->currencyCode) ?>
            </span>
            <?php if ($o->hasTracking()): ?>
                <span class="sf-order-row-dot" aria-hidden="true">&middot;</span>
                <span class="sf-order-row-tracking">
                    <i class="bi bi-truck" aria-hidden="true"></i>
                    <?= e((string) $o->carrierLabel()) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="sf-order-row-actions">
        <a href="/account/orders/<?= e($o->orderNumber) ?>"
           class="sf-btn sf-btn-outline sf-btn-sm">
            View
        </a>
    </div>
</div>
