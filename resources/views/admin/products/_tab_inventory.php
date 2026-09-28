<?php
/**
 * Product editor — Inventory tab.
 *
 * Simple products: stock quantity and threshold are editable.
 * Variable products: stock is managed per variant. This tab
 * shows a summary and a link to the per-product inventory
 * page (Stage 008D.2). The parent-level fields are disabled
 * and replaced with a read-only summary.
 */
$manageStock  = $valBool('manageStock', false);
$backorders   = $valBool('backordersAllowed', false);
$isVariable   = ($product ?? null) !== null && $product->isVariable();
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'inventory' ? ' show active' : '' ?>" id="tab-inventory" role="tabpanel">

<?php if ($isVariable): ?>

    <?php
    // Variable products: no parent inventory. Stock is per variant.
    // Show a summary + link. The full per-product inventory page
    // lands in Stage 008D.2; until then this link points at the
    // main inventory list filtered by product.
    ?>
    <div class="ra-alert ra-alert-info">
        <div style="font-size:1rem; font-weight:600; margin-bottom:.5rem;">
            <i class="bi bi-diagram-3 me-2"></i>
            Stock is managed per variant
        </div>
        <div class="small mb-2">
            This is a variable product. Each variant has its own
            inventory row with its own quantity, threshold, and
            movement history.
        </div>
        <div class="small">
            Adjust stock for each variant on the variations page
            or on the inventory page.
        </div>
    </div>

    <div style="margin-top:1.25rem; display:flex; gap:.75rem; flex-wrap:wrap;">
        <a href="/commerce/ca-admin/products/<?= (int) $product->id ?>/edit?tab=variants"
           class="ra-btn ra-btn-primary">
            <i class="bi bi-diagram-3 me-1"></i> Manage variations
        </a>
        <a href="/commerce/ca-admin/inventory?q=<?= e(urlencode($product->name)) ?>"
           class="ra-btn ra-btn-outline">
            <i class="bi bi-boxes me-1"></i> View in inventory
        </a>
    </div>

    <?php
    // Show the aggregate stock across all variants as read-only info.
    // Uses the inventoryRepo to fetch variant rows for this product.
    $inventoryRepo = \CorporateAid\Core\App::instance()
        ->container()
        ->get(\CorporateAid\Commerce\Repositories\InventoryRepository::class);
    $variantRows   = $inventoryRepo->listForProduct($product->id);
    $totalOnHand   = 0;
    $totalReserved = 0;
    foreach ($variantRows as $row) {
        $totalOnHand   += $row->quantity;
        $totalReserved += $row->reserved;
    }
    ?>
    <div class="ra-card mt-3">
        <div class="ra-card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <div class="muted small">Total across variants</div>
                    <div style="font-size:1.5rem; font-weight:600;">
                        <?= (int) $totalOnHand ?> on hand
                    </div>
                    <?php if ($totalReserved > 0): ?>
                        <div class="muted small"><?= (int) $totalReserved ?> reserved</div>
                    <?php endif; ?>
                </div>
                <div class="muted small text-end">
                    <?= count($variantRows) ?> variant<?= count($variantRows) === 1 ? '' : 's' ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

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

<?php endif; ?>

</div>
