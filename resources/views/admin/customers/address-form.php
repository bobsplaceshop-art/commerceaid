<?php
/**
 * Admin customer address create/edit form.
 *
 * Context (from AdminCustomerController::addressCreate / addressEdit):
 *   $customer  Customer
 *   $address   ?CustomerAddress   null on create, populated on edit
 *   $mode      string             'create' | 'edit'
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *
 * POSTs to:
 *   create -> /commerce/ca-admin/customers/{id}/addresses
 *   edit   -> /commerce/ca-admin/customers/{id}/addresses/{aid}
 */
$customer = $customer ?? null;
$address  = $address  ?? null;
$mode     = $mode     ?? 'create';
$errors   = $errors   ?? [];
$old      = $old      ?? [];

if ($customer === null) {
    return;
}

$isEdit = $mode === 'edit' && $address !== null;

$action = $isEdit
    ? '/commerce/ca-admin/customers/' . (int) $customer->id . '/addresses/' . (int) $address->id
    : '/commerce/ca-admin/customers/' . (int) $customer->id . '/addresses';

/**
 * Read the current value for a field. Old input first, then the
 * model, then the default.
 *
 * The $old array uses snake_case keys (as posted). The model uses
 * camelCase properties. This closure bridges the two.
 */
$val = function (string $key, string $default = '') use ($old, $address): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($address === null) {
        return $default;
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
    return (string) ($prop ?? $default);
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$selType = array_key_exists('type', $old)
    ? (string) $old['type']
    : ($address?->type ?? 'both');

$isDefaultChecked = array_key_exists('is_default', $old)
    ? !empty($old['is_default'])
    : ($address?->isDefault ?? false);

$displayName = $customer->fullName() ?: $customer->email;
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/customers" class="text-decoration-none">Customers</a>
            <span class="mx-1">&rsaquo;</span>
            <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>"
               class="text-decoration-none"><?= e($displayName) ?></a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= $isEdit ? 'Edit address' : 'Add address' ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= $isEdit ? 'Edit address' : 'Add address' ?></h1>
        <p class="muted mb-0">
            Address for <?= e($displayName) ?>.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" id="ra-address-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Address details</h2>
            <div class="row g-3">

                <div class="col-md-8">
                    <label class="muted small" for="af-label">Label</label>
                    <input type="text" id="af-label" name="label"
                           class="form-control <?= $err('label') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('label')) ?>"
                           placeholder="Home, Office, …"
                           maxlength="60">
                    <?php if ($err('label') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('label')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="muted small" for="af-type">Type</label>
                    <select id="af-type" name="type" class="form-select">
                        <option value="both"     <?= $selType === 'both'     ? 'selected' : '' ?>>Billing &amp; Shipping</option>
                        <option value="billing"  <?= $selType === 'billing'  ? 'selected' : '' ?>>Billing only</option>
                        <option value="shipping" <?= $selType === 'shipping' ? 'selected' : '' ?>>Shipping only</option>
                    </select>
                    <?php if ($err('type') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('type')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-first">First name</label>
                    <input type="text" id="af-first" name="first_name"
                           class="form-control <?= $err('first_name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('first_name')) ?>"
                           autocomplete="given-name"
                           maxlength="100">
                    <?php if ($err('first_name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('first_name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-last">Last name</label>
                    <input type="text" id="af-last" name="last_name"
                           class="form-control <?= $err('last_name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('last_name')) ?>"
                           autocomplete="family-name"
                           maxlength="100">
                    <?php if ($err('last_name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('last_name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="af-company">Company</label>
                    <input type="text" id="af-company" name="company"
                           class="form-control <?= $err('company') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('company')) ?>"
                           autocomplete="organization"
                           maxlength="200">
                    <?php if ($err('company') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('company')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="af-line1">Address line 1 <span aria-hidden="true">*</span></label>
                    <input type="text" id="af-line1" name="address_line_1"
                           class="form-control <?= $err('address_line_1') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('address_line_1')) ?>"
                           autocomplete="address-line1"
                           required
                           maxlength="255">
                    <?php if ($err('address_line_1') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('address_line_1')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="af-line2">Address line 2</label>
                    <input type="text" id="af-line2" name="address_line_2"
                           class="form-control <?= $err('address_line_2') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('address_line_2')) ?>"
                           autocomplete="address-line2"
                           maxlength="255">
                    <?php if ($err('address_line_2') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('address_line_2')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-city">City <span aria-hidden="true">*</span></label>
                    <input type="text" id="af-city" name="city"
                           class="form-control <?= $err('city') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('city')) ?>"
                           autocomplete="address-level2"
                           required
                           maxlength="120">
                    <?php if ($err('city') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('city')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-state">State / Region</label>
                    <input type="text" id="af-state" name="state"
                           class="form-control <?= $err('state') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('state')) ?>"
                           autocomplete="address-level1"
                           maxlength="120">
                    <?php if ($err('state') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('state')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-postal">Postal code</label>
                    <input type="text" id="af-postal" name="postal_code"
                           class="form-control <?= $err('postal_code') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('postal_code')) ?>"
                           autocomplete="postal-code"
                           maxlength="20">
                    <?php if ($err('postal_code') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('postal_code')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-country">Country code <span aria-hidden="true">*</span></label>
                    <input type="text" id="af-country" name="country_code"
                           class="form-control <?= $err('country_code') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('country_code')) ?>"
                           placeholder="KE"
                           autocomplete="country"
                           required
                           maxlength="2"
                           style="text-transform:uppercase;">
                    <div class="muted small mt-1">Two-letter ISO code (e.g. KE, US, GB).</div>
                    <?php if ($err('country_code') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('country_code')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-phone">Phone</label>
                    <input type="tel" id="af-phone" name="phone"
                           class="form-control <?= $err('phone') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('phone')) ?>"
                           autocomplete="tel"
                           maxlength="50">
                    <?php if ($err('phone') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('phone')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="af-email">Email</label>
                    <input type="email" id="af-email" name="email"
                           class="form-control <?= $err('email') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('email')) ?>"
                           autocomplete="email"
                           maxlength="190">
                    <?php if ($err('email') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('email')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1"
                               <?= $isDefaultChecked ? 'checked' : '' ?>>
                        <span class="form-check-label">
                            Make this the customer's default address for its type.
                        </span>
                    </label>
                </div>

            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>"
           class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-address-form" class="ra-btn ra-btn-primary">
            <?= $isEdit ? 'Save changes' : 'Add address' ?>
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
