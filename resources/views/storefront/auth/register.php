<?php
/**
 * Customer registration form at /account/register.
 *
 * Context (from AuthController::showRegister):
 *   $errors        array<string,string>  field name => error message
 *   $old           array<string,mixed>   previous input for repopulation
 *   $groupOptions  array<int, array{id:int, name:string,
 *                                   requires_approval:bool,
 *                                   is_b2b:bool}>
 *
 * See docs/stages/008c_customer-auth-plan.md.
 */

$errors       = $errors       ?? [];
$old          = $old          ?? [];
$groupOptions = $groupOptions ?? [];

$val = fn (string $k, string $d = ''): string => e((string) ($old[$k] ?? $d));
$err = fn (string $k): string => isset($errors[$k])
    ? '<span class="sf-form-error">' . e((string) $errors[$k]) . '</span>'
    : '';

$oldGroupId   = (int) ($old['customer_group_id'] ?? 0);
$oldMarketing = !empty($old['accepts_marketing']);
?>

<div class="sf-auth-wrap" data-sf-page="account-register">
    <div class="sf-auth-card sf-auth-card-wide">

        <h1 class="sf-auth-heading">Create your account</h1>
        <p class="sf-auth-subhead">Just a few details and you're in. You can add addresses and preferences later from your account.</p>

        <?php if (isset($errors['_form'])): ?>
            <div class="sf-form-alert sf-form-alert-error">
                <?= e((string) $errors['_form']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/account/register" novalidate>
            <?= csrf_field() ?>

            <div class="sf-form-row-split">
                <div class="sf-field">
                    <label class="sf-field-label" for="first_name">First name <span class="req">*</span></label>
                    <input type="text"
                           class="sf-input<?= isset($errors['first_name']) ? ' is-error' : '' ?>"
                           id="first_name"
                           name="first_name"
                           value="<?= $val('first_name') ?>"
                           required
                           autofocus
                           autocomplete="given-name"
                           maxlength="100">
                    <?= $err('first_name') ?>
                </div>

                <div class="sf-field">
                    <label class="sf-field-label" for="last_name">Last name <span class="req">*</span></label>
                    <input type="text"
                           class="sf-input<?= isset($errors['last_name']) ? ' is-error' : '' ?>"
                           id="last_name"
                           name="last_name"
                           value="<?= $val('last_name') ?>"
                           required
                           autocomplete="family-name"
                           maxlength="100">
                    <?= $err('last_name') ?>
                </div>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="email">Email address <span class="req">*</span></label>
                <input type="email"
                       class="sf-input<?= isset($errors['email']) ? ' is-error' : '' ?>"
                       id="email"
                       name="email"
                       value="<?= $val('email') ?>"
                       required
                       autocomplete="email"
                       maxlength="190">
                <?= $err('email') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="phone">Phone <span style="color:var(--sf-muted);font-weight:400;">(optional)</span></label>
                <input type="tel"
                       class="sf-input<?= isset($errors['phone']) ? ' is-error' : '' ?>"
                       id="phone"
                       name="phone"
                       value="<?= $val('phone') ?>"
                       autocomplete="tel"
                       maxlength="50">
                <?= $err('phone') ?>
            </div>

            <div class="sf-field">
                <label class="sf-field-label" for="company">Company <span style="color:var(--sf-muted);font-weight:400;">(optional)</span></label>
                <input type="text"
                       class="sf-input<?= isset($errors['company']) ? ' is-error' : '' ?>"
                       id="company"
                       name="company"
                       value="<?= $val('company') ?>"
                       autocomplete="organization"
                       maxlength="200">
                <?= $err('company') ?>
            </div>

            <?php if (!empty($groupOptions)): ?>
                <div class="sf-field">
                    <label class="sf-field-label" for="customer_group_id">Account type</label>
                    <select class="sf-select" id="customer_group_id" name="customer_group_id">
                        <option value="0" data-requires-approval="0">Standard account</option>
                        <?php foreach ($groupOptions as $g): ?>
                            <option value="<?= (int) $g['id'] ?>"
                                    data-requires-approval="<?= !empty($g['requires_approval']) ? '1' : '0' ?>"
                                    <?= $oldGroupId === (int) $g['id'] ? 'selected' : '' ?>>
                                <?= e($g['name']) ?><?= !empty($g['requires_approval']) ? ' (requires approval)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="sf-form-help" id="approval-note" hidden>
                        Your account will be reviewed before you can place orders. You can still browse and add items to your cart.
                    </span>
                </div>
            <?php endif; ?>

            <div class="sf-field">
                <label class="sf-field-label" for="password">Password <span class="req">*</span></label>
                <div class="sf-password-wrap">
                    <input type="password"
                           class="sf-input<?= isset($errors['password']) ? ' is-error' : '' ?>"
                           id="password"
                           name="password"
                           required
                           autocomplete="new-password"
                           minlength="8">
                    <button type="button"
                            class="sf-password-toggle"
                            data-target="password"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <span class="sf-form-help">At least 8 characters.</span>
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

            <div class="sf-field">
                <label class="sf-check">
                    <input type="checkbox"
                           name="accepts_marketing"
                           value="1"
                           <?= $oldMarketing ? 'checked' : '' ?>>
                    <span class="sf-check-label">Send me news, offers, and product updates.</span>
                </label>
            </div>

            <div class="sf-form-actions">
                <button type="submit" class="sf-btn sf-btn-primary">
                    Create account
                </button>
            </div>
        </form>

        <p class="sf-auth-footer">
            Already have an account?
            <a href="/account/login">Sign in</a>
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

    var groupSelect = document.getElementById('customer_group_id');
    var approvalNote = document.getElementById('approval-note');
    if (groupSelect && approvalNote) {
        var update = function () {
            var opt = groupSelect.options[groupSelect.selectedIndex];
            var requires = opt && opt.getAttribute('data-requires-approval') === '1';
            approvalNote.hidden = !requires;
        };
        groupSelect.addEventListener('change', update);
        update();
    }
})();
</script>
