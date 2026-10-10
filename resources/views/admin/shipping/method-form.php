<?php
/**
 * Shipping method create / edit form.
 *
 * Context (from AdminShippingController::showCreateMethod / showEditMethod):
 *   $title  string
 *   $mode   'create' | 'edit'
 *   $method ?array<string,mixed>
 *   $errors array<string,string>
 *   $old    array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/shipping/methods       (create)
 *          /commerce/ca-admin/shipping/methods/{id}   (edit)
 *
 * All config fields are rendered. A small script hides the
 * irrelevant ones based on the selected type.
 */
$title  = $title  ?? 'Shipping method';
$mode   = $mode   ?? 'create';
$method = $method ?? null;
$errors = $errors ?? [];
$old    = $old    ?? [];

$val = function (string $key, string $default = '') use ($old, $method): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($method === null) {
        return $default;
    }
    $prop = match ($key) {
        'code'        => (string) ($method['code'] ?? ''),
        'label'       => (string) ($method['label'] ?? ''),
        'description' => (string) ($method['description'] ?? ''),
        'type'        => (string) ($method['type'] ?? ''),
        'sort_order'  => (string) ($method['sort_order'] ?? ''),
        default       => $default,
    };
    return $prop;
};

$valBool = function (string $key, bool $default = false) use ($old, $method): bool {
    if (array_key_exists($key, $old)) {
        return (bool) $old[$key];
    }
    if ($method === null) {
        return $default;
    }
    return $key === 'is_active'
        ? ((int) ($method['is_active'] ?? 0)) === 1
        : $default;
};

// Config for the currently loaded method. Prefer old input
// (validation failure), then the stored JSON.
$config = [];
if (isset($old['_config']) && is_array($old['_config'])) {
    $config = $old['_config'];
} elseif ($method !== null && !empty($method['config_json'])) {
    $decoded = json_decode((string) $method['config_json'], true);
    if (is_array($decoded)) {
        $config = $decoded;
    }
}

