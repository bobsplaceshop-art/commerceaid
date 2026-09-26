<?php
/**
 * Attribute create/edit form.
 *
 * @var array|null $attribute
 * @var array $values
 * @var array $usedCategories
 * @var array $errors
 * @var array $old
 * @var string $mode
 */
$attribute      = $attribute      ?? null;
$values         = $values         ?? [];
$usedCategories = $usedCategories ?? [];
$errors         = $errors         ?? [];
$old            = $old            ?? [];
$mode           = $mode           ?? 'create';

$val = function (string $key, $default = '') use ($old, $attribute) {
    if (array_key_exists($key, $old)) {
        return (string) $old[$key];
    }
    if ($attribute === null) {
        return (string) $default;
    }
    return (string) ($attribute[$key] ?? $default);
};

$valBool = function (string $key, bool $default = false) use ($old, $attribute) {
    if (array_key_exists($key, $old)) {
        return !empty($old[$key]);
    }
    if ($attribute === null) {
        return $default;
    }
    return (bool) ($attribute[$key] ?? $default);
};

$err = function (string $key) use ($errors): string {
    return isset($errors[$key])
        ? '<div class="text-danger small mt-1">' . e((string) $errors[$key]) . '</div>'
        : '';
};

$actionUrl = $mode === 'edit' && $attribute !== null
    ? '/commerce/ca-admin/attributes/' . (int) $attribute['id']
    : '/commerce/ca-admin/attributes';

$cancelUrl = '/commerce/ca-admin/attributes';
$currentInputType = (string) $val('inputType', 'select');
?>

<style>
/* ================================================================
   Attribute editor styles — inline for reliability
   ================================================================ */

.ra-attr-values-list {
    display: flex;
    flex-direction: column;
    gap: .35rem;
}
.ra-attr-value-row {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem .75rem;
    background: var(--ra-hover, rgba(148, 163, 184, 0.08));
    border-radius: 8px;
}
.ra-attr-value-name {
    color: var(--ra-fg, #0f172a);
    font-weight: 500;
}

.ra-color-swatch-lg {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.15);
    flex-shrink: 0;
}

