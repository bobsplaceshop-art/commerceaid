<?php
/**
 * Setup hub shell.
 *
 * Wraps the sub-nav + the current tab's content. Each tab's view
 * is a separate file at admin/setup/{tab}.php, included below.
 *
 * Context (from SetupController::renderTab()):
 *   @var array  $store
 *   @var array  $missing         Mandatory fields still missing (keys)
 *   @var array  $missingLabels   Human-readable labels for missing
 *   @var array  $tabMeta
 *   @var string $currentTab
 *   @var bool   $setupComplete
 *   @var string $csrf
 *   @var array  $errors          Set by the controller on validation failure
 *   @var array  $old             Previous input for re-population
 */
$store         = $store         ?? [];
$missing       = $missing       ?? [];
$missingLabels = $missingLabels ?? [];
$tabMeta       = $tabMeta       ?? [];
$currentTab    = $currentTab    ?? 'business';
$setupComplete = $setupComplete ?? false;
$csrf          = $csrf          ?? '';
$errors        = $errors        ?? [];
$old           = $old           ?? [];
?>
<div class="ra-setup-wrap">

    <?= view_partial('admin/setup/_nav', [
        'tabMeta'       => $tabMeta,
        'currentTab'    => $currentTab,
        'missing'       => $missing,
        'setupComplete' => $setupComplete,
    ]) ?>

    <div class="ra-setup-content">

        <?php if (!$setupComplete && $missingLabels !== []): ?>
            <div class="ra-alert ra-alert-warning mb-4">
                <strong>Still needed before launch:</strong>
                <?= e(implode(' · ', $missingLabels)) ?>
            </div>
        <?php endif; ?>

        <?php
        // Include the current tab's view file. The tab view is
        // responsible for its own form + save button. Errors and old
        // input are already in scope ($errors, $old, $csrf).
        $tabView = __DIR__ . '/' . $currentTab . '.php';
        if (is_file($tabView)) {
            require $tabView;
        } else {
            ?>
            <div class="ra-card">
                <div class="ra-card-body">
                    <h2 class="h6 mb-2">Coming soon</h2>
                    <p class="muted mb-0">
                        This section is under construction and will be
                        available in a future update.
                    </p>
                </div>
            </div>
            <?php
        }
        ?>

    </div>
</div>
