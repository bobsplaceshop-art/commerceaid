<?php
/**
 * Product editor — Shipping tab.
 *
 * Weight/dimension units come from store config (not shown here,
 * but referenced in the field help text).
 */
$requiresShipping = $valBool('requiresShipping', true);
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'shipping' ? ' show active' : '' ?>" id="tab-shipping" role="tabpanel">

    <div class="row g-3">
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="requires_shipping" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="requires_shipping" name="requires_shipping" value="1"
                       <?= $requiresShipping ? 'checked' : '' ?>>
                <label class="form-check-label" for="requires_shipping">
                    <strong>This product requires shipping</strong>
                </label>
            </div>
            <div class="form-text ms-4">
                Turn off for services, downloads, or anything not physically shipped.
            </div>
        </div>

        <div class="col-md-3">
            <label for="weight" class="form-label">Weight</label>
            <input type="number" id="weight" name="weight" class="form-control"
                   min="0" step="0.001"
                   value="<?= e((string) $val('weight')) ?>"
                   placeholder="0.000">
            <div class="form-text">Uses store weight unit.</div>
            <?= $err('weight') ?>
        </div>

        <div class="col-md-3">
            <label for="length" class="form-label">Length</label>
            <input type="number" id="length" name="length" class="form-control"
                   min="0" step="0.01"
                   value="<?= e((string) $val('length')) ?>">
            <?= $err('length') ?>
        </div>

        <div class="col-md-3">
            <label for="width" class="form-label">Width</label>
            <input type="number" id="width" name="width" class="form-control"
                   min="0" step="0.01"
                   value="<?= e((string) $val('width')) ?>">
            <?= $err('width') ?>
        </div>

        <div class="col-md-3">
            <label for="height" class="form-label">Height</label>
            <input type="number" id="height" name="height" class="form-control"
                   min="0" step="0.01"
                   value="<?= e((string) $val('height')) ?>">
            <?= $err('height') ?>
        </div>

        <div class="col-12">
            <div class="form-text">
                Dimensions are used by the shipping rate calculator.
                Configure units in <a href="/commerce/ca-admin/setup/currency">Store Settings &rarr; Currency &amp; Region</a>.
            </div>
        </div>
    </div>

</div>
