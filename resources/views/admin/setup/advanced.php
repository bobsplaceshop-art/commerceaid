<?php
/**
 * Setup tab: Advanced.
 *
 * Groups the less-used / dangerous settings: API keys, webhooks,
 * feature flags, demo data reset, and the danger zone.
 *
 * Form only for now. All sections are stubs.
 *
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';
?>

<form method="POST" action="/commerce/ca-admin/setup/advanced" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-alert ra-alert-info mb-4">
        <i class="bi bi-info-circle me-1"></i>
        This section is under construction. Settings are accepted but not yet persisted.
        Advanced configuration ships in a future stage.
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">API access</h2>
        </div>
        <div class="ra-card-body">
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="api_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="api_enabled" name="api_enabled" value="1">
                    <label class="form-check-label" for="api_enabled">
                        <strong>Enable REST API</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Allows external applications to read and write store data
                    via <code>/api/v1/commerce/*</code>.
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label">API keys</label>
                <p class="muted small mb-0">
                    API keys will be generated and managed here once the API layer
                    ships. Each key will have scoped permissions.
                </p>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Webhooks</h2>
        </div>
        <div class="ra-card-body">
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="hidden" name="webhooks_enabled" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="webhooks_enabled" name="webhooks_enabled" value="1">
                    <label class="form-check-label" for="webhooks_enabled">
                        <strong>Send outbound webhooks</strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Notifies external systems (ERP, CRM, logistics) when events
                    such as <code>order.created</code> or <code>product.updated</code> occur.
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label">Endpoints</label>
                <p class="muted small mb-0">
                    Webhook endpoints and event subscriptions will be managed here.
                    Delivery logs and retry handling included.
                </p>
            </div>
        </div>
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Feature flags</h2>
        </div>
        <div class="ra-card-body">
            <p class="muted small mb-0">
                Toggle experimental features on or off. Flags are stored in
                <code>core_feature_flags</code> and can be scoped globally
                or per-store.
            </p>
        </div>
    </div>

    <div class="ra-card mb-4 ra-card-danger">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Danger zone</h2>
        </div>
        <div class="ra-card-body">
            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <div class="fw-medium">Reset demo data</div>
                    <div class="muted small">
                        Removes all products, orders, and customers created during
                        testing. Business configuration is preserved.
                    </div>
                </div>
                <button type="button" class="ra-btn ra-btn-outline-danger" disabled>
                    Reset demo data
                </button>
            </div>

            <hr class="my-3">

            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                <div>
                    <div class="fw-medium">Delete store</div>
                    <div class="muted small">
                        Permanently removes this store and all data associated with it.
                        This cannot be undone.
                    </div>
                </div>
                <button type="button" class="ra-btn ra-btn-outline-danger" disabled>
                    Delete store
                </button>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save preferences</button>
    </div>
</form>

<style>
.ra-card-danger {
    border-color: rgba(220, 38, 38, 0.35) !important;
}
.ra-card-danger .ra-card-header {
    border-bottom-color: rgba(220, 38, 38, 0.25) !important;
}
.ra-card-danger .ra-card-header h2 {
    color: #dc2626;
}
</style>
