<?php
/**
 * Admin order detail.
 *
 * Context (from AdminOrderController::show):
 *   $order              Order
 *   $items              array<int, OrderItem>
 *   $statusHistory      array<int, OrderStatusHistory>
 *   $notes              array<int, OrderNote>
 *   $customer           ?Customer
 *   $allowedTransitions list<string>  statuses this order may move to
 *
 * Order model fields used:
 *   orderNumber, status, paymentStatus, fulfilmentStatus,
 *   paymentProviderKey, netDueAt, hasTracking(), carrierLabel(),
 *   resolvedTrackingUrl(), trackingNumber, shippingAddress(),
 *   billingAddress(), currencyCode, subtotal, discountTotal,
 *   shippingTotal, taxTotal, grandTotal, refundedTotal,
 *   customerNote, internalNote, customerId, guestEmail, placedAt
 */

$order              = $order              ?? null;
$items              = $items              ?? [];
$statusHistory      = $statusHistory      ?? [];
$notes              = $notes              ?? [];
$customer           = $customer           ?? null;
$allowedTransitions = $allowedTransitions ?? [];

if ($order === null) {
    return;
}

$orderNumber = (string) $order->orderNumber;

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
$paymentLabels = [
    'cod'            => 'Cash on delivery',
    'bank_transfer'  => 'Bank transfer',
    'purchase_order' => 'Purchase order',
];

$statusLabel   = $statusLabels[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status));
$paymentLabel  = $paymentLabels[$order->paymentProviderKey ?? ''] ?? ($order->paymentProviderKey ?: '—');
$paymentStatusLabel = ucfirst(str_replace('_', ' ', $order->paymentStatus));
$fulfilmentStatusLabel = ucfirst(str_replace('_', ' ', $order->fulfilmentStatus));

// Status badge helper for the transition timeline rows.
$statusLabelFor = function (string $status): string {
    $map = [
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
        'created'            => 'Created',
    ];
    return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
};

$statusBadgeClass = match ($order->status) {
    'completed', 'delivered' => 'ra-badge ra-badge-success',
    'cancelled', 'failed'    => 'ra-badge',
    'pending_payment'        => 'ra-badge ra-badge-warning',
    default                  => 'ra-badge ra-badge-muted',
};
$statusBadgeStyle = match ($order->status) {
    'cancelled', 'failed' => 'style="background:rgba(220,38,38,0.15);color:#dc2626;"',
    default               => '',
};

$shippingAddr = $order->shippingAddress();
$billingAddr  = $order->billingAddress();

$hasTracking  = $order->hasTracking();
$trackingUrl  = $order->resolvedTrackingUrl();
$carrierLabel = $order->carrierLabel();

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y \a\t H:i', $ts);
};
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/orders" class="text-decoration-none">Orders</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($orderNumber) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($orderNumber) ?></h1>
        <p class="muted mb-0">
            Placed <?= e($fmtDateTime($order->placedAt)) ?>
            <?php if ($order->paidAt !== null): ?>
                &middot; Paid <?= e($fmtDateTime($order->paidAt)) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="<?= e($statusBadgeClass) ?>" <?= $statusBadgeStyle ?>>
            <?= e($statusLabel) ?>
        </span>
        <span class="ra-badge">
            Payment: <?= e($paymentStatusLabel) ?>
        </span>
        <span class="ra-badge">
            Fulfilment: <?= e($fulfilmentStatusLabel) ?>
        </span>
    </div>
</div>

