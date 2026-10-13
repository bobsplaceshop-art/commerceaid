<?php
/**
 * Audit Trail tab. Stage 014.
 *
 * Route: GET /commerce/ca-admin/logs/audit
 *
 * Rendered into admin/logs/index.php by
 * LogController::showAudit().
 *
 * Context (from LogController::showAudit):
 *   $result       array{items,total,page,per_page,pages}
 *   $counts       array{total,today,week,failed,system}
 *   $activeUsers  array<int,array{id,name,count}>
 *   $userId       int       (0 = all)
 *   $method       string    ('' = all)
 *   $path         string
 *   $entityType   string
 *   $dateFrom     string    YYYY-MM-DD
 *   $dateTo       string    YYYY-MM-DD
 *   $q            string
 *
 * Each row is one core_audit_logs row plus the joined
 * user_name. Fields: id, user_id, user_name, method,
 * path, ip_address, user_agent, request_hash,
 * response_status, entity_type, entity_id, created_at.
 */

$result      = $result      ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0];
$counts      = $counts      ?? ['total' => 0, 'today' => 0, 'week' => 0, 'failed' => 0, 'system' => 0];
$activeUsers = $activeUsers ?? [];
$userId      = (int) ($userId ?? 0);
$method      = (string) ($method ?? '');
$path        = (string) ($path ?? '');
$entityType  = (string) ($entityType ?? '');
$dateFrom    = (string) ($dateFrom ?? '');
$dateTo      = (string) ($dateTo ?? '');
$q           = (string) ($q ?? '');

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

// Quick filter tabs. Each tab is a preset combination of
// the query parameters. Clicking one replaces the current
// filters with the tab's own.
$today     = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-7 days'));

$tabUrl = function (array $overrides) use ($activeUsers): string {
    // Tab presets carry only what the tab needs.
    return '?' . http_build_query($overrides);
};

$tabActive = function (array $overrides) use ($userId, $method, $path, $entityType, $dateFrom, $dateTo, $q): bool {
    foreach ($overrides as $key => $value) {
        switch ($key) {
            case 'user_id':
                if ($userId !== (int) $value) return false;
                break;
            case 'method':
                if ($method !== (string) $value) return false;
                break;
            case 'date_from':
                if ($dateFrom !== (string) $value) return false;
                break;
            case 'date_to':
                if ($dateTo !== (string) $value) return false;
                break;
            case 'q':
                if ($q !== (string) $value) return false;
                break;
        }
    }
    // For "today" tab also require no other filters.
    return true;
};

$pageUrl = function (int $p) use ($userId, $method, $path, $entityType, $dateFrom, $dateTo, $q): string {
    $params = ['page' => $p];
    if ($userId > 0)       $params['user_id'] = $userId;
    if ($method !== '')    $params['method'] = $method;
    if ($path !== '')      $params['path'] = $path;
    if ($entityType !== '') $params['entity_type'] = $entityType;
    if ($dateFrom !== '')  $params['date_from'] = $dateFrom;
    if ($dateTo !== '')    $params['date_to'] = $dateTo;
    if ($q !== '')         $params['q'] = $q;
    return '?' . http_build_query($params);
};

$fmtWhen = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, H:i', $ts);
};

$methodBadge = function (string $m): string {
    return match (strtoupper($m)) {
        'POST'   => 'ra-badge ra-badge-success',
        'PUT'    => 'ra-badge',
        'PATCH'  => 'ra-badge ra-badge-warning',
        'DELETE' => 'ra-badge ra-badge-danger',
        default  => 'ra-badge ra-badge-muted',
    };
};

$statusBadge = function (?int $status): string {
    if ($status === null) {
        return '<span class="ra-badge ra-badge-muted">—</span>';
    }
    if ($status >= 500) {
        return '<span class="ra-badge ra-badge-danger">' . $status . '</span>';
    }
    if ($status >= 400) {
        return '<span class="ra-badge ra-badge-warning">' . $status . '</span>';
    }
    if ($status >= 200 && $status < 300) {
        return '<span class="ra-badge ra-badge-success">' . $status . '</span>';
    }
    return '<span class="ra-badge ra-badge-muted">' . $status . '</span>';
};

