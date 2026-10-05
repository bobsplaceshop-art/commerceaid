<?php
/**
 * One row in the customer's return list.
 *
 * @var \CorporateAid\Commerce\Models\ReturnRequest $r
 */
$r = $r ?? null;
if ($r === null) {
    return;
}

// statusBadgeVariant() returns warning|info|danger|success|muted.
// Map to the same badge classes the order rows use.
$statusClass = match ($r->statusBadgeVariant()) {
    'success' => 'sf-order-badge-success',
    'danger'  => 'sf-order-badge-danger',
    'warning' => 'sf-order-badge-warning',
    default   => 'sf-order-badge-muted',
};
?>
<div class="sf-order-row">
    <div class="sf-order-row-main">
        <div class="sf-order-row-header">
            <a href="/account/returns/<?= e($r->requestNumber) ?>"
               class="sf-order-row-number">
                <?= e($r->requestNumber) ?>
            </a>
            <span class="sf-order-badge <?= e($statusClass) ?>">
                <?= e($r->statusLabel()) ?>
            </span>
        </div>
        <div class="sf-order-row-meta">
            <span>
                <?= $r->requestedAt !== null
                    ? e(date('M j, Y', strtotime($r->requestedAt)))
                    : '—' ?>
            </span>
            <span class="sf-order-row-dot" aria-hidden="true">&middot;</span>
            <span><?= e($r->reasonLabel()) ?></span>
            <?php if ($r->refundTotal > 0): ?>
                <span class="sf-order-row-dot" aria-hidden="true">&middot;</span>
                <span>
                    <?= e(number_format($r->refundTotal, 2)) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="sf-order-row-actions">
        <a href="/account/returns/<?= e($r->requestNumber) ?>"
           class="sf-btn sf-btn-outline sf-btn-sm">
            View
        </a>
    </div>
</div>
