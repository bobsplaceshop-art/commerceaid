<?php
/**
 * Checkout step 4 - shipping method, payment method, legal
 * checkboxes, and totals.
 *
 * Context (from CheckoutController::payment):
 *   $state                    array<string,mixed>
 *   $customer                 ?Customer
 *   $availableMethods         array<string,string>   payment key => label
 *   $availableShippingMethods array<int,array<string,mixed>>
 *                             shipping methods (code, label, rate, is_pickup)
 *   $selectedShippingMethod   string
 *   $pricing                  array<string,mixed>   totals from OrderPricingService
 *   $errors                   array<string,string>
 *
 * The legal checkboxes (AGB, Widerrufsbelehrung) are mandatory
 * for German compliance. Checkout cannot proceed without both.
 *
 * The totals block shows the pricing breakdown that will
 * become the order. Discount, shipping, and VAT lines render
 * only when nonzero.
 */
$state                    = $state                    ?? [];
$customer                 = $customer                 ?? null;
$availableMethods         = $availableMethods         ?? [];
$availableShippingMethods = $availableShippingMethods ?? [];
$selectedShippingMethod   = $selectedShippingMethod   ?? '';
$pricing                  = $pricing                  ?? [];
$errors                   = $errors                   ?? [];

$selectedMethod = (string) ($state['payment_method'] ?? '');
$poNumber = (string) ($state['po_number'] ?? '');

$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};

$fmt = function ($v): string {
    return number_format((float) $v, 2);
};

// Totals from the pricing preview.
$subtotal      = (float) ($pricing['subtotal']       ?? 0.0);
$discountTotal = (float) ($pricing['discount_total'] ?? 0.0);
$taxTotal      = (float) ($pricing['tax_total']      ?? 0.0);
$shippingTotal = (float) ($pricing['shipping_total'] ?? 0.0);
$grandTotal    = (float) ($pricing['grand_total']    ?? $subtotal);
$coupon        = $pricing['coupon'] ?? null;
$taxClasses    = is_array($pricing['tax_classes'] ?? null) ? $pricing['tax_classes'] : [];

