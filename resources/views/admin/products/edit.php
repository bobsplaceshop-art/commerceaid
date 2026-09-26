<?php
/**
 * Product create/edit form — shell.
 *
 * Single <form>. All tabs submit together. Tab switching is
 * client-side. Every field is included in the POST regardless of
 * which tab is active.
 */
$product     = $product     ?? null;
$errors      = $errors      ?? [];
$old         = $old         ?? [];
$mode        = $mode        ?? 'create';
$categories  = $categories  ?? [];
$brands      = $brands      ?? [];
$categoryIds = $categoryIds ?? [];
$tagIds      = $tagIds      ?? [];
$inventory   = $inventory   ?? null;
$attributes  = $attributes  ?? [];
$activeTab   = $activeTab   ?? 'basic';

$val = function (string $key, $default = '') use ($old, $product) {
    if (array_key_exists($key, $old)) {
        return $old[$key];
    }
    if ($product === null) {
        return $default;
    }
    return $product->$key ?? $default;
};

$valBool = function (string $key, bool $default = false) use ($old, $product) {
    if (array_key_exists($key, $old)) {
        return !empty($old[$key]);
    }
    if ($product === null) {
        return $default;
    }
    return (bool) ($product->$key ?? $default);
};

$err = function (string $key) use ($errors): string {
    return isset($errors[$key])
        ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
        : '';
};

$actionUrl = $mode === 'edit' && $product !== null
    ? '/commerce/ca-admin/products/' . (int) $product->id
    : '/commerce/ca-admin/products';

$cancelUrl = '/commerce/ca-admin/products';

$stockQty = $old['stock_quantity']
    ?? ($inventory !== null ? $inventory->quantity : 0);

$status = (string) $val('status', 'draft');
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1"><?= $mode === 'edit' ? 'Edit product' : 'New product' ?></h1>
        <p class="muted mb-0">
            <?php if ($mode === 'edit' && $product !== null): ?>
                <?= e($product->name) ?>
            <?php else: ?>
                Add a product to your catalog.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to products
    </a>
</div>

<form method="POST" action="<?= e($actionUrl) ?>" novalidate id="ra-product-form">
    <?= csrf_field() ?>
    <input type="hidden" name="current_tab" id="ra-current-tab" value="<?= e($activeTab ?? 'basic') ?>">

    <div class="row g-4">
        <div class="col-lg-8">
            <ul class="nav nav-tabs ra-product-tabs" role="tablist">
                <?php
                $tabs = [
                    'basic'     => ['label' => 'Basic',     'icon' => 'bi-info-circle'],
                    'pricing'   => ['label' => 'Pricing',   'icon' => 'bi-currency-dollar'],
                    'inventory' => ['label' => 'Inventory', 'icon' => 'bi-boxes'],
                    'shipping'  => ['label' => 'Shipping',  'icon' => 'bi-truck'],
                    'seo'       => ['label' => 'SEO',       'icon' => 'bi-search'],
                ];
                $firstTab = true;
                foreach ($tabs as $key => $tab):
                    $active = ($key === $activeTab) ? 'active' : '';
                    $firstTab = false;
                ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $active ?>"
                                id="tab-<?= e($key) ?>-btn"
                                data-bs-toggle="tab"
                                data-bs-target="#tab-<?= e($key) ?>"
                                type="button"
                                role="tab">
                            <i class="bi <?= e($tab['icon']) ?> me-1"></i>
                            <?= e($tab['label']) ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="tab-content ra-product-tab-content">
                <?php require __DIR__ . '/_tab_basic.php'; ?>
                <?php require __DIR__ . '/_tab_pricing.php'; ?>
                <?php require __DIR__ . '/_tab_inventory.php'; ?>
                <?php require __DIR__ . '/_tab_shipping.php'; ?>
                <?php require __DIR__ . '/_tab_seo.php'; ?>
            </div>

        </div>

        <div class="col-lg-4">
            <?php require __DIR__ . '/_sidebar.php'; ?>
        </div>
    </div>
</form>

<?php if ($mode === 'edit' && $product !== null): ?>
    <?= view_partial('admin/products/_delete-modal') ?>
<?php endif; ?>

<style>
.ra-product-tabs {
    border-bottom: 1px solid var(--ra-border, #e5e9f2);
    margin-bottom: 0;
}
.ra-product-tabs .nav-link {
    border: 0;
    border-bottom: 2px solid transparent;
    color: var(--ra-muted, #64748b);
    font-size: .9rem;
    padding: .65rem 1rem;
    background: transparent;
}
.ra-product-tabs .nav-link:hover {
    color: var(--ra-fg, #0f172a);
}
.ra-product-tabs .nav-link.active {
    color: var(--ra-accent, #2563eb);
    border-bottom-color: var(--ra-accent, #2563eb);
    background: transparent;
}
.ra-product-tab-content {
    background: var(--ra-card-bg, #fff);
    border: 1px solid var(--ra-border, #e5e9f2);
    border-top: 0;
    border-radius: 0 0 12px 12px;
    padding: 1.5rem;
}
</style>

<script>
(function () {
    // Activate the tab matching the current URL hash (e.g. #tab-seo)
    function activateFromHash() {
        var hash = window.location.hash;
        if (!hash || hash.length < 2) return;
        var target = document.querySelector('[data-bs-target="' + hash + '"]');
        if (target && typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            var tab = new bootstrap.Tab(target);
            tab.show();
        }
    }

    // On page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', activateFromHash);
    } else {
        activateFromHash();
    }

    // When a tab is clicked, update the URL hash without reloading
    // AND update the hidden current_tab field so POST preserves the tab
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function (e) {
            var target = e.target.getAttribute('data-bs-target');
            if (target && target.indexOf('#tab-') === 0) {
                history.replaceState(null, '', target);

                // Update hidden field with tab name (strip leading 'tab-')
                var tabName = target.replace('#tab-', '');
                var hidden = document.getElementById('ra-current-tab');
                if (hidden) hidden.value = tabName;
            }
        });
    });
})();
</script>

