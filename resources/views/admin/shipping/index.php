<?php
/**
 * Shipping methods and zones.
 *
 * Context (from AdminShippingController::index):
 *   $methods       array<int,array<string,mixed>>
 *   $zonesByMethod array<int,array<int,array<string,mixed>>>
 *                  keyed by method_id
 *   $errors        array<string,string>
 *   $old           array<string,mixed>
 *
 * Methods are top-level. Each renders as a card with its
 * zones inline. Method create/edit is a separate page. Zone
 * create/edit is a separate page. Delete uses a parameterized
 * modal (method or zone).
 */
$methods       = $methods       ?? [];
$zonesByMethod = $zonesByMethod ?? [];
$errors        = $errors        ?? [];
$old           = $old           ?? [];

$typeLabel = function (string $type): string {
    return match ($type) {
        'flat'         => 'Flat rate',
        'free_over'    => 'Free over threshold',
        'weight_based' => 'Weight based',
        'price_based'  => 'Price based',
        'local_pickup' => 'Local pickup',
        default        => $type,
    };
};

$totalZones = 0;
foreach ($zonesByMethod as $list) {
    $totalZones += count($list);
}

$fmtRate = function ($v): string {
    if ($v === null || $v === '') return '—';
    return number_format((float) $v, 2);
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Shipping</h1>
        <p class="muted mb-0">
            <?= count($methods) ?> method<?= count($methods) === 1 ? '' : 's' ?>
            &middot;
            <?= $totalZones ?> zone<?= $totalZones === 1 ? '' : 's' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/shipping/methods/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New shipping method
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<?php if (empty($methods)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-truck" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No shipping methods yet</h2>
            <p class="muted small mb-3">
                Create a method to define how customers receive their orders, and which regions each method serves.
            </p>
            <a href="/commerce/ca-admin/shipping/methods/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New shipping method
            </a>
        </div>
    </div>
<?php else: ?>

    <?php foreach ($methods as $method): ?>
        <?php
        $mid       = (int) ($method['id'] ?? 0);
        $code      = (string) ($method['code'] ?? '');
        $label     = (string) ($method['label'] ?? '');
        $type      = (string) ($method['type'] ?? '');
        $isActive  = ((int) ($method['is_active'] ?? 0)) === 1;
        $zones     = $zonesByMethod[$mid] ?? [];
        ?>
        <div class="ra-card mb-4">
            <div class="ra-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="h6 mb-0"><?= e($label) ?></h3>
                    <code class="muted small"><?= e($code) ?></code>
                    <span class="ra-badge ra-badge-muted"><?= e($typeLabel($type)) ?></span>
                    <?php if (!$isActive): ?>
                        <span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Disabled</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="/commerce/ca-admin/shipping/zones/create?method_id=<?= $mid ?>"
                       class="ra-btn ra-btn-outline ra-btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> New zone
                    </a>
                    <a href="/commerce/ca-admin/shipping/methods/<?= $mid ?>/edit"
                       class="ra-icon-btn" title="Edit method">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <button type="button" class="ra-icon-btn"
                            title="Delete method"
                            data-shipping-delete
                            data-shipping-type="method"
                            data-shipping-id="<?= $mid ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <div class="ra-card-body">
                <?php if (empty($zones)): ?>
                    <p class="muted small mb-0">
                        No zones defined yet. Add a zone to make this method available in a country.
                    </p>
                <?php else: ?>
                    <div class="ra-table-responsive-wrap">
                        <table class="ra-table">
                            <thead>
                                <tr>
                                    <th style="width:80px;">Order</th>
                                    <th style="width:100px;">Country</th>
                                    <th class="ra-hide-sm" style="width:140px;">Region</th>
                                    <th style="width:130px;">Rate override</th>
                                    <th class="ra-hide-sm" style="width:110px;">Active</th>
                                    <th style="width:120px;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zones as $zone): ?>
                                    <?php
                                    $zid       = (int) ($zone['id'] ?? 0);
                                    $country   = (string) ($zone['country_code'] ?? '');
                                    $region    = (string) ($zone['region'] ?? '');
                                    $override  = $zone['rate_override'] ?? null;
                                    $sortOrder = (int) ($zone['sort_order'] ?? 0);
                                    $zActive   = ((int) ($zone['is_active'] ?? 0)) === 1;
                                    ?>
                                    <tr>
                                        <td><span class="muted small"><?= $sortOrder ?></span></td>
                                        <td><code><?= e($country) ?></code></td>
                                        <td class="ra-hide-sm">
                                            <?= $region !== '' ? '<code>' . e($region) . '</code>' : '<span class="muted">Country-wide</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($override !== null && $override !== ''): ?>
                                                <?= e($fmtRate($override)) ?>
                                            <?php else: ?>
                                                <span class="muted">Computed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="ra-hide-sm">
                                            <?php if ($zActive): ?>
                                                <span class="ra-badge ra-badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="ra-badge ra-badge-muted">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="/commerce/ca-admin/shipping/zones/<?= $zid ?>/edit"
                                               class="ra-icon-btn" title="Edit zone">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="ra-icon-btn"
                                                    title="Delete zone"
                                                    data-shipping-delete
                                                    data-shipping-type="zone"
                                                    data-shipping-id="<?= $zid ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?= view_partial('admin.shipping._delete-modal') ?>
