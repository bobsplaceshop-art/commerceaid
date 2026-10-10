<?php
/**
 * Setup tab: Currency & Region.
 *
 * @var array  $store
 * @var array  $currencies
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$store      = $store      ?? [];
$currencies = $currencies ?? [];
$errors     = $errors     ?? [];
$old        = $old        ?? [];
$csrf       = $csrf       ?? '';

use CorporateAid\Commerce\Support\StoreFieldReader;
$val = fn(string $k, $d = '') => e((string) ($old[$k] ?? StoreFieldReader::read($store, $k, $d)));
$err = fn(string $k) => isset($errors[$k])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$k]) . '</div>'
    : '';

$currentCurrency = (int) ($old['default_currency_id'] ?? StoreFieldReader::read($store, 'default_currency_id', 0));
$currentTz       = $val('timezone', 'Africa/Nairobi');
$currentLocale   = $val('locale', 'en_KE');
$currentCountry  = $val('country_code', '');
$currentDateFmt  = $val('date_format', 'd/m/Y');
$currentNumFmt   = $val('number_format', '1,234.56');
$currentWeight   = $val('weight_unit', 'kg');
$currentDim      = $val('dimension_unit', 'cm');
?>

<form method="POST" action="/commerce/ca-admin/setup/currency" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Currency</h2>
        </div>
        <div class="ra-card-body">
            <div class="mb-0">
                <label for="default_currency_id" class="form-label">Default currency <span class="text-danger">*</span></label>
                <select id="default_currency_id" name="default_currency_id" class="form-select" required>
                    <option value="">- Select -</option>
                    <?php foreach ($currencies as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $currentCurrency === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['code'] . ' - ' . $c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">All prices are stored in this currency. Additional currencies can be enabled later.</div>
                <?= $err('default_currency_id') ?>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Region &amp; locale</h2>
        </div>
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="country_code" class="form-label">Country <span class="text-danger">*</span></label>
                    <input type="text" id="country_code" name="country_code" class="form-control" required
                           maxlength="2" pattern="[A-Za-z]{2}"
                           value="<?= $currentCountry ?>"
                           placeholder="KE, DE, US">
                    <div class="form-text">ISO 3166-1 alpha-2 (two letters).</div>
                    <?= $err('country_code') ?>
                </div>

                <div class="col-md-6">
                    <label for="timezone" class="form-label">Timezone <span class="text-danger">*</span></label>
                    <input type="text" id="timezone" name="timezone" class="form-control" required
                           value="<?= $currentTz ?>"
                           placeholder="Africa/Nairobi">
                    <div class="form-text">IANA timezone identifier.</div>
                    <?= $err('timezone') ?>
                </div>

                <div class="col-md-6">
                    <label for="locale" class="form-label">Locale</label>
                    <input type="text" id="locale" name="locale" class="form-control"
                           value="<?= $currentLocale ?>"
                           placeholder="en_KE">
                    <div class="form-text">Language and region, e.g. <code>en_KE</code>, <code>de_DE</code>.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Formats &amp; units</h2>
        </div>
        <div class="ra-card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="date_format" class="form-label">Date format</label>
                    <select id="date_format" name="date_format" class="form-select">
                        <?php foreach (['d/m/Y', 'm/d/Y', 'Y-m-d', 'd.m.Y', 'd M Y'] as $fmt): ?>
                            <option value="<?= e($fmt) ?>" <?= $currentDateFmt === $fmt ? 'selected' : '' ?>>
                                <?= e($fmt . '  -  ' . date($fmt)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="number_format" class="form-label">Number format</label>
                    <select id="number_format" name="number_format" class="form-select">
                        <?php foreach (['1,234.56', '1.234,56', '1 234,56', '1,234'] as $fmt): ?>
                            <option value="<?= e($fmt) ?>" <?= $currentNumFmt === $fmt ? 'selected' : '' ?>>
                                <?= e($fmt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="weight_unit" class="form-label">Weight unit</label>
                    <select id="weight_unit" name="weight_unit" class="form-select">
                        <option value="kg" <?= $currentWeight === 'kg' ? 'selected' : '' ?>>Kilograms (kg)</option>
                        <option value="lb" <?= $currentWeight === 'lb' ? 'selected' : '' ?>>Pounds (lb)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="dimension_unit" class="form-label">Dimension unit</label>
                    <select id="dimension_unit" name="dimension_unit" class="form-select">
                        <option value="cm" <?= $currentDim === 'cm' ? 'selected' : '' ?>>Centimetres (cm)</option>
                        <option value="in" <?= $currentDim === 'in' ? 'selected' : '' ?>>Inches (in)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save</button>
    </div>
</form>