/* Colour picker */
.ra-color-picker {
    display: flex;
    flex-direction: column;
    gap: .5rem;
}
.ra-color-picker-row {
    display: flex;
    gap: .5rem;
    align-items: center;
}
.ra-color-native {
    width: 38px;
    height: 34px;
    padding: 2px;
    border-radius: 8px;
    border: 1px solid var(--ra-border, #d1d9e6);
    background: transparent;
    cursor: pointer;
    flex-shrink: 0;
}
.ra-color-hex {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    text-transform: lowercase;
}
.ra-color-swatches {
    display: flex;
    flex-wrap: wrap;
    gap: .3rem;
}
.ra-color-swatch-btn {
    width: 24px;
    height: 24px;
    border-radius: 6px;
    border: 1px solid rgba(0, 0, 0, 0.15);
    cursor: pointer;
    padding: 0;
    transition: transform .1s, box-shadow .1s;
}
.ra-color-swatch-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.ra-color-swatch-btn.is-active {
    outline: 2px solid var(--ra-accent, #2563eb);
    outline-offset: 2px;
}

/* Mapped categories list */
.ra-mapped-cats {
    display: flex;
    flex-direction: column;
    gap: .35rem;
}
.ra-mapped-cat {
    display: flex;
    align-items: center;
    gap: .6rem;
    padding: .55rem .75rem;
    background: var(--ra-hover, rgba(148, 163, 184, 0.08));
    border-radius: 8px;
    color: var(--ra-fg, #0f172a);
    text-decoration: none;
    transition: background-color .12s;
}
.ra-mapped-cat:hover {
    background: var(--ra-active, rgba(37, 99, 235, 0.10));
    color: var(--ra-accent, #2563eb);
    text-decoration: none;
}

/* ================================================================
   Sticky action bar — fixed to viewport bottom
   ================================================================ */

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
.ra-sticky-spacer {
    height: 6rem !important;
}

@media (max-width: 900px) {
    .ra-sticky-actions {
        left: 0 !important;
        padding: 1rem !important;
    }
}
</style>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1"><?= $mode === 'edit' ? 'Edit attribute' : 'New attribute' ?></h1>
        <p class="muted mb-0">
            <?php if ($mode === 'edit' && $attribute !== null): ?>
                <?= e($attribute['name']) ?>
            <?php else: ?>
                Define an attribute and its values.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>

<form method="POST" action="<?= e($actionUrl) ?>" novalidate id="ra-attribute-main-form">
    <?= csrf_field() ?>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Definition</h2>
        </div>
        <div class="ra-card-body">

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= e($val('name')) ?>"
                           placeholder="e.g. Color, Size, Material">
                    <?= $err('name') ?>
                </div>

                <div class="col-md-4">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control"
                           pattern="[a-z0-9][a-z0-9-]{0,58}[a-z0-9]"
                           value="<?= e($val('slug')) ?>"
                           placeholder="auto-generated">
                    <div class="form-text">Leave blank to auto-generate.</div>
                    <?= $err('slug') ?>
                </div>

                <div class="col-md-6">
                    <label for="input_type" class="form-label">Input type</label>
                    <select id="input_type" name="input_type" class="form-select">
                        <option value="select" <?= $currentInputType === 'select' ? 'selected' : '' ?>>Select (dropdown)</option>
                        <option value="color"  <?= $currentInputType === 'color'  ? 'selected' : '' ?>>Color (swatch)</option>
                        <option value="text"   <?= $currentInputType === 'text'   ? 'selected' : '' ?>>Text</option>
                        <option value="number" <?= $currentInputType === 'number' ? 'selected' : '' ?>>Number</option>
                    </select>
                    <div class="form-text">How values are shown on the product editor.</div>
                    <?= $err('input_type') ?>
                </div>

                <div class="col-md-3">
                    <label for="sort_order" class="form-label">Sort order</label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control"
                           min="0" max="9999"
                           value="<?= e($val('sortOrder', '0')) ?>">
                </div>

                <div class="col-md-3 d-flex align-items-center">
                    <div class="form-check form-switch mt-4">
                        <input type="hidden" name="is_variant_axis" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="is_variant_axis" name="is_variant_axis" value="1"
                               <?= $valBool('isVariantAxis') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_variant_axis">
                            <strong>Used for variants</strong>
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-text">
                        Turn on <strong>Used for variants</strong> if this attribute can generate product variants (e.g. Color, Size). Off for informational attributes.
                    </div>
                </div>
            </div>

        </div>
    </div>

</form>

<?php if ($mode === 'edit' && $attribute !== null): ?>

    <div class="ra-card mb-4">
        <div class="ra-card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0">Values</h2>
            <span class="muted small"><?= count($values) ?> value<?= count($values) === 1 ? '' : 's' ?></span>
        </div>
        <div class="ra-card-body">

            <?php if (empty($values)): ?>
                <p class="muted small mb-3">No values yet. Add the first one below.</p>
            <?php else: ?>
                <div class="ra-attr-values-list mb-3">
                    <?php foreach ($values as $v): ?>
                        <div class="ra-attr-value-row">
                            <?php if (($attribute['input_type'] ?? '') === 'color' && !empty($v['color_hex'])): ?>
                                <span class="ra-color-swatch-lg" style="background:<?= e($v['color_hex']) ?>;"></span>
                            <?php endif; ?>
                            <span class="ra-attr-value-name"><?= e($v['value']) ?></span>
                            <code class="muted small ms-2"><?= e($v['slug']) ?></code>
                            <form method="POST"
                                  action="/commerce/ca-admin/attributes/<?= (int) $attribute['id'] ?>/values/<?= (int) $v['id'] ?>/delete"
                                  class="ms-auto"
                                  onsubmit="return confirm('Remove this value?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="ra-icon-btn" title="Remove">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST"
                  action="/commerce/ca-admin/attributes/<?= (int) $attribute['id'] ?>/values">
                <?= csrf_field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="new_value" class="form-label small mb-1">Value</label>
                        <input type="text" id="new_value" name="value" class="form-control form-control-sm" required
                               placeholder="e.g. Red, Medium, Cotton">
                    </div>
                    <div class="col-md-3">
                        <label for="new_slug" class="form-label small mb-1">Slug (optional)</label>
                        <input type="text" id="new_slug" name="slug" class="form-control form-control-sm"
                               placeholder="auto-generated">
                    </div>

                    <?php if (($attribute['input_type'] ?? '') === 'color'): ?>
                        <div class="col-md-3">
                            <label for="new_color_hex" class="form-label small mb-1">Color</label>
                            <div class="ra-color-picker">
                                <div class="ra-color-picker-row">
                                    <input type="color" id="new_color_picker" value="#cccccc"
                                           class="ra-color-native">
                                    <input type="text" id="new_color_hex" name="color_hex"
                                           class="form-control form-control-sm ra-color-hex"
                                           value="#cccccc" pattern="#[0-9A-Fa-f]{6}" maxlength="7">
                                </div>
                                <div class="ra-color-swatches" id="ra-quick-swatches"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-md-2">
                        <button type="submit" class="ra-btn ra-btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header d-flex align-items-center justify-content-between">
            <h2 class="h6 mb-0">Used by categories</h2>
            <span class="muted small"><?= count($usedCategories) ?> categor<?= count($usedCategories) === 1 ? 'y' : 'ies' ?></span>
        </div>
        <div class="ra-card-body">

            <?php if (empty($usedCategories)): ?>
                <p class="muted small mb-0">
                    This attribute isn't mapped to any category yet. Open a category
                    and add it there to make it appear in that category's variant generator.
                </p>
            <?php else: ?>
                <p class="muted small mb-3">
                    Products in these categories see this attribute in their variant generator:
                </p>
                <div class="ra-mapped-cats">
                    <?php foreach ($usedCategories as $cat): ?>
                        <a href="/commerce/ca-admin/categories/<?= (int) $cat['id'] ?>/edit"
                           class="ra-mapped-cat">
                            <i class="bi bi-diagram-3"></i>
                            <span><?= e($cat['name']) ?></span>
                            <i class="bi bi-arrow-right ms-auto" style="font-size:.75rem;opacity:.5;"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

<?php endif; ?>

<!-- ============================================================= -->
<!-- Sticky action bar                                             -->
<!-- ============================================================= -->
<div class="ra-sticky-actions">
    <div class="ra-sticky-actions-inner">
        <a href="<?= e($cancelUrl) ?>" class="ra-btn ra-btn-outline">Cancel</a>
        <button type="submit" form="ra-attribute-main-form" class="ra-btn ra-btn-primary">
            <i class="bi bi-check-lg me-1"></i>
            <?= $mode === 'edit' ? 'Save changes' : 'Create attribute' ?>
        </button>
    </div>
</div>

<!-- Spacer so content does not hide behind fixed action bar -->
<div class="ra-sticky-spacer"></div>

<script>
window.RA_ColorPicker = (function () {
    var SWATCHES = [
        { name: 'Black',   hex: '#000000' },
        { name: 'White',   hex: '#ffffff' },
        { name: 'Gray',    hex: '#6b7280' },
        { name: 'Silver',  hex: '#d1d5db' },
        { name: 'Red',     hex: '#ef4444' },
        { name: 'Crimson', hex: '#dc2626' },
        { name: 'Orange',  hex: '#f97316' },
        { name: 'Amber',   hex: '#f59e0b' },
        { name: 'Yellow',  hex: '#facc15' },
        { name: 'Lime',    hex: '#84cc16' },
        { name: 'Green',   hex: '#22c55e' },
        { name: 'Emerald', hex: '#10b981' },
        { name: 'Teal',    hex: '#14b8a6' },
        { name: 'Cyan',    hex: '#06b6d4' },
        { name: 'Sky',     hex: '#0ea5e9' },
        { name: 'Blue',    hex: '#3b82f6' },
        { name: 'Navy',    hex: '#1e3a8a' },
        { name: 'Indigo',  hex: '#6366f1' },
        { name: 'Violet',  hex: '#8b5cf6' },
        { name: 'Purple',  hex: '#a855f7' },
        { name: 'Pink',    hex: '#ec4899' },
        { name: 'Rose',    hex: '#f43f5e' },
        { name: 'Brown',   hex: '#78350f' },
        { name: 'Beige',   hex: '#f5f5dc' },
        { name: 'Cream',   hex: '#fef3c7' },
        { name: 'Gold',    hex: '#d4af37' },
    ];

    function buildSwatches() {
        var container = document.getElementById('ra-quick-swatches');
        if (!container) return;
        container.innerHTML = '';
        SWATCHES.forEach(function (s) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ra-color-swatch-btn';
            btn.style.background = s.hex;
            btn.title = s.name + ' (' + s.hex + ')';
            btn.setAttribute('data-hex', s.hex);
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                setColor(s.hex);
            });
            container.appendChild(btn);
        });
    }

    function setColor(hex) {
        var picker = document.getElementById('new_color_picker');
        var hexInput = document.getElementById('new_color_hex');
        if (picker) picker.value = hex;
        if (hexInput) hexInput.value = hex;
        updateSwatches(hex);
    }

    function updateSwatches(hex) {
        hex = (hex || '').toLowerCase();
        document.querySelectorAll('.ra-color-swatch-btn').forEach(function (btn) {
            var isActive = (btn.getAttribute('data-hex') || '').toLowerCase() === hex;
            btn.classList.toggle('is-active', isActive);
        });
    }

    function init() {
        buildSwatches();
        var picker = document.getElementById('new_color_picker');
        var hexInput = document.getElementById('new_color_hex');

        if (picker) {
            picker.addEventListener('input', function () {
                if (hexInput) hexInput.value = this.value;
                updateSwatches(this.value);
            });
        }
        if (hexInput) {
            hexInput.addEventListener('input', function () {
                if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
                    if (picker) picker.value = this.value;
                    updateSwatches(this.value);
                }
            });
            updateSwatches(hexInput.value);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return { setColor: setColor, updateSwatches: updateSwatches };
})();
</script>
