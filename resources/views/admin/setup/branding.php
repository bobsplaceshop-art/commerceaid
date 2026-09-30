<?php
/**
 * Setup tab: Branding.
 *
 * Only colours are managed here. Logo, favicon, and the PWA
 * fields (app name, short name, theme colour, background
 * colour) moved to Store Settings -> Branding in 008G.4a.
 * This tab links there rather than duplicating the form.
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
            <h2 class="h6 mb-0">Logo, favicon, and PWA</h2>
        </div>
        <div class="ra-card-body">
            <p class="muted small mb-3">
                The store logo, favicon, and PWA fields (app name, short name,
                theme colour, background colour) are managed from Store Settings.
                That page also handles the file uploads and previews.
            </p>
            <a href="/commerce/ca-admin/settings?tab=branding"
               class="ra-btn ra-btn-outline">
                <i class="bi bi-palette me-1"></i>
                Open Store Settings &rarr; Branding
            </a>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save</button>
    </div>
</form>
