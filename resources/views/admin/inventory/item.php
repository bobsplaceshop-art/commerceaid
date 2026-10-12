<?php
/**
 * Per-product inventory detail page.
 *
 * Vars (from InventoryItemController::show):
 *   @var \CorporateAid\Commerce\Models\Product $product
 *   @var array<int, array<string,mixed>>       $units
 *   @var array<string,int|string>              $stats
 */
$product = $product ?? null;
$units   = $units   ?? [];
$stats   = $stats   ?? [];

// Per-row errors from the save handler, if any.
$rowErrors = function_exists('flash')
    ? (array) (flash('inventory_item_errors') ?? [])
    : [];

if ($product === null) {
    echo '<div class="ra-alert ra-alert-error">Product not found.</div>';
    return;
}

$productId    = (int) $product->id;
$isVariable   = ((int) ($stats['is_variable'] ?? 0)) === 1;
$unitCount    = (int) ($stats['unit_count'] ?? 0);
$totalUnits   = (int) ($stats['total_units'] ?? 0);
$lowCount     = (int) ($stats['low_count'] ?? 0);
$statusLabel  = (string) ($stats['status_label'] ?? 'In stock');
$statusClass  = (string) ($stats['status_class'] ?? 'ra-badge-success');
$typeLabel    = $isVariable
    ? $unitCount . ' variant' . ($unitCount === 1 ? '' : 's')
    : 'Simple product';
?>

<!-- Breadcrumb -->
<nav class="mb-3" aria-label="Breadcrumb">
    <a href="/commerce/ca-admin/inventory" class="text-decoration-none muted small">
        <i class="bi bi-arrow-left me-1"></i> Back to Inventory
    </a>
</nav>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 mb-1"><?= e($product->name) ?></h1>
        <p class="muted mb-0 small">
            <?= e($typeLabel) ?>
            <?php if ($product->sku !== null && $product->sku !== ''): ?>
                &middot; <code><?= e($product->sku) ?></code>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="text-end">
            <div class="muted small">Total units</div>
            <div class="h4 mb-0"><?= $totalUnits ?></div>
        </div>
        <?php if ($isVariable): ?>
            <div class="text-end">
                <div class="muted small">Low stock</div>
                <div class="h4 mb-0"><?= $lowCount ?></div>
            </div>
        <?php endif; ?>
        <span class="ra-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
    </div>
</div>

<?php if ($rowErrors !== []): ?>
    <div class="ra-alert ra-alert-error mb-3">
        <strong>Some rows could not be saved:</strong>
        <ul class="mb-0 mt-1 small">
            <?php foreach ($rowErrors as $err): ?>
                <li><?= e((string) $err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (empty($units)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No stock rows</h2>
            <p class="muted small mb-0">
                This product has no sellable units yet.
                <?php if ($isVariable): ?>
                    Generate variations to create stock rows.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <form method="POST" action="/commerce/ca-admin/inventory/item/<?= $productId ?>" id="ra-inventory-item-form">
        <?= csrf_field() ?>

        <div class="ra-card">
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th>Variant</th>
                            <th class="ra-hide-sm" style="width:160px;">SKU</th>
                            <th style="width:140px;text-align:right;">On hand</th>
                            <th class="ra-hide-sm" style="width:90px;text-align:right;">Reserved</th>
                            <th class="ra-hide-sm" style="width:90px;text-align:right;">Available</th>
                            <th style="width:130px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($units as $u): ?>
                            <?= view_partial('admin.inventory._variant-row', ['u' => $u]) ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </form>

    <?= view_partial('admin.inventory._movement-block', [
        'movements'   => $movements,
        'moveTotal'   => $moveTotal,
        'productId'   => $productId,
        'productName' => $product->name,
    ]) ?>

    <!-- Sticky action bar (outside the form, bound via form="" attribute) -->
    <div class="ra-sticky-actions">
        <div class="ra-sticky-actions-inner">
            <a href="/commerce/ca-admin/inventory" class="ra-btn ra-btn-outline">Cancel</a>
            <button type="submit" form="ra-inventory-item-form" class="ra-btn ra-btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save changes
            </button>
        </div>
    </div>

    <div class="ra-sticky-spacer"></div>

    <style>
    
    </style>
<?php endif; ?>
