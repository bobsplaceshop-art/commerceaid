<?php
/**
 * Admin dashboard content.
 *
 * @var array $inventoryHealth  ['low_stock' => int, 'out_of_stock' => int, 'units_on_hand' => int]
 */
$inventoryHealth = $inventoryHealth ?? ['low_stock' => 0, 'out_of_stock' => 0, 'units_on_hand' => 0];
$tiles = $tiles ?? [
    'products'  => ['total' => 0],
    'orders'    => ['total' => 0, 'pending' => 0],
    'customers' => ['total' => 0],
];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="muted mb-0">Welcome back.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="/commerce/ca-admin/products" class="ra-tile" style="text-decoration:none;color:inherit;display:block;">
            <div class="ra-tile-label">Products</div>
            <div class="ra-tile-value"><?= number_format((int) $tiles['products']['total']) ?></div>
            <div class="ra-tile-hint">
                <?= (int) $tiles['products']['total'] === 0 ? 'Catalog is empty' : 'View all products' ?>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/commerce/ca-admin/orders" class="ra-tile" style="text-decoration:none;color:inherit;display:block;">
            <div class="ra-tile-label">Orders</div>
            <div class="ra-tile-value"><?= number_format((int) $tiles['orders']['total']) ?></div>
            <div class="ra-tile-hint">
                <?php if ((int) $tiles['orders']['pending'] > 0): ?>
                    <strong style="color:#f59e0b;">
                        <?= (int) $tiles['orders']['pending'] ?>
                        awaiting action
                    </strong>
                <?php elseif ((int) $tiles['orders']['total'] === 0): ?>
                    No orders yet
                <?php else: ?>
                    View all orders
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <div class="ra-tile">
            <div class="ra-tile-label">Customers</div>
            <div class="ra-tile-value"><?= number_format((int) $tiles['customers']['total']) ?></div>
            <div class="ra-tile-hint">
                <?= (int) $tiles['customers']['total'] === 0 ? 'No customers yet' : 'Total registered customers' ?>
            </div>
        </div>
    </div>
</div>

<!-- Inventory health -->
<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Inventory health</h2>
        <a href="/commerce/ca-admin/inventory" class="ra-btn ra-btn-outline ra-btn-sm">
            Manage inventory
        </a>
    </div>
    <div class="ra-card-body">
        <div class="ra-health-grid">
            <a href="/commerce/ca-admin/inventory?status=low_stock" class="ra-health-tile">
                <div class="ra-health-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="ra-health-body">
                    <div class="ra-health-label">Low stock</div>
                    <div class="ra-health-value" style="color:#f59e0b;"><?= (int) $inventoryHealth['low_stock'] ?></div>
                </div>
            </a>
            <a href="/commerce/ca-admin/inventory?status=out_of_stock" class="ra-health-tile">
                <div class="ra-health-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
                    <i class="bi bi-x-octagon"></i>
                </div>
                <div class="ra-health-body">
                    <div class="ra-health-label">Out of stock</div>
                    <div class="ra-health-value" style="color:#ef4444;"><?= (int) $inventoryHealth['out_of_stock'] ?></div>
                </div>
            </a>
            <div class="ra-health-tile is-static">
                <div class="ra-health-icon" style="background: rgba(148, 163, 184, 0.15); color: var(--ra-muted,#94a3b8);">
                    <i class="bi bi-boxes"></i>
                </div>
                <div class="ra-health-body">
                    <div class="ra-health-label">Units on hand</div>
                    <div class="ra-health-value"><?= number_format((int) $inventoryHealth['units_on_hand']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ra-card">
    <div class="ra-card-header">
        <h2 class="h6 mb-0">Getting started</h2>
    </div>
    <div class="ra-card-body">
        <p class="mb-3">Complete business setup to unlock the catalog, orders, and customers areas.</p>
        <a href="/commerce/ca-admin/setup" class="ra-btn ra-btn-primary">
            <i class="bi bi-sliders me-1"></i> Go to setup
        </a>
    </div>
</div>


