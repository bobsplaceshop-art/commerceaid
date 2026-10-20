<?php
$salesSeries     = $salesSeries ?? [];
$totalRevenue    = $totalRevenue ?? 0.0;
$totalOrders     = $totalOrders ?? 0;
$averageOrder    = $averageOrder ?? 0.0;
$topProducts     = $topProducts ?? [];
$salesByCategory = $salesByCategory ?? [];
$statusBreakdown = $statusBreakdown ?? [];
$slowMoving      = $slowMoving ?? [];
$customerStats   = $customerStats ?? ['new'=>0,'returning'=>0,'total'=>0,'repeat_rate'=>0.0];
$days            = (int) ($days ?? 30);

// Chart geometry (same as dashboard)
$width = 900; $height = 240;
$padL = 50; $padR = 20; $padT = 20; $padB = 30;
$chartW = $width - $padL - $padR;
$chartH = $height - $padT - $padB;

$maxRevenue = 0;
foreach ($salesSeries as $s) if ($s['revenue'] > $maxRevenue) $maxRevenue = $s['revenue'];
if ($maxRevenue <= 0) $maxRevenue = 100;

$points = [];
$count = count($salesSeries);
for ($i = 0; $i < $count; $i++) {
    $x = $padL + ($i / max(1, $count - 1)) * $chartW;
    $y = $padT + (1 - ($salesSeries[$i]['revenue'] / $maxRevenue)) * $chartH;
    $points[] = round($x, 1) . ',' . round($y, 1);
}
$polyline = implode(' ', $points);

$categoryColors = ['#d4af37','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#6366f1'];
$totalCatSales  = array_sum(array_column($salesByCategory, 'revenue'));

$statusLabels = [
    'pending_payment' => 'Pending payment',
    'paid'            => 'Paid',
    'processing'      => 'Processing',
    'packed'          => 'Packed',
    'shipped'         => 'Shipped',
    'delivered'       => 'Delivered',
    'completed'       => 'Completed',
    'cancelled'       => 'Cancelled',
    'failed'          => 'Failed',
    'refunded'        => 'Refunded',
    'partially_refunded' => 'Partially refunded',
    'on_hold'         => 'On hold',
];
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Reports</h1>
        <p class="muted mb-0">Sales, products, customers, and operations for the selected window.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/reports/print?days=<?= $days ?>" target="_blank" class="ra-btn ra-btn-outline">
            <i class="bi bi-file-earmark-pdf me-1"></i> Save as PDF
        </a>
    </div>
</div>

<!-- Date Range Picker -->
<div class="ra-card mb-4">
    <div class="ra-card-body d-flex gap-2 align-items-center flex-wrap">
        <span class="text-muted small me-2">Range:</span>
        <?php foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last year'] as $d => $label): ?>
            <a href="/commerce/ca-admin/reports?days=<?= $d ?>"
               class="ra-btn ra-btn-sm <?= $days === $d ? 'ra-btn-primary' : 'ra-btn-outline' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="ra-card"><div class="ra-card-body">
            <div class="text-muted small mb-1">Revenue</div>
            <div class="h3 mb-0" style="color:var(--ra-success,#10b981);"><?= number_format($totalRevenue, 2) ?></div>
            <div class="text-muted small mt-1">from <?= (int) $totalOrders ?> orders</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="ra-card"><div class="ra-card-body">
            <div class="text-muted small mb-1">Orders</div>
            <div class="h3 mb-0"><?= (int) $totalOrders ?></div>
            <div class="text-muted small mt-1">paid orders</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="ra-card"><div class="ra-card-body">
            <div class="text-muted small mb-1">Average Order</div>
            <div class="h3 mb-0"><?= number_format($averageOrder, 2) ?></div>
            <div class="text-muted small mt-1">per order</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="ra-card"><div class="ra-card-body">
            <div class="text-muted small mb-1">Repeat Rate</div>
            <div class="h3 mb-0"><?= $customerStats['repeat_rate'] ?>%</div>
            <div class="text-muted small mt-1"><?= (int) $customerStats['returning'] ?> repeat buyers</div>
        </div></div>
    </div>
</div>

