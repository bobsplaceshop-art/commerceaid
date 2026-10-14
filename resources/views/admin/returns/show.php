<?php
/**
 * Admin return request detail. Stage 008H Session 3.
 *
 * Context (from AdminReturnController::show):
 *   $request    ReturnRequest
 *   $items      array<int,ReturnRequestItem>
 *   $order      ?Order
 *   $orderItems array<int,OrderItem>  keyed by order_item_id
 *   $customer   ?Customer
 */

$request    = $request    ?? null;
$items      = $items      ?? [];
$order      = $order      ?? null;
$orderItems = $orderItems ?? [];
$customer   = $customer   ?? null;

if ($request === null) {
    return;
}

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y \a\t H:i', $ts);
};

$badgeClass = function (string $variant): string {
    return match ($variant) {
        'success' => 'ra-badge ra-badge-success',
        'danger'  => 'ra-badge ra-badge-danger',
        'warning' => 'ra-badge ra-badge-warn',
        'info'    => 'ra-badge',
        'muted'   => 'ra-badge ra-badge-muted',
        default   => 'ra-badge',
    };
};

$refundableNow = 0.0;
foreach ($items as $line) {
    $refundableNow += $line->unitRefund * $line->quantity;
}
$refundableNow = round($refundableNow, 4);

$canApprove  = $request->status === 'requested';
$canReject   = in_array($request->status, ['requested', 'approved'], true);
$canReceive  = $request->status === 'approved';
$canRefund   = $request->status === 'received';

$filterBack = (string) ($_GET['return'] ?? 'open');
$currency   = $order !== null ? $order->currencyCode : 'EUR';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/returns?status=<?= e($filterBack) ?>" class="text-decoration-none">Returns</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($request->requestNumber) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($request->requestNumber) ?></h1>
        <p class="muted mb-0">
            Requested <?= e($fmtDateTime($request->requestedAt)) ?>
            <?php if ($request->isWiderruf()): ?>
                &middot; <span class="ra-badge ra-badge-muted">Widerruf</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="<?= e($badgeClass($request->statusBadgeVariant())) ?>">
            <?= e($request->statusLabel()) ?>
        </span>
    </div>
</div>

