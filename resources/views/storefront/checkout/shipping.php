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

// Old input from the flash. On validation failure, the state
// has no address yet, but the customer's typed values are in
// the flash. These keys carry the full form field names
// (shipping_first_name, shipping_city, etc.).
$old = $_SESSION['_old'] ?? [];

$val = function (array $addr, string $key, string $default = '') use ($old): string {
    // Prefer the address array (state on a successful submit).
    if (isset($addr[$key]) && $addr[$key] !== '') {
        return (string) $addr[$key];
    }
    // Fall back to the flash, keyed as shipping_<field>.
    $flashKey = 'shipping_' . $key;
    if (isset($old[$flashKey]) && $old[$flashKey] !== '') {
        return (string) $old[$flashKey];
    }
    return $default;
};
$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
// Per-field error helper. Reads the flash key prefixed with
// the field's context - shipping_first_name for shipping,
// billing_first_name for billing.
$fieldErr = function (string $field, string $context = 'shipping') use ($err): string {
    return $err($context . '_' . $field);
};
// Has the customer selected a saved address? If so, the manual
// form is hidden.
$selectedSavedId = (int) ($_POST['saved_shipping_address_id'] ?? 0);
$hasSavedSelection = $selectedSavedId > 0;

// Country catalog data for the searchable dropdown. The
// view passes it into the component as a JSON attribute.
// The helper reads CountryCatalog once; the browser renders
// every row from the payload.
$__countryCatalog = \CorporateAid\Commerce\Support\CountryCatalog::all();
$__countryJson    = json_encode(
    array_map(
        static fn (string $code, array $c): array => [
            'code' => $code,
            'name' => $c['name'],
            'flag' => $c['flag'],
        ],
        array_keys($__countryCatalog),
        array_values($__countryCatalog)
    ),
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
$__countryCurrent = strtoupper((string) $val($shipping, 'country_code', 'DE'));
$__countryName    = \CorporateAid\Commerce\Support\CountryCatalog::name($__countryCurrent) ?? '';
$__countryFlag    = \CorporateAid\Commerce\Support\CountryCatalog::flag($__countryCurrent);
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

            <fieldset class="sf-checkout-address-fields" id="sf-manual-shipping-fields"<?= $hasSavedSelection ? ' hidden' : '' ?>>
                <legend class="sf-field-label">Enter a shipping address</legend>

                <div class="sf-form-row-split">
                    <div class="sf-field">
                        <label for="shipping_first_name" class="sf-field-label">First name *</label>
                        <input type="text" id="shipping_first_name" name="shipping_first_name"
                               class="sf-input"
                               value="<?= e($val($shipping, 'first_name')) ?>">
                        <?php if ($fieldErr('first_name') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('first_name')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="sf-field">
                        <label for="shipping_last_name" class="sf-field-label">Last name *</label>
                        <input type="text" id="shipping_last_name" name="shipping_last_name"
                               class="sf-input"
                               value="<?= e($val($shipping, 'last_name')) ?>">
                        <?php if ($fieldErr('last_name') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('last_name')) ?></div>
                        <?php endif; ?>
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
                        <?php if ($fieldErr('address_line_1') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('address_line_1')) ?></div>
                        <?php endif; ?>
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
                        <?php if ($fieldErr('city') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('city')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="sf-field">
                        <label for="shipping_postal_code" class="sf-field-label">Postal code *</label>
                        <input type="text" id="shipping_postal_code" name="shipping_postal_code"
                               class="sf-input"
                               value="<?= e($val($shipping, 'postal_code')) ?>">
                        <?php if ($fieldErr('postal_code') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('postal_code')) ?></div>
                        <?php endif; ?>
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
                        <div class="sf-country-select"
                             data-sf-country-select
                             data-sf-country-options='<?= $__countryJson ?>'>
                            <input type="text"
                                   id="shipping_country_code"
                                   name="shipping_country_code"
                                   class="sf-input sf-country-select-input"
                                   value="<?= e($__countryCurrent) ?>"
                                   maxlength="2"
                                   data-sf-country-input>
                            <button type="button"
                                    class="sf-country-select-toggle"
                                    data-sf-country-toggle
                                    aria-haspopup="listbox"
                                    aria-expanded="false">
                                <span data-sf-country-display class="sf-country-select-display">
                                    <img src="/assets/flags/<?= e(strtolower($__countryCurrent)) ?>.svg"
                                         alt=""
                                         width="20"
                                         height="15"
                                         class="sf-country-select-flag"
                                         onerror="this.style.visibility='hidden';">
                                    <span class="sf-country-select-display-name"><?= e($__countryName) ?></span>
                                </span>
                            </button>
                            <div class="sf-country-select-panel"
                                 data-sf-country-panel
                                 role="listbox"
                                 hidden>
                                <input type="text"
                                       class="sf-country-select-search"
                                       data-sf-country-search
                                       placeholder="Search countries..."
                                       autocomplete="off"
                                       aria-label="Search countries">
                                <ul class="sf-country-select-list" data-sf-country-list></ul>
                            </div>
                        </div>
                        <?php if ($fieldErr('country_code') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('country_code')) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sf-form-row-split">
                    <div class="sf-field">
                        <label for="shipping_phone" class="sf-field-label">Phone number *</label>
                        <input type="tel" id="shipping_phone" name="shipping_phone"
                               class="sf-input"
                               value="<?= e($val($shipping, 'phone')) ?>">
                        <?php if ($fieldErr('phone') !== ''): ?>
                            <div class="sf-field-error"><?= e($fieldErr('phone')) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="sf-field">
                        <label for="shipping_email" class="sf-field-label">Email</label>
                        <input type="email" id="shipping_email" name="shipping_email"
                               class="sf-input"
                               value="<?= e($val($shipping, 'email')) ?>">
                    </div>
                </div>
            </fieldset>

            <script>
            (function () {
                'use strict';
                var savedRadios = document.querySelectorAll('input[name="saved_shipping_address_id"]');
                var manualFields = document.getElementById('sf-manual-shipping-fields');
                if (!savedRadios.length || !manualFields) return;
                function update() {
                    var selected = document.querySelector('input[name="saved_shipping_address_id"]:checked');
                    var useNew = !selected || selected.value === '0';
                    if (useNew) { manualFields.removeAttribute('hidden'); }
                    else { manualFields.setAttribute('hidden', ''); }
                }
                savedRadios.forEach(function (r) { r.addEventListener('change', update); });
                update();
            })();
            </script>

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
