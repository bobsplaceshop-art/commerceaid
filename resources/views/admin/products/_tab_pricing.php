<?php
/**
 * Product editor — Pricing tab.
 */
$currentTaxStatus = (string) $val('taxStatus', 'taxable');
$currentTaxClassId = $val('taxClassId');
$currentTaxOverride = $val('taxRateOverridePercent', '');
$currentTaxOverrideInclusive = $val('taxRateOverrideInclusive');
$taxClasses = $taxClasses ?? [];
$currentTaxClassIdInt = ($currentTaxClassId !== null && $currentTaxClassId !== '')
    ? (int) $currentTaxClassId
    : 0;
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'pricing' ? ' show active' : '' ?>" id="tab-pricing" role="tabpanel">

    <div class="row g-3">
        <div class="col-md-4">
            <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
            <input type="number" id="price" name="price" class="form-control" required
                   min="0" step="0.01"
                   value="<?= e((string) $val('price', '0')) ?>">
            <?= $err('price') ?>
        </div>

        <div class="col-md-4">
            <label for="compare_at_price" class="form-label">Compare-at price</label>
            <input type="number" id="compare_at_price" name="compare_at_price" class="form-control"
                   min="0" step="0.01"
                   value="<?= e((string) $val('compareAtPrice')) ?>"
                   placeholder="Optional">
            <div class="form-text">Original price, struck through on storefront when on sale.</div>
            <?= $err('compare_at_price') ?>
        </div>

        <div class="col-md-4">
            <label for="cost_price" class="form-label">Cost price</label>
            <input type="number" id="cost_price" name="cost_price" class="form-control"
                   min="0" step="0.01"
                   value="<?= e((string) $val('costPrice')) ?>"
                   placeholder="Optional">
            <div class="form-text">What you pay. Used for margin reports.</div>
            <?= $err('cost_price') ?>
        </div>

        <div class="col-md-6">
            <label for="tax_status" class="form-label">Tax status</label>
            <select id="tax_status" name="tax_status" class="form-select">
                <option value="taxable" <?= $currentTaxStatus === 'taxable' ? 'selected' : '' ?>>Taxable</option>
                <option value="none"    <?= $currentTaxStatus === 'none'    ? 'selected' : '' ?>>None (tax exempt)</option>
            </select>
            <?= $err('tax_status') ?>
        </div>

        <div class="col-md-6">
            <label for="tax_class_id" class="form-label">Tax class</label>
            <select id="tax_class_id" name="tax_class_id" class="form-select">
                <option value="">- Store default -</option>
                <?php foreach ($taxClasses as $class): ?>
                    <?php $classId = (int) ($class['id'] ?? 0); ?>
                    <option value="<?= $classId ?>"<?= $currentTaxClassIdInt === $classId ? ' selected' : '' ?>>
                        <?= e((string) ($class['name'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                The class's rate applies at checkout for the destination.
                Products without a class use the store's default.
            </div>
            <?= $err('tax_class_id') ?>
        </div>

        <div class="col-md-6">
            <label for="tax_rate_override_percent" class="form-label">
                Tax rate override (%)
            </label>
            <input type="number" id="tax_rate_override_percent"
                   name="tax_rate_override_percent" class="form-control"
                   min="0" max="100" step="0.001"
                   value="<?= e((string) $currentTaxOverride) ?>"
                   placeholder="Leave blank to use the class rate">
            <div class="form-text">
                Set a specific rate for this product. Leave blank to use the
                tax class's rate for the destination. Values 0&ndash;100.
                Exempt products ignore this field.
            </div>
            <?= $err('tax_rate_override_percent') ?>
        </div>

        <div class="col-md-6">
            <?php
            // Three-state: null (inherit), 0 (exclusive), 1 (inclusive).
            // The product's override semantics decide which note the
            // storefront shows: "inkl." for inclusive, "zzgl." for
            // exclusive.
            $overrideIncVal = $currentTaxOverrideInclusive === null
                ? ''
                : (string) ((int) (bool) $currentTaxOverrideInclusive);
            ?>
            <label for="tax_rate_override_inclusive" class="form-label">
                Override pricing regime
            </label>
            <select id="tax_rate_override_inclusive"
                    name="tax_rate_override_inclusive"
                    class="form-select">
                <option value=""  <?= $overrideIncVal === ''  ? 'selected' : '' ?>>
                    Inherit from tax class
                </option>
                <option value="1" <?= $overrideIncVal === '1' ? 'selected' : '' ?>>
                    Prices include tax (inclusive)
                </option>
                <option value="0" <?= $overrideIncVal === '0' ? 'selected' : '' ?>>
                    Prices exclude tax (exclusive)
                </option>
            </select>
            <div class="form-text">
                Only applies when a tax rate override is set above.
                Inclusive means the price is gross (German PAngV).
                Exclusive means tax is added at checkout.
            </div>
            <?= $err('tax_rate_override_inclusive') ?>
        </div>

        <div class="col-md-6">
            <label for="min_order_quantity" class="form-label">Minimum order quantity</label>
            <input type="number" id="min_order_quantity" name="min_order_quantity" class="form-control"
                   min="1" step="1"
                   value="<?= (int) $val('minOrderQuantity', 1) ?>">
            <div class="form-text">Customers must add at least this many units. Useful for B2B/wholesale.</div>
            <?= $err('min_order_quantity') ?>
        </div>
    </div>

</div>
