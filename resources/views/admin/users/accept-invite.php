<?php
/**
 * Invitation acceptance: set your password.
 *
 * Rendered through layouts.auth (not the admin shell). The layout
 * provides the outer container; this file is the inner content.
 *
 * Matches the shape of resources/views/auth/reset-password.php.
 *
 * Context (from AdminUserController::acceptInvite):
 *   $csrf   string                current CSRF token
 *   $token  string                the invite token from the URL
 *   $name   string                the invitee's name
 *   $email  string                the invitee's email
 *   $errors array<string,string>  validation errors from a failed POST
 */
$csrf   = $csrf   ?? '';
$token  = $token  ?? '';
$name   = $name   ?? '';
$email  = $email  ?? '';
$errors = $errors ?? [];

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};
?>

<h1 class="h4 mb-1">Set your password</h1>
<p class="muted small mb-4">
    Welcome, <strong><?= e($name) ?></strong>. Choose a password to
    activate your account.
</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error mb-3" role="alert">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST"
      action="/commerce/ca-admin/users/accept-invite/<?= e(rawurlencode($token)) ?>"
      novalidate>

    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="mb-3">
        <label class="form-label muted small" for="ai-email">Email</label>
        <input type="email" id="ai-email" class="form-control"
               value="<?= e($email) ?>" disabled>
    </div>

    <div class="mb-3">
        <label class="form-label muted small" for="ai-password">Password</label>
        <input type="password" id="ai-password" name="password"
               class="form-control <?= $err('password') !== '' ? 'is-invalid' : '' ?>"
               autocomplete="new-password"
               minlength="8"
               required>
        <div class="muted small mt-1">At least 8 characters.</div>
        <?php if ($err('password') !== ''): ?>
            <div class="invalid-feedback d-block"><?= e($err('password')) ?></div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
        <label class="form-label muted small" for="ai-confirm">Confirm password</label>
        <input type="password" id="ai-confirm" name="password_confirmation"
               class="form-control <?= $err('password_confirmation') !== '' ? 'is-invalid' : '' ?>"
               autocomplete="new-password"
               minlength="8"
               required>
        <?php if ($err('password_confirmation') !== ''): ?>
            <div class="invalid-feedback d-block"><?= e($err('password_confirmation')) ?></div>
        <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2">
        <i class="bi bi-check-circle me-1"></i>
        Activate my account
    </button>
</form>
