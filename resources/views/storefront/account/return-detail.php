<?php
/**
 * Customer-facing return request detail at /account/returns/{requestNumber}.
 *
 * Context (from ReturnController::show):
 *   $customer     Customer
 *   $request      ReturnRequest
 *   $items        array<int, ReturnRequestItem>
 *   $order        ?Order
 *   $orderItems   array<int, OrderItem>  indexed by order_item_id
 *   $canCancel    bool
 *   $windowCloses ?string
 */
$customer     = $customer     ?? null;
$request      = $request      ?? null;
$items        = $items        ?? [];
$order        = $order        ?? null;
$orderItems   = $orderItems   ?? [];
$canCancel    = $canCancel    ?? false;
$windowCloses = $windowCloses ?? null;

if ($customer === null || $request === null) {
    return;
}

$requestNumber = (string) $request->requestNumber;
$currency      = $order !== null ? (string) $order->currencyCode : '';

// Status badge class from the variant the model returns.
$statusClass = match ($request->statusBadgeVariant()) {
    'success' => 'sf-order-badge-success',
    'danger'  => 'sf-order-badge-danger',
    'warning' => 'sf-order-badge-warning',
    default   => 'sf-order-badge-muted',
};
?>
<div class="sf-account-panel" data-sf-page="account-return-detail">

    <nav class="sf-order-detail-breadcrumb" aria-label="Breadcrumb">
        <a href="/account">Account</a>
        <span aria-hidden="true">&rsaquo;</span>
        <a href="/account/returns">Returns</a>
        <span aria-hidden="true">&rsaquo;</span>
        <span aria-current="page"><?= e($requestNumber) ?></span>
    </nav>

    <header class="sf-order-detail-header">
        <div>
            <h1 class="sf-order-detail-title"><?= e($requestNumber) ?></h1>
            <p class="sf-order-detail-meta">
                Requested
                <?= $request->requestedAt !== null
                    ? e(date('F j, Y', strtotime($request->requestedAt)))
                    : '—' ?>
                <?php if ($order !== null): ?>
                    &middot; Order
                    <a href="/account/orders/<?= e($order->orderNumber) ?>" class="sf-link">
                        <?= e($order->orderNumber) ?>
                    </a>
                <?php endif; ?>
            </p>
        </div>
        <span class="sf-order-badge <?= e($statusClass) ?> sf-order-badge-lg">
            <?= e($request->statusLabel()) ?>
        </span>
    </header>

    <!-- ============================ STATUS CARDS ============================ -->
    <section class="sf-order-status-grid">
        <div class="sf-order-status-card">
            <div class="sf-order-status-label">Reason</div>
            <div class="sf-order-status-value"><?= e($request->reasonLabel()) ?></div>
        </div>
        <div class="sf-order-status-card">
            <div class="sf-order-status-label">Refund</div>
            <div class="sf-order-status-value">
                <?php if ($request->refundTotal > 0): ?>
                    <?= e(number_format($request->refundTotal, 2)) ?> <?= e($currency) ?>
                <?php else: ?>
                    Pending
                <?php endif; ?>
            </div>
        </div>
        <div class="sf-order-status-card">
            <div class="sf-order-status-label">Status</div>
            <div class="sf-order-status-value"><?= e($request->statusLabel()) ?></div>
            <?php if ($windowCloses !== null): ?>
                <div class="sf-order-status-sub">
                    Window closes
                    <?= e(date('M j, Y', strtotime($windowCloses))) ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================ ITEMS ============================ -->
    <section class="sf-order-section">
        <h2 class="sf-order-section-title">Items to return</h2>
        <div class="sf-order-items">
            <?php foreach ($items as $line): ?>
                <?php $oi = $orderItems[$line->orderItemId] ?? null; ?>
                <div class="sf-order-item">
                    <div class="sf-order-item-main">
                        <div class="sf-order-item-name">
                            <?= e($oi?->productName ?? 'Item') ?>
                        </div>
                        <?php if ($oi !== null && $oi->variantLabel !== null && $oi->variantLabel !== ''): ?>
                            <div class="sf-order-item-variant"><?= e($oi->variantLabel) ?></div>
                        <?php endif; ?>
                        <?php if ($oi !== null && $oi->sku !== null && $oi->sku !== ''): ?>
                            <div class="sf-order-item-sku">
                                SKU <code><?= e($oi->sku) ?></code>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="sf-order-item-qty">
                        Qty <?= (int) $line->quantity ?>
                    </div>
                    <div class="sf-order-item-total">
                        <?= e(number_format($line->lineRefund, 2)) ?> <?= e($currency) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============================ REASON NOTE ============================ -->
    <?php if ($request->reasonNote !== null && $request->reasonNote !== ''): ?>
        <section class="sf-order-section">
            <h2 class="sf-order-section-title">Your note</h2>
            <p><?= e($request->reasonNote) ?></p>
        </section>
    <?php endif; ?>

    <!-- ============================ ADMIN NOTE ============================ -->
    <?php if ($request->adminNote !== null && $request->adminNote !== ''): ?>
        <section class="sf-order-section">
            <h2 class="sf-order-section-title">Message from the store</h2>
            <p><?= e($request->adminNote) ?></p>
        </section>
    <?php endif; ?>

    <!-- ============================ WIDERRUFSRECHT ============================ -->
    <?php if ($request->isWiderruf()): ?>
        <section class="sf-order-section sf-order-withdrawal">
            <h2 class="sf-order-section-title">Right of withdrawal</h2>
            <p class="mb-0">
                This return was requested under your statutory right of
                withdrawal. Read the full policy for details.
            </p>
            <p class="mb-0">
                <a href="/legal/widerruf" class="sf-link" target="_blank" rel="noopener">
                    Read the full withdrawal policy
                </a>
            </p>
        </section>
    <?php endif; ?>

    <!-- ============================ FOOTER ACTIONS ============================ -->
    <div class="sf-order-detail-footer">
        <a href="/account/returns" class="sf-btn sf-btn-outline">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
            Back to my returns
        </a>
        <?php if ($canCancel): ?>
            <form method="POST"
                  action="/account/returns/<?= (int) $request->id ?>/cancel"
                  class="d-inline">
                <?= csrf_field() ?>
                <button type="submit"
                        class="sf-btn sf-btn-outline"
                        onclick="return confirm('Cancel this return request?');">
                    Cancel request
                </button>
            </form>
        <?php endif; ?>
    </div>

</div>
