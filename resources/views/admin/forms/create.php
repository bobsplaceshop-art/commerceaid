<?php
/**
 * Create a form. Stage 009.
 *
 * Context:
 *   $errors array<string,string>
 *   $old    array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/forms.
 */
$errors = $errors ?? [];
$old    = $old    ?? [];

$val = function (string $key, string $default = '') use ($old): string {
    return array_key_exists($key, $old) ? (string) $old[$key] : $default;
};

$bool = function (string $key, bool $default) use ($old): bool {
    if (!array_key_exists($key, $old)) return $default;
    return !empty($old[$key]);
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/forms" class="text-decoration-none">Forms</a>
            <span class="mx-1">&rsaquo;</span>
            <span>New form</span>
        </nav>
        <h1 class="h3 mb-1">New form</h1>
        <p class="muted mb-0">
            Create the form first. You will add fields after saving.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="/commerce/ca-admin/forms" id="ra-form-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Basic</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="muted small" for="ff-name">
                        Name <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="ff-name" name="name"
                           class="form-control <?= $err('name') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('name')) ?>"
                           maxlength="120"
                           placeholder="Contact us"
                           required>
                    <?php if ($err('name') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('name')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="ff-slug">
                        Slug <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="ff-slug" name="slug"
                           class="form-control <?= $err('slug') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('slug')) ?>"
                           maxlength="120"
                           placeholder="contact-us"
                           required>
                    <div class="muted small mt-1">
                        Lowercase letters, digits, and hyphens only. Becomes the public URL /forms/{slug}.
                    </div>
                    <?php if ($err('slug') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('slug')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="ff-description">Description</label>
                    <input type="text" id="ff-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="500"
                           placeholder="Shown to admins only">
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">After submit</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label class="muted small" for="ff-success">Success message</label>
                    <textarea id="ff-success"
                              name="success_message"
                              class="form-control"
                              rows="2"
                              maxlength="1000"
                              placeholder="Thank you. We will be in touch shortly."><?= e($val('success_message')) ?></textarea>
                    <div class="muted small mt-1">
                        Shown to the customer after a successful submission. A default message is used if left blank.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="ff-notify">Notify email</label>
                    <input type="email" id="ff-notify" name="notify_email"
                           class="form-control <?= $err('notify_email') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('notify_email')) ?>"
                           maxlength="190"
                           placeholder="sales@example.com">
                    <div class="muted small mt-1">
                        Optional. Every submission is emailed here.
                    </div>
                    <?php if ($err('notify_email') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('notify_email')) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Protection</h2>

            <div class="form-check mb-2">
                <input class="form-check-input"
                       type="checkbox"
                       id="ff-honeypot"
                       name="honeypot_enabled"
                       value="1"
                       <?= $bool('honeypot_enabled', true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ff-honeypot">
                    Enable honeypot spam protection
                </label>
                <div class="muted small mt-1">
                    Adds an off-screen field. Real users never fill it. Bots do, and their submissions are silently dropped.
                </div>
            </div>

            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="ff-auth"
                       name="requires_auth"
                       value="1"
                       <?= $bool('requires_auth', false) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ff-auth">
                    Require login to view this form
                </label>
                <div class="muted small mt-1">
                    Only logged-in customers can view or submit the form. Off by default.
                </div>
            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/forms" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-form-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i> Create form
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
