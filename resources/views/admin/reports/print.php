<?php
/** @var array $salesSeries */
$salesSeries     = $salesSeries ?? [];
$totalRevenue    = $totalRevenue ?? 0.0;
$totalOrders     = $totalOrders ?? 0;
$averageOrder    = $averageOrder ?? 0.0;
$topProducts     = $topProducts ?? [];
$salesByCategory = $salesByCategory ?? [];
$statusBreakdown = $statusBreakdown ?? [];
$customerStats   = $customerStats ?? ['new'=>0,'returning'=>0,'total'=>0,'repeat_rate'=>0.0];
$days            = (int) ($days ?? 30);
$storeName       = $store !== null ? $store->name : 'Store';
$categoryColors  = ['#d4af37','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'];
$totalCatSales   = array_sum(array_column($salesByCategory, 'revenue'));
$statusLabels = [
    'pending_payment' => 'Pending payment', 'paid' => 'Paid', 'processing' => 'Processing',
    'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered',
    'completed' => 'Completed', 'cancelled' => 'Cancelled', 'failed' => 'Failed',
    'refunded' => 'Refunded', 'partially_refunded' => 'Partially refunded', 'on_hold' => 'On hold',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - <?= e($storeName) ?></title>
    <style>
        @page { size: A4; margin: 15mm; }
        * { box-sizing: border-box; }
        body { font-family: -apple-system, "Segoe UI", Helvetica, Arial, sans-serif; color: #111827; font-size: 11pt; line-height: 1.4; margin: 0; }
        h1 { font-size: 22pt; margin: 0 0 4pt; }
        h2 { font-size: 13pt; margin: 24pt 0 8pt; border-bottom: 1px solid #e5e7eb; padding-bottom: 4pt; }
        .meta { color: #6b7280; font-size: 9pt; margin-bottom: 16pt; }
        .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12pt; margin-bottom: 16pt; }
        .kpi { border: 1px solid #e5e7eb; border-radius: 6pt; padding: 10pt; }
        .kpi-label { color: #6b7280; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.05em; }
        .kpi-value { font-size: 16pt; font-weight: 700; margin-top: 2pt; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th, td { padding: 6pt 8pt; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f9fafb; font-weight: 600; }
        td.num, th.num { text-align: right; }
        .doughnut-row { display: flex; align-items: center; gap: 16pt; }
        .legend { flex: 1; }
        .legend-row { display: flex; justify-content: space-between; font-size: 9pt; padding: 3pt 0; border-bottom: 1px solid #f3f4f6; }
        .swatch { display: inline-block; width: 8pt; height: 8pt; border-radius: 2pt; margin-right: 6pt; vertical-align: middle; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16pt; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
    <script>
        // Auto-trigger the browser's print dialog on load.
        // The user saves it as a PDF via the print dialog.
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 500);
        });
    </script>
</head>
<body>

<h1>Reports</h1>
<div class="meta">
    <?= e($storeName) ?> · Last <?= $days ?> days · Generated <?= date('M j, Y \a\t H:i') ?>
</div>

<h2>Summary</h2>
<div class="kpi-grid">
    <div class="kpi"><div class="kpi-label">Revenue</div><div class="kpi-value"><?= number_format($totalRevenue, 2) ?></div></div>
    <div class="kpi"><div class="kpi-label">Orders</div><div class="kpi-value"><?= (int) $totalOrders ?></div></div>
    <div class="kpi"><div class="kpi-label">Average Order</div><div class="kpi-value"><?= number_format($averageOrder, 2) ?></div></div>
    <div class="kpi"><div class="kpi-label">Repeat Rate</div><div class="kpi-value"><?= $customerStats['repeat_rate'] ?>%</div></div>
</div>

<h2>Top Products</h2>
<?php if (empty($topProducts)): ?>
    <p style="color:#6b7280;">No products sold in this window.</p>
<?php else: ?>
    <table>
        <thead><tr><th>#</th><th>Product</th><th>SKU</th><th class="num">Units</th><th class="num">Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($topProducts as $i => $p): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($p['name']) ?></td>
                <td><?= e($p['sku'] ?? '—') ?></td>
                <td class="num"><?= (int) $p['units'] ?></td>
                <td class="num"><?= number_format($p['revenue'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Sales by Category</h2>
<?php if (empty($salesByCategory) || $totalCatSales <= 0): ?>
    <p style="color:#6b7280;">No category sales in this window.</p>
<?php else: ?>
    <div class="doughnut-row">
        <?php
        $cumulative = 0; $stops = [];
        foreach ($salesByCategory as $i => $c) {
            $percent = ($c['revenue'] / $totalCatSales) * 100;
            $color = $categoryColors[$i % count($categoryColors)];
            $stops[] = "{$color} {$cumulative}% " . ($cumulative + $percent) . "%";
            $cumulative += $percent;
        }
        ?>
        <div style="width:110pt;height:110pt;border-radius:50%;background:conic-gradient(<?= implode(',', $stops) ?>);flex-shrink:0;"></div>
        <div class="legend">
            <?php foreach ($salesByCategory as $i => $c): ?>
                <div class="legend-row">
                    <span><span class="swatch" style="background:<?= $categoryColors[$i % count($categoryColors)] ?>;"></span><?= e($c['name']) ?></span>
                    <strong><?= number_format($c['revenue'], 2) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<h2>Customers &amp; Orders</h2>
<div class="two-col">
    <div>
        <div style="display:flex;justify-content:space-between;padding:4pt 0;border-bottom:1px solid #f3f4f6;"><span>Total customers</span><strong><?= (int) $customerStats['total'] ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:4pt 0;border-bottom:1px solid #f3f4f6;"><span>New (in window)</span><strong>+<?= (int) $customerStats['new'] ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:4pt 0;border-bottom:1px solid #f3f4f6;"><span>Repeat buyers</span><strong><?= (int) $customerStats['returning'] ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:4pt 0;"><span>Repeat rate</span><strong><?= $customerStats['repeat_rate'] ?>%</strong></div>
    </div>
    <div>
        <?php foreach ($statusBreakdown as $status => $n): ?>
            <div style="display:flex;justify-content:space-between;padding:4pt 0;border-bottom:1px solid #f3f4f6;">
                <span><?= e($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', (string)$status))) ?></span>
                <strong><?= (int) $n ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
