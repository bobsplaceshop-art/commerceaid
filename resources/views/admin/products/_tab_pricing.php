<?php
/**
 * Product editor — Pricing tab.
 */
$currentTaxStatus = (string) $val('taxStatus', 'taxable');
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
            <select id="tax_class_id" name="tax_class_id" class="form-select" disabled>
                <option value="">- Standard -</option>
            </select>
            <div class="form-text">Custom tax classes ship with the tax engine (Stage 009).</div>
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
