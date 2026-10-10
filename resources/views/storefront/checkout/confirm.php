<?php
/**
 * Checkout step 5 - review and place order.
 *
 * Context (from CheckoutController::confirm):
 *   $state    array<string,mixed>
 *   $lines    array<int, CartLineItem>
 *   $subtotal float
 *   $pricing  array<string,mixed>  totals from OrderPricingService
 *   $errors   array<string,string>
 */
$state    = $state    ?? [];
$lines    = $lines    ?? [];
$subtotal = $subtotal ?? 0.0;
$pricing  = $pricing  ?? [];
$errors   = $errors   ?? [];

$shipping = $state['shipping_address'] ?? [];
$billing  = $state['billing_address']  ?? [];
$payment  = (string) ($state['payment_method'] ?? '');
$poNumber = (string) ($state['po_number'] ?? '');

$discountTotal = (float) ($pricing['discount_total'] ?? 0.0);
$taxTotal      = (float) ($pricing['tax_total']      ?? 0.0);
$shippingTotal = (float) ($pricing['shipping_total'] ?? 0.0);
$grandTotal    = (float) ($pricing['grand_total']    ?? $subtotal);
$coupon        = $pricing['coupon'] ?? null;
$shippingMethod = $pricing['shipping_method'] ?? null;
$taxClasses    = is_array($pricing['tax_classes'] ?? null) ? $pricing['tax_classes'] : [];

$fmt = function ($v): string {
    return number_format((float) $v, 2);
};

$vatLabel = '';
$vatParts = [];
foreach ($taxClasses as $c) {
    $rate = (float) ($c['rate_percent'] ?? 0);
    if ($rate <= 0) continue;
    $vatParts[] = 'inkl. ' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '% MwSt.';
}
if ($vatParts !== []) {
    $vatLabel = implode(' · ', $vatParts);
}

$paymentLabels = [
    'cod'            => 'Cash on delivery',
    'bank_transfer'  => 'Bank transfer',
    'purchase_order' => 'Purchase order',
];
$paymentLabel = $paymentLabels[$payment] ?? $payment;

$addrLine = function (array $addr): string {
    $parts = array_filter([
        trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
        $addr['company'] ?? '',
        $addr['address_line_1'] ?? '',
        $addr['address_line_2'] ?? '',
        trim(($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '')),
        $addr['state'] ?? '',
        $addr['country_code'] ?? '',
    ]);
    return implode("\n", $parts);
};
?>
<div class="sf-checkout" data-sf-page="checkout-confirm">

    <div class="sf-container">
        <?= view_partial('storefront.checkout._steps', ['current' => 5]) ?>

        <h1 class="sf-checkout-title">Confirm your order</h1>

        <?php if (!empty($errors['_general'])): ?>
            <div class="sf-alert sf-alert-error"><?= e($errors['_general']) ?></div>
        <?php endif; ?>

        <div class="sf-checkout-confirm-grid">
            <div class="sf-checkout-confirm-left">

                <section class="sf-checkout-block">
                    <h2 class="sf-checkout-block-title">Items</h2>
                    <div class="sf-checkout-lines">
                        <?php foreach ($lines as $line): ?>
                            <div class="sf-checkout-line">
                                <div class="sf-checkout-line-info">
                                    <div class="sf-checkout-line-name">
                                        <?= e($line->item->productName) ?>
                                    </div>
                                    <?php if ($line->item->variantLabel): ?>
                                        <div class="muted small"><?= e($line->item->variantLabel) ?></div>
                                    <?php endif; ?>
                                    <div class="muted small">Qty <?= (int) $line->item->quantity ?></div>
                                </div>
                                <div class="sf-checkout-line-total">
                                    <?= e(number_format($line->effectiveSubtotal(), 2)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="sf-checkout-block">
                    <h2 class="sf-checkout-block-title">Shipping address</h2>
                    <pre class="sf-checkout-address-preview"><?= e($addrLine($shipping)) ?></pre>
                </section>

                <section class="sf-checkout-block">
                    <h2 class="sf-checkout-block-title">Billing address</h2>
                    <pre class="sf-checkout-address-preview"><?= e($addrLine($billing)) ?></pre>
                </section>

                <?php if ($shippingMethod !== null): ?>
                    <section class="sf-checkout-block">
                        <h2 class="sf-checkout-block-title">Shipping method</h2>
                        <p class="mb-0"><?= e((string) ($shippingMethod['label'] ?? '')) ?></p>
                        <?php if ($shippingTotal > 0): ?>
                            <p class="muted small mb-0"><?= e($fmt($shippingTotal)) ?></p>
                        <?php else: ?>
                            <p class="muted small mb-0">Free</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="sf-checkout-block">
                    <h2 class="sf-checkout-block-title">Payment</h2>
                    <p class="mb-0"><?= e($paymentLabel) ?></p>
                    <?php if ($payment === 'purchase_order' && $poNumber !== ''): ?>
                        <p class="muted small mb-0">PO number: <?= e($poNumber) ?></p>
                    <?php endif; ?>
                </section>

            </div>

            <aside class="sf-checkout-summary">
                <h2 class="sf-checkout-summary-title">Order total</h2>
                <dl class="sf-checkout-summary-list">
                    <dt>Subtotal</dt>
                    <dd><?= e($fmt($subtotal)) ?></dd>

                    <?php if ($discountTotal > 0 && $coupon !== null): ?>
                        <dt>Discount</dt>
                        <dd class="sf-checkout-summary-discount">
                            -<?= e($fmt($discountTotal)) ?>
                        </dd>
                    <?php endif; ?>

                    <?php if ($shippingTotal > 0): ?>
                        <dt>Shipping</dt>
                        <dd><?= e($fmt($shippingTotal)) ?></dd>
                    <?php elseif ($shippingMethod !== null): ?>
                        <dt>Shipping</dt>
                        <dd class="muted">Free</dd>
                    <?php endif; ?>

                    <?php if ($taxTotal > 0): ?>
                        <dt>VAT</dt>
                        <dd><?= e($fmt($taxTotal)) ?></dd>
                    <?php endif; ?>
                </dl>
                <div class="sf-checkout-summary-total">
                    <span>Total</span>
                    <span><?= e($fmt($grandTotal)) ?></span>
                </div>

                <?php if ($vatLabel !== ''): ?>
                    <p class="sf-checkout-summary-note muted small">
                        <?= e($vatLabel) ?>
                        <?php if ($shippingTotal > 0): ?>
                            , zzgl. Versandkosten
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <form method="POST" action="/checkout/place" class="mt-3">
                    <?= csrf_field() ?>
                    <button type="submit" class="sf-btn sf-btn-primary sf-btn-block">
                        Place order
                    </button>
                </form>

                <a href="/checkout/payment" class="sf-btn sf-btn-outline sf-btn-block mt-2">
                    Back
                </a>
            </aside>
        </div>
    </div>

</div>
