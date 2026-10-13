<?php
/**
 * Store Settings page. Tabbed, dynamic.
 *
 * Context (from SettingsController::index):
 *   $groups       array<int,string>   tab keys in order
 *   $activeTab    string              current tab key or 'advanced'
 *   $definitions  array<string,array> the active tab's definitions
 *   $values       array<string,string> every stored value for the store
 *   $custom       array<string,string> custom settings (advanced tab only)
 *
 * The form renders every definition for the active tab. Adding
 * a new setting to StoreSettingDefinitions::DEFINITIONS makes it
 * appear here automatically. No view edit.
 */
$groups      = $groups      ?? [];
$activeTab   = $activeTab   ?? 'general';
$definitions = $definitions ?? [];
$values      = $values      ?? [];
$custom      = $custom      ?? [];

// Field name transform: dots to double underscores.
$fieldName = function (string $key): string {
    return str_replace('.', '__', $key);
};

$tabs = [];
foreach ($groups as $g) {
    $tabs[$g] = \CorporateAid\Commerce\Support\StoreSettingDefinitions::groupLabel($g);
}
$tabs['advanced'] = 'Advanced';
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Store Settings</h1>
        <p class="muted mb-0">
            Configure how your store behaves.
        </p>
    </div>
</div>

<div class="ra-card">
    <div class="ra-settings-tabs" role="tablist">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="/commerce/ca-admin/settings?tab=<?= e($key) ?>"
               class="ra-settings-tab <?= $activeTab === $key ? 'is-active' : '' ?>"
               role="tab">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="ra-card-body">

        <?php if ($activeTab === 'advanced'): ?>
            <!-- ============================== ADVANCED ============================== -->

            <?php if ($custom === []): ?>
                <div class="text-center py-4">
                    <p class="muted small mb-3">
                        No custom settings yet. Add one below.
                    </p>
                </div>
            <?php else: ?>
                <div class="ra-table-responsive-wrap mb-4" data-responsive="scroll">
                    <table class="ra-table">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Value</th>
                                <th style="width:120px;text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($custom as $k => $v): ?>
                                <tr>
                                    <td><code class="small"><?= e((string) $k) ?></code></td>
                                    <td><span class="small"><?= e((string) $v) ?></span></td>
                                    <td class="text-end">
                                        <form method="POST"
                                              action="/commerce/ca-admin/settings/advanced/delete"
                                              class="d-inline"
                                              onsubmit="return confirm('Remove this setting?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="custom_key" value="<?= e((string) $k) ?>">
                                            <button type="submit"
                                                    class="ra-icon-btn"
                                                    title="Remove">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h3 class="h6 mb-2">Add a custom setting</h3>
            <p class="muted small mb-3">
                Custom settings are pure data. Nothing reads them
                until the platform has code for them. Useful for
                staging config or storing merchant-specific flags.
            </p>

            <form method="POST"
                  action="/commerce/ca-admin/settings/advanced"
                  class="ra-settings-add-form">
                <?= csrf_field() ?>
                <div class="row g-2 align-items-end">
                    <div class="col-md-5">
                        <label class="ra-settings-label" for="custom_key">Key</label>
                        <input type="text"
                               id="custom_key"
                               name="custom_key"
                               class="ra-settings-input"
                               placeholder="my.custom.flag"
                               required>
                    </div>
                    <div class="col-md-5">
                        <label class="ra-settings-label" for="custom_value">Value</label>
                        <input type="text"
                               id="custom_value"
                               name="custom_value"
                               class="ra-settings-input"
                               placeholder="any value">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="ra-btn ra-btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i> Add
                        </button>
                    </div>
                </div>
            </form>

        <?php elseif ($definitions === []): ?>
            <!-- ============================== EMPTY TAB ============================== -->
            <div class="text-center py-4">
                <p class="muted small mb-0">
                    No settings in this tab yet.
                </p>
            </div>

        <?php else: ?>
            <!-- ============================== NORMAL TAB ============================== -->
            <form method="POST"
                  action="/commerce/ca-admin/settings"
                  class="ra-settings-form"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="_tab" value="<?= e($activeTab) ?>">

                <?php foreach ($definitions as $key => $def): ?>
                    <?php
                    $value = $values[$key] ?? (string) ($def['default'] ?? '');
                    // Booleans with no stored row: show the default.
                    if ($def['type'] === 'boolean' && !isset($values[$key])) {
                        $value = ($def['default'] ?? false) ? '1' : '0';
                    } elseif (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    }
                    ?>
                    <?= view_partial('admin.settings._field', [
                        'key'       => $key,
                        'def'       => $def,
                        'value'     => (string) $value,
                        'fieldName' => $fieldName($key),
                    ]) ?>
                <?php endforeach; ?>

                <div class="mt-4 pt-3" style="border-top:1px solid var(--ra-border,#1f2a44);">
                    <button type="submit" class="ra-btn ra-btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save settings
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<style>
.ra-settings-tabs {
    display: flex;
    gap: .25rem;
    padding: 1rem 1.25rem 0;
    border-bottom: 1px solid var(--ra-border, #1f2a44);
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}
.ra-settings-tabs::-webkit-scrollbar {
    display: none;
}
.ra-settings-tab {
    display: inline-block;
    padding: .55rem 1rem;
    font-size: .9rem;
    color: var(--ra-muted, #94a3b8);
    text-decoration: none;
    border: 1px solid transparent;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
    white-space: nowrap;
}
.ra-settings-tab:hover {
    color: var(--ra-fg, #e2e8f0);
}
.ra-settings-tab.is-active {
    color: var(--ra-fg, #e2e8f0);
    background: var(--ra-card-bg, #111c33);
    border-color: var(--ra-border, #1f2a44);
    border-bottom-color: var(--ra-card-bg, #111c33);
    margin-bottom: -1px;
    font-weight: 500;
}

.ra-settings-field {
    margin-bottom: 1.5rem;
}
.ra-settings-label {
    display: block;
    font-size: .85rem;
    font-weight: 600;
    margin-bottom: .4rem;
    color: var(--ra-fg, #e2e8f0);
}
.ra-settings-hint {
    font-size: .82rem;
    color: var(--ra-muted, #94a3b8);
    margin-top: .35rem;
    line-height: 1.5;
}
.ra-settings-input,
.ra-settings-select,
.ra-settings-textarea {
    width: 100%;
    max-width: 480px;
    padding: .55rem .75rem;
    font-size: .95rem;
    background: var(--ra-input-bg, #0b1220);
    color: var(--ra-fg, #e2e8f0);
    border: 1px solid var(--ra-border, #1f2a44);
    border-radius: 6px;
}
.ra-settings-textarea {
    max-width: 640px;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: .85rem;
    line-height: 1.6;
}

/* Toggle */
.ra-settings-toggle {
    display: inline-flex;
    align-items: center;
    gap: .75rem;
    cursor: pointer;
}
.ra-settings-toggle input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.ra-settings-toggle-track {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    background: var(--ra-border, #1f2a44);
    border-radius: 12px;
    transition: background .15s ease;
}
.ra-settings-toggle input:checked + .ra-settings-toggle-track {
    background: var(--ra-primary, #3b82f6);
}
.ra-settings-toggle-knob {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    transition: transform .15s ease;
}
.ra-settings-toggle input:checked + .ra-settings-toggle-track .ra-settings-toggle-knob {
    transform: translateX(20px);
}
.ra-settings-toggle-state {
    font-size: .85rem;
    color: var(--ra-muted, #94a3b8);
}
</style>
