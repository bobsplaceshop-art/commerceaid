<?php
/**
 * Recent movement history for a single product.
 *
 * Rendered below the unit table on the per-product detail page.
 *
 * Vars:
 *   @var array<int, array<string,mixed>> $movements   enriched rows
 *   @var int                             $moveTotal   total count
 *   @var int                             $productId
 *   @var string                          $productName
 */
$movements  = $movements  ?? [];
$moveTotal  = (int) ($moveTotal ?? 0);
$productId  = (int) ($productId ?? 0);
$productName= (string) ($productName ?? '');

$typeBadge = function (string $type): string {
    $cls = match ($type) {
        'sale', 'transfer_out', 'damage' => 'ra-badge-danger',
        'return', 'refund', 'transfer_in' => 'ra-badge-success',
        'adjustment', 'stocktake'         => 'ra-badge-muted',
        'opening', 'cancel'               => 'ra-badge-warning',
        default                           => 'ra-badge-muted',
    };
    return '<span class="ra-badge ' . $cls . '">'
         . e(ucfirst(str_replace('_', ' ', $type)))
         . '</span>';
};

$movementsUrl = '/commerce/ca-admin/inventory/movements?q='
              . urlencode($productName);
?>

<div class="d-flex align-items-center justify-content-between mt-4 mb-2 flex-wrap gap-2">
    <div>
        <h2 class="h6 mb-0">Recent movements</h2>
        <p class="muted small mb-0">
            <?= (int) $moveTotal ?>
            movement<?= $moveTotal === 1 ? '' : 's' ?>
            for this product
        </p>
    </div>
    <a href="<?= e($movementsUrl) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
        <i class="bi bi-clock-history me-1"></i> View full history
    </a>
</div>

<?php if (empty($movements)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-4">
            <i class="bi bi-clock-history" style="font-size:2rem;color:var(--ra-muted,#94a3b8);"></i>
            <p class="muted small mb-0 mt-2">
                No movements recorded for this product yet.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th style="width:150px;">When</th>
                        <th class="ra-hide-sm" style="width:200px;">Variant</th>
                        <th style="width:140px;">Type</th>
                        <th style="width:90px;text-align:right;">Change</th>
                        <th class="ra-hide-sm" style="width:90px;text-align:right;">After</th>
                        <th>Reason</th>
                        <th class="ra-hide-sm" style="width:140px;">Actor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $m): ?>
                        <?php
                        $delta      = (int) $m['quantity_delta'];
                        $deltaColor = $delta > 0 ? '#22c55e' : ($delta < 0 ? '#ef4444' : 'inherit');
                        $deltaText  = ($delta > 0 ? '+' : '') . $delta;
                        $createdAt  = (string) ($m['created_at'] ?? '');
                        $ts         = $createdAt !== '' ? strtotime($createdAt) : time();
                        ?>
                        <tr>
                            <td>
                                <span class="muted small">
                                    <?= e(date('M j, Y', $ts)) ?>
                                </span>
                                <div class="muted" style="font-size:.72rem;">
                                    <?= e(date('H:i', $ts)) ?>
                                </div>
                            </td>
                            <td class="ra-hide-sm">
                                <?php if (!empty($m['variant_summary'])): ?>
                                    <span class="muted small"><?= e($m['variant_summary']) ?></span>
                                <?php else: ?>
                                    <span class="muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $typeBadge((string) $m['movement_type']) ?></td>
                            <td class="text-end">
                                <strong style="color:<?= $deltaColor ?>"><?= e($deltaText) ?></strong>
                            </td>
                            <td class="text-end muted ra-hide-sm"><?= (int) $m['quantity_after'] ?></td>
                            <td>
                                <span class="small"><?= e($m['reason'] ?? '—') ?></span>
                                <?php if (!empty($m['reference_type']) && !empty($m['reference_id'])): ?>
                                    <div class="muted" style="font-size:.72rem;">
                                        <?= e($m['reference_type']) ?> #<?= (int) $m['reference_id'] ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm">
                                <span class="small"><?= e($m['actor_name'] ?? 'System') ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