// PAngV § 6 note. tax_note() reads the tax classes and
// picks the correct wording: "inkl." for inclusive pricing,
// "zzgl." for exclusive, or both for a mixed cart.
// See app/Helpers/functions.php.
$vatLabel = tax_note($pricing);
?>
<div class="sf-checkout" data-sf-page="checkout-payment">

    <div class="sf-container">
        <?= view_partial('storefront.checkout._steps', ['current' => 4]) ?>

        <div class="sf-checkout-centered">

        <h1 class="sf-checkout-title">Payment</h1>

        <form method="POST" action="/checkout/payment" class="sf-checkout-form">
            <?= csrf_field() ?>

            <?php if (!empty($availableShippingMethods)): ?>
                <fieldset class="sf-checkout-payment-methods">
                    <legend class="sf-field-label">Shipping method</legend>

                    <?php foreach ($availableShippingMethods as $m): ?>
                        <?php
                        $code  = (string) ($m['code'] ?? '');
                        $label = (string) ($m['label'] ?? '');
                        $rate  = (float) ($m['rate'] ?? 0.0);
                        $isPickup = !empty($m['is_pickup']);
                        ?>
                        <label class="sf-checkout-payment-option">
                            <input type="radio" name="shipping_method_code"
                                   value="<?= e($code) ?>"
                                   <?= $selectedShippingMethod === $code ? 'checked' : '' ?>>
                            <span class="sf-checkout-payment-label">
                                <strong><?= e($label) ?></strong>
                                <span class="muted small">
                                    -
                                    <?php if ($rate <= 0): ?>
                                        Free
                                    <?php else: ?>
                                        <?= e($fmt($rate)) ?>
                                    <?php endif; ?>
                                    <?php if ($isPickup): ?>
                                        (pick up in store)
                                    <?php endif; ?>
                                </span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>

            <?php if ($err('payment_method') !== ''): ?>
                <div class="sf-alert sf-alert-error"><?= e($err('payment_method')) ?></div>
            <?php endif; ?>

            <fieldset class="sf-checkout-payment-methods">
                <legend class="sf-field-label">How would you like to pay?</legend>

                <?php foreach ($availableMethods as $key => $label): ?>
                    <label class="sf-checkout-payment-option">
                        <input type="radio" name="payment_method"
                               value="<?= e($key) ?>"
                               <?= $selectedMethod === $key ? 'checked' : '' ?>>
                        <span class="sf-checkout-payment-label">
                            <strong><?= e($label) ?></strong>
                            <?php if ($key === 'cod'): ?>
                                <span class="muted small"> - Pay when your order is delivered</span>
                            <?php elseif ($key === 'bank_transfer'): ?>
                                <span class="muted small"> - Bank details sent after checkout</span>
                            <?php elseif ($key === 'purchase_order'): ?>
                                <span class="muted small"> - Invoice per your net terms</span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <?php if (isset($availableMethods['purchase_order'])): ?>
                <div class="sf-field" id="po-number-group">
                    <label for="po_number" class="sf-field-label">Purchase order number *</label>
                    <input type="text" id="po_number" name="po_number"
                           class="sf-input <?= $err('po_number') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($poNumber) ?>">
                    <?php if ($err('po_number') !== ''): ?>
                        <div class="sf-form-error"><?= e($err('po_number')) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <fieldset class="sf-checkout-legal">
                <legend class="sf-field-label">Legal</legend>

                <label class="sf-form-check">
                    <input type="checkbox" name="agb_accepted" value="1"
                           <?= $err('agb_accepted') !== '' ? '' : '' ?>>
                    <span>
                        I have read and accept the
                        <a href="/legal/agb" target="_blank" class="sf-link">Terms and Conditions</a>. *
                    </span>
                </label>
                <?php if ($err('agb_accepted') !== ''): ?>
                    <div class="sf-form-error"><?= e($err('agb_accepted')) ?></div>
                <?php endif; ?>

                <label class="sf-form-check">
                    <input type="checkbox" name="widerruf_accepted" value="1">
                    <span>
                        I have read and accept the
                        <a href="/legal/widerruf" target="_blank" class="sf-link">withdrawal policy</a>
                        (Widerrufsbelehrung). *
                    </span>
                </label>
                <?php if ($err('widerruf_accepted') !== ''): ?>
                    <div class="sf-form-error"><?= e($err('widerruf_accepted')) ?></div>
                <?php endif; ?>

                <p class="sf-form-help mt-2">
                    See our
                    <a href="/legal/datenschutz" target="_blank" class="sf-link">Privacy Policy</a>
                    and
                    <a href="/legal/impressum" target="_blank" class="sf-link">Impressum</a>.
                </p>
            </fieldset>

            <div class="sf-checkout-summary">
                <h2 class="sf-checkout-summary-title">Order summary</h2>

                <dl class="sf-checkout-summary-list">
                    <dt>Subtotal</dt>
                    <dd><?= e($fmt($subtotal)) ?></dd>

                    <?php if ($discountTotal > 0 && $coupon !== null): ?>
                        <dt>Discount (<code><?= e((string) ($coupon['code'] ?? '')) ?></code>)</dt>
                        <dd class="sf-checkout-summary-discount">
                            -<?= e($fmt($discountTotal)) ?>
                        </dd>
                    <?php endif; ?>

                    <?php if ($shippingTotal > 0): ?>
                        <dt>Shipping</dt>
                        <dd><?= e($fmt($shippingTotal)) ?></dd>
                    <?php endif; ?>

                    <?php if ($taxTotal > 0): ?>
                        <dt>VAT</dt>
                        <dd><?= e($fmt($taxTotal)) ?></dd>
                    <?php endif; ?>
                </dl>

                <div class="sf-checkout-summary-total">
                    <span class="sf-checkout-summary-total-label">Total</span>
                    <span class="sf-checkout-summary-total-value"><?= e($fmt($grandTotal)) ?></span>
                </div>

                <?php if ($vatLabel !== ''): ?>
                    <p class="sf-checkout-summary-vat-note muted small">
                        <?= e($vatLabel) ?>
                        <?php if ($shippingTotal > 0): ?>
                            , zzgl. Versandkosten
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="sf-checkout-actions">
                <a href="/checkout/shipping" class="sf-btn sf-btn-outline">Back</a>
                <button type="submit" class="sf-btn sf-btn-primary">Continue to review</button>
            </div>
        </form>
        </div>
    </div>

</div>