<?php if (!empty($allowedTransitions)): ?>
    <div class="ra-card mb-3">
        <div class="ra-card-body py-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="muted small me-2">Move to:</span>
                <?php foreach ($allowedTransitions as $target): ?>
                    <button type="button"
                            class="ra-btn ra-btn-outline ra-btn-sm"
                            data-ra-transition-open
                            data-transition-to="<?= e($target) ?>"
                            data-transition-label="<?= e($statusLabelFor($target)) ?>"
                            data-order-id="<?= (int) $order->id ?>"
                            data-order-number="<?= e($orderNumber) ?>">
                        <?= e($statusLabelFor($target)) ?>
                    </button>
                <?php endforeach; ?>
                <button type="button"
                        class="ra-btn ra-btn-outline ra-btn-sm ms-auto"
                        data-ra-note-open
                        data-order-id="<?= (int) $order->id ?>"
                        data-order-number="<?= e($orderNumber) ?>">
                    <i class="bi bi-journal-plus me-1"></i> Add internal note
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================ STATUS CARDS ============================ -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Payment</div>
                <div style="font-weight:600;font-size:1.05rem;"><?= e($paymentStatusLabel) ?></div>
                <div class="muted small mt-1"><?= e($paymentLabel) ?></div>
                <?php if ($order->paymentReference !== null && $order->paymentReference !== ''): ?>
                    <div class="muted small">Ref: <code><?= e($order->paymentReference) ?></code></div>
                <?php endif; ?>
                <?php if ($order->poNumber !== null && $order->poNumber !== ''): ?>
                    <div class="muted small">PO: <code><?= e($order->poNumber) ?></code></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Fulfilment</div>
                <div style="font-weight:600;font-size:1.05rem;"><?= e($fulfilmentStatusLabel) ?></div>
                <?php if ($order->netDueAt !== null): ?>
                    <div class="muted small mt-1">
                        Due <?= e($fmtDateTime($order->netDueAt)) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Order</div>
                <div style="font-weight:600;font-size:1.05rem;"><?= e($statusLabel) ?></div>
                <div class="muted small mt-1">
                    <?= count($items) ?> line<?= count($items) === 1 ? '' : 's' ?>
                    &middot; <?= e($order->currencyCode) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ CUSTOMER ============================ -->
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
        <?php elseif ($order->guestEmail !== null && $order->guestEmail !== ''): ?>
            <div class="muted">
                Guest order placed with
                <code><?= e($order->guestEmail) ?></code>
            </div>
        <?php else: ?>
            <div class="muted">Guest order with no email on file.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ TRACKING ============================ -->