// Detect whether any filter is active (to show the Clear
// link).
$hasFilters = $userId > 0 || $method !== '' || $path !== '' || $entityType !== ''
            || $q !== '' || $dateFrom !== $today || $dateTo !== '';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Activity Log</h1>
        <p class="muted mb-0">
            <?= number_format($total) ?>
            entr<?= $total === 1 ? 'y' : 'ies' ?>
            <?php if ($hasFilters): ?>
                &middot; filtered
                <a href="/commerce/ca-admin/logs/audit" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-3">
    <div class="ra-card-body py-2">
        <ul class="nav nav-pills gap-2 flex-wrap mb-0">
            <li class="nav-item">
                <a href="<?= e($tabUrl(['date_from' => $today])) ?>"
                   class="ra-btn <?= ($dateFrom === $today && $userId === 0 && $method === '') ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                    Today
                    <span class="ra-badge ms-1" style="font-size:.7rem;"><?= number_format($counts['today']) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= e($tabUrl(['date_from' => $weekStart])) ?>"
                   class="ra-btn <?= ($dateFrom === $weekStart) ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                    This week
                    <span class="ra-badge ms-1" style="font-size:.7rem;"><?= number_format($counts['week']) ?></span>
                </a>
            </li>
            <li class="nav-item">
                <a href="?" class="ra-btn <?= !$hasFilters ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                    All time
                    <span class="ra-badge ms-1" style="font-size:.7rem;"><?= number_format($counts['total']) ?></span>
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body py-2">
        <form method="GET" action="/commerce/ca-admin/logs/audit" class="d-flex gap-2 flex-wrap align-items-end" role="search">
            <div>
                <label for="ra-act-user" class="form-label small mb-1">User</label>
                <select id="ra-act-user" name="user_id" class="form-select form-select-sm" style="min-width:180px;">
                    <option value="">All users</option>
                    <?php foreach ($activeUsers as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= $userId === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?> (<?= (int) $u['count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="ra-act-method" class="form-label small mb-1">Method</label>
                <select id="ra-act-method" name="method" class="form-select form-select-sm">
                    <option value="">Any</option>
                    <?php foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m): ?>
                        <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="ra-act-path" class="form-label small mb-1">Path</label>
                <input type="text"
                       id="ra-act-path"
                       name="path"
                       value="<?= e($path) ?>"
                       placeholder="/commerce/ca-admin/products"
                       class="form-control form-control-sm"
                       style="min-width:200px;">
            </div>

            <div>
                <label for="ra-act-from" class="form-label small mb-1">From</label>
                <input type="date"
                       id="ra-act-from"
                       name="date_from"
                       value="<?= e($dateFrom) ?>"
                       class="form-control form-control-sm">
            </div>

            <div>
                <label for="ra-act-to" class="form-label small mb-1">To</label>
                <input type="date"
                       id="ra-act-to"
                       name="date_to"
                       value="<?= e($dateTo) ?>"
                       class="form-control form-control-sm">
            </div>

            <div>
                <label for="ra-act-q" class="form-label small mb-1">Search</label>
                <input type="search"
                       id="ra-act-q"
                       name="q"
                       value="<?= e($q) ?>"
                       placeholder="Path or user agent"
                       class="form-control form-control-sm">
            </div>

            <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                <i class="bi bi-search me-1"></i> Filter
            </button>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-list-check" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($hasFilters): ?>
                <h2 class="h6 mt-3 mb-1">No activity matches your filters</h2>
                <p class="muted small mb-3">Try a different user or clear the date range.</p>
                <a href="/commerce/ca-admin/logs/audit" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No activity recorded</h2>
                <p class="muted small mb-3">
                    State-changing actions (create, edit, delete) will appear here.
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
                        <th style="width:130px;">When</th>
                        <th style="width:160px;">User</th>
                        <th class="ra-hide-sm" style="width:150px;">Store</th>
                        <th>Action</th>
                        <th class="ra-hide-sm" style="width:180px;">Entity</th>
                        <th style="width:90px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $userName = $row['user_name'] ?? null;
                        if ($userName === null || $userName === '') {
                            $userName = ($row['user_id'] ?? null) !== null
                                ? 'User #' . (int) $row['user_id']
                                : 'System';
                        }
                        $entityLabel = '';
                        if (!empty($row['entity_type'])) {
                            $entityLabel = (string) $row['entity_type'];
                            if (!empty($row['entity_id'])) {
                                $entityLabel .= ' #' . (int) $row['entity_id'];
                            }
                        }
                        $ua = (string) ($row['user_agent'] ?? '');
                        $ip = (string) ($row['ip_address'] ?? '');
                        $hash = (string) ($row['request_hash'] ?? '');
                        ?>
                        <tr>
                            <td data-label="When" data-col="half">
                                <span class="muted small"><?= e($fmtWhen($row['created_at'] ?? null)) ?></span>
                            </td>
                            <td data-label="User" data-col="half">
                                <?= e($userName) ?>
                            </td>
                            <td class="ra-hide-sm" data-label="Store" data-col="half">
                                <?php $storeName = (string) ($row['store_name'] ?? ''); ?>
                                <?= e($storeName !== '' ? $storeName : '—') ?>
                            </td>
                            <td data-label="Action" data-col="full">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="<?= e($methodBadge((string) ($row['method'] ?? ''))) ?>">
                                        <?= e((string) ($row['method'] ?? '')) ?>
                                    </span>
                                    <code class="small" style="word-break:break-all;"><?= e((string) ($row['path'] ?? '')) ?></code>
                                </div>
                                <?php if ($ua !== '' || $ip !== '' || $hash !== ''): ?>
                                    <details class="mt-2">
                                        <summary class="muted small" style="cursor:pointer;">Details</summary>
                                        <dl class="row small mt-2 mb-0">
                                            <?php if ($ip !== ''): ?>
                                                <dt class="col-3 muted">IP</dt>
                                                <dd class="col-9 mb-1"><?= e($ip) ?></dd>
                                            <?php endif; ?>
                                            <?php if ($hash !== ''): ?>
                                                <dt class="col-3 muted">Payload hash</dt>
                                                <dd class="col-9 mb-1"><code class="small"><?= e(substr($hash, 0, 16)) ?>…</code></dd>
                                            <?php endif; ?>
                                            <?php if ($ua !== ''): ?>
                                                <dt class="col-3 muted">User agent</dt>
                                                <dd class="col-9 mb-0" style="word-break:break-all;"><?= e($ua) ?></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </details>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm" data-label="Entity" data-col="half">
                                <?php if ($entityLabel !== ''): ?>
                                    <code class="small"><?= e($entityLabel) ?></code>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status" data-col="half">
                                <?= $statusBadge($row['response_status'] !== null ? (int) $row['response_status'] : null) ?>
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
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">&laquo;</a></li>
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
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>
