<?php
/**
 * Activity Log tab. Stage 014.
 *
 * The merchant-facing timeline. One human sentence per row.
 * Reads core_activities. Distinct from the Audit Trail
 * (raw HTTP) and System Logs (developer log).
 *
 * Context (from LogController::showActivity):
 *   $result         array{items,total,page,per_page,pages}
 *   $counts         array{total,today,week}
 *   $activeModules  array<int,array{module:string,count:int}>
 *   $userId         int
 *   $module         string
 *   $dateFrom       string
 *   $dateTo         string
 *   $q              string
 *
 * Each item is a core_activities row joined with the user
 * name: id, user_id, user_name, action, module,
 * subject_type, subject_id, description, meta, ip_address,
 * ip_address_text, created_at.
 */

$result        = $result        ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'pages' => 0];
$counts        = $counts        ?? ['total' => 0, 'today' => 0, 'week' => 0];
$activeModules = $activeModules ?? [];
$userId        = (int) ($userId ?? 0);
$module        = (string) ($module ?? '');
$dateFrom      = (string) ($dateFrom ?? '');
$dateTo        = (string) ($dateTo ?? '');
$q             = (string) ($q ?? '');

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$today     = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-7 days'));

$fmtWhen = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    if ($ts === false) return '—';
    $delta = time() - $ts;
    if ($delta < 60)    return 'just now';
    if ($delta < 3600)  return (int) floor($delta / 60) . 'm ago';
    if ($delta < 86400) return (int) floor($delta / 3600) . 'h ago';
    return date('M j, H:i', $ts);
};

$pageUrl = function (int $p) use ($userId, $module, $dateFrom, $dateTo, $q): string {
    $params = ['page' => $p];
    if ($userId > 0)      $params['user_id'] = $userId;
    if ($module !== '')   $params['module'] = $module;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    if ($q !== '')        $params['q'] = $q;
    return '?' . http_build_query($params);
};

$tabUrl = function (array $overrides): string {
    return '?' . http_build_query($overrides);
};

$hasFilters = $userId > 0 || $module !== '' || $q !== ''
            || $dateFrom !== $today || $dateTo !== '';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Activity Log</h1>
        <p class="muted mb-0">
            <?= number_format($total) ?>
            entr<?= $total === 1 ? 'y' : 'ies' ?>
            <?php if ($hasFilters): ?>
                &middot; filtered
                <a href="/commerce/ca-admin/logs/activity" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-3">
    <div class="ra-card-body py-2">
        <ul class="nav nav-pills gap-2 flex-wrap mb-0">
            <li class="nav-item">
                <a href="<?= e($tabUrl(['date_from' => $today])) ?>"
                   class="ra-btn <?= ($dateFrom === $today && $userId === 0 && $module === '') ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
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
        <form method="GET" action="/commerce/ca-admin/logs/activity" class="d-flex gap-2 flex-wrap align-items-end" role="search">
            <div>
                <label for="ra-act-module" class="form-label small mb-1">Module</label>
                <select id="ra-act-module" name="module" class="form-select form-select-sm" style="min-width:180px;">
                    <option value="">All modules</option>
                    <?php foreach ($activeModules as $m): ?>
                        <option value="<?= e($m['module']) ?>" <?= $module === $m['module'] ? 'selected' : '' ?>>
                            <?= e($m['module']) ?> (<?= (int) $m['count'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
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
                       placeholder="Description contains..."
                       class="form-control form-control-sm"
                       style="min-width:220px;">
            </div>

            <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <?php if ($hasFilters): ?>
                <a href="/commerce/ca-admin/logs/activity" class="ra-btn ra-btn-outline ra-btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-clock-history" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($hasFilters): ?>
                <h2 class="h6 mt-3 mb-1">No activity matches your filters</h2>
                <p class="muted small mb-3">Try a different module or clear the date range.</p>
                <a href="/commerce/ca-admin/logs/activity" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No activity recorded yet</h2>
                <p class="muted small mb-3">
                    When admins create, edit, or delete records,
                    those actions will appear here as a human-readable
                    timeline.
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
                        <th>What happened</th>
                        <th class="ra-hide-sm" style="width:120px;">Module</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $userName = (string) ($row['user_name'] ?? '');
                        if ($userName === '') {
                            $uid = $row['user_id'] ?? null;
                            $userName = $uid !== null ? 'User #' . (int) $uid : 'System';
                        }
                        $desc   = (string) ($row['description'] ?? '');
                        $moduleLabel = (string) ($row['module'] ?? '');
                        $subjectType = (string) ($row['subject_type'] ?? '');
                        $subjectId   = $row['subject_id'] ?? null;
                        $meta = $row['meta'] ?? null;
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
                            <td data-label="What happened" data-col="full">
                                <div><?= e($desc !== '' ? $desc : $row['action'] ?? '') ?></div>
                                <?php if ($subjectType !== '' && $subjectId !== null): ?>
                                    <div class="muted small mt-1">
                                        <code class="small"><?= e($subjectType) ?> #<?= (int) $subjectId ?></code>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm" data-label="Module" data-col="half">
                                <?php if ($moduleLabel !== ''): ?>
                                    <span class="ra-badge ra-badge-muted"><?= e($moduleLabel) ?></span>
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
