<?php
/**
 * Setup wizard - configure business details.
 *
 * Renders inside the admin shell (layouts.app).
 * @var array    $store
 * @var string[] $missing
 * @var array    $currencies
 * @var array    $old
 * @var array    $errors
 * @var bool     $saved
 * @var string   $csrf
 */
$store    = $store    ?? [];
$missing  = $missing  ?? [];
$currencies = $currencies ?? [];
$old      = $old      ?? [];
$errors   = $errors   ?? [];
$saved    = $saved    ?? false;
$csrf     = $csrf     ?? '';

$val = fn(string $key, $default = '') => e((string) ($old[$key] ?? $store[$key] ?? $default));
$err = fn(string $key) => isset($errors[$key])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
    : '';
?>

<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Finish setting up your business</h1>
        <p class="muted mb-0">
            A few details are needed before the storefront can go live.
            You can skip and come back later - a reminder stays until these are complete.
        </p>
    </div>
</div>

<?php if ($saved): ?>
    <div class="ra-alert ra-alert-success mb-4">Your changes were saved.</div>
<?php endif; ?>

<?php if ($missing !== []): ?>
    <div class="ra-alert ra-alert-warning mb-4">
        <strong>Still needed:</strong>
        <?= e(implode(', ', $missing)) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/commerce/ca-admin/setup" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Business</h2>
        </div>
        <div class="ra-card-body">

            <div class="mb-3">
                <label for="name" class="form-label">Business name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required value="<?= $val('name') ?>">
                <?= $err('name') ?>
            </div>

            <div class="mb-3">
                <label for="slug" class="form-label">Store slug <span class="text-danger">*</span></label>
                <input type="text" id="slug" name="slug" class="form-control" required
                       pattern="[a-z0-9][a-z0-9-]{1,78}[a-z0-9]"
                       value="<?= $val('slug') ?>">
                <div class="form-text">Used in URLs. Lowercase letters, digits, hyphens. Cannot be changed later.</div>
                <?= $err('slug') ?>
            </div>

            <div class="mb-3">
                <label for="tagline" class="form-label">Tagline</label>
                <input type="text" id="tagline" name="tagline" class="form-control"
                       value="<?= $val('tagline') ?>">
            </div>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Currency &amp; region</h2>
        </div>
        <div class="ra-card-body">

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="default_currency_id" class="form-label">Default currency <span class="text-danger">*</span></label>
                    <select id="default_currency_id" name="default_currency_id" class="form-select" required>
                        <option value="">- Select -</option>
                        <?php foreach ($currencies as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) ($old['default_currency_id'] ?? $store['default_currency_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['code'] . ' - ' . $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('default_currency_id') ?>
                </div>

                <div class="col-md-6">
                    <label for="country_code" class="form-label">Country <span class="text-danger">*</span></label>
                    <input type="text" id="country_code" name="country_code" class="form-control" required
                           maxlength="2" pattern="[A-Za-z]{2}"
                           value="<?= $val('country_code') ?>"
                           placeholder="KE, DE, US">
                    <div class="form-text">ISO 3166-1 alpha-2 (two letters).</div>
                    <?= $err('country_code') ?>
                </div>

                <div class="col-md-6">
                    <label for="timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                    <input type="text" id="timezone" name="timezone" class="form-control" required
                           value="<?= $val('timezone', 'Africa/Nairobi') ?>"
                           placeholder="Africa/Nairobi">
                    <div class="form-text">IANA timezone identifier.</div>
                    <?= $err('timezone') ?>
                </div>

                <div class="col-md-6">
                    <label for="locale" class="form-label">Locale</label>
                    <input type="text" id="locale" name="locale" class="form-control"
                           value="<?= $val('locale', 'en_KE') ?>"
                           placeholder="en_KE">
                </div>
            </div>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Branding <span class="muted fw-normal">(optional)</span></h2>
        </div>
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="primary_color" class="form-label">Primary colour</label>
                    <input type="text" id="primary_color" name="primary_color" class="form-control"
                           pattern="#[0-9A-Fa-f]{6}"
                           value="<?= $val('primary_color', '#0f172a') ?>"
                           placeholder="#0f172a">
                </div>
                <div class="col-md-6">
                    <label for="secondary_color" class="form-label">Secondary colour</label>
                    <input type="text" id="secondary_color" name="secondary_color" class="form-control"
                           pattern="#[0-9A-Fa-f]{6}"
                           value="<?= $val('secondary_color', '#475569') ?>"
                           placeholder="#475569">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save</button>
        <a href="/commerce/ca-admin/setup/skip" class="ra-btn ra-btn-outline">Remind me later</a>
    </div>
</form>
