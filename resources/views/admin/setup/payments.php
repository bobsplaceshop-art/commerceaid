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


