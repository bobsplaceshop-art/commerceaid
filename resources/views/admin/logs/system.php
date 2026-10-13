<?php
/**
 * System Logs tab. Stage 014.
 *
 * Route: GET /commerce/ca-admin/logs/system
 *
 * Rendered into admin/logs/index.php by
 * LogController::showSystem().
 *
 * Context (from LogController::showSystem):
 *   $result   array{items,total,page,per_page,pages}
 *   $level    'all' | 'exception' | 'error' | 'warning' | 'info'
 *   $dateFrom string
 *   $dateTo   string
 *   $q        string
 *   $logPath  ?string  the resolved log file, or null if none
 *
 * Each item in $result['items'] is the shape LogReader
 * returns:
 *   raw, timestamp, epoch, level, message, context
 */

$result  = $result  ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0];
$level   = $level   ?? 'exception';
$dateFrom = $dateFrom ?? date('Y-m-d', time() - 86400);
$dateTo   = $dateTo   ?? '';
$q       = $q       ?? '';
$logPath = $logPath ?? null;

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$levelTabs = [
    'exception' => 'Exceptions',
    'error'     => 'Errors',
    'warning'   => 'Warnings',
    'info'      => 'Debug',
    'all'       => 'All',
];

$tabUrl = function (string $key) use ($q, $dateFrom, $dateTo): string {
    $params = ['level' => $key];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return '?' . http_build_query($params);
};

$pageUrl = function (int $p) use ($level, $q, $dateFrom, $dateTo): string {
    $params = ['level' => $level, 'page' => $p];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return '?' . http_build_query($params);
};

