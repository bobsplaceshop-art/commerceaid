<?php
/**
 * Inventory movement history.
 *
 * @var array $result    ['items' => [...], 'total' => int, 'page' => int, 'per_page' => int, 'pages' => int]
 * @var array $filters
 */
$result  = $result  ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 1];
$filters = $filters ?? [];

$items = $result['items'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];

$hasFilters = !empty($filters);

$pageUrl = function (int $p) use ($filters): string {
    $q = array_merge($filters, ['page' => $p]);
    return '?' . http_build_query(array_filter($q, fn ($v) => $v !== ''));
};

$exportUrl = '/commerce/ca-admin/inventory/movements/export.csv' . ($hasFilters ? '?' . http_build_query($filters) : '');

$typeLabel = function (string $type): string {
    return match ($type) {
        'opening'      => 'Opening',
        'adjustment'   => 'Adjustment',
        'sale'         => 'Sale',
        'return'       => 'Return',
        'refund'       => 'Refund',
        'cancel'       => 'Cancel',
        'transfer_in'  => 'Transfer In',
        'transfer_out' => 'Transfer Out',
        'damage'       => 'Damage',
        'stocktake'    => 'Stocktake',
        default        => ucfirst($type),
    };
};

$typeBadge = function (string $type): string {
    $cls = match ($type) {
        'sale', 'transfer_out', 'damage' => 'ra-badge-danger',
        'return', 'refund', 'transfer_in' => 'ra-badge-success',
        'adjustment', 'stocktake'          => 'ra-badge-muted',
        'opening', 'cancel'                => 'ra-badge-warning',
        default                            => 'ra-badge-muted',
    };
    return '<span class="ra-badge ' . $cls . '">' . e(ucfirst(str_replace('_', ' ', $type))) . '</span>';
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Inventory Movements</h1>
        <p class="muted mb-0">
            <?= number_format((int) $total) ?> movement<?= $total === 1 ? '' : 's' ?>
            <?php if ($hasFilters): ?>
                &middot; filtered
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e($exportUrl) ?>" class="ra-btn ra-btn-outline">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <a href="/commerce/ca-admin/inventory" class="ra-btn ra-btn-outline">
            <i class="bi bi-boxes me-1"></i> Inventory
        </a>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="/commerce/ca-admin/inventory/movements" class="ra-card mb-4" id="ra-movement-filters">
    <div class="ra-card-body">
        <button type="button" class="ra-filter-toggle" data-ra-filter-toggle>
            <i class="bi bi-funnel"></i> Filters
        </button>
        <div class="ra-filter-bar">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small muted mb-1">Search</label>
                <input type="text" name="q" id="ra-movement-search" class="form-control form-control-sm"
                       value="<?= e((string) ($filters['q'] ?? '')) ?>"
                       placeholder="Product name, SKU, or reason"
                       autocomplete="off">
            </div>
            <div class="col-md-3">
                <label class="form-label small muted mb-1">Movement type</label>
                <select name="type" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All types</option>
                    <?php foreach (['opening','adjustment','sale','return','refund','cancel','transfer_in','transfer_out','damage','stocktake'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>>
                            <?= e($typeLabel($t)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small muted mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" data-ra-filter
                       value="<?= e((string) ($filters['from'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small muted mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" data-ra-filter
                       value="<?= e((string) ($filters['to'] ?? '')) ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <?php if ($hasFilters): ?>
                    <a href="/commerce/ca-admin/inventory/movements" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>
</form>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-clock-history" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?= $hasFilters ? 'No movements match your filters' : 'No movements yet' ?>
            </h2>
            <p class="muted small mb-3">
                <?= $hasFilters ? 'Try widening the date range or clearing filters.' : 'Movements are logged when stock changes.' ?>
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
                    <th>Product</th>
                    <th style="width:150px;">Variant</th>
                    <th style="width:130px;">Type</th>
                    <th style="width:70px;text-align:right;">Change</th>
                    <th style="width:70px;text-align:right;">After</th>
                    <th>Reason</th>
                    <th style="width:130px;">Actor</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $m): ?>
                    <?php
                    $delta = (int) $m['quantity_delta'];
                    $deltaColor = $delta > 0 ? '#22c55e' : ($delta < 0 ? '#ef4444' : 'inherit');
                    $deltaText = ($delta > 0 ? '+' : '') . $delta;
                    ?>
                    <tr>
                        <td>
                            <span class="muted small">
                                <?= e(date('M j, Y', strtotime($m['created_at']))) ?>
                            </span>
                            <div class="muted" style="font-size:.72rem;">
                                <?= e(date('H:i', strtotime($m['created_at']))) ?>
                            </div>
                        </td>
                        <td>
                            <a href="/commerce/ca-admin/products/<?= (int) $m['product_id'] ?>/edit"
                               class="text-decoration-none">
                                <?= e($m['product_name']) ?>
                            </a>
                        </td>
                        <td>
                            <?php if (!empty($m['variant_summary'])): ?>
                                <span class="muted small"><?= e($m['variant_summary']) ?></span>
                            <?php else: ?>
                                <span class="muted small">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $typeBadge($m['movement_type']) ?></td>
                        <td class="text-end">
                            <strong style="color:<?= $deltaColor ?>"><?= e($deltaText) ?></strong>
                        </td>
                        <td class="text-end muted"><?= (int) $m['quantity_after'] ?></td>
                        <td>
                            <span class="small">
                                <?= e($m['reason'] ?? '—') ?>
                            </span>
                            <?php if (!empty($m['reference_type']) && !empty($m['reference_id'])): ?>
                                <div class="muted" style="font-size:.72rem;">
                                    <?= e($m['reference_type']) ?> #<?= (int) $m['reference_id'] ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="small">
                                <?= e($m['actor_name'] ?? 'System') ?>
                            </span>
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

                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>

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

<script>
(function () {
    var form = document.getElementById('ra-movement-filters');
    if (!form) return;

    var searchInput = document.getElementById('ra-movement-search');
    var debounceTimer = null;

    function submit() { form.submit(); }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(submit, 400);
        });
    }

    form.querySelectorAll('[data-ra-filter]').forEach(function (el) {
        el.addEventListener('change', submit);
    });
})();
</script>
