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
                            <th style="width:90px;text-align:right;">Available</th>
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

        <!-- Movement history lands in File 7 -->

        <!-- Sticky action bar -->
        <div class="ra-sticky-actions">
            <div class="ra-sticky-actions-inner">
                <a href="/commerce/ca-admin/inventory" class="ra-btn ra-btn-outline">Cancel</a>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save changes
                </button>
            </div>
        </div>

        <div class="ra-sticky-spacer"></div>
    </form>

    <style>
    .ra-btn-sm { padding: .35rem .65rem; font-size: .82rem; }
    .ra-sticky-actions {
        position: fixed !important;
        bottom: 0 !important;
        left: var(--ra-sidebar-w, 260px) !important;
        right: 0 !important;
        padding: 1.5rem 2rem 1rem !important;
        background: linear-gradient(to top, #0b1220 55%, transparent 100%) !important;
        z-index: 900 !important;
        pointer-events: none !important;
    }
    [data-theme="light"] .ra-sticky-actions {
        background: linear-gradient(to top, #f4f6fb 55%, transparent 100%) !important;
    }
    .ra-sticky-actions-inner {
        display: flex !important;
        justify-content: flex-end !important;
        gap: .5rem !important;
        max-width: 1400px !important;
        margin: 0 auto !important;
        background: var(--ra-card-bg, #111c33) !important;
        border: 1px solid var(--ra-border, #1f2a44) !important;
        border-radius: 12px !important;
        padding: .75rem 1rem !important;
        box-shadow: 0 -4px 20px -8px rgba(0, 0, 0, 0.35) !important;
        pointer-events: auto !important;
    }
    .ra-sticky-spacer { height: 6rem !important; }
    @media (max-width: 900px) {
        .ra-sticky-actions { left: 0 !important; padding: 1rem !important; }
    }
    </style>
<?php endif; ?>
