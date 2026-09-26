<?php
/**
 * Setup tab: Tax.
 *
 * Form only for now. Backing tables (commerce_tax_*) ship in Stage 009.
 *
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';
?>

<form method="POST" action="/commerce/ca-admin/setup/tax" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-alert ra-alert-info mb-4">
        <i class="bi bi-info-circle me-1"></i>
        This section is under construction. Settings are accepted but not yet persisted.
        Full tax classes and rates ship in a future stage.
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Tax status</h2>
        </div>
        <div class="ra-card-body">

            <div class="mb-3">
                <label for="tax_status" class="form-label">How should prices be displayed?</label>
                <select id="tax_status" name="tax_status" class="form-select">
                    <option value="exclusive">Prices exclude tax (added at checkout)</option>
                    <option value="inclusive">Prices include tax</option>
                </select>
                <div class="form-text">
                    Choose based on how your market expects to see prices.
                    B2B is usually exclusive; B2C varies by country.
                </div>
            </div>

            <div class="mb-0">
                <label for="tax_id_label" class="form-label">Tax ID label</label>
                <input type="text" id="tax_id_label" name="tax_id_label" class="form-control"
                       placeholder="VAT number, TIN, PIN">
                <div class="form-text">
                    Label shown to customers when entering a tax identifier at checkout.
                </div>
            </div>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Tax rates</h2>
        </div>
        <div class="ra-card-body">
            <p class="muted small mb-0">
                Detailed tax classes (Standard, Reduced, Zero-rated, Exempt) and
                regional rates will be configurable once the tax engine ships.
                This will include support for per-country, per-region, and
                per-product tax rules.
            </p>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save preferences</button>
    </div>
</form>
