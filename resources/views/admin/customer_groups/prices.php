<?php
/**
 * Customer group price list.
 *
 * @var \CorporateAid\Commerce\Models\CustomerGroup $group
 * @var array $overrides        Price override rows with joined product data
 * @var array $productOptions   [{id, name}] for the add form
 * @var array $errors
 * @var array $old
 */
$group          = $group          ?? null;
$overrides      = $overrides      ?? [];
$productOptions = $productOptions ?? [];
$errors         = $errors         ?? [];
$old            = $old            ?? [];

$groupId = $group !== null ? (int) $group->id : 0;
$cancelUrl = '/commerce/ca-admin/customer-groups/' . $groupId . '/edit';

$err = function (string $key) use ($errors): string {
    return isset($errors[$key])
        ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
        : '';
};

$val = function (string $key, $default = '') use ($old) {
    return (string) ($old[$key] ?? $default);
};

$fmtPrice = function (?float $v): string {
    return $v === null ? '—' : number_format($v, 2);
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Price list</h1>
        <p class="muted mb-0">
            <?= e($group->name) ?>
            &middot; <?= count($overrides) ?> override<?= count($overrides) === 1 ? '' : 's' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/customer-groups/<?= $groupId ?>/edit" class="ra-btn ra-btn-outline">
            <i class="bi bi-arrow-left me-1"></i> Back to group
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        <?php foreach ($errors as $e): ?>
            <div><?= e((string) $e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add override form -->
<div class="ra-card mb-4">
    <div class="ra-card-header">
        <h2 class="h6 mb-0">Add price override</h2>
    </div>
    <div class="ra-card-body">
        <p class="muted small mb-3">
            Set a special price for this group. Optionally add tier pricing
            (e.g. 10+ at $9, 50+ at $7) by adding multiple overrides with
            different quantity ranges.
        </p>

        <form method="POST" action="/commerce/ca-admin/customer-groups/<?= $groupId ?>/prices" id="ra-price-form">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-5">
                    <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                    <select id="product_id" name="product_id" class="form-select" required>
                        <option value="">- Select a product -</option>
                        <?php foreach ($productOptions as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $val('product_id') === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('product_id') ?>
                </div>

                <div class="col-md-2">
                    <label for="min_quantity" class="form-label">Min qty</label>
                    <input type="number" id="min_quantity" name="min_quantity" class="form-control"
                           min="1" step="1" value="<?= e($val('min_quantity', '1')) ?>">
                    <div class="form-text">Tier floor</div>
                </div>

                <div class="col-md-2">
                    <label for="max_quantity" class="form-label">Max qty</label>
                    <input type="number" id="max_quantity" name="max_quantity" class="form-control"
                           min="1" step="1" value="<?= e($val('max_quantity')) ?>"
                           placeholder="any">
                    <div class="form-text">Leave blank for unbounded</div>
                </div>

                <div class="col-md-3">
                    <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                    <input type="number" id="price" name="price" class="form-control"
                           min="0" step="0.01" required value="<?= e($val('price')) ?>">
                    <?= $err('price') ?>
                </div>

                <div class="col-md-3">
                    <label for="compare_at_price" class="form-label">Compare-at</label>
                    <input type="number" id="compare_at_price" name="compare_at_price" class="form-control"
                           min="0" step="0.01" value="<?= e($val('compare_at_price')) ?>">
                    <div class="form-text">Optional strike-through</div>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="ra-btn ra-btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i> Add
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Existing overrides -->
<?php if (empty($overrides)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-tags" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No price overrides yet</h2>
            <p class="muted small mb-0">
                This group currently sees base catalog prices for every product.
                Add an override above to give them a different price.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="ra-hide-sm" style="width:150px;">Variant</th>
                        <th style="width:120px;text-align:right;">Base price</th>
                        <th style="width:120px;text-align:right;">Group price</th>
                        <th class="ra-hide-sm" style="width:110px;text-align:right;">Compare-at</th>
                        <th style="width:120px;">Tier</th>
                        <th style="width:80px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($overrides as $o): ?>
                        <?php
                        $basePrice = (float) ($o['base_price'] ?? 0);
                        $groupPrice = $o['price'] !== null ? (float) $o['price'] : null;
                        $discountPct = 0;
                        if ($basePrice > 0 && $groupPrice !== null && $groupPrice < $basePrice) {
                            $discountPct = (int) round((($basePrice - $groupPrice) / $basePrice) * 100);
                        }

                        $tierLabel = '—';
                        if ((int) $o['min_quantity'] > 1 || $o['max_quantity'] !== null) {
                            $min = (int) $o['min_quantity'];
                            $max = $o['max_quantity'] !== null ? (int) $o['max_quantity'] : null;
                            $tierLabel = $max === null ? $min . '+' : $min . ' - ' . $max;
                        }
                        ?>
                        <tr>
                            <td data-label="Product" data-col="full">
                                <a href="/commerce/ca-admin/products/<?= (int) $o['product_id'] ?>/edit"
                                   class="text-decoration-none">
                                    <?= e($o['product_name']) ?>
                                </a>
                                <?php if (!empty($o['product_sku'])): ?>
                                    <code class="muted small ms-2"><?= e($o['product_sku']) ?></code>
                                <?php endif; ?>
                            </td>
                            <td data-label="Variant" data-col="half" class="ra-hide-sm">
                                <?php if (!empty($o['variant_summary'])): ?>
                                    <span class="muted small"><?= e($o['variant_summary']) ?></span>
                                <?php else: ?>
                                    <span class="muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Base price" data-col="half" class="text-end muted"><?= $fmtPrice($basePrice) ?></td>
                            <td data-label="Group price" data-col="half" class="text-end">
                                <strong style="color:<?= $discountPct > 0 ? '#22c55e' : 'inherit' ?>">
                                    <?= $fmtPrice($groupPrice) ?>
                                </strong>
                                <?php if ($discountPct > 0): ?>
                                    <div class="muted" style="font-size:.72rem;">-<?= $discountPct ?>%</div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Compare-at" data-col="half" class="text-end muted ra-hide-sm">
                                <?= $fmtPrice($o['compare_at_price'] !== null ? (float) $o['compare_at_price'] : null) ?>
                            </td>
                            <td data-label="Tier" data-col="half">
                                <?php if ($tierLabel !== '—'): ?>
                                    <span class="ra-badge ra-badge-muted"><?= e($tierLabel) ?></span>
                                <?php else: ?>
                                    <span class="muted small">All qty</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions" data-col="full" class="text-end">
                                <form method="POST"
                                      action="/commerce/ca-admin/customer-groups/<?= $groupId ?>/prices/<?= (int) $o['id'] ?>/delete"
                                      class="d-inline"
                                      onsubmit="return confirm('Remove this price override?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn" title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