<!-- Sales Chart -->
<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Sales Trend</h2>
        <span class="text-muted small">Revenue (paid only)</span>
    </div>
    <div class="ra-card-body" style="overflow-x:auto;">
        <?php if ($maxRevenue <= 0): ?>
            <p class="text-muted text-center py-4 mb-0">No paid orders in this window.</p>
        <?php else: ?>
            <svg viewBox="0 0 <?= $width ?> <?= $height ?>" style="width:100%; height:auto; display:block;">
                <?php for ($i = 0; $i <= 4; $i++): ?>
                    <?php $y = $padT + ($i / 4) * $chartH; $val = $maxRevenue * (1 - $i / 4); ?>
                    <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $width - $padR ?>" y2="<?= $y ?>" stroke="#2d3748" stroke-width="1" stroke-dasharray="2 4"/>
                    <text x="<?= $padL - 6 ?>" y="<?= $y + 4 ?>" fill="#9ca3af" font-size="10" text-anchor="end"><?= number_format($val, 0) ?></text>
                <?php endfor; ?>
                <polygon points="<?= $padL ?>,<?= $padT + $chartH ?> <?= $polyline ?> <?= $width - $padR ?>,<?= $padT + $chartH ?>" fill="rgba(212, 175, 55, 0.15)"/>
                <polyline points="<?= $polyline ?>" fill="none" stroke="#d4af37" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                <?php for ($i = 0; $i < $count; $i += max(1, intdiv($count, 8))): ?>
                    <?php $x = $padL + ($i / max(1, $count - 1)) * $chartW; ?>
                    <text x="<?= $x ?>" y="<?= $height - 8 ?>" fill="#9ca3af" font-size="10" text-anchor="middle"><?= date('M j', strtotime($salesSeries[$i]['date'])) ?></text>
                <?php endfor; ?>
            </svg>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Top Products -->
    <div class="col-lg-7">
        <div class="ra-card h-100">
            <div class="ra-card-header"><h2 class="h6 mb-0">Top Products</h2></div>
            <div class="ra-card-body p-0">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted text-center py-4 mb-0">No products sold in this window.</p>
                <?php else: ?>
                    <div data-responsive="scroll">
                        <table class="ra-table">
                            <thead><tr><th>#</th><th>Product</th><th style="text-align:right;">Units</th><th style="text-align:right;">Revenue</th></tr></thead>
                            <tbody>
                            <?php foreach ($topProducts as $i => $p): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
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
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Category Doughnut -->
    <div class="col-lg-5">
        <div class="ra-card h-100">
            <div class="ra-card-header"><h2 class="h6 mb-0">Sales by Category</h2></div>
            <div class="ra-card-body">
                <?php if (empty($salesByCategory) || $totalCatSales <= 0): ?>
                    <p class="text-muted text-center py-4 mb-0">No category sales in this window.</p>
                <?php else: ?>
                    <?php
                    $cumulative = 0;
                    $stops = [];
                    foreach ($salesByCategory as $i => $c) {
                        $percent = ($c['revenue'] / $totalCatSales) * 100;
                        $color = $categoryColors[$i % count($categoryColors)];
                        $stops[] = "{$color} {$cumulative}% " . ($cumulative + $percent) . "%";
                        $cumulative += $percent;
                    }
                    ?>
                    <div class="d-flex align-items-center gap-4 mb-3">
                        <div style="width:120px;height:120px;border-radius:50%;background:conic-gradient(<?= implode(',', $stops) ?>);flex-shrink:0;"></div>
                        <div style="flex:1;">
                            <?php foreach ($salesByCategory as $i => $c): ?>
                                <div class="d-flex align-items-center gap-2 mb-1 small">
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

<!-- Slow Moving Stock -->
<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Slow-Moving Stock</h2>
        <span class="text-muted small">Products with stock but 2 or fewer sales</span>
    </div>
    <div class="ra-card-body p-0">
        <?php if (empty($slowMoving)): ?>
            <p class="text-muted text-center py-4 mb-0">All stocked products are selling well. No slow-movers to worry about.</p>
        <?php else: ?>
            <div data-responsive="scroll">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align:right;">In Stock</th>
                            <th style="text-align:right;">Sold (<?= $days ?>d)</th>
                            <th>Last Sale</th>
                            <th>Recommendation</th>
                        </tr>
                    </thead>
                <tbody>
                <?php foreach ($slowMoving as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?= e($p['name']) ?></div>
                            <div class="text-muted small"><?= e($p['sku'] ?? '—') ?></div>
                        </td>
                        <td style="text-align:right;"><?= (int) $p['stock'] ?></td>
                        <td style="text-align:right;"><?= (int) $p['sold'] ?></td>
                        <td>
                            <?php if ($p['days_since_last_sale'] !== null): ?>
                                <?= (int) $p['days_since_last_sale'] ?> days ago
                            <?php else: ?>
                                <span class="text-muted">Never sold in window</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['sold'] === 0 && $p['stock'] >= 10): ?>
                                <span class="ra-badge ra-badge-danger">Discount or stop restocking</span>
                            <?php elseif ($p['sold'] === 0): ?>
                                <span class="ra-badge ra-badge-warning">Low visibility</span>
                            <?php else: ?>
                                <span class="ra-badge ra-badge-muted">Monitor</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Customer & Order Ops -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="ra-card h-100">
            <div class="ra-card-header"><h2 class="h6 mb-0">Customers</h2></div>
            <div class="ra-card-body">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total customers</span><span class="fw-bold"><?= (int) $customerStats['total'] ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">New (in window)</span><span class="fw-bold" style="color:var(--ra-success,#10b981);">+<?= (int) $customerStats['new'] ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">Repeat buyers</span><span class="fw-bold"><?= (int) $customerStats['returning'] ?></span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Repeat rate</span><span class="fw-bold"><?= $customerStats['repeat_rate'] ?>%</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="ra-card h-100">
            <div class="ra-card-header"><h2 class="h6 mb-0">Orders by Status</h2></div>
            <div class="ra-card-body">
                <?php if (empty($statusBreakdown)): ?>
                    <p class="text-muted mb-0">No orders yet.</p>
                <?php else: ?>
                    <?php foreach ($statusBreakdown as $status => $n): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted"><?= e($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status))) ?></span>
                            <span class="fw-bold"><?= (int) $n ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
