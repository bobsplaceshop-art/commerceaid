<?php
/**
 * Variations manager.
 *
 * Paginated flat table with search, group filters, and bulk actions.
 *
 * @var \CorporateAid\Commerce\Models\Product $product
 * @var array $variants        current page of ProductVariant
 * @var array $variantStock    map variantId => quantity
 * @var array $variantAxes     attributes with values
 * @var array $incompleteAxes  attributes missing values
 * @var array $drift           drift detection
 * @var array $groups          first-axis values with counts
 * @var array $filters         ['q' => ..., 'group' => ...]
 * @var array $pagination      ['page','per_page','total','pages','from','to']
 * @var int   $totalVariations
 * @var array $errors
 */

$product          = $product          ?? null;
$variants         = $variants         ?? [];
$variantStock     = $variantStock     ?? [];
$variantAxes      = $variantAxes      ?? [];
$incompleteAxes   = $incompleteAxes   ?? [];
$drift            = $drift            ?? ['drift' => false, 'missing' => 0, 'extra' => 0, 'expected_count' => 0];
$groups           = $groups           ?? [];
$filters          = $filters          ?? ['q' => '', 'group' => ''];
$pagination       = $pagination       ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'pages' => 1, 'from' => 0, 'to' => 0];
$totalVariations  = $totalVariations  ?? 0;
$errors           = $errors           ?? [];

$productId = $product !== null ? (int) $product->id : 0;
$backUrl   = '/commerce/ca-admin/products/' . $productId . '/edit';
$baseUrl   = '/commerce/ca-admin/products/' . $productId . '/variants';

$hasFilters = ($filters['q'] ?? '') !== '' || ($filters['group'] ?? '') !== '';

// Build a URL preserving current filters + page
$pageUrl = function (int $p) use ($filters, $baseUrl): string {
    $q = array_merge($filters, ['page' => $p]);
    $q = array_filter($q, fn ($v) => $v !== '' && $v !== null);
    return $baseUrl . '?' . http_build_query($q);
};

// Build a URL for a group filter
$groupUrl = function (string $groupValue) use ($filters, $baseUrl): string {
    $q = $filters;
    $q['group'] = ($q['group'] ?? '') === $groupValue ? '' : $groupValue;
    $q = array_filter($q, fn ($v) => $v !== '' && $v !== null);
    return $baseUrl . ($q !== [] ? '?' . http_build_query($q) : '');
};

