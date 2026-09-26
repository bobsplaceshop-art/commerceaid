<?php
/**
 * Setup tab: Branding.
 *
 * Colours and PWA fields are real (write to commerce_stores).
 * Logo and favicon uploads are stubbed until the FileService
 * integration lands.
 *
 * @var array  $store
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$store  = $store  ?? [];
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';

$val = fn(string $k, $d = '') => e((string) ($old[$k] ?? $store[$k] ?? $d));
$err = fn(string $k) => isset($errors[$k])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$k]) . '</div>'
    : '';
?>

<form method="POST" action="/commerce/ca-admin/setup/branding" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Colours</h2>
        </div>
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="primary_color" class="form-label">Primary colour</label>
                    <div class="d-flex gap-2">
                        <input type="color" id="primary_color_picker" value="<?= $val('primary_color', '#0f172a') ?>"
                               style="width:44px;height:38px;padding:2px;border-radius:8px;border:1px solid var(--ra-border, #334155);cursor:pointer;background:transparent;"
                               oninput="document.getElementById('primary_color').value = this.value">
                        <input type="text" id="primary_color" name="primary_color" class="form-control"
                               pattern="#[0-9A-Fa-f]{6}"
                               value="<?= $val('primary_color', '#0f172a') ?>"
                               placeholder="#0f172a"
                               oninput="document.getElementById('primary_color_picker').value = this.value">
                    </div>
                    <?= $err('primary_color') ?>
                </div>

                <div class="col-md-6">
                    <label for="secondary_color" class="form-label">Secondary colour</label>
                    <div class="d-flex gap-2">
                        <input type="color" id="secondary_color_picker" value="<?= $val('secondary_color', '#475569') ?>"
                               style="width:44px;height:38px;padding:2px;border-radius:8px;border:1px solid var(--ra-border, #334155);cursor:pointer;background:transparent;"
                               oninput="document.getElementById('secondary_color').value = this.value">
                        <input type="text" id="secondary_color" name="secondary_color" class="form-control"
                               pattern="#[0-9A-Fa-f]{6}"
                               value="<?= $val('secondary_color', '#475569') ?>"
                               placeholder="#475569"
                               oninput="document.getElementById('secondary_color_picker').value = this.value">
                    </div>
                    <?= $err('secondary_color') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">PWA <span class="muted fw-normal">(installable app)</span></h2>
        </div>
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="pwa_name" class="form-label">App name</label>
                    <input type="text" id="pwa_name" name="pwa_name" class="form-control"
                           value="<?= $val('pwa_name') ?>"
                           placeholder="Anaya Global">
                    <div class="form-text">Full name shown when installing the app.</div>
                </div>

                <div class="col-md-6">
                    <label for="pwa_short_name" class="form-label">Short name</label>
                    <input type="text" id="pwa_short_name" name="pwa_short_name" class="form-control"
                           maxlength="12"
                           value="<?= $val('pwa_short_name') ?>"
                           placeholder="Anaya">
                    <div class="form-text">Shown under the app icon. 12 characters max.</div>
                </div>

                <div class="col-md-6">
                    <label for="pwa_theme_color" class="form-label">Theme colour</label>
                    <div class="d-flex gap-2">
                        <input type="color" id="pwa_theme_color_picker" value="<?= $val('pwa_theme_color', '#0f172a') ?>"
                               style="width:44px;height:38px;padding:2px;border-radius:8px;border:1px solid var(--ra-border, #334155);cursor:pointer;background:transparent;"
                               oninput="document.getElementById('pwa_theme_color').value = this.value">
                        <input type="text" id="pwa_theme_color" name="pwa_theme_color" class="form-control"
                               pattern="#[0-9A-Fa-f]{6}"
                               value="<?= $val('pwa_theme_color', '#0f172a') ?>">
                    </div>
                    <div class="form-text">Colour of the browser bar when the app is open.</div>
                </div>

                <div class="col-md-6">
                    <label for="pwa_background_color" class="form-label">Background colour</label>
                    <div class="d-flex gap-2">
                        <input type="color" id="pwa_background_color_picker" value="<?= $val('pwa_background_color', '#ffffff') ?>"
                               style="width:44px;height:38px;padding:2px;border-radius:8px;border:1px solid var(--ra-border, #334155);cursor:pointer;background:transparent;"
                               oninput="document.getElementById('pwa_background_color').value = this.value">
                        <input type="text" id="pwa_background_color" name="pwa_background_color" class="form-control"
                               pattern="#[0-9A-Fa-f]{6}"
                               value="<?= $val('pwa_background_color', '#ffffff') ?>">
                    </div>
                    <div class="form-text">Shown while the app is loading.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Assets <span class="muted fw-normal">(coming soon)</span></h2>
        </div>
        <div class="ra-card-body">
            <p class="muted small mb-0">
                Logo and favicon upload will be enabled once the media library is wired.
                For now, the platform uses the defaults from <code>config/branding.php</code>.
            </p>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save</button>
    </div>
</form>
