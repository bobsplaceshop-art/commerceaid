<?php
/**
 * Admin dashboard with analytics.
 */
$inventoryHealth = $inventoryHealth ?? ['low_stock' => 0, 'out_of_stock' => 0, 'units_on_hand' => 0];
$tiles = $tiles ?? [
    'products'  => ['total' => 0],
    'orders'    => ['total' => 0, 'pending' => 0],
    'customers' => ['total' => 0],
];
$salesSeries     = $salesSeries ?? [];
$totalRevenue    = $totalRevenue ?? 0.0;
$totalOrders     = $totalOrders ?? 0;
$averageOrder    = $averageOrder ?? 0.0;
$topProducts     = $topProducts ?? [];
$salesByCategory = $salesByCategory ?? [];
$statusBreakdown = $statusBreakdown ?? [];
$customerStats   = $customerStats ?? ['new' => 0, 'returning' => 0, 'total' => 0, 'repeat_rate' => 0.0];

// SVG line chart geometry
$width = 800;
$height = 200;
$padL = 40; $padR = 20; $padT = 20; $padB = 30;
$chartW = $width - $padL - $padR;
$chartH = $height - $padT - $padB;

$maxRevenue = 0;
foreach ($salesSeries as $s) {
    if ($s['revenue'] > $maxRevenue) $maxRevenue = $s['revenue'];
}
if ($maxRevenue <= 0) $maxRevenue = 100;

$points = [];
$count = count($salesSeries);
for ($i = 0; $i < $count; $i++) {
    $x = $padL + ($i / max(1, $count - 1)) * $chartW;
    $y = $padT + (1 - ($salesSeries[$i]['revenue'] / $maxRevenue)) * $chartH;
    $points[] = round($x, 1) . ',' . round($y, 1);
}
$polyline = implode(' ', $points);