// Build a URL preserving everything except the given key
$clearUrl = function (string $key) use ($filters, $baseUrl): string {
    $q = $filters;
    unset($q[$key]);
    $q = array_filter($q, fn ($v) => $v !== '' && $v !== null);
    return $baseUrl . ($q !== [] ? '?' . http_build_query($q) : '');
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Variations</h1>
        <p class="muted mb-0">
            <?= e($product->name) ?>
            &middot;
            <?= (int) $totalVariations ?> total
            <?php if ($hasFilters): ?>
                &middot; <?= (int) $pagination['total'] ?> matching
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($backUrl) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to product
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        <?php foreach ($errors as $e): ?>
            <div><?= e((string) $e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (empty($variantAxes)): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-diagram-3" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-2">No variation attributes available</h2>
            <p class="muted small mb-3">
                This product has no variation attributes because none of its categories
                declare any. Assign the product to a category that has attributes mapped
                (like <em>Color</em> or <em>Size</em>), then return here.
            </p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="<?= e($backUrl) ?>" class="ra-btn ra-btn-outline">Back to product</a>
                <a href="/commerce/ca-admin/categories" class="ra-btn ra-btn-primary">
                    <i class="bi bi-diagram-3 me-1"></i> Manage categories
                </a>
            </div>
        </div>
    </div>
<?php else: ?>

    <!-- Incomplete axes warning -->
    <?php if (!empty($incompleteAxes)): ?>
        <div class="ra-alert ra-alert-warning mb-4">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-exclamation-triangle" style="font-size:1.25rem;flex-shrink:0;"></i>
                <div class="flex-grow-1">
                    <strong>Variations cannot be generated yet.</strong>
                    <div class="small mt-2">
                        The following attributes are mapped to this product's categories
                        but have no values yet:
                    </div>
                    <ul class="small mb-2 mt-2">
                        <?php foreach ($incompleteAxes as $axis): ?>
                            <li>
                                <strong><?= e($axis['name']) ?></strong>
                                &mdash;
                                <a href="/commerce/ca-admin/attributes/<?= (int) $axis['id'] ?>/edit">
                                    Add values to this attribute
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Attribute summary -->
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
                <div>
                    <div class="muted small mb-2">Variations come from these attributes:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($variantAxes as $axis): ?>
                            <span class="ra-attr-badge">
                                <i class="bi bi-input-cursor-text"></i>
                                <strong><?= e($axis['name']) ?></strong>
                                <span class="muted">(<?= count($axis['values']) ?>)</span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="<?= e($backUrl) ?>?tab=basic"
                   class="ra-btn ra-btn-outline ra-btn-sm">
                    Edit product categories
                </a>
            </div>
        </div>
    </div>

    <!-- Drift warning -->
    <?php if (!empty($drift['drift'])): ?>
        <div class="ra-alert ra-alert-warning mb-4">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-exclamation-triangle" style="font-size:1.25rem;flex-shrink:0;"></i>
                <div class="flex-grow-1">
                    <strong>Category attributes have changed since these variations were generated.</strong>
                    <div class="small mt-2">
                        Expected <?= (int) $drift['expected_count'] ?> combinations based on current
                        attributes; found <?= (int) $totalVariations ?> existing.
                        <?php if ($drift['missing'] > 0): ?>
                            <strong><?= (int) $drift['missing'] ?> missing</strong>.
                        <?php endif; ?>
                        <?php if ($drift['extra'] > 0): ?>
                            <strong><?= (int) $drift['extra'] ?> obsolete</strong>.
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="ra-btn ra-btn-danger ra-btn-sm"
                                onclick="RA_RegenerateVariants.open()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Regenerate variations
                        </button>
                        <span class="muted small ms-2">
                            Warning: this deletes all current variations and their stock.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($variants) && !$hasFilters): ?>
        <!-- Empty state -->
        <div class="ra-card">
            <div class="ra-card-body text-center py-5">
                <i class="bi bi-box" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
                <h2 class="h6 mt-3 mb-1">No variations yet</h2>
                <p class="muted small mb-0">
                    Variations are generated automatically from the attribute combinations above.
                    Refresh the page if they don't appear.
                </p>
            </div>
        </div>
    <?php elseif (empty($variants) && $hasFilters): ?>
        <!-- No matches -->
        <div class="ra-card">
            <div class="ra-card-body text-center py-5">
                <i class="bi bi-search" style="font-size:2rem;color:var(--ra-muted,#94a3b8);"></i>
                <h2 class="h6 mt-3 mb-1">No variations match your filters</h2>
                <p class="muted small mb-3">Try adjusting your search or clearing the filters.</p>
                <a href="<?= e($baseUrl) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            </div>
        </div>
    <?php else: ?>

        <!-- Search + group filter bar -->
        <div class="ra-card mb-4">
            <div class="ra-card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Search</label>
                        <form method="GET" action="<?= e($baseUrl) ?>" id="ra-variation-search-form">
                            <?php if (($filters['group'] ?? '') !== ''): ?>
                                <input type="hidden" name="group" value="<?= e($filters['group']) ?>">
                            <?php endif; ?>
                            <div class="position-relative">
                                <input type="text" name="q" id="ra-variation-search"
                                       class="form-control form-control-sm"
                                       value="<?= e($filters['q'] ?? '') ?>"
                                       placeholder="Search by SKU or attribute value"
                                       autocomplete="off">
                                <span id="ra-variation-search-spinner"
                                      style="display:none;position:absolute;right:.6rem;top:50%;transform:translateY(-50%);">
                                    <span class="spinner-border spinner-border-sm text-muted"></span>
                                </span>
                            </div>
                        </form>
                        <div class="form-text">Type at least 2 characters. Results update automatically.</div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <?php if ($hasFilters): ?>
                            <a href="<?= e($baseUrl) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                                <i class="bi bi-x-lg me-1"></i> Clear all filters
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($groups) && count($groups) > 1): ?>
                    <hr class="my-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <span class="muted small me-1">Filter by <?= e($variantAxes[0]['name'] ?? 'first attribute') ?>:</span>
                        <?php foreach ($groups as $g): ?>
                            <?php $active = ($filters['group'] ?? '') === $g['value']; ?>
                            <a href="<?= e($groupUrl($g['value'])) ?>"
                               class="ra-group-chip <?= $active ? 'is-active' : '' ?>">
                                <?= e($g['value']) ?>
                                <span class="ra-group-chip-count"><?= (int) $g['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Variants table -->
        <form method="POST" action="<?= e($baseUrl) ?>/save" novalidate id="ra-variant-form">
            <?= csrf_field() ?>

            <!-- Bulk actions toolbar (hidden until rows are checked) -->
            <div class="ra-bulk-toolbar" id="ra-bulk-toolbar" style="display:none;">
                <div class="ra-bulk-toolbar-inner">
                    <span class="muted small me-2">
                        <strong id="ra-bulk-count">0</strong> selected
                    </span>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm"
                            onclick="RA_BulkAction.setActive(true)">
                        <i class="bi bi-check-circle me-1"></i> Enable
                    </button>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm"
                            onclick="RA_BulkAction.setActive(false)">
                        <i class="bi bi-x-circle me-1"></i> Disable
                    </button>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm"
                            onclick="RA_BulkAction.promptPrice()">
                        <i class="bi bi-currency-exchange me-1"></i> Set price
                    </button>
                    <button type="button" class="ra-btn ra-btn-outline ra-btn-sm"
                            onclick="RA_BulkAction.clearSelection()">
                        Clear selection
                    </button>
                </div>
            </div>

            <div class="ra-card mb-3">
                <div class="ra-card-header d-flex align-items-center justify-content-between">
                    <h2 class="h6 mb-0">Variation matrix</h2>
                    <span class="muted small">
                        Showing <?= (int) $pagination['from'] ?>&ndash;<?= (int) $pagination['to'] ?>
                        of <?= (int) $pagination['total'] ?>
                    </span>
                </div>
                <div class="ra-card-body p-0">
                    <div class="table-responsive">
                        <table class="ra-table">
                            <thead>
                                <tr>
                                    <th style="width:36px;">
                                        <input type="checkbox" class="form-check-input"
                                               id="ra-select-all"
                                               onchange="RA_BulkAction.toggleAll(this.checked)">
                                    </th>
                                    <th>Combination</th>
                                    <th style="width:150px;">SKU</th>
                                    <th style="width:110px;">Price</th>
                                    <th style="width:120px;">Compare-at</th>
                                    <th style="width:90px;">Stock</th>
                                    <th style="width:70px;text-align:center;">Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($variants as $v): ?>
                                    <?php
                                    $stockQty = $variantStock[$v->id] ?? 0;
                                    $isActive = $v->status === 'active';
                                    ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox"
                                                   class="form-check-input ra-row-checkbox"
                                                   data-variant-id="<?= (int) $v->id ?>"
                                                   onchange="RA_BulkAction.updateToolbar()">
                                        </td>
                                        <td>
                                            <strong><?= e($v->attributeSummary) ?></strong>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="variants[<?= (int) $v->id ?>][sku]"
                                                   class="form-control form-control-sm"
                                                   value="<?= e((string) $v->sku) ?>"
                                                   placeholder="SKU">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="variants[<?= (int) $v->id ?>][price]"
                                                   class="form-control form-control-sm ra-variant-price"
                                                   data-variant-id="<?= (int) $v->id ?>"
                                                   step="0.01" min="0"
                                                   value="<?= e(number_format($v->price, 2, '.', '')) ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="variants[<?= (int) $v->id ?>][compare_at_price]"
                                                   class="form-control form-control-sm"
                                                   step="0.01" min="0"
                                                   value="<?= $v->compareAtPrice !== null ? e(number_format($v->compareAtPrice, 2, '.', '')) : '' ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="variants[<?= (int) $v->id ?>][stock_quantity]"
                                                   class="form-control form-control-sm"
                                                   step="1" min="0"
                                                   value="<?= (int) $stockQty ?>">
                                        </td>
                                        <td style="text-align:center;">
                                            <input type="hidden"
                                                   name="variants[<?= (int) $v->id ?>][active]"
                                                   value="0"
                                                   id="ra-active-hidden-<?= (int) $v->id ?>">
                                            <input type="checkbox"
                                                   name="variants[<?= (int) $v->id ?>][active]"
                                                   value="1"
                                                   class="form-check-input ra-variant-active"
                                                   data-variant-id="<?= (int) $v->id ?>"
                                                   <?= $isActive ? 'checked' : '' ?>>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($pagination['pages'] > 1): ?>
                <nav class="mt-3 mb-4" aria-label="Pagination">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php if ($pagination['page'] > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= e($pageUrl($pagination['page'] - 1)) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $pagination['page'] - 2);
                        $end   = min($pagination['pages'], $pagination['page'] + 2);

                        if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a></li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif;
                        endif;

                        for ($p = $start; $p <= $end; $p++): ?>
                            <li class="page-item <?= $p === $pagination['page'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor;

                        if ($end < $pagination['pages']): ?>
                            <?php if ($end < $pagination['pages'] - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= e($pageUrl($pagination['pages'])) ?>"><?= $pagination['pages'] ?></a></li>
                        <?php endif; ?>

                        <?php if ($pagination['page'] < $pagination['pages']): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= e($pageUrl($pagination['page'] + 1)) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <!-- Sticky action bar -->
            <div class="ra-sticky-actions">
                <div class="ra-sticky-actions-inner">
                    <a href="<?= e($backUrl) ?>" class="ra-btn ra-btn-outline">Back to product</a>
                    <button type="submit" class="ra-btn ra-btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save all variations
                    </button>
                </div>
            </div>

            <div class="ra-sticky-spacer"></div>
        </form>
    <?php endif; ?>

<?php endif; ?>

<!-- Regenerate confirmation modal -->
<div id="ra-regen-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_RegenerateVariants.close()"></div>
    <div class="ra-modal-panel" role="dialog">
        <div class="ra-modal-header">
            <h3 class="h6 mb-0">Regenerate variations</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_RegenerateVariants.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                This will <strong>permanently delete all <?= (int) $totalVariations ?> variations</strong>
                and their inventory records, then regenerate from the current category attributes.
            </p>
            <div class="ra-alert ra-alert-error small">
                <strong>Lost data:</strong> existing SKUs, custom prices, and stock quantities
                for all variations. Movement history is preserved in the audit log but is no
                longer linked to live variations.
            </div>
            <div class="mt-3">
                <label for="ra-regen-confirm" class="form-label small">
                    Type <code>regenerate</code> to confirm:
                </label>
                <input type="text" id="ra-regen-confirm" class="form-control form-control-sm"
                       autocomplete="off" placeholder="regenerate">
            </div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_RegenerateVariants.close()">
                Cancel
            </button>
            <form method="POST" action="<?= e($baseUrl) ?>/regenerate"
                  id="ra-regen-form" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger" id="ra-regen-submit" disabled>
                    <i class="bi bi-arrow-clockwise me-1"></i> Regenerate variations
                </button>
            </form>
        </div>
    </div>
</div>

<?= view_partial('admin.products._action-forms', [
    'product' => $product ?? null,
    'mode'    => $mode    ?? 'create',
]) ?>

<?php if ($product !== null): ?>
    <?= view_partial('admin.products._delete-modal') ?>
<?php endif; ?>

<script>
// ============ Regenerate modal ============
window.RA_RegenerateVariants = (function () {
    var modalId = 'ra-regen-modal';
    var inputEl, submitEl;

    function init() {
        inputEl  = document.getElementById('ra-regen-confirm');
        submitEl = document.getElementById('ra-regen-submit');
        if (inputEl && submitEl) {
            inputEl.addEventListener('input', function () {
                submitEl.disabled = (inputEl.value.trim() !== 'regenerate');
            });
        }
    }

    function open() {
        if (!inputEl) init();
        if (inputEl) inputEl.value = '';
        if (submitEl) submitEl.disabled = true;
        document.getElementById(modalId).style.display = 'flex';
        if (inputEl) inputEl.focus();
    }

    function close() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    return { open: open, close: close };
})();

// ============ Bulk actions ============
window.RA_BulkAction = (function () {
    function getChecked() {
        return Array.from(document.querySelectorAll('.ra-row-checkbox:checked'));
    }

    function updateToolbar() {
        var checked = getChecked();
        var toolbar = document.getElementById('ra-bulk-toolbar');
        var counter = document.getElementById('ra-bulk-count');
        if (!toolbar) return;

        if (checked.length === 0) {
            toolbar.style.display = 'none';
        } else {
            toolbar.style.display = 'block';
            if (counter) counter.textContent = checked.length;
        }

        // Sync select-all checkbox state
        var all = document.querySelectorAll('.ra-row-checkbox');
        var selectAll = document.getElementById('ra-select-all');
        if (selectAll && all.length > 0) {
            selectAll.checked = (checked.length === all.length);
            selectAll.indeterminate = (checked.length > 0 && checked.length < all.length);
        }
    }

    function toggleAll(checked) {
        document.querySelectorAll('.ra-row-checkbox').forEach(function (cb) {
            cb.checked = checked;
        });
        updateToolbar();
    }

    function clearSelection() {
        document.querySelectorAll('.ra-row-checkbox').forEach(function (cb) {
            cb.checked = false;
        });
        updateToolbar();
    }

    function setActive(isActive) {
        getChecked().forEach(function (cb) {
            var vid = cb.getAttribute('data-variant-id');
            var activeCb = document.querySelector('.ra-variant-active[data-variant-id="' + vid + '"]');
            if (activeCb) activeCb.checked = isActive;
        });
    }

    function promptPrice() {
        var input = window.prompt('Enter new price for selected variations (e.g. 4500):');
        if (input === null) return;
        var price = parseFloat(input);
        if (isNaN(price) || price < 0) {
            window.alert('Please enter a valid positive number.');
            return;
        }
        getChecked().forEach(function (cb) {
            var vid = cb.getAttribute('data-variant-id');
            var priceInput = document.querySelector('.ra-variant-price[data-variant-id="' + vid + '"]');
            if (priceInput) priceInput.value = price.toFixed(2);
        });
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', updateToolbar);

    return {
        updateToolbar: updateToolbar,
        toggleAll: toggleAll,
        clearSelection: clearSelection,
        setActive: setActive,
        promptPrice: promptPrice,
    };
})();

// ============ Auto-submit search ============
(function () {
    var input = document.getElementById('ra-variation-search');
    var form = document.getElementById('ra-variation-search-form');
    var spinner = document.getElementById('ra-variation-search-spinner');
    if (!input || !form) return;

    var debounceTimer = null;

    function submit() {
        if (spinner) spinner.style.display = 'inline-block';
        form.submit();
    }

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var value = input.value.trim();
        // Require at least 2 characters before auto-submit (or empty to clear)
        if (value.length > 0 && value.length < 2) return;

        debounceTimer = setTimeout(submit, 400);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(debounceTimer);
            submit();
        }
    });
})();
</script>

