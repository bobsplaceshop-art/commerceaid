<?php
/**
 * Checkout step 3 - shipping address.
 *
 * Context (from CheckoutController::shipping):
 *   $state          array<string,mixed>
 *   $savedAddresses array<int, CustomerAddress>
 *   $errors         array<string,string>
 */
$state          = $state          ?? [];
$savedAddresses = $savedAddresses ?? [];
$errors         = $errors         ?? [];

$shipping = $state['shipping_address'] ?? [];
$billing  = $state['billing_address']  ?? [];

$val = function (array $addr, string $key, string $default = ''): string {
    return (string) ($addr[$key] ?? $default);
};
$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
?>
<div class="sf-checkout" data-sf-page="checkout-shipping">

    <div class="sf-container">
        <?= view_partial('storefront.checkout._steps', ['current' => 3]) ?>

        <div class="sf-checkout-centered">

        <h1 class="sf-checkout-title">Shipping address</h1>

        <?php if ($err('shipping') !== ''): ?>
            <div class="sf-alert sf-alert-error"><?= e($err('shipping')) ?></div>
        <?php endif; ?>

        <form method="POST" action="/checkout/shipping" class="sf-checkout-form">
            <?= csrf_field() ?>

            <?php if ($savedAddresses !== []): ?>
                <fieldset class="sf-checkout-addresses">
                    <legend class="sf-field-label">Choose a saved address</legend>
                    <?php foreach ($savedAddresses as $addr): ?>
                        <label class="sf-checkout-address-option">
                            <input type="radio" name="saved_shipping_address_id"
                                   value="<?= (int) $addr->id ?>">
                            <span class="sf-checkout-address-body">
                                <strong><?= e($addr->firstName . ' ' . $addr->lastName) ?></strong><br>
                                <?= e($addr->addressLine1) ?><br>
                                <?php if ($addr->addressLine2): ?>
                                    <?= e($addr->addressLine2) ?><br>
                                <?php endif; ?>
                                <?= e($addr->city) ?>, <?= e($addr->postalCode) ?><br>
                                <?= e($addr->countryCode) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <label class="sf-checkout-address-option">
                        <input type="radio" name="saved_shipping_address_id" value="0" checked>
                        <span class="sf-checkout-address-body">
                            Use a new address
                        </span>
                    </label>
                </fieldset>
            <?php endif; ?>

            <fieldset class="sf-checkout-address-fields">
                <legend class="sf-field-label">Enter a shipping address</legend>

                <div class="sf-form-row-split">
                    <div class="sf-field">
                        <label for="shipping_first_name" class="sf-field-label">First name *</label>
                        <input type="text" id="shipping_first_name" name="shipping_first_name"
                               class="sf-input"
                               value="<?= e($val($shipping, 'first_name')) ?>">
                    </div>
                    <div class="sf-field">
                        <label for="shipping_last_name" class="sf-field-label">Last name *</label>
                        <input type="text" id="shipping_last_name" name="shipping_last_name"
                               class="sf-input"
                               value="<?= e($val($shipping, 'last_name')) ?>">
                    </div>
                </div>

                <div class="sf-field">
                    <label for="shipping_company" class="sf-field-label">Company</label>
                    <input type="text" id="shipping_company" name="shipping_company"
                           class="sf-input"
                           value="<?= e($val($shipping, 'company')) ?>">
                </div>

                <div class="sf-field">
                    <label for="shipping_address_line_1" class="sf-field-label">Address line 1 *</label>
                    <input type="text" id="shipping_address_line_1" name="shipping_address_line_1"
                           class="sf-input"
                           value="<?= e($val($shipping, 'address_line_1')) ?>">
                </div>
                <div class="sf-field">
                    <label for="shipping_address_line_2" class="sf-field-label">Address line 2</label>
                    <input type="text" id="shipping_address_line_2" name="shipping_address_line_2"
                           class="sf-input"
                           value="<?= e($val($shipping, 'address_line_2')) ?>">
                </div>

                <div class="sf-form-row-split">
                    <div class="sf-field">
                        <label for="shipping_city" class="sf-field-label">City *</label>
                        <input type="text" id="shipping_city" name="shipping_city"
                               class="sf-input"
                               value="<?= e($val($shipping, 'city')) ?>">
                    </div>
                    <div class="sf-field">
                        <label for="shipping_postal_code" class="sf-field-label">Postal code *</label>
                        <input type="text" id="shipping_postal_code" name="shipping_postal_code"
                               class="sf-input"
                               value="<?= e($val($shipping, 'postal_code')) ?>">
                    </div>
                </div>

                <div class="sf-form-row-split">
                    <div class="sf-field">
                        <label for="shipping_state" class="sf-field-label">State / region</label>
                        <input type="text" id="shipping_state" name="shipping_state"
                               class="sf-input"
                               value="<?= e($val($shipping, 'state')) ?>">
                    </div>
                    <div class="sf-field">
                        <label for="shipping_country_code" class="sf-field-label">Country *</label>
                        <input type="text" id="shipping_country_code" name="shipping_country_code"
                               class="sf-input" maxlength="2"
                               value="<?= e($val($shipping, 'country_code', 'DE')) ?>">
                    </div>
                </div>

                <div class="sf-form-row-split">
                    <div class="sf-field">
                        <label for="shipping_phone" class="sf-field-label">Phone</label>
                        <input type="tel" id="shipping_phone" name="shipping_phone"
                               class="sf-input"
                               value="<?= e($val($shipping, 'phone')) ?>">
                    </div>
                    <div class="sf-field">
                        <label for="shipping_email" class="sf-field-label">Email</label>
                        <input type="email" id="shipping_email" name="shipping_email"
                               class="sf-input"
                               value="<?= e($val($shipping, 'email')) ?>">
                    </div>
                </div>
            </fieldset>

            <div class="sf-field">
                <label class="sf-form-check">
                    <input type="checkbox" checked disabled>
                    <span>Billing address is the same as shipping</span>
                </label>
                <div class="sf-form-help">
                    Separate billing addresses are coming in a future update.
                </div>
            </div>

            <div class="sf-checkout-actions">
                <a href="/checkout/info" class="sf-btn sf-btn-outline">Back</a>
                <button type="submit" class="sf-btn sf-btn-primary">Continue to payment</button>
            </div>
        </form>
        </div>
    </div>

</div>
