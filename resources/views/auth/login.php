<?php
/**
 * Login form.
 *
 * @var array  $errors   Associative: ['email' => '...', '_form' => '...']
 * @var array  $old      Previous input: ['email' => '...']
 * @var string $intended Optional internal path to redirect to after login
 */
$errors   = $errors   ?? [];
$old      = $old      ?? [];
$intended = $intended ?? '';
$val      = fn(string $k, string $d = '') => e((string) ($old[$k] ?? $d));
$err      = fn(string $k) => isset($errors[$k])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$k]) . '</div>'
    : '';
?>
<form method="POST" action="/login" novalidate>
    <?= csrf_field() ?>
    <?php if ($intended !== ''): ?>
        <input type="hidden" name="intended" value="<?= e($intended) ?>">
    <?php endif; ?>

    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= $val('email') ?>" required autofocus autocomplete="username">
        <?= $err('email') ?>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div style="position:relative;">
            <input type="password" class="form-control" id="password" name="password"
                   required autocomplete="current-password" style="padding-right:2.75rem;">
            <button type="button" class="pw-toggle" data-target="password"
                    aria-label="Show password"
                    style="position:absolute;top:50%;right:.75rem;transform:translateY(-50%);background:transparent;border:0;cursor:pointer;padding:.25rem;">
                <i class="bi bi-eye"></i>
            </button>
        </div>
        <?= $err('password') ?>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
    </button>
</form>

<script>
(function () {
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
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
