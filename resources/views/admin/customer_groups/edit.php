<?php
/**
 * Customer group create/edit form.
 *
 * @var \CorporateAid\Commerce\Models\CustomerGroup|null $group
 * @var array $errors
 * @var array $old
 * @var string $mode
 */
$group  = $group  ?? null;
$errors = $errors ?? [];
$old    = $old    ?? [];
$mode   = $mode   ?? 'create';

$val = function (string $key, $default = '') use ($old, $group) {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($group === null) {
        return (string) $default;
    }
    return (string) ($group->$key ?? $default);
};

$valBool = function (string $key, bool $default = false) use ($old, $group) {
    if (array_key_exists($key, $old)) {
        return !empty($old[$key]);
    }
    if ($group === null) {
        return $default;
    }
    return (bool) ($group->$key ?? $default);
};

$err = function (string $key) use ($errors): string {
    return isset($errors[$key])
        ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
        : '';
};

$actionUrl = $mode === 'edit' && $group !== null
    ? '/commerce/ca-admin/customer-groups/' . (int) $group->id
    : '/commerce/ca-admin/customer-groups';

$cancelUrl = '/commerce/ca-admin/customer-groups';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $mode === 'edit' ? 'Edit customer group' : 'New customer group' ?></h1>
        <p class="muted mb-0">
            <?php if ($mode === 'edit' && $group !== null): ?>
                <?= e($group->name) ?>
            <?php else: ?>
                Groups determine pricing and, for B2B, signup approval.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<form method="POST" action="<?= e($actionUrl) ?>" novalidate id="ra-group-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Basic information</h2>
        </div>
        <div class="ra-card-body">

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= e($val('name')) ?>"
                           placeholder="e.g. Wholesale">
                    <?= $err('name') ?>
                </div>

                <div class="col-md-4">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control"
                           pattern="[a-z0-9][a-z0-9-]{0,78}[a-z0-9]"
                           value="<?= e($val('slug')) ?>"
                           placeholder="auto-generated">
                    <div class="form-text">Leave blank to auto-generate from name.</div>
                    <?= $err('slug') ?>
                </div>

                <div class="col-md-8">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="2"
                              maxlength="500"
                              placeholder="Optional - appears in admin listings"><?= e($val('description')) ?></textarea>
                    <?= $err('description') ?>
                </div>

                <div class="col-md-4">
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control"
                           min="0" max="9999"
                           value="<?= e($val('sortOrder', '0')) ?>">
                    <div class="form-text">Lower numbers appear first.</div>
                </div>
            </div>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Behavior</h2>
        </div>
        <div class="ra-card-body">

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_default" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="is_default" name="is_default" value="1"
                           <?= $valBool('isDefault') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_default">
                        <strong>Default group</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    New customers are automatically assigned to the default group.
                    Only one group can be default.
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="is_b2b" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="is_b2b" name="is_b2b" value="1"
                           <?= $valBool('isB2b') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_b2b">
                        <strong>This is a B2B group</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    B2B groups get wholesale UX on the storefront: PO checkout,
                    net terms, larger quantity pickers.
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="requires_approval" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="requires_approval" name="requires_approval" value="1"
                           <?= $valBool('requiresApproval') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="requires_approval">
                        <strong>Require admin approval for new signups</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    New customers who register under this group go into an
                    approval queue. They can't order until approved.
                </div>
            </div>

            <div class="mb-0">
                <label for="default_net_days" class="form-label">
                    <strong>Net terms</strong>
                </label>
                <div class="d-flex align-items-center gap-2" style="max-width:220px;">
                    <input type="number" id="default_net_days" name="default_net_days"
                           class="form-control" min="0" max="365" step="1"
                           value="<?= e($val('defaultNetDays', '0')) ?>">
                    <span class="muted small">days</span>
                </div>
                <div class="form-text">
                    Days until payment is due. 0 = prepay (immediate), 30 = net-30, 60 = net-60.
                    Applies to all customers in this group. Only meaningful for B2B groups.
                </div>
                <?= $err('default_net_days') ?>
            </div>

        </div>
    </div>
</form>

<?php if ($mode === 'edit' && $group !== null): ?>
<div class="ra-card mb-4">
    <div class="ra-card-header d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Price list</h2>
        <a href="/commerce/ca-admin/customer-groups/<?= (int) $group->id ?>/prices"
           class="ra-btn ra-btn-outline ra-btn-sm">
            <i class="bi bi-tags me-1"></i> Manage prices
        </a>
    </div>
    <div class="ra-card-body">
        <p class="muted small mb-0">
            This group has its own price list. Members see group prices instead
            of base catalog prices when signed in. Anonymous visitors always see
            base prices.
        </p>
    </div>
</div>
<?php endif; ?>

<!-- Sticky action bar -->
<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-group-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            <?= $mode === 'edit' ? 'Save changes' : 'Create group' ?>
        </button>
    </div>
</div>

<div class="ra-sticky-spacer"></div>

<style>
.ra-btn-sm { padding: .35rem .65rem; font-size: .82rem; }
.ra-sticky-actions {
    position: fixed !important;
    bottom: 0 !important;
    left: var(--ra-sidebar-w, 260px) !important;
    right: 0 !important;
    padding: 1.5rem 2rem 1rem !important;
    background: linear-gradient(to top, #0b1220 55%, transparent 100%) !important;
    z-index: 900 !important;
    pointer-events: none !important;
}
[data-theme="light"] .ra-sticky-actions {
    background: linear-gradient(to top, #f4f6fb 55%, transparent 100%) !important;
}
.ra-sticky-actions-inner {
    display: flex !important;
    justify-content: flex-end !important;
    gap: .5rem !important;
    max-width: 1400px !important;
    margin: 0 auto !important;
    background: var(--ra-card-bg, #111c33) !important;
    border: 1px solid var(--ra-border, #1f2a44) !important;
    border-radius: 12px !important;
    padding: .75rem 1rem !important;
    box-shadow: 0 -4px 20px -8px rgba(0, 0, 0, 0.35) !important;
    pointer-events: auto !important;
}
.ra-sticky-spacer { height: 6rem !important; }
@media (max-width: 900px) {
    .ra-sticky-actions { left: 0 !important; padding: 1rem !important; }
}
</style>
