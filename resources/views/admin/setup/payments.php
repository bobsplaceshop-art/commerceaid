<?php
/**
 * Setup tab: Payments.
 *
 * Form only for now. Save is a no-op until the payment provider
 * infrastructure lands (Stage 008). Provider list is static.
 *
 * @var array  $providers
 * @var array  $errors
 * @var array  $old
 * @var string $csrf
 */
$providers = $providers ?? [];
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$csrf      = $csrf      ?? '';
$enabled   = $old['providers'] ?? [];
?>

<form method="POST" action="/commerce/ca-admin/setup/payments" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="ra-alert ra-alert-info mb-4">
        <i class="bi bi-info-circle me-1"></i>
        This section is under construction. Settings are accepted but not yet persisted.
        Full payment provider integration ships in a future stage.
    </div>

    <div class="ra-card mb-4">
        <div class="ra-card-header">
            <h2 class="h6 mb-0">Payment providers</h2>
        </div>
        <div class="ra-card-body">
            <p class="muted small mb-3">
                Enable the providers your customers can use at checkout. Each provider
                will need its own credentials configured once the integration is live.
            </p>

            <div class="ra-payment-grid">
                <?php foreach ($providers as $p): ?>
                    <?php
                    $key = (string) $p['key'];
                    $isEnabled = in_array($key, $enabled, true);
                    ?>
                    <div class="ra-payment-card">
                        <div class="ra-payment-icon">
                            <i class="bi <?= e($p['icon']) ?>"></i>
                        </div>
                        <div class="ra-payment-body">
                            <div class="ra-payment-name"><?= e($p['label']) ?></div>
                            <div class="ra-payment-desc"><?= e($p['desc']) ?></div>
                        </div>
                        <div class="ra-payment-toggle">
                            <div class="form-check form-switch mb-0">
                                <input type="hidden" name="providers[]" value="">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="provider_<?= e($key) ?>"
                                       name="providers[]" value="<?= e($key) ?>"
                                       <?= $isEnabled ? 'checked' : '' ?>>
                                <label class="form-check-label visually-hidden"
                                       for="provider_<?= e($key) ?>"><?= e($p['label']) ?></label>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="ra-btn ra-btn-primary">Save preferences</button>
    </div>
</form>

<style>
.ra-payment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 0.75rem;
}
.ra-payment-card {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.85rem 1rem;
    border: 1px solid var(--ra-border, rgba(148,163,184,.15));
    border-radius: 10px;
    background: var(--ra-card-bg, transparent);
}
.ra-payment-icon {
    flex: 0 0 auto;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    background: var(--ra-hover, rgba(148,163,184,.1));
    color: var(--ra-fg, inherit);
    font-size: 1.15rem;
}
.ra-payment-body { flex: 1; min-width: 0; }
.ra-payment-name { font-weight: 500; font-size: .92rem; }
.ra-payment-desc { font-size: .78rem; color: var(--ra-muted, #94a3b8); margin-top: 1px; }
.ra-payment-toggle { flex: 0 0 auto; }
</style>
