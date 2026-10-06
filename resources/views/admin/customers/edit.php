<?php
/**
 * Customer profile editor.
 *
 * Context (from AdminCustomerController::edit):
 *   $customer Customer
 *   $errors   array<string,string>
 *   $old      array<string,mixed>
 *
 * Email is read-only. The customer owns their email; the
 * admin cannot change it. Changing an email is a security
 * action that requires verification.
 */
$customer = $customer ?? null;
$errors   = $errors   ?? [];
$old      = $old      ?? [];

if ($customer === null) {
    return;
}

/**
 * Read the current value for a field, checking old input
 * first, then the model, then the default.
 *
 * The $old array uses snake_case keys (as posted). The
 * model uses camelCase properties. This closure bridges
 * the two.
 */
$val = function (string $key, mixed $default = '') use ($old, $customer): mixed {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
    if (property_exists($customer, $camel)) {
        return $customer->{$camel};
    }
    return $default;
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

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
            <span>Edit</span>
        </nav>
        <h1 class="h3 mb-1">Edit profile</h1>
        <p class="muted mb-0"><?= e($customer->email) ?></p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST"
      action="/commerce/ca-admin/customers/<?= (int) $customer->id ?>"
      id="ra-customer-edit-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Profile</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="muted small" for="cf-first">First name</label>
                    <input type="text" id="cf-first" name="first_name"
                           class="form-control <?= $err('first_name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e((string) $val('first_name', '')) ?>"
                           maxlength="50">
                    <?php if ($err('first_name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('first_name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="cf-last">Last name</label>
                    <input type="text" id="cf-last" name="last_name"
                           class="form-control <?= $err('last_name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e((string) $val('last_name', '')) ?>"
                           maxlength="50">
                    <?php if ($err('last_name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('last_name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="cf-email">Email</label>
                    <input type="email" id="cf-email"
                           class="form-control"
                           value="<?= e($customer->email) ?>"
                           disabled>
                    <div class="muted small mt-1">
                        Email is owned by the customer. To change it,
                        ask them to update it from their account.
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="cf-phone">Phone</label>
                    <input type="text" id="cf-phone" name="phone"
                           class="form-control <?= $err('phone') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e((string) $val('phone', '')) ?>"
                           maxlength="30">
                    <?php if ($err('phone') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('phone')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="cf-company">Company</label>
                    <input type="text" id="cf-company" name="company"
                           class="form-control <?= $err('company') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e((string) $val('company', '')) ?>"
                           maxlength="100">
                    <?php if ($err('company') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('company')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="cf-tax">Tax ID</label>
                    <input type="text" id="cf-tax" name="tax_id"
                           class="form-control <?= $err('tax_id') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e((string) $val('tax_id', '')) ?>"
                           maxlength="30">
                    <?php if ($err('tax_id') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('tax_id')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="cf-dob">Date of birth</label>
                    <input type="date" id="cf-dob" name="date_of_birth"
                           class="form-control"
                           value="<?= e((string) $val('date_of_birth', '')) ?>">
                </div>
            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>"
           class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-customer-edit-form" class="ra-btn ra-btn-primary">
            Save changes
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