<?php if ($hasTracking): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">
                <i class="bi bi-truck me-1" aria-hidden="true"></i>
                Tracking
            </h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="muted small">Carrier</div>
                    <div><?= e((string) $carrierLabel) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="muted small">Tracking number</div>
                    <div><code><?= e((string) $order->trackingNumber) ?></code></div>
                </div>
                <?php if ($trackingUrl !== null): ?>
                    <div class="col-md-4 d-flex align-items-end">
                        <a href="<?= e($trackingUrl) ?>"
                           target="_blank"
                           rel="noopener"
                           class="ra-btn ra-btn-primary ra-btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>
                            Track package
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ============================ ITEMS ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Items</h2>
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="width:120px;text-align:right;">Qty</th>
                        <th style="width:140px;text-align:right;">Unit price</th>
                        <th style="width:140px;text-align:right;">Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div><?= e($item->productName) ?></div>
                                <?php if ($item->variantLabel !== null && $item->variantLabel !== ''): ?>
                                    <div class="muted small"><?= e($item->variantLabel) ?></div>
                                <?php endif; ?>
                                <?php if ($item->sku !== null && $item->sku !== ''): ?>
                                    <div class="muted small">SKU <code><?= e($item->sku) ?></code></div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <?= (int) $item->quantity ?>
                                <?php if ($item->returnedQuantity > 0): ?>
                                    <div class="muted small">
                                        <?= (int) $item->returnedQuantity ?> returned
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">
                                <?= e(number_format($item->unitPrice, 2)) ?> <?= e($order->currencyCode) ?>
                            </td>
                            <td style="text-align:right;">
                                <?= e(number_format($item->lineTotal, 2)) ?> <?= e($order->currencyCode) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================ TOTALS ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Order total</h2>
        <dl class="row mb-0" style="max-width:420px;margin-left:auto;">
            <dt class="col-6">Subtotal</dt>
            <dd class="col-6 text-end"><?= e(number_format($order->subtotal, 2)) ?> <?= e($order->currencyCode) ?></dd>

            <?php if ($order->discountTotal > 0): ?>
                <dt class="col-6">Discount</dt>
                <dd class="col-6 text-end">&minus;<?= e(number_format($order->discountTotal, 2)) ?> <?= e($order->currencyCode) ?></dd>
            <?php endif; ?>

            <dt class="col-6">Shipping</dt>
            <dd class="col-6 text-end">
                <?php if ($order->shippingTotal > 0): ?>
                    <?= e(number_format($order->shippingTotal, 2)) ?> <?= e($order->currencyCode) ?>
                <?php else: ?>
                    Free
                <?php endif; ?>
            </dd>

            <dt class="col-6">VAT</dt>
            <dd class="col-6 text-end muted">
                <?php if ($order->taxTotal > 0): ?>
                    <?= e(number_format($order->taxTotal, 2)) ?> <?= e($order->currencyCode) ?>
                <?php else: ?>
                    Included in price
                <?php endif; ?>
            </dd>

            <dt class="col-6" style="font-weight:600;border-top:1px solid var(--ra-border,#e5e1d8);padding-top:.5rem;">Total</dt>
            <dd class="col-6 text-end" style="font-weight:600;border-top:1px solid var(--ra-border,#e5e1d8);padding-top:.5rem;">
                <?= e(number_format($order->grandTotal, 2)) ?> <?= e($order->currencyCode) ?>
            </dd>

            <?php if ($order->paidTotal > 0): ?>
                <dt class="col-6">Paid</dt>
                <dd class="col-6 text-end"><?= e(number_format($order->paidTotal, 2)) ?> <?= e($order->currencyCode) ?></dd>
            <?php endif; ?>

            <?php if ($order->refundedTotal > 0): ?>
                <dt class="col-6" style="color:#991b1b;font-weight:500;">Refunded</dt>
                <dd class="col-6 text-end" style="color:#991b1b;font-weight:500;">
                    &minus;<?= e(number_format($order->refundedTotal, 2)) ?> <?= e($order->currencyCode) ?>
                </dd>
                <dt class="col-6" style="font-weight:600;border-top:1px solid var(--ra-border,#e5e1d8);padding-top:.5rem;">Net paid</dt>
                <dd class="col-6 text-end" style="font-weight:600;border-top:1px solid var(--ra-border,#e5e1d8);padding-top:.5rem;">
                    <?= e(number_format($order->grandTotal - $order->refundedTotal, 2)) ?> <?= e($order->currencyCode) ?>
                </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<!-- ============================ ADDRESSES ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <h3 class="h6 mb-2">Shipping address</h3>
                <?php if ($shippingAddr !== null): ?>
                    <address class="mb-0" style="font-style:normal;line-height:1.65;">
                        <?= e((string) ($shippingAddr['first_name'] ?? '')) ?>
                        <?= e((string) ($shippingAddr['last_name'] ?? '')) ?><br>
                        <?php if (!empty($shippingAddr['company'])): ?>
                            <?= e((string) $shippingAddr['company']) ?><br>
                        <?php endif; ?>
                        <?= e((string) ($shippingAddr['address_line_1'] ?? '')) ?><br>
                        <?php if (!empty($shippingAddr['address_line_2'])): ?>
                            <?= e((string) $shippingAddr['address_line_2']) ?><br>
                        <?php endif; ?>
                        <?= e((string) ($shippingAddr['postal_code'] ?? '')) ?>
                        <?= e((string) ($shippingAddr['city'] ?? '')) ?><br>
                        <?php if (!empty($shippingAddr['state'])): ?>
                            <?= e((string) $shippingAddr['state']) ?><br>
                        <?php endif; ?>
                        <?= e((string) ($shippingAddr['country_code'] ?? '')) ?>
                    </address>
                <?php else: ?>
                    <p class="muted mb-0">—</p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h3 class="h6 mb-2">Billing address</h3>
                <?php if ($billingAddr !== null): ?>
                    <address class="mb-0" style="font-style:normal;line-height:1.65;">
                        <?= e((string) ($billingAddr['first_name'] ?? '')) ?>
                        <?= e((string) ($billingAddr['last_name'] ?? '')) ?><br>
                        <?php if (!empty($billingAddr['company'])): ?>
                            <?= e((string) $billingAddr['company']) ?><br>
                        <?php endif; ?>
                        <?= e((string) ($billingAddr['address_line_1'] ?? '')) ?><br>
                        <?php if (!empty($billingAddr['address_line_2'])): ?>
                            <?= e((string) $billingAddr['address_line_2']) ?><br>
                        <?php endif; ?>
                        <?= e((string) ($billingAddr['postal_code'] ?? '')) ?>
                        <?= e((string) ($billingAddr['city'] ?? '')) ?><br>
                        <?php if (!empty($billingAddr['state'])): ?>
                            <?= e((string) $billingAddr['state']) ?><br>
                        <?php endif; ?>
                        <?= e((string) ($billingAddr['country_code'] ?? '')) ?>
                    </address>
                <?php else: ?>
                    <p class="muted mb-0">—</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================ CUSTOMER NOTE ============================ -->
