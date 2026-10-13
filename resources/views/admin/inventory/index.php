<?php
/**
 * Inventory list - one row per product with aggregated stock.
 * Simple products show one sellable unit; variable products
 * show the count of variants plus aggregate quantity.
 *
 * @var array $result      ['items' => [...], 'total' => int, 'page' => int, 'per_page' => int, 'pages' => int]
 * @var array $filters
 * @var array $categories
 * @var array $brands
 * @var array $summary     ['low_stock' => int, 'out_of_stock' => int, 'units_on_hand' => int]
 */
$result     = $result     ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 1];
$filters    = $filters    ?? [];
$categories = $categories ?? [];
$brands     = $brands     ?? [];
$summary    = $summary    ?? ['low_stock' => 0, 'out_of_stock' => 0, 'units_on_hand' => 0];

$items = $result['items'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];

$hasFilters = !empty($filters);

$pageUrl = function (int $p) use ($filters): string {
    $q = array_merge($filters, ['page' => $p]);
    return '?' . http_build_query(array_filter($q, fn ($v) => $v !== '' && $v !== 0));
};

$exportUrl = '/commerce/ca-admin/inventory/export.csv' . ($hasFilters ? '?' . http_build_query($filters) : '');
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Inventory</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> product<?= $total === 1 ? '' : 's' ?>
            <?php if ($hasFilters): ?>
                &middot; filtered
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e($exportUrl) ?>" class="ra-btn ra-btn-outline">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <a href="/commerce/ca-admin/inventory/movements" class="ra-btn ra-btn-outline">
            <i class="bi bi-clock-history me-1"></i> Movements
        </a>
    </div>
</div>

<!-- Summary tiles -->
<div class="ra-inv-summary mb-4">
    <a href="?status=low_stock" class="ra-inv-tile">
        <div class="ra-inv-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;">
            <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="ra-inv-body">
            <div class="ra-inv-label">Low stock</div>
            <div class="ra-inv-value" style="color:#f59e0b;"><?= (int) $summary['low_stock'] ?></div>
            <div class="ra-inv-hint">At or below threshold</div>
        </div>
    </a>
    <a href="?status=out_of_stock" class="ra-inv-tile">
        <div class="ra-inv-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444;">
            <i class="bi bi-x-octagon"></i>
        </div>
        <div class="ra-inv-body">
            <div class="ra-inv-label">Out of stock</div>
            <div class="ra-inv-value" style="color:#ef4444;"><?= (int) $summary['out_of_stock'] ?></div>
            <div class="ra-inv-hint">Unavailable units</div>
        </div>
    </a>
    <div class="ra-inv-tile is-static">
        <div class="ra-inv-icon" style="background: rgba(148, 163, 184, 0.15); color: var(--ra-muted,#94a3b8);">
            <i class="bi bi-boxes"></i>
        </div>
        <div class="ra-inv-body">
            <div class="ra-inv-label">Units on hand</div>
            <div class="ra-inv-value"><?= number_format((int) $summary['units_on_hand']) ?></div>
            <div class="ra-inv-hint">Total across all products</div>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="/commerce/ca-admin/inventory" class="ra-card mb-4" id="ra-inventory-filters">
    <div class="ra-card-body">
        <button type="button" class="ra-filter-toggle" data-ra-filter-toggle>
            <i class="bi bi-funnel"></i> Filters
        </button>
        <div class="ra-filter-bar">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small muted mb-1">Search</label>
                <input type="text" name="q" id="ra-inventory-search" class="form-control form-control-sm"
                       value="<?= e((string) ($filters['q'] ?? '')) ?>"
                       placeholder="Product name or SKU"
                       autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label small muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All</option>
                    <option value="in_stock"     <?= ($filters['status'] ?? '') === 'in_stock'     ? 'selected' : '' ?>>In stock</option>
                    <option value="low_stock"    <?= ($filters['status'] ?? '') === 'low_stock'    ? 'selected' : '' ?>>Low stock</option>
                    <option value="out_of_stock" <?= ($filters['status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small muted mb-1">Category</label>
                <select name="category_id" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small muted mb-1">Brand</label>
                <select name="brand_id" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (int) ($filters['brand_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if ($hasFilters): ?>
            <div class="mt-3 text-end">
                <a href="/commerce/ca-admin/inventory" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg me-1"></i> Clear filters
                </a>
            </div>
        <?php endif; ?>
        </div>
    </div>
</form>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-boxes" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?= $hasFilters ? 'No units match your filters' : 'No inventory yet' ?>
            </h2>
            <p class="muted small mb-3">
                <?= $hasFilters ? 'Try clearing filters or adjusting your search.' : 'Products with managed stock appear here once they are saved.' ?>
            </p>
            <?php if ($hasFilters): ?>
                <a href="/commerce/ca-admin/inventory" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
        <table class="ra-table ra-inv-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="ra-hide-sm" style="width:130px;">Type</th>
                    <th class="ra-hide-sm" style="width:130px;">SKU</th>
                    <th style="width:90px;text-align:right;">Units</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:120px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                    <?php
                    $unitCount  = (int) $row['unit_count'];
                    $isVariable = $row['product_type'] === 'variable';
                    $statusKey  = $row['product_status_display'] ?? 'in_stock';

                    $statusBadge = match ($statusKey) {
                        'out_of_stock' => '<span class="ra-badge ra-badge-danger">Out of stock</span>',
                        'low_stock'    => '<span class="ra-badge ra-badge-warning">Low stock</span>',
                        'no_stock'     => '<span class="ra-badge ra-badge-muted">No stock row</span>',
                        default        => '<span class="ra-badge ra-badge-success">In stock</span>',
                    };

                    $typeLabel = $isVariable
                        ? $unitCount . ' variant' . ($unitCount === 1 ? '' : 's')
                        : 'Simple';

                    $detailUrl = '/commerce/ca-admin/inventory/item/' . (int) $row['product_id'];
                    $units     = (int) $row['total_quantity'];
                    ?>
                    <tr>
                        <td data-label="Product" data-col="full">
                            <a href="<?= e($detailUrl) ?>" class="text-decoration-none">
                                <?= e($row['product_name']) ?>
                            </a>
                        </td>
                        <td class="ra-hide-sm" data-label="Type" data-col="half">
                            <span class="muted small"><?= e($typeLabel) ?></span>
                        </td>
                        <td class="ra-hide-sm" data-label="SKU" data-col="half">
                            <code class="muted small"><?= e($row['product_sku'] ?: '—') ?></code>
                        </td>
                        <td class="text-end" data-label="Units" data-col="half">
                            <strong><?= $units ?></strong>
                        </td>
                        <td data-label="Status" data-col="half"><?= $statusBadge ?></td>
                        <td class="text-end ra-actions-cell" data-label="Actions" data-col="full">
                            <?php if (!$isVariable): ?>
                                <button type="button" class="ra-icon-btn ra-adjust-btn"
                                        data-product-id="<?= (int) $row['product_id'] ?>"
                                        data-variant-id="0"
                                        data-product-name="<?= e($row['product_name']) ?>"
                                        data-variant-label=""
                                        title="Adjust stock">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                            <?php endif; ?>
                            <a href="<?= e($detailUrl) ?>"
                               class="ra-icon-btn" title="View stock detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="/commerce/ca-admin/inventory/movements?q=<?= urlencode($row['product_name']) ?>"
                               class="ra-icon-btn" title="View movements">
                                <i class="bi bi-clock-history"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-3" aria-label="Pagination">
            <ul class="pagination pagination-sm justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);

                if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a></li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif;
                endif;

                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor;

                if ($end < $pages): ?>
                    <?php if ($end < $pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl($pages)) ?>"><?= $pages ?></a></li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?= view_partial('admin/inventory/_adjust-modal') ?>

<script>
(function () {
    var form = document.getElementById('ra-inventory-filters');
    if (!form) return;

    var searchInput = document.getElementById('ra-inventory-search');
    var debounceTimer = null;

    function submit() { form.submit(); }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submit, 400);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                submit();
            }
        });
    }

    form.querySelectorAll('[data-ra-filter]').forEach(function (el) {
        el.addEventListener('change', submit);
    });
})();

