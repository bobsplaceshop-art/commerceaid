<?php
/**
 * Forgot password form at /account/forgot-password.
 *
 * Context (from AuthController::showForgotPassword):
 *   $errors  array<string,string>  field name => error message
 *   $old     array<string,mixed>   previous input
 *
 * If an account exists for the submitted email, a reset link is
 * generated. The flash message on success is deliberately generic
 * ("If an account exists...") to prevent email enumeration.
 *
 * EMAIL SENDING IS NOT YET IMPLEMENTED. The reset link is logged to
 * storage/logs/app.log at INFO level. Grep for
 * "Password reset link generated" to retrieve it during testing.
 * See docs/stages/008c_customer-auth-plan.md.
 */

$errors = $errors ?? [];
$old    = $old    ?? [];

$val = fn (string $k, string $d = ''): string => e((string) ($old[$k] ?? $d));
$err = fn (string $k): string => isset($errors[$k])
    ? '<span class="sf-form-error">' . e((string) $errors[$k]) . '</span>'
    : '';
?>

<div class="sf-auth-wrap" data-sf-page="account-forgot">
    <div class="sf-auth-card">

        <h1 class="sf-auth-heading">Reset your password</h1>
        <p class="sf-auth-subhead">Enter the email you signed up with. We'll send you a link to set a new password.</p>

        <form method="POST" action="/account/forgot-password" novalidate>
            <?= csrf_field() ?>

            <div class="sf-field">
                <label class="sf-field-label" for="email">Email address <span class="req">*</span></label>
                <input type="email"
                       class="sf-input<?= isset($errors['email']) ? ' is-error' : '' ?>"
                       id="email"
                       name="email"
                       value="<?= $val('email') ?>"
                       required
                       autofocus
                       autocomplete="email"
                       maxlength="190">
                <?= $err('email') ?>
            </div>

            <div class="sf-form-actions">
                <button type="submit" class="sf-btn sf-btn-primary">
                    Send reset link
                </button>
            </div>
        </form>

        <p class="sf-auth-footer">
            Remembered it?
            <a href="/account/login">Back to sign in</a>
        </p>

    </div>
</div>
