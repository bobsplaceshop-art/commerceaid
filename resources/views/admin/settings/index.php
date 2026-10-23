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

// Payments-tab provider list. Empty on every other tab.
$paymentProviders = $paymentProviders ?? [];
$paymentMethods   = $paymentMethods   ?? [];

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
                  id="ra-settings-form"
                  enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="_tab" value="<?= e($activeTab) ?>">

                <?php if ($activeTab === 'payments'): ?>
                    <!-- ============================== PAYMENTS: PROVIDERS ============================== -->
                    <?php if ($paymentProviders === []): ?>
                        <div class="text-center py-4">
                            <p class="muted small mb-0">
                                No payment providers are registered in this build.
                            </p>
                        </div>
                    <?php else: ?>
                        <h3 class="h6 mb-2">Payment providers</h3>
                        <p class="muted small mb-3">
                            Enable a provider to offer it at checkout. A provider
                            is only offered when its credentials below are filled
                            in and its connection test succeeds.
                        </p>

                        <div class="ra-paytab-providers">
                            <?php foreach ($paymentProviders as $key => $label):
                                $state    = $paymentMethods[$key] ?? ['is_enabled' => false, 'sort_order' => 0];
                                $enabled  = (bool) ($state['is_enabled'] ?? false);
                                $fieldKey = 'payments__' . $key . '__enabled';
                            ?>
                                <div class="ra-paytab-row">
                                    <label class="ra-settings-toggle" for="<?= e($fieldKey) ?>">
                                        <input type="checkbox"
                                               id="<?= e($fieldKey) ?>"
                                               name="<?= e($fieldKey) ?>"
                                               value="1"
                                               <?= $enabled ? 'checked' : '' ?>>
                                        <span class="ra-settings-toggle-track">
                                            <span class="ra-settings-toggle-knob"></span>
                                        </span>
                                        <span class="ra-paytab-label"><?= e($label) ?></span>
                                    </label>
                                    <span class="muted small ra-paytab-key"><code><?= e($key) ?></code></span>
                                    <button type="submit"
                                            formaction="/commerce/ca-admin/settings/payments/test"
                                            formnovalidate
                                            name="provider_key"
                                            value="<?= e($key) ?>"
                                            class="ra-btn ra-btn-outline ra-btn-sm ra-paytab-test">
                                        <i class="bi bi-plug me-1"></i> Test connection
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="ra-paytab-divider">

                        <h3 class="h6 mb-2">Credentials</h3>
                        <p class="muted small mb-3">
                            Enter the API keys the provider gave you. Test-mode
                            credentials start with <code>sk_test_</code> or
                            <code>pk_test_</code>; live credentials start with
                            <code>sk_live_</code> or <code>pk_live_</code>.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>

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

            </form>

            <div class="ra-sticky-actions">
                <div class="ra-sticky-actions-inner">
                    <button type="submit" form="ra-settings-form" class="ra-btn ra-btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save settings
                    </button>
                </div>
            </div>
            <div class="ra-sticky-spacer"></div>
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

/* Payments tab: provider rows. The row uses flex-wrap so
   the key code and the test button stack naturally at
   narrow widths without a media query. */
.ra-paytab-providers {
    display: flex;
    flex-direction: column;
    gap: .75rem;
    margin-bottom: 1.25rem;
}
.ra-paytab-row {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: .85rem 1rem;
    border: 1px solid var(--ra-border, #1f2a44);
    border-radius: 8px;
    background: var(--ra-card-bg, #111c33);
    flex-wrap: wrap;
}
.ra-paytab-label {
    font-size: .95rem;
    font-weight: 500;
    color: var(--ra-fg, #e2e8f0);
}
.ra-paytab-key {
    margin-left: auto;
}
.ra-paytab-key code {
    font-size: .78rem;
    color: var(--ra-muted, #94a3b8);
}
.ra-paytab-test {
    margin: 0;
}
.ra-paytab-divider {
    border: 0;
    border-top: 1px solid var(--ra-border, #1f2a44);
    margin: 1.5rem 0;
}
</style>
<script>
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var startsInput = document.querySelector('[data-sf-date-field="popup__starts_at"]');
        var endsInput   = document.querySelector('[data-sf-date-field="popup__ends_at"]');
        if (!startsInput || !endsInput) return;

        function syncMin() {
            if (startsInput.value) {
                endsInput.min = startsInput.value;
                if (endsInput.value && endsInput.value < startsInput.value) {
                    endsInput.value = startsInput.value;
                }
            } else {
                endsInput.removeAttribute('min');
            }
        }

        startsInput.addEventListener('change', syncMin);
        endsInput.addEventListener('change', syncMin);
        syncMin();
    });
})();
</script>

<?php
// Include the media picker modal if any file fields are present.
$hasFileFields = false;
foreach ($definitions as $def) {
    if (($def['type'] ?? '') === 'file') {
        $hasFileFields = true;
        break;
    }
}
if ($hasFileFields):
    $container = \CorporateAid\Core\App::instance()->container();
    $mediaRepo = $container->get(\CorporateAid\Commerce\Repositories\MediaRepository::class);
    $mediaLibrary = $mediaRepo->paginateForStore(
        $container->get(\CorporateAid\Commerce\Context\StoreContext::class)->id(),
        [],
        1,
        40
    )['items'];
    ?>
    <?= view_partial('admin/pages/_block-media-picker', ['mediaLibrary' => $mediaLibrary]) ?>
<?php endif; ?>