// Wire adjust-stock buttons
(function () {
    document.querySelectorAll('.ra-adjust-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var productId   = parseInt(btn.getAttribute('data-product-id'), 10);
            var variantId   = parseInt(btn.getAttribute('data-variant-id'), 10);
            var productName = btn.getAttribute('data-product-name') || 'Product';
            var variantLabel = btn.getAttribute('data-variant-label') || null;

            if (typeof window.RA_AdjustStock === 'undefined') {
                alert('Adjust modal not loaded. Please reload the page.');
                return;
            }

            window.RA_AdjustStock.open(productId, variantId, productName, variantLabel);
        });
    });
})();
</script>

<style>
.ra-inv-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}
.ra-inv-tile {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    background: var(--ra-card-bg, #ffffff);
    border: 1px solid var(--ra-border, #e5e9f2);
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: background-color .12s, border-color .12s, transform .12s;
}
.ra-inv-tile:not(.is-static):hover {
    background: var(--ra-hover, rgba(148, 163, 184, 0.06));
    border-color: var(--ra-accent, #2563eb);
    text-decoration: none;
    color: inherit;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px -12px rgba(15, 23, 42, 0.2);
}
.ra-inv-tile.is-static {
    cursor: default;
}
.ra-inv-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 14px;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.ra-inv-body {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    min-width: 0;
}
.ra-inv-label {
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--ra-muted, #94a3b8);
    font-weight: 600;
}
.ra-inv-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--ra-fg, #0f172a);
    line-height: 1;
    margin: .15rem 0;
}
.ra-inv-hint {
    font-size: .78rem;
    color: var(--ra-muted, #94a3b8);
}
.ra-badge-danger {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}
[data-theme="dark"] .ra-badge-danger {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}
</style>
