<?php
/**
 * Checkout step 2 - customer information.
 *
 * Context (from CheckoutController::info):
 *   $state    array<string,mixed>
 *   $customer ?Customer
 *   $errors   array<string,string>
 */
$state    = $state    ?? [];
$customer = $customer ?? null;
$errors   = $errors   ?? [];

$data = $state['customer_data'] ?? [];
$isGuest = $customer === null;

$val = function (string $key, string $default = '') use ($data): string {
    return (string) ($data[$key] ?? $default);
};
$err = function (string $key) use ($errors): string {
    return isset($errors[$key]) ? (string) $errors[$key] : '';
};
?>
<div class="sf-checkout" data-sf-page="checkout-info">

    <div class="sf-container">
        <?= view_partial('storefront.checkout._steps', ['current' => 2]) ?>

        <div class="sf-checkout-centered">

        <h1 class="sf-checkout-title">Your details</h1>

        <?php if ($customer !== null): ?>
            <p class="muted small">
                You're signed in as
                <strong><?= e($customer->email) ?></strong>.
                Your account information is pre-filled below.
            </p>
        <?php endif; ?>

        <form method="POST" action="/checkout/info" class="sf-checkout-form">
            <?= csrf_field() ?>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label for="first_name" class="sf-field-label">First name *</label>
                    <input type="text" id="first_name" name="first_name"
                           class="sf-input <?= $err('first_name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('first_name', $customer !== null ? ($customer->firstName ?? '') : '')) ?>"
                           required>
                    <?php if ($err('first_name') !== ''): ?>
                        <div class="sf-form-error"><?= e($err('first_name')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="sf-field">
                    <label for="last_name" class="sf-field-label">Last name *</label>
                    <input type="text" id="last_name" name="last_name"
                           class="sf-input <?= $err('last_name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('last_name', $customer !== null ? ($customer->lastName ?? '') : '')) ?>"
                           required>
                    <?php if ($err('last_name') !== ''): ?>
                        <div class="sf-form-error"><?= e($err('last_name')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isGuest): ?>
                <div class="sf-field">
                    <label for="email" class="sf-field-label">Email address *</label>
                    <input type="email" id="email" name="email"
                           class="sf-input <?= $err('email') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('email', (string) ($state['guest_email'] ?? ''))) ?>"
                           required>
                    <?php if ($err('email') !== ''): ?>
                        <div class="sf-form-error"><?= e($err('email')) ?></div>
                    <?php endif; ?>
                    <div class="sf-form-help">
                        Your order confirmation will be sent to this address.
                    </div>
                </div>
            <?php endif; ?>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label for="phone" class="sf-field-label">Phone</label>
                    <input type="tel" id="phone" name="phone" class="sf-input"
                           value="<?= e($val('phone', $customer !== null ? ($customer->phone ?? '') : '')) ?>">
                </div>
                <div class="sf-field">
                    <label for="company" class="sf-field-label">Company</label>
                    <input type="text" id="company" name="company" class="sf-input"
                           value="<?= e($val('company', $customer !== null ? ($customer->company ?? '') : '')) ?>">
                </div>
            </div>

            <div class="sf-checkout-actions">
                <a href="/checkout" class="sf-btn sf-btn-outline">Back</a>
                <button type="submit" class="sf-btn sf-btn-primary">Continue to shipping</button>
            </div>
        </form>
        </div>
    </div>

</div>
