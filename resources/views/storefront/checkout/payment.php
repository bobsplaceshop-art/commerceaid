<?php
/**
 * Checkout step 4 - payment method + legal checkboxes.
 *
 * Context (from CheckoutController::payment):
 *   $state            array<string,mixed>
 *   $customer         ?Customer
 *   $availableMethods array<string,string>   key => label
 *   $errors           array<string,string>
 *
 * The legal checkboxes (AGB, Widerrufsbelehrung) are mandatory
 * for German compliance. Checkout cannot proceed without both.
 */
$state            = $state            ?? [];
$customer         = $customer         ?? null;
$availableMethods = $availableMethods ?? [];
$errors           = $errors           ?? [];

$selectedMethod = (string) ($state['payment_method'] ?? '');
$poNumber = (string) ($state['po_number'] ?? '');

$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
?>
<div class="sf-checkout" data-sf-page="checkout-payment">

    <div class="sf-container">
        <?= view_partial('storefront.checkout._steps', ['current' => 4]) ?>

        <div class="sf-checkout-centered">

        <h1 class="sf-checkout-title">Payment</h1>

        <form method="POST" action="/checkout/payment" class="sf-checkout-form">
            <?= csrf_field() ?>

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

            <div class="sf-checkout-actions">
                <a href="/checkout/shipping" class="sf-btn sf-btn-outline">Back</a>
                <button type="submit" class="sf-btn sf-btn-primary">Continue to review</button>
            </div>
        </form>
        </div>
    </div>

</div>
