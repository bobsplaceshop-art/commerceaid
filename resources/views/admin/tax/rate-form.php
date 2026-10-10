<?php
/**
 * Tax rate create / edit form.
 *
 * Context (from AdminTaxController::showCreateRate / showEditRate):
 *   $title     string
 *   $mode      'create' | 'edit'
 *   $rate      ?array<string,mixed>  null on create, row on edit
 *   $classes   array<int,array<string,mixed>>
 *   $preClass  int   preselected class_id (from ?class_id= or rate row)
 *   $errors    array<string,string>
 *   $old       array<string,mixed>
 *   $countries array<string,string>
 *
 * POSTs to /commerce/ca-admin/tax/rates         (create)
 *          /commerce/ca-admin/tax/rates/{id}    (edit)
 */
$title     = $title     ?? 'Tax rate';
$mode      = $mode      ?? 'create';
$rate      = $rate      ?? null;
$classes   = $classes   ?? [];
$preClass  = (int) ($preClass ?? 0);
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$countries = $countries ?? [];

$val = function (string $key, string $default = '') use ($old, $rate): string {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($rate === null) {
        return $default;
    }
    $prop = match ($key) {
        'tax_class_id'        => (string) ($rate['tax_class_id'] ?? ''),
        'country_code'        => (string) ($rate['country_code'] ?? ''),
        'state_code'          => (string) ($rate['state_code'] ?? ''),
        'rate_percent'        => (string) ($rate['rate_percent'] ?? ''),
        'priority'            => (string) ($rate['priority'] ?? ''),
        default               => $default,
    };
    return $prop;
};

$valBool = function (string $key, bool $default = false) use ($old, $rate): bool {
    if (array_key_exists($key, $old)) {
        return (bool) $old[$key];
    }
    if ($rate === null) {
        return $default;
    }
    $prop = match ($key) {
        'is_inclusive'        => ((int) ($rate['is_inclusive'] ?? 0)) === 1,
        'is_shipping_taxable' => ((int) ($rate['is_shipping_taxable'] ?? 0)) === 1,
        default               => $default,
    };
    return $prop;
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

// Determine the class to preselect: old input > explicit
// preClass > first class in list.
$selectedClassId = (int) $val('tax_class_id', (string) $preClass);
if ($selectedClassId === 0 && !empty($classes)) {
    $selectedClassId = (int) ($classes[0]['id'] ?? 0);
}

// Determine the country to preselect.
$selectedCountry = $val('country_code', 'DE');

$action = $mode === 'edit'
    ? '/commerce/ca-admin/tax/rates/' . (int) ($rate['id'] ?? 0)
    : '/commerce/ca-admin/tax/rates';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/tax" class="text-decoration-none">Tax</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($title) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($title) ?></h1>
        <p class="muted mb-0">
            A rate applies to one tax class in one country (and optionally one state or region).
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="<?= e($action) ?>" id="ra-tax-rate-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Rate details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="trf-class">
                        Tax class <span aria-hidden="true">*</span>
                    </label>
                    <select id="trf-class" name="tax_class_id"
                            class="form-select <?= $err('tax_class_id') !== '' ? 'is-invalid' : '' ?>"
                            required>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                    <?= $selectedClassId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e((string) $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('tax_class_id') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('tax_class_id')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="trf-country">
                        Country <span aria-hidden="true">*</span>
                    </label>
                    <select id="trf-country" name="country_code"
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
                    <label class="muted small" for="trf-state">State / region code</label>
                    <input type="text" id="trf-state" name="state_code"
                           class="form-control <?= $err('state_code') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('state_code')) ?>"
                           maxlength="10"
                           placeholder="Leave blank for country-wide">
                    <div class="muted small mt-1">
                        Optional. Use a two-letter code for US states or German Bundeslaender, or any short identifier your jurisdiction uses.
                    </div>
                    <?php if ($err('state_code') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('state_code')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="trf-rate">
                        Rate % <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="trf-rate" name="rate_percent"
                           class="form-control <?= $err('rate_percent') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('rate_percent', '0')) ?>"
                           inputmode="decimal"
                           required>
                    <div class="muted small mt-1">
                        Number between 0 and 100. Germany standard: 19. Reduced: 7.
                    </div>
                    <?php if ($err('rate_percent') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('rate_percent')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="trf-priority">Priority</label>
                    <input type="text" id="trf-priority" name="priority"
                           class="form-control"
                           value="<?= e($val('priority', '0')) ?>"
                           inputmode="numeric">
                    <div class="muted small mt-1">
                        Higher values win when two rates match the same destination. Rarely needed.
                    </div>
                </div>

                <div class="col-12"><hr></div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_inclusive" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="trf-inclusive" name="is_inclusive" value="1"
                               <?= $valBool('is_inclusive', false) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="trf-inclusive">
                            <strong>Prices include tax</strong>
                            <div class="muted" style="font-size:.75rem;">
                                On when the storefront shows gross prices (Germany, PAngV § 4).
                                Off when prices are net and the tax is added at checkout.
                            </div>
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_shipping_taxable" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="trf-ship-tax" name="is_shipping_taxable" value="1"
                               <?= $valBool('is_shipping_taxable', false) ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="trf-ship-tax">
                            <strong>Shipping is taxable</strong>
                            <div class="muted" style="font-size:.75rem;">
                                On when the shipping charge is part of the taxable base for this rate.
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
        <a href="/commerce/ca-admin/tax" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-tax-rate-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i>
            <?= $mode === 'edit' ? 'Save changes' : 'Create rate' ?>
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>
