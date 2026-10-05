<?php
/**
 * Customer-facing order detail at /account/orders/{orderNumber}.
 *
 * Context (from AccountController::orderDetail):
 *   $customer  Customer
 *   $order     Order
 *   $items     array<int, OrderItem>
 */
$customer = $customer ?? null;
$order    = $order    ?? null;
$items    = $items    ?? [];

if ($customer === null || $order === null) {
    return;
}

$orderNumber = (string) $order->orderNumber;

// Human-readable labels.
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

$statusLabel  = $statusLabels[$order->status] ?? ucfirst(str_replace('_', ' ', $order->status));
$paymentLabel = $paymentLabels[$order->paymentProviderKey ?? ''] ?? ($order->paymentProviderKey ?: '—');

$statusClass = match ($order->status) {
    'completed', 'delivered' => 'sf-order-badge-success',
    'cancelled', 'failed'    => 'sf-order-badge-danger',
    'pending_payment'        => 'sf-order-badge-warning',
    default                  => 'sf-order-badge-muted',
};

$paymentStatusLabel = ucfirst(str_replace('_', ' ', $order->paymentStatus));
$fulfilmentStatusLabel = ucfirst(str_replace('_', ' ', $order->fulfilmentStatus));

$shippingAddr = $order->shippingAddress();
$billingAddr  = $order->billingAddress();

$hasTracking = $order->hasTracking();
$trackingUrl = $order->resolvedTrackingUrl();
$carrierLabel = $order->carrierLabel();

$withdrawalEligible = $order->isWithdrawalEligible();
$withdrawalDeadline = $order->withdrawalDeadline();

// Return eligibility. Passed by AccountController::orderDetail().
// Default-guarded here so the view renders correctly even if the
// controller stops sending it (a defensive default that matches
// the pattern every other view uses).
//   canReturn    bool         true when the customer may start a
//                             new return for this order
//   windowCloses ?string      ISO timestamp the window closes, or
//                             null when no delivery date is set
//   openRequest  ?ReturnRequest  an open request if one exists
$returnEligibility = $returnEligibility ?? [
    'canReturn'    => false,
    'windowCloses' => null,
    'openRequest'  => null,
];

// Derived once and used in two places: the header badge (Return
// in progress) and the withdrawal section link. Null when no
// request is open.
$openRequest = $returnEligibility['openRequest'] ?? null;

