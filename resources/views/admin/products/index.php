<?php
/**
 * Products list with filters, search, pagination.
 *
 * @var array $result      ['items' => Product[], 'total' => int, 'page' => int, 'per_page' => int, 'pages' => int]
 * @var array $filters     Applied filters
 * @var array $categories  For category filter dropdown
 * @var array $brands      For brand filter dropdown
 */
$result     = $result     ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$filters    = $filters    ?? [];
$categories = $categories ?? [];
$brands     = $brands     ?? [];

$items = $result['items'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];

$hasFilters = !empty($filters);

/** Build a query string for a given page, preserving filters */
$pageUrl = function (int $p) use ($filters): string {
    $q = array_merge($filters, ['page' => $p]);
    return '?' . http_build_query(array_filter($q, fn ($v) => $v !== '' && $v !== 0 && $v !== false));
};

/** Format price with store currency symbol */
$fmtPrice = function (float $v): string {
    return number_format($v, 2);
};

$statusBadge = function (string $status): string {
    return match ($status) {
        'published' => '<span class="ra-badge ra-badge-success">Published</span>',
        'draft'     => '<span class="ra-badge ra-badge-muted">Draft</span>',
        'archived'  => '<span class="ra-badge ra-badge-warning">Archived</span>',
        default     => '<span class="ra-badge">' . e($status) . '</span>',
    };
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Products</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> product<?= $total === 1 ? '' : 's' ?>
            <?php if ($hasFilters): ?>
                &middot; filtered
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/products/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New product
        </a>
    </div>
</div>

<form method="GET" action="/commerce/ca-admin/products" class="ra-card mb-4" id="ra-product-filters">
    <div class="ra-card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small mb-1">Search</label>
                <div class="position-relative">
                    <input type="text" name="q" id="ra-product-search" class="form-control form-control-sm"
                           value="<?= e((string) ($filters['q'] ?? '')) ?>"
                           placeholder="Name or SKU"
                           autocomplete="off">
                    <span id="ra-product-search-spinner"
                          style="display:none;position:absolute;right:.6rem;top:50%;transform:translateY(-50%);">
                        <span class="spinner-border spinner-border-sm text-muted"></span>
                    </span>
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All</option>
                    <option value="published" <?= ($filters['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft"     <?= ($filters['status'] ?? '') === 'draft'     ? 'selected' : '' ?>>Draft</option>
                    <option value="archived"  <?= ($filters['status'] ?? '') === 'archived'  ? 'selected' : '' ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Category</label>
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
                <label class="form-label small mb-1">Brand</label>
                <select name="brand_id" class="form-select form-select-sm" data-ra-filter>
                    <option value="">All brands</option>
                    <?php foreach ($brands as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (int) ($filters['brand_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1"
                           data-ra-filter
                           <?= !empty($filters['featured']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="featured">Featured only</label>
                </div>
            </div>
            <div class="col-md-9 d-flex align-items-end justify-content-end">
                <?php if ($hasFilters): ?>
                    <a href="/commerce/ca-admin/products" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg me-1"></i> Clear filters
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('ra-product-filters');
    if (!form) return;

    var searchInput = document.getElementById('ra-product-search');
    var spinner     = document.getElementById('ra-product-search-spinner');
    var debounceTimer = null;

    function submitForm() {
        form.submit();
    }

    function showSpinner() {
        if (spinner) spinner.style.display = 'inline-block';
    }

    // Search box: debounce 350ms
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                showSpinner();
                submitForm();
            }, 350);
        });

        // Enter submits immediately
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(debounceTimer);
                showSpinner();
                submitForm();
            }
        });
    }

    // Dropdowns and toggles: submit immediately on change
    form.querySelectorAll('[data-ra-filter]').forEach(function (el) {
        el.addEventListener('change', function () {
            showSpinner();
            submitForm();
        });
    });
})();
</script>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-box-seam" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?= $hasFilters ? 'No products match your filters' : 'No products yet' ?>
            </h2>
            <p class="muted small mb-3">
                <?= $hasFilters ? 'Try clearing filters or adjusting your search.' : 'Add your first product to get started.' ?>
            </p>
            <?php if ($hasFilters): ?>
                <a href="/commerce/ca-admin/products" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <a href="/commerce/ca-admin/products/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create your first product
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th style="width:120px;">SKU</th>
                    <th style="width:130px;text-align:right;">Price</th>
                    <th style="width:120px;">Status</th>
                    <th style="width:120px;">Stock</th>
                    <th style="width:180px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $product): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-box-seam" style="color:var(--ra-muted,#94a3b8);"></i>
                                <div>
                                    <div><?= e($product->name) ?></div>
                                    <?php if ($product->isFeatured): ?>
                                        <span class="muted" style="font-size:.72rem;">
                                            <i class="bi bi-star-fill" style="color:#f59e0b;"></i> Featured
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code class="muted small"><?= e($product->sku ?? '-') ?></code></td>
                        <td class="text-end">
                            <div><?= $fmtPrice($product->price) ?></div>
                            <?php if ($product->isOnSale()): ?>
                                <div class="muted" style="font-size:.75rem;text-decoration:line-through;">
                                    <?= $fmtPrice((float) $product->compareAtPrice) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= $statusBadge($product->status) ?></td>
                        <td>
                            <?php if (!$product->manageStock): ?>
                                <span class="muted small">Not tracked</span>
                            <?php else: ?>
                                <span class="muted small">Managed</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="/commerce/ca-admin/products/<?= (int) $product->id ?>/edit"
                               class="ra-icon-btn" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ($product->status === 'published'): ?>
                                <form method="POST" action="/commerce/ca-admin/products/<?= (int) $product->id ?>/unpublish" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn" title="Unpublish (revert to draft)">
                                        <i class="bi bi-eye-slash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="/commerce/ca-admin/products/<?= (int) $product->id ?>/publish" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn" title="Publish">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="ra-icon-btn"
                                    data-ra-delete-product
                                    data-product-id="<?= (int) $product->id ?>"
                                    data-product-name="<?= e($product->name) ?>"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

<?= view_partial('admin/products/_delete-modal') ?>
