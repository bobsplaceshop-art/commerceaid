<?php
/**
 * Create a new coupon.
 *
 * Context (from AdminCouponController::showCreate):
 *   $categories array<int,array{id:int,name:string}>
 *   $errors     array<string,string>
 *   $old        array<string,mixed>
 *
 * POSTs to /commerce/ca-admin/coupons.
 */
$categories = $categories ?? [];
$errors     = $errors     ?? [];
$old        = $old        ?? [];

$val = function (string $key, string $default = '') use ($old): string {
    return array_key_exists($key, $old) ? (string) $old[$key] : $default;
};

$err = function (string $key) use ($errors): string {
    return $errors[$key] ?? '';
};

$selectedCategories = $old['category_ids'] ?? [];
if (!is_array($selectedCategories)) {
    $selectedCategories = [];
}
$selectedCategories = array_map('intval', $selectedCategories);

$currentType   = $val('type', 'percentage');
$currentScope  = $val('applies_to', 'all_products');
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/coupons" class="text-decoration-none">Coupons</a>
            <span class="mx-1">&rsaquo;</span>
            <span>New coupon</span>
        </nav>
        <h1 class="h3 mb-1">New coupon</h1>
        <p class="muted mb-0">
            Discount codes customers enter at checkout.
        </p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="ra-alert ra-alert-error mb-4">
        Please fix the highlighted fields and try again.
    </div>
<?php endif; ?>

