<?php
/**
 * Change password form at /account/password.
 *
 * Context (from AccountController::showPassword):
 *   $customer  ?Customer
 *   $errors    array<string,string>
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$customer = $customer ?? null;
$errors   = $errors   ?? [];

$err = fn (string $k): string => isset($errors[$k])
    ? '<span class="sf-form-error">' . e((string) $errors[$k]) . '</span>'
    : '';
?>

<div class="sf-account-panel" data-sf-page="account-password">

        <h1 class="sf-auth-heading">Change password</h1>
        <p class="sf-auth-subhead">
            Enter your current password, then choose a new one. At least 8 characters.
        </p>

        <form method="POST" action="/account/password" novalidate>
            <?= csrf_field() ?>

            <div class="sf-field">
                <label class="sf-field-label" for="current_password">Current password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['current_password']) ? ' is-error' : '' ?>"
                           id="current_password"
                           name="current_password"
                           required
                           autofocus
                           autocomplete="current-password">
                    <button type="button" class="sf-password-toggle"
                            data-target="current_password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?= $err('current_password') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="new_password">New password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['new_password']) ? ' is-error' : '' ?>"
                           id="new_password"
                           name="new_password"
                           required
                           autocomplete="new-password"
                           minlength="8">
                    <button type="button" class="sf-password-toggle"
                            data-target="new_password" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?= $err('new_password') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="new_password_confirmation">Confirm new password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['new_password_confirmation']) ? ' is-error' : '' ?>"
                           id="new_password_confirmation"
                           name="new_password_confirmation"
                           required
                           autocomplete="new-password">
                    <button type="button" class="sf-password-toggle"
                            data-target="new_password_confirmation" aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?= $err('new_password_confirmation') ?>
            </div>

            <div class="sf-form-actions">
                <button type="submit" class="sf-btn sf-btn-primary">
                    Change password
                </button>
            </div>
        </form>

        <p class="sf-auth-footer">
            <a href="/account">Back to account</a>
        </p>

</div>

<script>
(function () {
    document.querySelectorAll('.sf-password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) return;
            var isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            var icon = btn.querySelector('i');
            if (icon) icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });
})();
</script>
