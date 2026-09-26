<?php
/**
 * Product editor — Inventory tab.
 *
 * Simple products only (variant_id = 0). Variants get their own
 * inventory rows in Stage 006F.
 */
$manageStock  = $valBool('manageStock', false);
$backorders   = $valBool('backordersAllowed', false);
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'inventory' ? ' show active' : '' ?>" id="tab-inventory" role="tabpanel">

    <div class="row g-3">
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="manage_stock" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="manage_stock" name="manage_stock" value="1"
                       <?= $manageStock ? 'checked' : '' ?>>
                <label class="form-check-label" for="manage_stock">
                    <strong>Track inventory for this product</strong>
                </label>
            </div>
            <div class="form-text ms-4">
                Turn off for services and digital goods that have unlimited stock.
            </div>
        </div>

        <div class="col-md-6" id="stock-qty-field">
            <label for="stock_quantity" class="form-label">Stock quantity</label>
            <input type="number" id="stock_quantity" name="stock_quantity" class="form-control"
                   min="0" step="1"
                   value="<?= (int) $stockQty ?>">
            <div class="form-text">Current units on hand.</div>
            <?= $err('stock_quantity') ?>
        </div>

        <div class="col-md-6">
            <label for="low_stock_threshold" class="form-label">Low stock threshold</label>
            <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control"
                   min="0" step="1"
                   value="<?= e((string) $val('lowStockThreshold')) ?>"
                   placeholder="Defaults to store setting">
            <div class="form-text">Alert when stock falls at or below this number.</div>
            <?= $err('low_stock_threshold') ?>
        </div>

        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="backorders_allowed" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="backorders_allowed" name="backorders_allowed" value="1"
                       <?= $backorders ? 'checked' : '' ?>>
                <label class="form-check-label" for="backorders_allowed">
                    <strong>Allow backorders</strong>
                </label>
            </div>
            <div class="form-text ms-4">
                Customers can order even when stock reaches zero.
            </div>
        </div>

        <?php if ($inventory !== null): ?>
            <div class="col-12 mt-2">
                <div class="ra-alert ra-alert-info">
                    <div><strong>Current stock:</strong> <?= (int) $inventory->quantity ?> on hand</div>
                    <div class="small">Reserved: <?= (int) $inventory->reserved ?> &middot; Available: <?= (int) $inventory->available ?></div>
                    <div class="small">
                        All stock changes are logged. <a href="#" onclick="alert('Inventory history view ships in Stage 006G'); return false;">View history</a>.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>