$cfgVal = function (string $key, string $default = '') use ($config): string {
    if (!array_key_exists($key, $config)) return $default;
    $v = $config[$key];
    if ($v === null) return $default;
    return (string) $v;
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$currentType = $val('type', 'flat');
if ($currentType === '') $currentType = 'flat';

$action = $mode === 'edit'
    ? '/commerce/ca-admin/shipping/methods/' . (int) ($method['id'] ?? 0)
    : '/commerce/ca-admin/shipping/methods';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/shipping" class="text-decoration-none">Shipping</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($title) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($title) ?></h1>
        <p class="muted mb-0">
            A method is the shipping option customers pick at checkout. Zones define where it is offered.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" id="ra-shipping-method-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Method details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="smf-label">
                        Label <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-label" name="label"
                           class="form-control <?= $err('label') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('label')) ?>"
                           maxlength="120"
                           placeholder="Standard shipping"
                           required>
                    <div class="muted small mt-1">Shown to customers at checkout.</div>
                    <?php if ($err('label') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('label')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="smf-code">
                        Code <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-code" name="code"
                           class="form-control <?= $err('code') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('code')) ?>"
                           maxlength="60"
                           placeholder="standard"
                           required
                           style="text-transform:lowercase;">
                    <div class="muted small mt-1">
                        Stable identifier. Lowercase letters, digits, hyphens, underscores.
                    </div>
                    <?php if ($err('code') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('code')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="muted small" for="smf-description">Description</label>
                    <input type="text" id="smf-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="255"
                           placeholder="Shown to you only">
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="smf-type">
                        Type <span aria-hidden="true">*</span>
                    </label>
                    <select id="smf-type" name="type"
                            class="form-select <?= $err('type') !== '' ? 'is-invalid' : '' ?>"
                            data-shipping-method-type
                            required>
                        <option value="flat"         <?= $currentType === 'flat'         ? 'selected' : '' ?>>Flat rate</option>
                        <option value="free_over"    <?= $currentType === 'free_over'    ? 'selected' : '' ?>>Free over threshold</option>
                        <option value="weight_based" <?= $currentType === 'weight_based' ? 'selected' : '' ?>>Weight based</option>
                        <option value="price_based"  <?= $currentType === 'price_based'  ? 'selected' : '' ?>>Price based</option>
                        <option value="local_pickup" <?= $currentType === 'local_pickup' ? 'selected' : '' ?>>Local pickup</option>
                    </select>
                    <?php if ($err('type') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('type')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="smf-sort">Sort order</label>
                    <input type="text" id="smf-sort" name="sort_order"
                           class="form-control"
                           value="<?= e($val('sort_order', '0')) ?>"
                           inputmode="numeric">
                    <div class="muted small mt-1">Lower numbers appear first at checkout.</div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="smf-active" name="is_active" value="1"
                               <?= $valBool('is_active', true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="smf-active">
                            <strong>Active</strong>
                            <div class="muted" style="font-size:.75rem;">
                                Disabled methods are hidden from checkout but keep their zones.
                            </div>
                        </label>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-1">Rate configuration</h2>
            <p class="muted small mb-3">
                Which fields appear depends on the type. Only the fields for the selected type are used.
            </p>

            <!-- flat: rate -->
            <div class="row g-3" data-shipping-config="flat">
                <div class="col-md-6">
                    <label class="muted small" for="smf-rate-flat">
                        Rate <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-rate-flat" name="config_rate"
                           class="form-control <?= $err('config_rate') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($cfgVal('rate', '0')) ?>"
                           inputmode="decimal">
                    <?php if ($err('config_rate') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('config_rate')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- free_over: rate + threshold -->
            <div class="row g-3" data-shipping-config="free_over" hidden>
                <div class="col-md-6">
                    <label class="muted small" for="smf-rate-free">
                        Rate <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-rate-free" name="config_rate"
                           class="form-control"
                           value="<?= e($cfgVal('rate', '0')) ?>"
                           inputmode="decimal">
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="smf-threshold">
                        Free over <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-threshold" name="config_threshold"
                           class="form-control <?= $err('config_threshold') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($cfgVal('threshold', '0')) ?>"
                           inputmode="decimal">
                    <?php if ($err('config_threshold') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('config_threshold')) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- weight_based: per_kg + min_charge -->
            <div class="row g-3" data-shipping-config="weight_based" hidden>
                <div class="col-md-6">
                    <label class="muted small" for="smf-per-kg">
                        Rate per kg <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-per-kg" name="config_per_kg"
                           class="form-control <?= $err('config_per_kg') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($cfgVal('per_kg', '0')) ?>"
                           inputmode="decimal">
                    <?php if ($err('config_per_kg') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('config_per_kg')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="muted small" for="smf-min-weight">Minimum charge</label>
                    <input type="text" id="smf-min-weight" name="config_min_charge"
                           class="form-control"
                           value="<?= e($cfgVal('min_charge', '0')) ?>"
                           inputmode="decimal">
                </div>
            </div>

            <!-- price_based: per_unit + min_charge + unit_size -->
            <div class="row g-3" data-shipping-config="price_based" hidden>
                <div class="col-md-4">
                    <label class="muted small" for="smf-per-unit">
                        Rate per unit <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-per-unit" name="config_per_unit"
                           class="form-control <?= $err('config_per_unit') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($cfgVal('per_unit', '0')) ?>"
                           inputmode="decimal">
                    <?php if ($err('config_per_unit') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('config_per_unit')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="muted small" for="smf-unit-size">
                        Unit size <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="smf-unit-size" name="config_unit_size"
                           class="form-control <?= $err('config_unit_size') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($cfgVal('unit_size', '1')) ?>"
                           inputmode="decimal">
                    <div class="muted small mt-1">e.g. 1.00 for per-currency, 10.00 for per-ten.</div>
                    <?php if ($err('config_unit_size') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('config_unit_size')) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="muted small" for="smf-min-price">Minimum charge</label>
                    <input type="text" id="smf-min-price" name="config_min_charge"
                           class="form-control"
                           value="<?= e($cfgVal('min_charge', '0')) ?>"
                           inputmode="decimal">
                </div>
            </div>

            <!-- local_pickup: no config -->
            <div data-shipping-config="local_pickup" hidden>
                <p class="muted small mb-0">
                    Local pickup has no rate. The method always charges 0.00.
                </p>
            </div>

        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/shipping" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-shipping-method-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i>
            <?= $mode === 'edit' ? 'Save changes' : 'Create method' ?>
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>

<script>
(function () {
    'use strict';

    var typeSelect = document.querySelector('[data-shipping-method-type]');
    if (!typeSelect) return;

    function sync() {
        var active = typeSelect.value;
        document.querySelectorAll('[data-shipping-config]').forEach(function (block) {
            block.hidden = block.getAttribute('data-shipping-config') !== active;
        });
    }

    typeSelect.addEventListener('change', sync);
    sync();
})();
</script>
