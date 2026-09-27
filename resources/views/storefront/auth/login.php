<?php
/**
 * Customer login form at /account/login.
 *
 * Context (from AuthController::showLogin):
 *   $errors    array<string,string>  field name => error message
 *                                    special key '_form' for
 *                                    non-field-level failures
 *   $old       array<string,mixed>   previous input for repopulation
 *   $intended  string                safe internal path to redirect to
 *                                    after success (may be empty)
 *
 * Distinct from the admin login at /login. Different user table,
 * different session cookie (ca_customer_session). A customer logging
 * in here does not affect an admin session in another tab.
 */

$errors   = $errors   ?? [];
$old      = $old      ?? [];
$intended = $intended ?? '';

$val = fn (string $k, string $d = ''): string => e((string) ($old[$k] ?? $d));
$err = fn (string $k): string => isset($errors[$k])
    ? '<span class="sf-form-error">' . e((string) $errors[$k]) . '</span>'
    : '';
?>

<div class="sf-auth-wrap" data-sf-page="account-login">
    <div class="sf-auth-card">

        <h1 class="sf-auth-heading">Welcome back</h1>
        <p class="sf-auth-subhead">Sign in to your account to view orders, manage addresses, and continue where you left off.</p>

        <?php if (isset($errors['_form'])): ?>
            <div class="sf-form-alert sf-form-alert-error">
                <?= e((string) $errors['_form']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/account/login" novalidate>
            <?= csrf_field() ?>

            <?php if ($intended !== ''): ?>
                <input type="hidden" name="intended" value="<?= e($intended) ?>">
            <?php endif; ?>

            <div class="sf-field">
                <label class="sf-field-label" for="email">Email address <span class="req">*</span></label>
                <input type="email"
                       class="sf-input<?= isset($errors['email']) ? ' is-error' : '' ?>"
                       id="email"
                       name="email"
                       value="<?= $val('email') ?>"
                       required
                       autofocus
                       autocomplete="username">
                <?= $err('email') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="password">Password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['password']) ? ' is-error' : '' ?>"
                           id="password"
                           name="password"
                           required
                           autocomplete="current-password">
                    <button type="button"
                            class="sf-password-toggle"
                            data-target="password"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <?= $err('password') ?>
            </div>

            <div style="text-align: right; margin-bottom: 1rem;">
                <a href="/account/forgot-password"
                   style="font-size: 0.875rem; color: var(--sf-gold, #C6A15B); text-decoration: none;">
                    Forgot your password?
                </a>
            </div>

            <div class="sf-form-actions">
                <button type="submit" class="sf-btn sf-btn-primary">
                    Sign in
                </button>
            </div>
        </form>

        <p class="sf-auth-footer">
            Don't have an account?
            <a href="/account/register">Create one</a>
        </p>

    </div>
</div>

<script>
(function () {
    var toggles = document.querySelectorAll('.sf-password-toggle');
    toggles.forEach(function (btn) {
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
