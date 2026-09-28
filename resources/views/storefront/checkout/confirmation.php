<?php
/**
 * Order confirmation page. Reached after a successful place().
 *
 * Context (from CheckoutController::confirmation):
 *   $order  Order
 *
 * The customer can refresh this page freely - the order is
 * already committed. No second order is created.
 */
$order = $order ?? null;

if ($order === null) {
    echo '<div class="sf-container"><p>Order not found.</p></div>';
    return;
}

$paymentLabels = [
    'cod'            => 'Cash on delivery',
    'bank_transfer'  => 'Bank transfer',
    'purchase_order' => 'Purchase order',
];
$paymentLabel = $paymentLabels[$order->paymentProviderKey ?? ''] ?? 'Payment';
?>
<div class="sf-checkout" data-sf-page="checkout-confirmation">

    <div class="sf-container">

        <div class="sf-checkout-confirmation-hero">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <h1 class="sf-checkout-title">Thank you for your order</h1>
            <p class="sf-checkout-subtitle">
                Order <strong><?= e($order->orderNumber) ?></strong> is confirmed.
            </p>
        </div>

        <div class="sf-checkout-summary sf-checkout-summary-wide">
            <h2 class="sf-checkout-summary-title">Order summary</h2>
            <dl class="sf-checkout-summary-list">
                <dt>Order number</dt>
                <dd><?= e($order->orderNumber) ?></dd>

                <dt>Total</dt>
                <dd><?= e(number_format($order->grandTotal, 2)) ?> <?= e($order->currencyCode) ?></dd>

                <dt>Payment method</dt>
                <dd><?= e($paymentLabel) ?></dd>

                <?php if ($order->poNumber !== null && $order->poNumber !== ''): ?>
                    <dt>PO number</dt>
                    <dd><?= e($order->poNumber) ?></dd>
                <?php endif; ?>

                <?php if ($order->netDueAt !== null): ?>
                    <dt>Payment due</dt>
                    <dd><?= e(date('M j, Y', strtotime($order->netDueAt))) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($order->paymentProviderKey === 'bank_transfer'): ?>
                <div class="sf-alert sf-alert-info mt-3">
                    <strong>Bank transfer details</strong>
                    <p class="mb-0 small mt-1">
                        You will receive the bank account details by email.
                        Please include the order number
                        <strong><?= e($order->orderNumber) ?></strong>
                        as the payment reference.
                    </p>
                </div>
            <?php elseif ($order->paymentProviderKey === 'cod'): ?>
                <div class="sf-alert sf-alert-info mt-3">
                    <p class="mb-0 small">
                        Payment will be collected on delivery.
                    </p>
                </div>
            <?php endif; ?>

            <p class="muted small mt-3">
                A confirmation email has been queued for the address on this order.
            </p>

            <div class="sf-checkout-actions mt-3">
                <a href="/" class="sf-btn sf-btn-outline">Back to shop</a>
                <a href="/products" class="sf-btn sf-btn-primary">Continue shopping</a>
            </div>
        </div>

    </div>

</div>
