<?php
/**
 * Tax classes and rates.
 *
 * Context (from AdminTaxController::index):
 *   $classes      array<int,array<string,mixed>>
 *   $ratesByClass array<int,array<int,array<string,mixed>>>  keyed by class_id
 *   $errors       array<string,string>
 *   $old          array<string,mixed>
 *
 * Classes are top-level. Each class renders as a card with
 * its rates inline. Class create/edit is a modal. Rate
 * create/edit is a separate page. Delete uses a
 * parameterized modal (class or rate).
 */
$classes      = $classes      ?? [];
$ratesByClass = $ratesByClass ?? [];
$errors       = $errors       ?? [];
$old          = $old          ?? [];

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$fmtRate = function ($percent): string {
    $p = (float) $percent;
    // Trim trailing zeros: 19.000 -> 19, 19.500 -> 19.5.
    $s = rtrim(rtrim(number_format($p, 3, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
};

$totalRates = 0;
foreach ($ratesByClass as $list) {
    $totalRates += count($list);
}
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Tax</h1>
        <p class="muted mb-0">
            <?= count($classes) ?> class<?= count($classes) === 1 ? '' : 'es' ?>
            &middot;
            <?= $totalRates ?> rate<?= $totalRates === 1 ? '' : 's' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="ra-btn ra-btn-primary"
                data-tax-class-open="create">
            <i class="bi bi-plus-lg me-1"></i> New tax class
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<?php if (empty($classes)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-percent" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No tax classes yet</h2>
            <p class="muted small mb-3">
                Create a class to define how tax is charged. Most stores start with a single Standard class.
            </p>
            <button type="button" class="ra-btn ra-btn-primary"
                    data-tax-class-open="create">
                <i class="bi bi-plus-lg me-1"></i> New tax class
            </button>
        </div>
    </div>
<?php else: ?>

    <?php foreach ($classes as $class): ?>
        <?php
        $classId   = (int) ($class['id'] ?? 0);
        $className = (string) ($class['name'] ?? '');
        $isDefault = ((int) ($class['is_default'] ?? 0)) === 1;
        $rates     = $ratesByClass[$classId] ?? [];
        ?>
        <div class="ra-card mb-4">
            <div class="ra-card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h3 class="h6 mb-0"><?= e($className) ?></h3>
                    <?php if ($isDefault): ?>
                        <span class="ra-badge ra-badge-success">Default</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <a href="/commerce/ca-admin/tax/rates/create?class_id=<?= $classId ?>"
                       class="ra-btn ra-btn-outline ra-btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> New rate
                    </a>
                    <button type="button" class="ra-icon-btn"
                            title="Edit class"
                            data-tax-class-open="edit"
                            data-tax-class-id="<?= $classId ?>"
                            data-tax-class-name="<?= e($className) ?>"
                            data-tax-class-default="<?= $isDefault ? '1' : '0' ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="ra-icon-btn"
                            title="Delete class"
                            data-tax-delete
                            data-tax-type="class"
                            data-tax-id="<?= $classId ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <div class="ra-card-body">
                <?php if (empty($rates)): ?>
                    <p class="muted small mb-0">
                        No rates defined yet. Add a rate to charge tax for this class.
                    </p>
                <?php else: ?>
                    <div class="ra-table-responsive-wrap" data-responsive="cards">
                        <table class="ra-table">
                            <thead>
                                <tr>
                                    <th style="width:100px;">Country</th>
                                    <th class="ra-hide-sm" style="width:140px;">State / Region</th>
                                    <th style="width:110px;">Rate</th>
                                    <th class="ra-hide-sm" style="width:130px;">Inclusive</th>
                                    <th class="ra-hide-sm" style="width:150px;">Shipping taxable</th>
                                    <th style="width:120px;text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rates as $rate): ?>
                                    <?php
                                    $rateId   = (int) ($rate['id'] ?? 0);
                                    $country  = (string) ($rate['country_code'] ?? '');
                                    $state    = (string) ($rate['state_code'] ?? '');
                                    $percent  = $fmtRate($rate['rate_percent'] ?? 0);
                                    $incl     = ((int) ($rate['is_inclusive'] ?? 0)) === 1;
                                    $shipTax  = ((int) ($rate['is_shipping_taxable'] ?? 0)) === 1;
                                    ?>
                                    <tr>
                                        <td data-label="Country" data-col="half"><code><?= e($country) ?></code></td>
                                        <td data-label="State / Region" data-col="half" class="ra-hide-sm">
                                            <?= $state !== '' ? '<code>' . e($state) . '</code>' : '<span class="muted">—</span>' ?>
                                        </td>
                                        <td data-label="Rate" data-col="half"><?= e($percent) ?>%</td>
                                        <td data-label="Inclusive" data-col="half" class="ra-hide-sm">
                                            <?php if ($incl): ?>
                                                <span class="ra-badge ra-badge-success">Yes</span>
                                            <?php else: ?>
                                                <span class="ra-badge ra-badge-muted">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Shipping taxable" data-col="half" class="ra-hide-sm">
                                            <?php if ($shipTax): ?>
                                                <span class="ra-badge ra-badge-success">Yes</span>
                                            <?php else: ?>
                                                <span class="ra-badge ra-badge-muted">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Actions" data-col="full" class="text-end">
                                            <a href="/commerce/ca-admin/tax/rates/<?= $rateId ?>/edit"
                                               class="ra-icon-btn" title="Edit rate">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="ra-icon-btn"
                                                    title="Delete rate"
                                                    data-tax-delete
                                                    data-tax-type="rate"
                                                    data-tax-id="<?= $rateId ?>">
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

<?= view_partial('admin.tax._class-modal') ?>
<?= view_partial('admin.tax._delete-modal') ?>
