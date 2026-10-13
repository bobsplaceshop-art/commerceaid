<?php
/**
 * Logs hub shell.
 *
 * Wraps the sub-nav + the current tab's content. Each tab's
 * view is a separate file at admin/logs/{tab}.php, included
 * below.
 *
 * Context (from LogController::renderTab()):
 *   @var array  $tabMeta
 *   @var string $currentTab
 *
 * Plus the tab-specific data provided by the handler:
 *   overview:  activityCounts, auditCounts, systemCounts, lastActivity
 *   activity:  result, counts, activeModules, userId, module, dateFrom, dateTo, q
 *   system:    result, level, dateFrom, dateTo, q, logPath
 *   audit:     result, counts, activeUsers, userId, method, path, dateFrom, dateTo, q
 */
$tabMeta    = $tabMeta    ?? [];
$currentTab = $currentTab ?? 'overview';
?>
<div class="ra-setup-wrap">

    <?= view_partial('admin/logs/_nav', [
        'tabMeta'    => $tabMeta,
        'currentTab' => $currentTab,
    ]) ?>

    <div class="ra-setup-content">
        <?php
        $tabView = __DIR__ . '/' . $currentTab . '.php';
        if (is_file($tabView)) {
            require $tabView;
        } else {
            ?>
            <div class="ra-card">
                <div class="ra-card-body">
                    <h2 class="h6 mb-2">Coming soon</h2>
                    <p class="muted mb-0">
                        This log view is under construction.
                    </p>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

</div>
