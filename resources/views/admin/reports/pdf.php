<?php
$salesSeries     = $salesSeries ?? [];
$totalRevenue    = $totalRevenue ?? 0.0;
$totalOrders     = $totalOrders ?? 0;
$averageOrder    = $averageOrder ?? 0.0;
$topProducts     = $topProducts ?? [];
$salesByCategory = $salesByCategory ?? [];
$statusBreakdown = $statusBreakdown ?? [];
$customerStats   = $customerStats ?? ['new'=>0,'returning'=>0,'total'=>0,'repeat_rate'=>0.0];
$slowMoving      = $slowMoving ?? [];
$days            = (int) ($days ?? 30);
$storeName       = $store !== null ? $store->name : 'Store';

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
</head>
<body style="font-family: dejavusans, sans-serif; color: #1f2937; font-size: 10pt; line-height: 1.4; margin: 0;">

<!-- HEADER -->
<div style="font-size: 12pt; font-weight: bold; color: #111827;"><?= e($storeName) ?></div>
<div style="font-size: 22pt; font-weight: bold; color: #111827; margin: 4px 0;">Reports</div>
<div style="color: #6b7280; font-size: 9pt; padding-bottom: 8px; border-bottom: 2pt solid #d4af37; margin-bottom: 16px;">
    Last <?= $days ?> days &middot; Generated <?= date('M j, Y \a\t H:i') ?>
</div>

<!-- SUMMARY KPIs (4 boxes across) -->
<div style="font-size: 12pt; color: #111827; padding: 6px 0; border-bottom: 0.5pt solid #e5e7eb; margin-top: 10px; margin-bottom: 10px;">Summary</div>
<table width="100%" cellpadding="0" cellspacing="4" border="0">
    <tr>
        <td width="25%" valign="top">
            <div style="border: 0.5pt solid #e5e7eb; border-radius: 4px; padding: 10px; background-color: #fafafa;">
                <div style="color: #6b7280; font-size: 8pt; text-transform: uppercase;">Revenue</div>
                <div style="font-size: 14pt; font-weight: bold; color: #111827; padding: 4px 0;"><?= number_format($totalRevenue, 2) ?></div>
                <div style="color: #9ca3af; font-size: 8pt;">from <?= (int) $totalOrders ?> orders</div>
            </div>
        </td>
        <td width="25%" valign="top">
            <div style="border: 0.5pt solid #e5e7eb; border-radius: 4px; padding: 10px; background-color: #fafafa;">
                <div style="color: #6b7280; font-size: 8pt; text-transform: uppercase;">Orders</div>
                <div style="font-size: 14pt; font-weight: bold; color: #111827; padding: 4px 0;"><?= (int) $totalOrders ?></div>
                <div style="color: #9ca3af; font-size: 8pt;">paid</div>
            </div>
        </td>
        <td width="25%" valign="top">
            <div style="border: 0.5pt solid #e5e7eb; border-radius: 4px; padding: 10px; background-color: #fafafa;">
                <div style="color: #6b7280; font-size: 8pt; text-transform: uppercase;">Average Order</div>
                <div style="font-size: 14pt; font-weight: bold; color: #111827; padding: 4px 0;"><?= number_format($averageOrder, 2) ?></div>
                <div style="color: #9ca3af; font-size: 8pt;">per order</div>
            </div>
        </td>
        <td width="25%" valign="top">
            <div style="border: 0.5pt solid #e5e7eb; border-radius: 4px; padding: 10px; background-color: #fafafa;">
                <div style="color: #6b7280; font-size: 8pt; text-transform: uppercase;">Repeat Rate</div>
                <div style="font-size: 14pt; font-weight: bold; color: #111827; padding: 4px 0;"><?= $customerStats['repeat_rate'] ?>%</div>
                <div style="color: #9ca3af; font-size: 8pt;"><?= (int) $customerStats['returning'] ?> buyers</div>
            </div>
        </td>
    </tr>
</table>