<?php if ($order->customerNote !== null && $order->customerNote !== ''): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-2">Customer note</h2>
            <p class="mb-0"><?= nl2br(e($order->customerNote)) ?></p>
        </div>
    </div>
<?php endif; ?>

<!-- ============================ INTERNAL NOTES ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="h6 mb-0">Internal notes (<?= count($notes) ?>)</h2>
            <button type="button"
                    class="ra-btn ra-btn-outline ra-btn-sm"
                    data-ra-note-open
                    data-order-id="<?= (int) $order->id ?>"
                    data-order-number="<?= e($orderNumber) ?>">
                <i class="bi bi-journal-plus me-1"></i> Add note
            </button>
        </div>
        <?php if (empty($notes)): ?>
            <p class="muted mb-0">No internal notes yet.</p>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($notes as $note): ?>
                    <div class="border-bottom pb-3" style="border-color:var(--ra-border,#e5e1d8) !important;">
                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-1">
                            <span class="muted small">
                                <?= $note->createdBy !== null
                                    ? 'Admin user #' . (int) $note->createdBy
                                    : 'System' ?>
                            </span>
                            <span class="muted small"><?= e($fmtDateTime($note->createdAt)) ?></span>
                        </div>
                        <div><?= nl2br(e($note->note)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ STATUS HISTORY ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Status history</h2>
        <?php if (empty($statusHistory)): ?>
            <p class="muted mb-0">No status transitions recorded.</p>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($statusHistory as $h): ?>
                    <div class="d-flex gap-3">
                        <div style="flex-shrink:0;width:150px;" class="muted small">
                            <?= e($fmtDateTime($h->createdAt)) ?>
                        </div>
                        <div style="min-width:0;">
                            <div>
                                <?php if ($h->isCreation()): ?>
                                    <span class="ra-badge ra-badge-muted">Created</span>
                                    <span class="ms-2">Order placed</span>
                                <?php else: ?>
                                    <span class="ra-badge ra-badge-muted"><?= e($statusLabelFor((string) $h->fromStatus)) ?></span>
                                    <i class="bi bi-arrow-right mx-1"></i>
                                    <span class="ra-badge ra-badge-muted"><?= e($statusLabelFor($h->toStatus)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="muted small mt-1">
                                <?= $h->isByAdmin()
                                    ? 'by admin user #' . (int) $h->changedBy
                                    : 'by customer or system' ?>
                            </div>
                            <?php if ($h->hasNote()): ?>
                                <div class="mt-1"><?= nl2br(e((string) $h->note)) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ FOOTER ============================ -->
<div class="d-flex gap-2 mb-4">
    <a href="/commerce/ca-admin/orders" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to orders
    </a>
</div>

<?= view_partial('admin/orders/_status-modal', ['order' => $order]) ?>
<?= view_partial('admin/orders/_note-modal') ?>