<?php if ($canApprove || $canReject || $canReceive || $canRefund): ?>
    <div class="ra-card mb-3">
        <div class="ra-card-body py-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="muted small me-2">Actions:</span>

                <?php if ($canApprove): ?>
                    <form method="POST"
                          action="/commerce/ca-admin/returns/<?= (int) $request->id ?>/approve"
                          class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="<?= e($filterBack) ?>">
                        <button type="submit" class="ra-btn ra-btn-primary ra-btn-sm">
                            <i class="bi bi-check-lg me-1"></i> Approve
                        </button>
                    </form>
                <?php endif; ?>

                <?php if ($canReject): ?>
                    <button type="button"
                            class="ra-btn ra-btn-outline ra-btn-sm"
                            data-ra-reject-open
                            data-request-id="<?= (int) $request->id ?>"
                            data-request-number="<?= e($request->requestNumber) ?>">
                        <i class="bi bi-x-lg me-1"></i> Reject
                    </button>
                <?php endif; ?>

                <?php if ($canReceive): ?>
                    <button type="button"
                            class="ra-btn ra-btn-outline ra-btn-sm"
                            data-ra-receive-open
                            data-request-id="<?= (int) $request->id ?>"
                            data-request-number="<?= e($request->requestNumber) ?>">
                        <i class="bi bi-box-seam me-1"></i> Mark received
                    </button>
                <?php endif; ?>

                <?php if ($canRefund): ?>
                    <button type="button"
                            class="ra-btn ra-btn-primary ra-btn-sm"
                            data-ra-refund-open
                            data-request-id="<?= (int) $request->id ?>"
                            data-request-number="<?= e($request->requestNumber) ?>"
                            data-amount="<?= e(number_format($refundableNow, 2)) ?>"
                            data-currency="<?= e($currency) ?>"
                            data-provider="<?= e($order !== null ? (string) ($order->paymentProviderKey ?? '') : '') ?>">
                        <i class="bi bi-cash-coin me-1"></i> Process refund
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Reason</div>
                <div style="font-weight:600;"><?= e($request->reasonLabel()) ?></div>
                <?php if ($request->reasonNote !== null && $request->reasonNote !== ''): ?>
                    <div class="muted small mt-1">
                        <?= nl2br(e((string) $request->reasonNote)) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Refund</div>
                <div style="font-weight:600;font-size:1.05rem;">
                    <?= e(number_format($request->refundTotal, 2)) ?> <?= e($currency) ?>
                </div>
                <div class="muted small mt-1">as recorded on the request</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Requested</div>
                <div style="font-weight:600;"><?= e($fmtDateTime($request->requestedAt)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Resolved</div>
                <div style="font-weight:600;"><?= e($fmtDateTime($request->resolvedAt)) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="h6 mb-0">Customer</h2>
            <?php if ($customer !== null): ?>
                <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>"
                   class="ra-btn ra-btn-outline ra-btn-sm">
                    View customer profile
                </a>
            <?php endif; ?>
        </div>
        <?php if ($customer !== null): ?>
            <div class="d-flex align-items-center gap-3">
                <div class="ra-avatar" style="width:44px;height:44px;font-size:1rem;">
                    <?= e($customer->initials()) ?>
                </div>
                <div>
                    <div style="font-weight:600;"><?= e($customer->fullName()) ?></div>
                    <div class="muted small"><?= e($customer->email) ?></div>
                    <?php if ($customer->company !== null && $customer->company !== ''): ?>
                        <div class="muted small"><?= e($customer->company) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="muted">Customer #<?= (int) $request->customerId ?> (deleted or unavailable).</div>
        <?php endif; ?>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="h6 mb-0">Original order</h2>
            <?php if ($order !== null): ?>
                <a href="/commerce/ca-admin/orders/<?= (int) $order->id ?>"
                   class="ra-btn ra-btn-outline ra-btn-sm">
                    View order
                </a>
            <?php endif; ?>
        </div>
        <?php if ($order !== null): ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="muted small">Order number</div>
                    <div><a href="/commerce/ca-admin/orders/<?= (int) $order->id ?>" class="text-decoration-none"><?= e($order->orderNumber) ?></a></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">Placed</div>
                    <div><?= e($fmtDateTime($order->placedAt)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">Order total</div>
                    <div><?= e(number_format($order->grandTotal, 2)) ?> <?= e($order->currencyCode) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="muted small">Refunded so far</div>
                    <div><?= e(number_format($order->refundedTotal, 2)) ?> <?= e($order->currencyCode) ?></div>
                </div>
            </div>
        <?php else: ?>
            <div class="muted">Order #<?= (int) $request->orderId ?> (deleted or unavailable).</div>
        <?php endif; ?>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Return items</h2>
        <?php if (empty($items)): ?>
            <p class="muted mb-0">No line items on this request.</p>
        <?php else: ?>
            <div class="ra-table-responsive-wrap" data-responsive="cards">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="width:120px;text-align:right;">Requested</th>
                            <th style="width:140px;text-align:right;">Unit refund</th>
                            <th style="width:140px;text-align:right;">Line refund</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $line): ?>
                            <?php $oi = $orderItems[$line->orderItemId] ?? null; ?>
                            <tr>
                                <td data-label="Product" data-col="full">
                                    <?php if ($oi !== null): ?>
                                        <div><?= e($oi->productName) ?></div>
                                        <?php if ($oi->variantLabel !== null && $oi->variantLabel !== ''): ?>
                                            <div class="muted small"><?= e($oi->variantLabel) ?></div>
                                        <?php endif; ?>
                                        <?php if ($oi->sku !== null && $oi->sku !== ''): ?>
                                            <div class="muted small">SKU <code><?= e($oi->sku) ?></code></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="muted">Order line #<?= (int) $line->orderItemId ?> (unavailable)</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Requested" data-col="half" style="text-align:right;"><?= (int) $line->quantity ?></td>
                                <td data-label="Unit refund" data-col="half" style="text-align:right;">
                                    <?= e(number_format($line->unitRefund, 2)) ?> <?= e($currency) ?>
                                </td>
                                <td data-label="Line refund" data-col="half" style="text-align:right;">
                                    <?= e(number_format($line->lineRefund, 2)) ?> <?= e($currency) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($request->adminNote !== null && $request->adminNote !== ''): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-2">Internal note</h2>
            <p class="mb-0"><?= nl2br(e((string) $request->adminNote)) ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex gap-2 mb-4">
    <a href="/commerce/ca-admin/returns?status=<?= e($filterBack) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to returns
    </a>
</div>

<?= view_partial('admin/returns/_reject-modal') ?>
<?= view_partial('admin/returns/_receive-modal', ['items' => $items, 'orderItems' => $orderItems]) ?>
<?= view_partial('admin/returns/_refund-modal') ?>