<!-- TOP PRODUCTS -->
<div style="font-size: 12pt; color: #111827; padding: 6px 0; border-bottom: 0.5pt solid #e5e7eb; margin-top: 20px; margin-bottom: 10px;">Top Products</div>
<?php if (empty($topProducts)): ?>
    <p style="color: #6b7280;">No products sold in this window.</p>
<?php else: ?>
    <table width="100%" cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr style="background-color: #f3f4f6;">
            <th align="left" width="5%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">#</th>
            <th align="left" width="55%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">Product</th>
            <th align="left" width="15%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">SKU</th>
            <th align="right" width="10%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">Units</th>
            <th align="right" width="15%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">Revenue</th>
        </tr>
        <?php foreach ($topProducts as $i => $p): ?>
            <tr>
                <td style="font-size: 10pt; color: #9ca3af; border-bottom: 0.5pt solid #f3f4f6;"><?= $i + 1 ?></td>
                <td style="font-size: 10pt; border-bottom: 0.5pt solid #f3f4f6;"><?= e($p['name']) ?></td>
                <td style="font-size: 10pt; color: #6b7280; border-bottom: 0.5pt solid #f3f4f6;"><?= e($p['sku'] ?? '—') ?></td>
                <td align="right" style="font-size: 10pt; border-bottom: 0.5pt solid #f3f4f6;"><?= (int) $p['units'] ?></td>
                <td align="right" style="font-size: 10pt; font-weight: bold; border-bottom: 0.5pt solid #f3f4f6;"><?= number_format($p['revenue'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!-- SALES BY CATEGORY -->
<div style="font-size: 12pt; color: #111827; padding: 6px 0; border-bottom: 0.5pt solid #e5e7eb; margin-top: 20px; margin-bottom: 10px;">Sales by Category</div>
<?php if (empty($salesByCategory) || array_sum(array_column($salesByCategory, 'revenue')) <= 0): ?>
    <p style="color: #6b7280;">No category sales in this window.</p>
<?php else: ?>
    <?php $totalCatSales = array_sum(array_column($salesByCategory, 'revenue')); ?>
    <?php foreach ($salesByCategory as $c): ?>
        <?php $percent = ($c['revenue'] / $totalCatSales) * 100; ?>
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 6px;">
            <tr>
                <td width="20%" style="font-size: 10pt; font-weight: bold;"><?= e($c['name']) ?></td>
                <td width="50%" valign="middle" style="padding: 0 8px;">
                    <div style="background-color: #e5e7eb; height: 8px; border-radius: 4px;">
                        <div style="background-color: #d4af37; height: 8px; width: <?= round($percent, 1) ?>%; border-radius: 4px;"></div>
                    </div>
                </td>
                <td width="30%" align="right" style="font-size: 10pt; font-weight: bold;">
                    <?= number_format($c['revenue'], 2) ?>
                    <span style="color: #9ca3af; font-weight: normal; font-size: 8pt;">(<?= round($percent) ?>%)</span>
                </td>
            </tr>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

<!-- SLOW-MOVING STOCK -->
<div style="font-size: 12pt; color: #111827; padding: 6px 0; border-bottom: 0.5pt solid #e5e7eb; margin-top: 20px; margin-bottom: 10px;">Slow-Moving Stock</div>
<?php if (empty($slowMoving)): ?>
    <p style="color: #6b7280;">All stocked products are selling well in this window.</p>
<?php else: ?>
    <table width="100%" cellpadding="6" cellspacing="0" border="0" style="border-collapse: collapse;">
        <tr style="background-color: #f3f4f6;">
            <th align="left" width="55%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">Product</th>
            <th align="right" width="15%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">In Stock</th>
            <th align="right" width="15%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">Sold (<?= $days ?>d)</th>
            <th align="left" width="15%" style="font-size: 8pt; color: #6b7280; border-bottom: 0.5pt solid #d1d5db;">Last Sale</th>
        </tr>
        <?php foreach ($slowMoving as $p): ?>
            <tr>
                <td style="font-size: 10pt; border-bottom: 0.5pt solid #f3f4f6;">
                    <strong><?= e($p['name']) ?></strong>
                    <?php if (!empty($p['sku'])): ?>
                        <br><span style="color: #9ca3af; font-size: 8pt;"><?= e($p['sku']) ?></span>
                    <?php endif; ?>
                </td>
                <td align="right" style="font-size: 10pt; border-bottom: 0.5pt solid #f3f4f6;"><?= (int) $p['stock'] ?></td>
                <td align="right" style="font-size: 10pt; border-bottom: 0.5pt solid #f3f4f6;"><?= (int) $p['sold'] ?></td>
                <td style="font-size: 10pt; border-bottom: 0.5pt solid #f3f4f6;">
                    <?php if ($p['days_since_last_sale'] !== null): ?>
                        <?= (int) $p['days_since_last_sale'] ?> days ago
                    <?php else: ?>
                        <span style="color: #9ca3af;">Never in window</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<!-- CUSTOMERS & ORDERS -->
<div style="font-size: 12pt; color: #111827; padding: 6px 0; border-bottom: 0.5pt solid #e5e7eb; margin-top: 20px; margin-bottom: 10px;">Customers &amp; Orders</div>
<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="48%" valign="top">
            <div style="font-size: 11pt; font-weight: bold; padding-bottom: 6px;">Customers</div>
            <table width="100%" cellpadding="6" cellspacing="0" border="0">
                <tr>
                    <td style="border-bottom: 0.5pt solid #f3f4f6; color: #6b7280; font-size: 10pt;">Total customers</td>
                    <td align="right" style="border-bottom: 0.5pt solid #f3f4f6; font-weight: bold; font-size: 10pt;"><?= (int) $customerStats['total'] ?></td>
                </tr>
                <tr>
                    <td style="border-bottom: 0.5pt solid #f3f4f6; color: #6b7280; font-size: 10pt;">New in window</td>
                    <td align="right" style="border-bottom: 0.5pt solid #f3f4f6; font-weight: bold; font-size: 10pt; color: #059669;">+<?= (int) $customerStats['new'] ?></td>
                </tr>
                <tr>
                    <td style="border-bottom: 0.5pt solid #f3f4f6; color: #6b7280; font-size: 10pt;">Repeat buyers</td>
                    <td align="right" style="border-bottom: 0.5pt solid #f3f4f6; font-weight: bold; font-size: 10pt;"><?= (int) $customerStats['returning'] ?></td>
                </tr>
                <tr>
                    <td style="color: #6b7280; font-size: 10pt;">Repeat rate</td>
                    <td align="right" style="font-weight: bold; font-size: 10pt;"><?= $customerStats['repeat_rate'] ?>%</td>
                </tr>
            </table>
        </td>
        <td width="4%"></td>
        <td width="48%" valign="top">
            <div style="font-size: 11pt; font-weight: bold; padding-bottom: 6px;">Orders by Status</div>
            <?php if (empty($statusBreakdown)): ?>
                <p style="color: #6b7280;">No orders yet.</p>
            <?php else: ?>
                <table width="100%" cellpadding="6" cellspacing="0" border="0">
                    <?php foreach ($statusBreakdown as $status => $n): ?>
                        <tr>
                            <td style="border-bottom: 0.5pt solid #f3f4f6; color: #6b7280; font-size: 10pt;"><?= e($statusLabels[$status] ?? ucfirst(str_replace('_', ' ', (string) $status))) ?></td>
                            <td align="right" style="border-bottom: 0.5pt solid #f3f4f6; font-weight: bold; font-size: 10pt;"><?= (int) $n ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- FOOTER -->
<div style="margin-top: 30px; padding-top: 8px; border-top: 0.5pt solid #e5e7eb; color: #9ca3af; font-size: 8pt; text-align: center;">
    <?= e($storeName) ?> &middot; Reports &middot; Generated by Corporate Aid Solutions
</div>

</body>
</html>
