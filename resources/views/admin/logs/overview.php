<?php
/**
 * Logs Overview tab. Stage 014.
 *
 * The merchant's landing page for the Logs hub. Three
 * numbers from three streams, one "last action" line, and
 * quick links to the detail tabs.
 *
 * Context (from LogController::showOverview):
 *   $activityCounts  array{total, today, week}
 *   $auditCounts     array{total, today, week, failed, system}
 *   $systemCounts    array{exceptions_today, errors_today, total_today}
 *   $lastActivity    array<string,mixed>|null
 */

$activityCounts = $activityCounts ?? ['total' => 0, 'today' => 0, 'week' => 0];
$auditCounts    = $auditCounts    ?? ['total' => 0, 'today' => 0, 'week' => 0, 'failed' => 0, 'system' => 0];
$systemCounts   = $systemCounts   ?? ['exceptions_today' => 0, 'errors_today' => 0, 'total_today' => 0];
$lastActivity   = $lastActivity   ?? null;

$fmtWhen = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    if ($ts === false) return '—';
    $delta = time() - $ts;
    if ($delta < 60)    return 'just now';
    if ($delta < 3600)  return (int) floor($delta / 60) . ' minutes ago';
    if ($delta < 86400) return (int) floor($delta / 3600) . ' hours ago';
    return date('M j, H:i', $ts);
};

$hasProblems = ($systemCounts['exceptions_today'] > 0 || $systemCounts['errors_today'] > 0);
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Logs</h1>
        <p class="muted mb-0">
            Four views of what has happened in the store.
            The <strong>Activity Log</strong> is the merchant
            timeline. <strong>System Logs</strong> and
            <strong>Audit Trail</strong> are developer surfaces.
        </p>
    </div>
</div>

<div class="row g-3 mb-3">

    <div class="col-md-4">
        <div class="ra-card h-100">
            <div class="ra-card-body d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0">Activity</h2>
                    <i class="bi bi-list-check muted"></i>
                </div>
                <div style="font-size:1.75rem;font-weight:600;line-height:1.1;">
                    <?= number_format($activityCounts['today']) ?>
                </div>
                <p class="muted small mb-3">actions today</p>
                <div class="d-flex justify-content-between muted small">
                    <span>This week: <?= number_format($activityCounts['week']) ?></span>
                    <span>Total: <?= number_format($activityCounts['total']) ?></span>
                </div>
                <a href="/commerce/ca-admin/logs/activity"
                   class="ra-btn ra-btn-outline ra-btn-sm w-100 mt-auto pt-3">
                    Open Activity Log
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="ra-card h-100">
            <div class="ra-card-body d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0">System</h2>
                    <i class="bi bi-journal-text muted"></i>
                </div>
                <div style="font-size:1.75rem;font-weight:600;line-height:1.1;<?= $hasProblems ? 'color:var(--ra-error-fg,#dc2626);' : '' ?>">
                    <?= number_format($systemCounts['exceptions_today'] + $systemCounts['errors_today']) ?>
                </div>
                <p class="muted small mb-3">exceptions and errors today</p>
                <div class="d-flex justify-content-between muted small">
                    <span>Exceptions: <?= number_format($systemCounts['exceptions_today']) ?></span>
                    <span>Errors: <?= number_format($systemCounts['errors_today']) ?></span>
                </div>
                <a href="/commerce/ca-admin/logs/system"
                   class="ra-btn ra-btn-outline ra-btn-sm w-100 mt-auto pt-3">
                    Open System Logs
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="ra-card h-100">
            <div class="ra-card-body d-flex flex-column">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0">Audit Trail</h2>
                    <i class="bi bi-shield-check muted"></i>
                </div>
                <div style="font-size:1.75rem;font-weight:600;line-height:1.1;">
                    <?= number_format($auditCounts['today']) ?>
                </div>
                <p class="muted small mb-3">state-changing requests today</p>
                <div class="d-flex justify-content-between muted small">
                    <span>This week: <?= number_format($auditCounts['week']) ?></span>
                    <span>Total: <?= number_format($auditCounts['total']) ?></span>
                </div>
                <a href="/commerce/ca-admin/logs/audit"
                   class="ra-btn ra-btn-outline ra-btn-sm w-100 mt-auto pt-3">
                    Open Audit Trail
                </a>
            </div>
        </div>
    </div>

</div>

<?php if ($lastActivity !== null): ?>
    <?php
    $desc = (string) ($lastActivity['description'] ?? '');
    $when = $fmtWhen($lastActivity['created_at'] ?? null);
    $who  = (string) ($lastActivity['user_name'] ?? 'System');
    ?>
    <div class="ra-card">
        <div class="ra-card-body">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <div class="muted small mb-1">Last activity</div>
                    <div><?= e($desc) ?></div>
                    <div class="muted small mt-1">
                        by <?= e($who) ?> &middot; <?= e($when) ?>
                    </div>
                </div>
                <a href="/commerce/ca-admin/logs/activity" class="ra-btn ra-btn-outline ra-btn-sm">
                    See all activity
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-4">
            <i class="bi bi-clock-history muted" style="font-size:2rem;"></i>
            <p class="muted small mb-0 mt-2">
                No activity has been recorded yet. Once admin
                actions start writing to the activity log, they
                will appear here.
            </p>
        </div>
    </div>
<?php endif; ?>