// In-flight return quantity per order line. Passed by
// AccountController::orderDetail(). Default-guarded here so the
// view renders correctly even if the controller stops sending it.
//   order_item_id => quantity
$inFlightMap = $inFlightMap ?? [];
?>
<div class="sf-account-panel" data-sf-page="account-order-detail">

    <nav class="sf-order-detail-breadcrumb" aria-label="Breadcrumb">
        <a href="/account">Account</a>
        <span aria-hidden="true">&rsaquo;</span>
        <a href="/account/orders">My Orders</a>
        <span aria-hidden="true">&rsaquo;</span>
        <span aria-current="page"><?= e($orderNumber) ?></span>
    </nav>

    <header class="sf-order-detail-header">
        <div>
            <h1 class="sf-order-detail-title"><?= e($orderNumber) ?></h1>
            <p class="sf-order-detail-meta">
                Placed
                <?= $order->placedAt !== null
                    ? e(date('F j, Y', strtotime($order->placedAt)))
                    : '—' ?>
            </p>
        </div>
        <div class="sf-order-detail-badges">
            <span class="sf-order-badge <?= e($statusClass) ?> sf-order-badge-lg">
                <?= e($statusLabel) ?>
            </span>
            <?php if ($openRequest !== null): ?>
                <a href="/account/returns/<?= e($openRequest->requestNumber) ?>"
                   class="sf-order-badge sf-order-badge-lg sf-order-badge-muted">
                    Return in progress
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- ============================ STATUS CARDS ============================ -->
    <section class="sf-order-status-grid">
        <div class="sf-order-status-card">
            <div class="sf-order-status-label">Payment</div>
            <div class="sf-order-status-value"><?= e($paymentStatusLabel) ?></div>
            <div class="sf-order-status-sub"><?= e($paymentLabel) ?></div>
        </div>
        <div class="sf-order-status-card">
            <div class="sf-order-status-label">Fulfilment</div>
            <div class="sf-order-status-value"><?= e($fulfilmentStatusLabel) ?></div>
            <?php if ($order->netDueAt !== null): ?>
                <div class="sf-order-status-sub">
                    Due <?= e(date('M j, Y', strtotime($order->netDueAt))) ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="sf-order-status-card">
            <div class="sf-order-status-label">Order</div>
            <div class="sf-order-status-value"><?= e($statusLabel) ?></div>
        </div>
    </section>

    <!-- ============================ TRACKING ============================ -->
    <?php if ($hasTracking): ?>
        <section class="sf-order-section sf-order-tracking">
            <h2 class="sf-order-section-title">
                <i class="bi bi-truck me-1" aria-hidden="true"></i>
                Tracking
            </h2>
            <div class="sf-order-tracking-body">
                <div>
                    <div class="sf-order-tracking-label">Carrier</div>
                    <div class="sf-order-tracking-value"><?= e((string) $carrierLabel) ?></div>
                </div>
                <div>
                    <div class="sf-order-tracking-label">Tracking number</div>
                    <div class="sf-order-tracking-value">
                        <code><?= e((string) $order->trackingNumber) ?></code>
                    </div>
                </div>
                <?php if ($trackingUrl !== null): ?>
                    <div class="sf-order-tracking-action">
                        <a href="<?= e($trackingUrl) ?>"
                           target="_blank"
                           rel="noopener"
                           class="sf-btn sf-btn-primary">
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>
                            Track package
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ============================ ITEMS ============================ -->
    <section class="sf-order-section">
        <h2 class="sf-order-section-title">Items</h2>
        <div class="sf-order-items">
            <?php foreach ($items as $item): ?>
                <div class="sf-order-item">
                    <div class="sf-order-item-main">
                        <div class="sf-order-item-name"><?= e($item->productName) ?></div>
                        <?php if ($item->variantLabel !== null && $item->variantLabel !== ''): ?>
                            <div class="sf-order-item-variant"><?= e($item->variantLabel) ?></div>
                        <?php endif; ?>
                        <?php if ($item->sku !== null && $item->sku !== ''): ?>
                            <div class="sf-order-item-sku">
                                SKU <code><?= e($item->sku) ?></code>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="sf-order-item-qty">
                        Qty <?= (int) $item->quantity ?>
                        <?php if ($item->returnedQuantity > 0): ?>
                            <span class="sf-order-item-returned">
                                (<?= (int) $item->returnedQuantity ?> of <?= (int) $item->quantity ?> returned)
                            </span>
                        <?php elseif (($inFlightMap[$item->id] ?? 0) > 0): ?>
                            <span class="sf-order-item-returned">
                                (<?= (int) $inFlightMap[$item->id] ?> being returned)
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="sf-order-item-total">
                        <?= e(number_format($item->lineTotal, 2)) ?> <?= e($order->currencyCode) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============================ TOTALS ============================ -->
    <section class="sf-order-section">
        <h2 class="sf-order-section-title">Order total</h2>
        <dl class="sf-order-totals">
            <dt>Subtotal</dt>
            <dd><?= e(number_format($order->subtotal, 2)) ?> <?= e($order->currencyCode) ?></dd>

            <?php if ($order->discountTotal > 0): ?>
                <dt>Discount</dt>
                <dd>&minus;<?= e(number_format($order->discountTotal, 2)) ?> <?= e($order->currencyCode) ?></dd>
            <?php endif; ?>

            <dt>Shipping</dt>
            <dd>
                <?php if ($order->shippingTotal > 0): ?>
                    <?= e(number_format($order->shippingTotal, 2)) ?> <?= e($order->currencyCode) ?>
                <?php else: ?>
                    Free
                <?php endif; ?>
            </dd>

            <dt>VAT</dt>
            <dd class="sf-order-totals-muted">
                <?php if ($order->taxTotal > 0): ?>
                    <?= e(number_format($order->taxTotal, 2)) ?> <?= e($order->currencyCode) ?>
                <?php else: ?>
                    Included in price
                <?php endif; ?>
            </dd>

            <dt class="sf-order-totals-grand">Total</dt>
            <dd class="sf-order-totals-grand">
                <?= e(number_format($order->grandTotal, 2)) ?> <?= e($order->currencyCode) ?>
            </dd>

            <?php if ($order->refundedTotal > 0): ?>
                <dt>Refunded</dt>
                <dd class="sf-order-totals-refund">
                    &minus;<?= e(number_format($order->refundedTotal, 2)) ?> <?= e($order->currencyCode) ?>
                </dd>
                <dt class="sf-order-totals-net">Net paid</dt>
                <dd class="sf-order-totals-net">
                    <?= e(number_format($order->grandTotal - $order->refundedTotal, 2)) ?> <?= e($order->currencyCode) ?>
                </dd>
            <?php endif; ?>
        </dl>
    </section>

    <!-- ============================ ADDRESSES ============================ -->
    <section class="sf-order-section">
        <div class="sf-order-addresses">
            <div>
                <h3 class="sf-order-address-title">Shipping address</h3>
                <?php if ($shippingAddr !== null): ?>
                    <address class="sf-order-address">
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
                    <p class="sf-order-address-empty">—</p>
                <?php endif; ?>
            </div>
            <div>
                <h3 class="sf-order-address-title">Billing address</h3>
                <?php if ($billingAddr !== null): ?>
                    <address class="sf-order-address">
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
                    <p class="sf-order-address-empty">—</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============================ WIDERRUFSRECHT ============================ -->
    <?php if ($withdrawalEligible && $withdrawalDeadline !== null): ?>
        <section class="sf-order-section sf-order-withdrawal">
            <h2 class="sf-order-section-title">Right of withdrawal</h2>
            <p>
                Under German consumer law you have the right to withdraw
                from this purchase within 14 days. The withdrawal window
                for this order closes on
                <strong><?= e(date('F j, Y', strtotime($withdrawalDeadline))) ?></strong>.
            </p>
            <p class="mb-0">
                <a href="/legal/widerruf" class="sf-link" target="_blank" rel="noopener">
                    Read the full withdrawal policy
                </a>
            </p>

            <?php if ($openRequest !== null): ?>
                <p class="mt-3 mb-0">
                    <a href="/account/returns/<?= e($openRequest->requestNumber) ?>" class="sf-link">
                        View your open return request
                    </a>
                </p>
            <?php elseif (!empty($returnEligibility['canReturn'])): ?>
                <p class="mt-3 mb-0">
                    <a href="/account/returns/new/<?= e($order->orderNumber) ?>"
                       class="sf-btn sf-btn-primary sf-btn-sm">
                        Start a return
                    </a>
                </p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- ============================ FOOTER ACTION ============================ -->
    <div class="sf-order-detail-footer">
        <a href="/account/orders" class="sf-btn sf-btn-outline">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
            Back to my orders
        </a>
    </div>

</div>