// Total sales by category for the doughnut
$totalCatSales = array_sum(array_column($salesByCategory, 'revenue'));
$categoryColors = ['#d4af37', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#6366f1'];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Dashboard</h1>
        <p class="muted mb-0">Last 30 days · Updated <?= date('M j, H:i') ?></p>
    </div>
    <a href="/commerce/ca-admin/orders" class="ra-btn ra-btn-outline ra-btn-sm">
        View all orders
    </a>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="text-muted small mb-1">Revenue</div>
                <div class="h3 mb-0" style="color:var(--ra-success,#10b981);"><?= number_format($totalRevenue, 2) ?></div>
                <div class="text-muted small mt-1">from <?= (int) $totalOrders ?> paid orders</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="text-muted small mb-1">Average Order</div>
                <div class="h3 mb-0"><?= number_format($averageOrder, 2) ?></div>
                <div class="text-muted small mt-1">per paid order</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="text-muted small mb-1">New Customers</div>
                <div class="h3 mb-0"><?= (int) $customerStats['new'] ?></div>
                <div class="text-muted small mt-1"><?= (int) $customerStats['returning'] ?> returning · <?= $customerStats['repeat_rate'] ?>% repeat rate</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="text-muted small mb-1">Pending</div>
                <div class="h3 mb-0" style="color:#f59e0b;"><?= (int) $tiles['orders']['pending'] ?></div>
                <div class="text-muted small mt-1">orders awaiting action</div>
            </div>
        </div>
    </div>
</div>

<!-- Sales Trend Chart -->
<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Sales over the last 30 days</h2>
        <span class="text-muted small">Revenue (paid only)</span>
    </div>
    <div class="ra-card-body" style="overflow-x:auto;">
        <?php if ($maxRevenue <= 0): ?>
            <p class="text-muted text-center py-4 mb-0">No paid orders yet. Your first sale will appear here.</p>
        <?php else: ?>
            <svg viewBox="0 0 <?= $width ?> <?= $height ?>" style="width:100%; height:auto; min-width:500px;">
                <!-- Y-axis grid lines -->
                <?php for ($i = 0; $i <= 4; $i++): ?>
                    <?php
                    $y = $padT + ($i / 4) * $chartH;
                    $val = $maxRevenue * (1 - $i / 4);
                    ?>
                    <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>" stroke="#2d3748" stroke-width="1" stroke-dasharray="2 4"/>
                    <text x="<?= $padL - 6 ?>" y="<?= $y + 4 ?>" fill="#9ca3af" font-size="10" text-anchor="end"><?= e(($store->defaultCurrency ?? '') . ' ' . number_format($val, 0)) ?></text>
                <?php endfor; ?>
                <!-- Area fill -->
                <polygon points="<?= $padL ?>,<?= $padT + $chartH ?> <?= $polyline ?> <?= $width - $padR ?>,<?= $padT + $chartH ?>" fill="rgba(212, 175, 55, 0.15)"/>
                <!-- Line -->
                <polyline points="<?= $polyline ?>" fill="none" stroke="#d4af37" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                <!-- X-axis labels (every 5th day) -->
                <?php for ($i = 0; $i < $count; $i += 5): ?>
                    <?php $x = $padL + ($i / max(1, $count - 1)) * $chartW; ?>
                    <text x="<?= $x ?>" y="<?= $height - 8 ?>" fill="#9ca3af" font-size="10" text-anchor="middle"><?= date('M j', strtotime($salesSeries[$i]['date'])) ?></text>
                <?php endfor; ?>
            </svg>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Top Products -->
    <div class="col-lg-6">
        <div class="ra-card h-100">
            <div class="ra-card-header d-flex align-items-center justify-content-between">
                <h2 class="h6 mb-0">Top Products (30 days)</h2>
                <a href="/commerce/ca-admin/products" class="text-muted small">All products</a>
            </div>
            <div class="ra-card-body p-0">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted text-center py-4 mb-0">No products sold yet.</p>
                <?php else: ?>
                    <table class="ra-table">
                        <thead><tr><th>Product</th><th style="text-align:right;">Units</th><th style="text-align:right;">Revenue</th></tr></thead>
                        <tbody>
                        <?php foreach ($topProducts as $p): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= e($p['name']) ?></div>
                                    <div class="text-muted small"><?= e($p['sku'] ?? '—') ?></div>
                                </td>
                                <td style="text-align:right;"><?= (int) $p['units'] ?></td>
                                <td style="text-align:right;"><?= number_format($p['revenue'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Doughnut -->
    <div class="col-lg-6">
        <div class="ra-card h-100">
            <div class="ra-card-header">
                <h2 class="h6 mb-0">Sales by Category</h2>
            </div>
            <div class="ra-card-body">
                <?php if (empty($salesByCategory) || $totalCatSales <= 0): ?>
                    <p class="text-muted text-center py-4 mb-0">No category sales yet.</p>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-4">
                        <?php
                        // Build the conic-gradient string
                        $cumulative = 0;
                        $stops = [];
                        foreach ($salesByCategory as $i => $c) {
                            $percent = ($c['revenue'] / $totalCatSales) * 100;
                            $color = $categoryColors[$i % count($categoryColors)];
                            $stops[] = "{$color} {$cumulative}% " . ($cumulative + $percent) . "%";
                            $cumulative += $percent;
                        }
                        ?>
                        <div style="width:140px;height:140px;border-radius:50%;background:conic-gradient(<?= implode(',', $stops) ?>);flex-shrink:0;"></div>
                        <div style="flex:1;">
                            <?php foreach ($salesByCategory as $i => $c): ?>
                                <div class="d-flex align-items-center gap-2 mb-2 small">
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= $categoryColors[$i % count($categoryColors)] ?>;"></span>
                                    <span style="flex:1;"><?= e($c['name']) ?></span>
                                    <span class="text-muted"><?= number_format($c['revenue'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Inventory health (existing) -->
<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Inventory health</h2>
        <a href="/commerce/ca-admin/inventory" class="ra-btn ra-btn-outline ra-btn-sm">Manage inventory</a>
    </div>
    <div class="ra-card-body">
        <div class="ra-health-grid">
            <a href="/commerce/ca-admin/inventory?status=low_stock" class="ra-health-tile">
                <div class="ra-health-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="ra-health-body">
                    <div class="ra-health-label">Low stock</div>
                    <div class="ra-health-value" style="color:#f59e0b;"><?= (int) $inventoryHealth['low_stock'] ?></div>
                </div>
            </a>
            <a href="/commerce/ca-admin/inventory?status=out_of_stock" class="ra-health-tile">
                <div class="ra-health-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;"><i class="bi bi-x-octagon"></i></div>
                <div class="ra-health-body">
                    <div class="ra-health-label">Out of stock</div>
                    <div class="ra-health-value" style="color:#ef4444;"><?= (int) $inventoryHealth['out_of_stock'] ?></div>
                </div>
            </a>
            <div class="ra-health-tile is-static">
                <div class="ra-health-icon" style="background: rgba(148, 163, 184, 0.15); color: var(--ra-muted,#94a3b8);"><i class="bi bi-boxes"></i></div>
                <div class="ra-health-body">
                    <div class="ra-health-label">Units on hand</div>
                    <div class="ra-health-value"><?= number_format((int) $inventoryHealth['units_on_hand']) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
