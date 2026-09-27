<?php
/**
 * Address create/edit form at /account/addresses/create and
 * /account/addresses/{id}/edit.
 *
 * Context (from AccountController):
 *   $customer  ?Customer
 *   $address   ?CustomerAddress  null on create, populated on edit
 *   $mode      string            'create' or 'edit'
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$customer = $customer ?? null;
$address  = $address  ?? null;
$mode     = $mode     ?? 'create';
$errors   = $errors   ?? [];
$old      = $old      ?? [];

$action = $mode === 'edit' && $address !== null
    ? '/account/addresses/' . (int) $address->id
    : '/account/addresses';

$val = function (string $key, string $default = '') use ($old, $address): string {
    if (array_key_exists($key, $old)) {
        return e((string) $old[$key]);
    }
    if ($address === null) {
        return e($default);
    }
    $prop = match ($key) {
        'label'          => $address->label,
        'type'           => $address->type,
        'first_name'     => $address->firstName,
        'last_name'      => $address->lastName,
        'company'        => $address->company,
        'address_line_1' => $address->addressLine1,
        'address_line_2' => $address->addressLine2,
        'city'           => $address->city,
        'state'          => $address->state,
        'postal_code'    => $address->postalCode,
        'country_code'   => $address->countryCode,
        'phone'          => $address->phone,
        'email'          => $address->email,
        default          => '',
    };
    return e((string) ($prop ?? $default));
};

$err = fn (string $k): string => isset($errors[$k])
    ? '<span class="sf-form-error">' . e((string) $errors[$k]) . '</span>'
    : '';

$selType = array_key_exists('type', $old)
    ? (string) $old['type']
    : ($address?->type ?? 'both');

$isDefaultChecked = array_key_exists('is_default', $old)
    ? !empty($old['is_default'])
    : ($address?->isDefault ?? false);
?>

<div class="sf-auth-wrap" data-sf-page="account-address-form">
    <div class="sf-auth-card sf-auth-card-wide">

        <h1 class="sf-auth-heading">
            <?= $mode === 'edit' ? 'Edit address' : 'Add an address' ?>
        </h1>
        <p class="sf-auth-subhead">
            The address is used at checkout. You can set a default for billing, shipping, or both.
        </p>

        <form method="POST" action="<?= e($action) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label class="sf-field-label" for="label">Label <span style="color:var(--sf-muted);font-weight:400;">(optional)</span></label>
                    <input type="text"
                           class="sf-input"
                           id="label"
                           name="label"
                           value="<?= $val('label') ?>"
                           placeholder="Home, Office, …"
                           maxlength="60">
                    <?= $err('label') ?>
                </div>

                <div class="sf-field">
                    <label class="sf-field-label" for="type">Type</label>
                    <select class="sf-select" id="type" name="type">
                        <option value="both"     <?= $selType === 'both'     ? 'selected' : '' ?>>Billing &amp; Shipping</option>
                        <option value="billing"  <?= $selType === 'billing'  ? 'selected' : '' ?>>Billing only</option>
                        <option value="shipping" <?= $selType === 'shipping' ? 'selected' : '' ?>>Shipping only</option>
                    </select>
                    <?= $err('type') ?>
                </div>
            </div>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label class="sf-field-label" for="first_name">First name</label>
                    <input type="text" class="sf-input" id="first_name" name="first_name"
                           value="<?= $val('first_name') ?>" autocomplete="given-name" maxlength="100">
                    <?= $err('first_name') ?>
                </div>
                <div class="sf-field">
                    <label class="sf-field-label" for="last_name">Last name</label>
                    <input type="text" class="sf-input" id="last_name" name="last_name"
                           value="<?= $val('last_name') ?>" autocomplete="family-name" maxlength="100">
                    <?= $err('last_name') ?>
                </div>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="company">Company <span style="color:var(--sf-muted);font-weight:400;">(optional)</span></label>
                <input type="text" class="sf-input" id="company" name="company"
                       value="<?= $val('company') ?>" autocomplete="organization" maxlength="200">
                <?= $err('company') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="address_line_1">Address line 1 <span class="req">*</span></label>
                <input type="text"
                       class="sf-input<?= isset($errors['address_line_1']) ? ' is-error' : '' ?>"
                       id="address_line_1" name="address_line_1"
                       value="<?= $val('address_line_1') ?>"
                       required autocomplete="address-line1" maxlength="255">
                <?= $err('address_line_1') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="address_line_2">Address line 2 <span style="color:var(--sf-muted);font-weight:400;">(optional)</span></label>
                <input type="text" class="sf-input" id="address_line_2" name="address_line_2"
                       value="<?= $val('address_line_2') ?>" autocomplete="address-line2" maxlength="255">
                <?= $err('address_line_2') ?>
            </div>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label class="sf-field-label" for="city">City <span class="req">*</span></label>
                    <input type="text"
                           class="sf-input<?= isset($errors['city']) ? ' is-error' : '' ?>"
                           id="city" name="city"
                           value="<?= $val('city') ?>"
                           required autocomplete="address-level2" maxlength="120">
                    <?= $err('city') ?>
                </div>
                <div class="sf-field">
                    <label class="sf-field-label" for="state">State / Region</label>
                    <input type="text" class="sf-input" id="state" name="state"
                           value="<?= $val('state') ?>" autocomplete="address-level1" maxlength="120">
                    <?= $err('state') ?>
                </div>
            </div>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label class="sf-field-label" for="postal_code">Postal code</label>
                    <input type="text" class="sf-input" id="postal_code" name="postal_code"
                           value="<?= $val('postal_code') ?>" autocomplete="postal-code" maxlength="20">
                    <?= $err('postal_code') ?>
                </div>
                <div class="sf-field">
                    <label class="sf-field-label" for="country_code">Country code <span class="req">*</span></label>
                    <input type="text"
                           class="sf-input<?= isset($errors['country_code']) ? ' is-error' : '' ?>"
                           id="country_code" name="country_code"
                           value="<?= $val('country_code') ?>"
                           placeholder="KE"
                           required autocomplete="country" maxlength="2"
                           style="text-transform:uppercase;">
                    <span class="sf-form-help">Two-letter ISO code (e.g. KE, US, GB).</span>
                    <?= $err('country_code') ?>
                </div>
            </div>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label class="sf-field-label" for="phone">Phone</label>
                    <input type="tel" class="sf-input" id="phone" name="phone"
                           value="<?= $val('phone') ?>" autocomplete="tel" maxlength="50">
                    <?= $err('phone') ?>
                </div>
                <div class="sf-field">
                    <label class="sf-field-label" for="email">Email</label>
                    <input type="email" class="sf-input" id="email" name="email"
                           value="<?= $val('email') ?>" autocomplete="email" maxlength="190">
                    <?= $err('email') ?>
                </div>
            </div>

            <div class="sf-field">
                <label class="sf-check">
                    <input type="checkbox" name="is_default" value="1" <?= $isDefaultChecked ? 'checked' : '' ?>>
                    <span class="sf-check-label">Make this my default address for its type.</span>
                </label>
            </div>

            <div class="sf-form-actions" style="display:flex;gap:0.75rem;">
                <a href="/account/addresses" class="sf-btn sf-btn-outline" style="flex:1;justify-content:center;">
                    Cancel
                </a>
                <button type="submit" class="sf-btn sf-btn-primary" style="flex:2;justify-content:center;">
                    <?= $mode === 'edit' ? 'Save changes' : 'Add address' ?>
                </button>
            </div>
        </form>

    </div>
</div>
