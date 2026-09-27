<?php
/**
 * Reset password form at /account/reset-password?token=...
 *
 * Context (from AuthController::showResetPassword):
 *   $token   string                the raw reset token from the URL
 *   $errors  array<string,string>  field name => error message
 *
 * The controller verifies the token (by SHA-256 hash) before
 * rendering. If the token is invalid, expired, or already used,
 * the controller redirects to /account/forgot-password. So this
 * view is only reached with a valid token.
 *
 * The token is passed through as a hidden field so the POST to
 * /account/reset-password can verify it again. This avoids
 * trusting a query param on a state-changing request.
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$errors = $errors ?? [];
$token  = $token  ?? '';

$err = fn (string $k): string => isset($errors[$k])
    ? '<span class="sf-form-error">' . e((string) $errors[$k]) . '</span>'
    : '';
?>

<div class="sf-auth-wrap" data-sf-page="account-reset">
    <div class="sf-auth-card">

        <h1 class="sf-auth-heading">Set a new password</h1>
        <p class="sf-auth-subhead">Choose something you'll remember. At least 8 characters.</p>

        <form method="POST" action="/account/reset-password" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <div class="sf-field">
                <label class="sf-field-label" for="password">New password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['password']) ? ' is-error' : '' ?>"
                           id="password"
                           name="password"
                           required
                           autofocus
                           autocomplete="new-password"
                           minlength="8">
                    <button type="button"
                            class="sf-password-toggle"
                            data-target="password"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?= $err('password') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="password_confirmation">Confirm password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['password_confirmation']) ? ' is-error' : '' ?>"
                           id="password_confirmation"
                           name="password_confirmation"
                           required
                           autocomplete="new-password">
                    <button type="button"
                            class="sf-password-toggle"
                            data-target="password_confirmation"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?= $err('password_confirmation') ?>
            </div>

            <div class="sf-form-actions">
                <button type="submit" class="sf-btn sf-btn-primary">
                    Reset password
                </button>
            </div>
        </form>

        <p class="sf-auth-footer">
            Remembered it?
            <a href="/account/login">Back to sign in</a>
        </p>

    </div>
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
