<?php
/**
 * Shipping zone create / edit form.
 *
 * Context (from AdminShippingController::showCreateZone / showEditZone):
 *   $title     string
 *   $mode      'create' | 'edit'
 *   $zone      ?array<string,mixed>
 *   $methods   array<int,array<string,mixed>>
 *   $preMethod int   preselected method_id
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *   $countries array<string,string>
 *
 * POSTs to /commerce/ca-admin/shipping/zones          (create)
 *          /commerce/ca-admin/shipping/zones/{id}     (edit)
 *
 * Zone precedence: zones with a lower sort_order match
 * first. The merchant orders them most-specific-first
 * (e.g., Berlin at 0, Germany at 10).
 */
$title     = $title     ?? 'Shipping zone';
$mode      = $mode      ?? 'create';
$zone      = $zone      ?? null;
$methods   = $methods   ?? [];
$preMethod = (int) ($preMethod ?? 0);
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$countries = $countries ?? [];

$val = function (string $key, string $default = '') use ($old, $zone): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($zone === null) {
        return $default;
    }
    $prop = match ($key) {
        'method_id'     => (string) ($zone['method_id'] ?? ''),
        'country_code'  => (string) ($zone['country_code'] ?? ''),
        'region'        => (string) ($zone['region'] ?? ''),
        'sort_order'    => (string) ($zone['sort_order'] ?? '0'),
        'rate_override' => $zone['rate_override'] !== null ? (string) $zone['rate_override'] : '',
        default         => $default,
    };
    return $prop;
};

$valBool = function (string $key, bool $default = false) use ($old, $zone): bool {
    if (array_key_exists($key, $old)) {
        return (bool) $old[$key];
    }
    if ($zone === null) {
        return $default;
    }
    return $key === 'is_active'
        ? ((int) ($zone['is_active'] ?? 0)) === 1
        : $default;
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

// Determine the method to preselect: old input > explicit
// preMethod > first in list.
$selectedMethodId = (int) $val('method_id', (string) $preMethod);
if ($selectedMethodId === 0 && !empty($methods)) {
    $selectedMethodId = (int) ($methods[0]['id'] ?? 0);
}

$selectedCountry = $val('country_code', 'DE');

$action = $mode === 'edit'
    ? '/commerce/ca-admin/shipping/zones/' . (int) ($zone['id'] ?? 0)
    : '/commerce/ca-admin/shipping/zones';
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
            A zone declares where one method is available. The rate can override the method's computed rate.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" id="ra-shipping-zone-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Zone details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="szf-method">
                        Shipping method <span aria-hidden="true">*</span>
                    </label>
                    <select id="szf-method" name="method_id"
                            class="form-select <?= $err('method_id') !== '' ? 'is-invalid' : '' ?>"
                            required>
                        <?php foreach ($methods as $m): ?>
                            <option value="<?= (int) $m['id'] ?>"
                                    <?= $selectedMethodId === (int) $m['id'] ? 'selected' : '' ?>>
                                <?= e((string) $m['label']) ?>
                                (<?= e((string) $m['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('method_id') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('method_id')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="szf-country">
                        Country <span aria-hidden="true">*</span>
                    </label>
                    <select id="szf-country" name="country_code"
                            class="form-select <?= $err('country_code') !== '' ? 'is-invalid' : '' ?>"
                            required>
                        <?php foreach ($countries as $code => $label): ?>
                            <option value="<?= e($code) ?>"
                                    <?= $selectedCountry === $code ? 'selected' : '' ?>>
                                <?= e($label) ?> (<?= e($code) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('country_code') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('country_code')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="szf-region">Region</label>
                    <input type="text" id="szf-region" name="region"
                           class="form-control <?= $err('region') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('region')) ?>"
                           maxlength="60"
                           placeholder="Leave blank for country-wide">
                    <div class="muted small mt-1">
                        Optional. Any short identifier your jurisdiction uses (Berlin, BE, Bavaria). Use the same value as the customer's delivery region.
                    </div>
                    <?php if ($err('region') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('region')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="szf-order">Order</label>
                    <input type="text" id="szf-order" name="sort_order"
                           class="form-control"
                           value="<?= e($val('sort_order', '0')) ?>"
                           inputmode="numeric">
                    <div class="muted small mt-1">
                        Lower numbers match first. Put the most specific zone first — Berlin at 0, Germany at 10 — so a Berlin customer matches the Berlin zone.
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="szf-override">Rate override</label>
                    <input type="text" id="szf-override" name="rate_override"
                           class="form-control"
                           value="<?= e($val('rate_override')) ?>"
                           inputmode="decimal"
                           placeholder="Leave blank to use the method's rate">
                    <div class="muted small mt-1">
                        Replaces the method's computed rate for this zone only. Useful for surcharges to remote regions.
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="szf-active" name="is_active" value="1"
                               <?= $valBool('is_active', true) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="szf-active">
                            <strong>Active</strong>
                            <div class="muted" style="font-size:.75rem;">
                                Inactive zones are skipped during destination resolution.
                            </div>
                        </label>
                    </div>
                </div>

            </div>
        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/shipping" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-shipping-zone-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i>
            <?= $mode === 'edit' ? 'Save changes' : 'Create zone' ?>
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