<style>
.ra-attr-badge {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .7rem;
    background: var(--ra-hover, rgba(148, 163, 184, 0.12));
    border-radius: 8px;
    font-size: .88rem;
    color: var(--ra-fg);
}
.ra-attr-badge i { color: var(--ra-muted, #94a3b8); }

.ra-group-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .3rem .75rem;
    background: var(--ra-hover, rgba(148, 163, 184, 0.1));
    color: var(--ra-fg, #0f172a);
    border: 1px solid transparent;
    border-radius: 999px;
    font-size: .85rem;
    text-decoration: none;
    transition: background-color .12s, border-color .12s, color .12s;
    cursor: pointer;
}
.ra-group-chip:hover {
    background: var(--ra-active, rgba(37, 99, 235, 0.1));
    color: var(--ra-accent, #2563eb);
    text-decoration: none;
}
.ra-group-chip.is-active {
    background: var(--ra-accent, #2563eb);
    color: #ffffff;
    border-color: var(--ra-accent, #2563eb);
}
.ra-group-chip-count {
    font-size: .72rem;
    opacity: .7;
    padding: 0 .35rem;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.15);
}
.ra-group-chip.is-active .ra-group-chip-count {
    background: rgba(255, 255, 255, 0.25);
}

.ra-bulk-toolbar {
    position: sticky;
    top: calc(var(--ra-topbar-h, 64px) + 8px);
    z-index: 20;
    margin-bottom: 1rem;
}
.ra-bulk-toolbar-inner {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .65rem 1rem;
    background: var(--ra-accent, #2563eb);
    color: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 16px -8px rgba(37, 99, 235, 0.4);
}
.ra-bulk-toolbar-inner .ra-btn-outline {
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.35);
}
.ra-bulk-toolbar-inner .ra-btn-outline:hover {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
}
</style>