<form method="POST" action="/commerce/ca-admin/coupons" id="ra-coupon-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Coupon details</h2>
            <div class="row g-3">

                <div class="col-md-6">
                    <label class="muted small" for="cf-code">
                        Code <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="cf-code" name="code"
                           class="form-control <?= $err('code') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('code')) ?>"
                           maxlength="60"
                           placeholder="SAVE10"
                           required
                           autofocus
                           style="text-transform:uppercase;">
                    <div class="muted small mt-1">
                        Letters, digits, hyphens, and underscores only. Case insensitive at checkout.
                    </div>
                    <?php if ($err('code') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('code')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="cf-description">Description</label>
                    <input type="text" id="cf-description" name="description"
                           class="form-control <?= $err('description') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('description')) ?>"
                           maxlength="255"
                           placeholder="Shown to you only, not customers">
                    <?php if ($err('description') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('description')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="cf-type">
                        Type <span aria-hidden="true">*</span>
                    </label>
                    <select id="cf-type" name="type"
                            class="form-select <?= $err('type') !== '' ? 'is-invalid' : '' ?>"
                            required
                            data-ra-coupon-type>
                        <option value="percentage"   <?= $currentType === 'percentage'   ? 'selected' : '' ?>>Percent off</option>
                        <option value="fixed_amount" <?= $currentType === 'fixed_amount' ? 'selected' : '' ?>>Fixed amount off</option>
                        <option value="free_shipping"<?= $currentType === 'free_shipping'? 'selected' : '' ?>>Free shipping</option>
                    </select>
                    <?php if ($err('type') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('type')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6" data-ra-value-field>
                    <label class="muted small" for="cf-value" data-ra-value-label>
                        Discount value
                    </label>
                    <input type="text" id="cf-value" name="value"
                           class="form-control <?= $err('value') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('value', '0')) ?>"
                           inputmode="decimal">
                    <div class="muted small mt-1" data-ra-value-hint>
                        Percentage (0-100) or a fixed currency amount.
                    </div>
                    <?php if ($err('value') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('value')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <hr>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="cf-valid-from">Valid from</label>
                    <input type="datetime-local" id="cf-valid-from" name="valid_from"
                           class="form-control <?= $err('valid_from') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('valid_from')) ?>">
                    <div class="muted small mt-1">Leave blank for no start date.</div>
                    <?php if ($err('valid_from') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('valid_from')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label class="muted small" for="cf-valid-to">Valid to</label>
                    <input type="datetime-local" id="cf-valid-to" name="valid_to"
                           class="form-control <?= $err('valid_to') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('valid_to')) ?>">
                    <div class="muted small mt-1">Leave blank for no end date.</div>
                    <?php if ($err('valid_to') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('valid_to')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="muted small" for="cf-min-order">Minimum order</label>
                    <input type="text" id="cf-min-order" name="min_order_subtotal"
                           class="form-control <?= $err('min_order_subtotal') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('min_order_subtotal')) ?>"
                           inputmode="decimal"
                           placeholder="No minimum">
                    <?php if ($err('min_order_subtotal') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('min_order_subtotal')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="muted small" for="cf-usage-limit">Global usage limit</label>
                    <input type="text" id="cf-usage-limit" name="usage_limit"
                           class="form-control <?= $err('usage_limit') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('usage_limit')) ?>"
                           inputmode="numeric"
                           placeholder="Unlimited">
                    <?php if ($err('usage_limit') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('usage_limit')) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="muted small" for="cf-per-customer">Per-customer limit</label>
                    <input type="text" id="cf-per-customer" name="per_customer_limit"
                           class="form-control <?= $err('per_customer_limit') !== '' ? 'is-invalid' : '' ?>"
                           value="<?= e($val('per_customer_limit')) ?>"
                           inputmode="numeric"
                           placeholder="Unlimited">
                    <?php if ($err('per_customer_limit') !== ''): ?>
                        <div class="invalid-feedback d-block"><?= e($err('per_customer_limit')) ?></div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Applies to</h2>

            <div class="mb-3">
                <label class="muted small" for="cf-scope">Scope</label>
                <select id="cf-scope" name="applies_to"
                        class="form-select <?= $err('applies_to') !== '' ? 'is-invalid' : '' ?>"
                        data-ra-coupon-scope>
                    <option value="all_products"         <?= $currentScope === 'all_products'         ? 'selected' : '' ?>>All products in the cart</option>
                    <option value="specific_categories"  <?= $currentScope === 'specific_categories'  ? 'selected' : '' ?>>Only products in specific categories</option>
                </select>
                <?php if ($err('applies_to') !== ''): ?>
                    <div class="invalid-feedback d-block"><?= e($err('applies_to')) ?></div>
                <?php endif; ?>
            </div>

            <div data-ra-category-scope <?= $currentScope !== 'specific_categories' ? 'hidden' : '' ?>>
                <label class="muted small d-block mb-2">
                    Categories <span aria-hidden="true">*</span>
                </label>
                <?php if (!empty($err('category_ids'))): ?>
                    <div class="ra-alert ra-alert-error mb-3" style="font-size:.9rem;">
                        <?= e($err('category_ids')) ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($categories)): ?>
                    <p class="muted small mb-0">
                        No categories exist yet. Create one first at
                        <a href="/commerce/ca-admin/categories">Categories</a>.
                    </p>
                <?php else: ?>
                    <div class="row g-1" style="max-height:280px;overflow:auto;">
                        <?php foreach ($categories as $c): ?>
                            <div class="col-md-4">
                                <label class="form-check">
                                    <input type="checkbox" class="form-check-input"
                                           name="category_ids[]"
                                           value="<?= (int) $c['id'] ?>"
                                           <?= in_array((int) $c['id'], $selectedCategories, true) ? 'checked' : '' ?>>
                                    <span class="form-check-label"><?= e($c['name']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</form>

<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="/commerce/ca-admin/coupons" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-coupon-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check2 me-1"></i> Create coupon
        </button>
    </div>
</div>
<div class="ra-sticky-spacer"></div>

<script>
(function () {
    'use strict';

    var typeSelect  = document.querySelector('[data-ra-coupon-type]');
    var valueField  = document.querySelector('[data-ra-value-field]');
    var valueLabel  = document.querySelector('[data-ra-value-label]');
    var valueHint   = document.querySelector('[data-ra-value-hint]');
    var scopeSelect = document.querySelector('[data-ra-coupon-scope]');
    var scopeBlock  = document.querySelector('[data-ra-category-scope]');

    function syncType() {
        if (!typeSelect || !valueField) return;
        var isFree = typeSelect.value === 'free_shipping';
        valueField.hidden = isFree;
        if (valueLabel) {
            valueLabel.textContent = typeSelect.value === 'percentage'
                ? 'Percentage (0-100)'
                : (typeSelect.value === 'fixed_amount'
                    ? 'Amount off'
                    : 'Discount value');
        }
        if (valueHint) {
            valueHint.textContent = typeSelect.value === 'percentage'
                ? 'A number between 0 and 100.'
                : (typeSelect.value === 'fixed_amount'
                    ? 'A currency amount, e.g. 25.00.'
                    : '');
        }
    }

    function syncScope() {
        if (!scopeSelect || !scopeBlock) return;
        scopeBlock.hidden = scopeSelect.value !== 'specific_categories';
    }

    if (typeSelect) typeSelect.addEventListener('change', syncType);
    if (scopeSelect) scopeSelect.addEventListener('change', syncScope);

    syncType();
    syncScope();
})();
</script>