$levelBadge = function (?string $lvl): string {
    return match ($lvl) {
        'exception' => 'ra-badge ra-badge-danger',
        'error'     => 'ra-badge ra-badge-danger',
        'warning'   => 'ra-badge ra-badge-warning',
        'info'      => 'ra-badge ra-badge-muted',
        default     => 'ra-badge',
    };
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">System Logs</h1>
        <p class="muted mb-0">
            <?= number_format($total) ?>
            entr<?= $total === 1 ? 'y' : 'ies' ?>
            <?php if ($logPath === null): ?>
                &middot; <span class="text-danger">no log file found</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-3">
    <div class="ra-card-body py-2">
        <ul class="nav nav-pills gap-2 flex-wrap mb-0">
            <?php foreach ($levelTabs as $key => $label): ?>
                <li class="nav-item">
                    <a href="<?= e($tabUrl($key)) ?>"
                       class="ra-btn <?= $level === $key ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                        <?= e($label) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($level === 'info' || $level === 'all'): ?>
            <p class="muted small mb-0 mt-2">
                Debug entries include audit-trail lines (form.submitted,
                http 404) that are informational, not errors.
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body py-2">
        <form method="GET" action="/commerce/ca-admin/logs/system" class="d-flex gap-2 flex-wrap align-items-end" role="search">
            <input type="hidden" name="level" value="<?= e($level) ?>">

            <div>
                <label for="ra-log-q" class="form-label small mb-1">Search</label>
                <input type="search"
                       id="ra-log-q"
                       name="q"
                       value="<?= e($q) ?>"
                       placeholder="ERR-xxxxxx, request id, uri, message"
                       class="form-control form-control-sm"
                       style="min-width:280px;">
            </div>

            <div>
                <label for="ra-log-from" class="form-label small mb-1">From</label>
                <input type="date"
                       id="ra-log-from"
                       name="date_from"
                       value="<?= e($dateFrom) ?>"
                       class="form-control form-control-sm">
            </div>

            <div>
                <label for="ra-log-to" class="form-label small mb-1">To</label>
                <input type="date"
                       id="ra-log-to"
                       name="date_to"
                       value="<?= e($dateTo) ?>"
                       class="form-control form-control-sm">
            </div>

            <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <?php if ($q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="?level=<?= e($level) ?>" class="ra-btn ra-btn-outline ra-btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-journal-text" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($q !== '' || $dateFrom !== '' || $dateTo !== '' || $level !== 'all'): ?>
                <h2 class="h6 mt-3 mb-1">No entries match your filters</h2>
                <p class="muted small mb-3">Try a different search term or clear the filters.</p>
                <a href="/commerce/ca-admin/logs/system" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No log entries</h2>
                <p class="muted small mb-3">
                    Nothing has been logged yet, or the log file is empty.
                </p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th style="width:160px;">Timestamp</th>
                        <th style="width:120px;">Level</th>
                        <th>Message</th>
                        <th class="ra-hide-sm" style="width:240px;">Request</th>
                        <th class="ra-hide-sm" style="width:160px;">Request ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $entry): ?>
                        <?php
                        $context = $entry['context'] ?? [];
                        $message = $entry['message'] ?? '';
                        $uri     = (string) ($context['request_uri']    ?? '');
                        $method  = (string) ($context['request_method'] ?? '');
                        $reqId   = (string) ($context['request_id']     ?? '');
                        $ref     = (string) ($context['ref']            ?? '');
                        $rowId   = 'ra-log-row-' . $i;
                        ?>
                        <tr>
                            <td data-label="Timestamp" data-col="half">
                                <span class="muted small"><?= e((string) ($entry['timestamp'] ?? '')) ?></span>
                            </td>
                            <td data-label="Level" data-col="half">
                                <span class="<?= e($levelBadge($entry['level'] ?? null)) ?>">
                                    <?= e((string) ($entry['level'] ?? 'unknown')) ?>
                                </span>
                                <?php
                                $repeat = (int) ($entry['repeat_count'] ?? 1);
                                if ($repeat > 1):
                                ?>
                                    <span class="ra-badge ra-badge-muted ms-1"
                                          title="This exact line appeared <?= $repeat ?> times within one minute">
                                        &times;<?= $repeat ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Message" data-col="full">
                                <?php if ($ref !== ''): ?>
                                    <div class="mb-1">
                                        <code class="small"><?= e($ref) ?></code>
                                    </div>
                                <?php endif; ?>
                                <div style="word-break:break-word;">
                                    <?= e($message) ?>
                                </div>
                                <div class="mt-2">
                                    <button type="button"
                                            class="ra-btn ra-btn-outline ra-btn-sm"
                                            data-ra-log-toggle="<?= e($rowId) ?>">
                                        <i class="bi bi-chevron-down me-1"></i> Context
                                    </button>
                                </div>
                                <pre id="<?= e($rowId) ?>"
                                     style="display:none;margin:.75rem 0 0;padding:.75rem;background:var(--ra-surface-3);border-radius:6px;font-size:.78rem;overflow:auto;max-height:24rem;"><?= e((string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                            </td>
                            <td class="ra-hide-sm" data-label="Request" data-col="half">
                                <?php if ($uri !== ''): ?>
                                    <code class="small"><?= e($method) ?> <?= e($uri) ?></code>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm" data-label="Request ID" data-col="half">
                                <?php if ($reqId !== ''): ?>
                                    <code class="small" style="user-select:all;"><?= e($reqId) ?></code>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav aria-label="Pagination" class="mt-3">
            <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">&laquo;</a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);
                if ($start > 1) {
                    echo '<li class="page-item"><a class="page-link" href="' . e($pageUrl(1)) . '">1</a></li>';
                    if ($start > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                }
                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor;
                if ($end < $pages) {
                    if ($end < $pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="' . e($pageUrl($pages)) . '">' . $pages . '</a></li>';
                }
                ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">&raquo;</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    'use strict';
    document.querySelectorAll('[data-ra-log-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-ra-log-toggle');
            var pre = document.getElementById(id);
            if (!pre) return;
            var open = pre.style.display !== 'none';
            pre.style.display = open ? 'none' : 'block';
        });
    });
})();
</script>
