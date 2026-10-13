<?php
/**
 * Admin submissions list. Stage 009 Session 4.
 *
 * Route: GET /commerce/ca-admin/forms/{id}/submissions
 *
 * Context (from AdminFormController::submissions):
 *   $form     Form
 *   $result   array{items, total, page, per_page, pages}
 *   $filter   string  'new' | 'read' | 'spam' | 'archived' | 'all'
 *   $q        string  search term matched against email and name
 *   $dateFrom string  YYYY-MM-DD or ''
 *   $dateTo   string  YYYY-MM-DD or ''
 *   $counts   array{new:int, read:int, spam:int, archived:int, total:int}
 */

$form     = $form     ?? null;
$result   = $result   ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$filter   = $filter   ?? 'new';
$q        = $q        ?? '';
$dateFrom = $dateFrom ?? '';
$dateTo   = $dateTo   ?? '';
$counts   = $counts   ?? ['new' => 0, 'read' => 0, 'spam' => 0, 'archived' => 0, 'total' => 0];

if ($form === null) {
    return;
}

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$base = '/commerce/ca-admin/forms/' . (int) $form->id . '/submissions';

$filterTabs = [
    'new'      => 'New',
    'read'     => 'Read',
    'spam'     => 'Spam',
    'archived' => 'Archived',
    'all'      => 'All',
];

$tabUrl = function (string $key) use ($base, $q, $dateFrom, $dateTo): string {
    $params = ['status' => $key];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return $base . '?' . http_build_query($params);
};

$pageUrl = function (int $p) use ($base, $filter, $q, $dateFrom, $dateTo): string {
    $params = ['status' => $filter, 'page' => $p];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return $base . '?' . http_build_query($params);
};

$exportUrl = function () use ($base, $filter, $q, $dateFrom, $dateTo): string {
    $params = ['status' => $filter];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return $base . '/export.csv?' . http_build_query($params);
};

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y H:i', $ts);
};

$badgeClass = function (string $variant): string {
    return match ($variant) {
        'success' => 'ra-badge ra-badge-success',
        'danger'  => 'ra-badge ra-badge-danger',
        'warning' => 'ra-badge ra-badge-warn',
        'info'    => 'ra-badge',
        'muted'   => 'ra-badge ra-badge-muted',
        default   => 'ra-badge',
    };
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0" style="font-size:.85rem;">
                <li class="breadcrumb-item"><a href="/commerce/ca-admin/forms">Forms</a></li>
                <li class="breadcrumb-item"><a href="/commerce/ca-admin/forms/<?= (int) $form->id ?>/edit"><?= e($form->name) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Submissions</li>
            </ol>
        </nav>
        <h1 class="h3 mb-1">Submissions</h1>
        <p class="muted mb-0">
            <?= $total ?> submission<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== ''): ?>
                matching &ldquo;<?= e($q) ?>&rdquo;
            <?php elseif ($dateFrom !== '' || $dateTo !== ''): ?>
                in the selected date range
            <?php else: ?>
                in <?= e(strtolower($filterTabs[$filter] ?? $filter)) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e($exportUrl()) ?>" class="ra-btn ra-btn-outline">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <a href="/commerce/ca-admin/forms/<?= (int) $form->id ?>/edit" class="ra-btn ra-btn-outline">
            <i class="bi bi-arrow-left me-1"></i> Back to form
        </a>
    </div>
</div>

<div class="ra-card mb-3">
    <div class="ra-card-body py-2">
        <ul class="nav nav-pills gap-2 flex-wrap mb-0">
            <?php foreach ($filterTabs as $key => $label): ?>
                <?php
                $isActive = $filter === $key;
                $count    = $key === 'all' ? (int) $counts['total'] : (int) ($counts[$key] ?? 0);
                ?>
                <li class="nav-item">
                    <a href="<?= e($tabUrl($key)) ?>"
                       class="ra-btn <?= $isActive ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                        <?= e($label) ?>
                        <span class="ra-badge ms-1" style="font-size:.7rem;"><?= $count ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body py-2">
        <form method="GET" action="<?= e($base) ?>" class="d-flex gap-2 flex-wrap align-items-end" role="search">
            <input type="hidden" name="status" value="<?= e($filter) ?>">

            <div>
                <label for="ra-sub-q" class="form-label small mb-1">Search</label>
                <input type="search"
                       id="ra-sub-q"
                       name="q"
                       value="<?= e($q) ?>"
                       placeholder="Email or name"
                       class="form-control form-control-sm"
                       style="max-width:220px;">
            </div>

            <div>
                <label for="ra-sub-from" class="form-label small mb-1">From</label>
                <input type="date"
                       id="ra-sub-from"
                       name="date_from"
                       value="<?= e($dateFrom) ?>"
                       class="form-control form-control-sm">
            </div>

            <div>
                <label for="ra-sub-to" class="form-label small mb-1">To</label>
                <input type="date"
                       id="ra-sub-to"
                       name="date_to"
                       value="<?= e($dateTo) ?>"
                       class="form-control form-control-sm">
            </div>

            <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <?php if ($q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="<?= e($base) ?>?status=<?= e($filter) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <h2 class="h6 mt-3 mb-1">No submissions match your filters</h2>
                <p class="muted small mb-3">
                    Try a different search term or clear the date range.
                </p>
                <a href="<?= e($base) ?>?status=<?= e($filter) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No submissions in this view</h2>
                <p class="muted small mb-3">
                    <?php if ($filter === 'new'): ?>
                        New submissions from the public form will appear here.
                    <?php else: ?>
                        Try a different tab.
                    <?php endif; ?>
                </p>
                <?php if ($filter !== 'all'): ?>
                    <a href="<?= e($base) ?>?status=all" class="ra-btn ra-btn-outline">View all submissions</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>

    <form method="POST" action="<?= e($base) ?>/bulk" id="ra-submissions-bulk-form">
        <?= csrf_field() ?>

        <div class="ra-card mb-2 d-none" id="ra-bulk-bar">
            <div class="ra-card-body py-2 d-flex align-items-center gap-2 flex-wrap">
                <span class="muted small" id="ra-bulk-count">0 selected</span>
                <div class="ms-auto d-flex gap-2">
                    <button type="submit" name="action" value="mark_read" class="ra-btn ra-btn-outline ra-btn-sm">
                        <i class="bi bi-envelope-open me-1"></i> Mark read
                    </button>
                    <button type="submit" name="action" value="mark_spam" class="ra-btn ra-btn-outline ra-btn-sm">
                        <i class="bi bi-shield-exclamation me-1"></i> Mark spam
                    </button>
                    <button type="submit" name="action" value="archive" class="ra-btn ra-btn-outline ra-btn-sm">
                        <i class="bi bi-archive me-1"></i> Archive
                    </button>
                    <button type="submit" name="action" value="delete" class="ra-btn ra-btn-danger ra-btn-sm"
                            onclick="return confirm('Delete the selected submissions? This cannot be undone.');">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>

        <div class="ra-card">
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" id="ra-select-all" aria-label="Select all">
                            </th>
                            <th style="width:70px;">#</th>
                            <th class="ra-hide-sm" style="width:180px;">Submitted</th>
                            <th style="width:200px;">From</th>
                            <th class="ra-hide-sm" style="width:240px;">Email</th>
                            <th style="width:110px;">Status</th>
                            <th style="width:100px;text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $s): ?>
                            <tr>
                                <td>
                                    <input type="checkbox"
                                           name="ids[]"
                                           value="<?= (int) $s->id ?>"
                                           class="ra-row-checkbox"
                                           aria-label="Select submission <?= (int) $s->id ?>">
                                </td>
                                <td>
                                    <a href="<?= e($base) ?>/<?= (int) $s->id ?>" class="text-decoration-none">
                                        #<?= (int) $s->id ?>
                                    </a>
                                </td>
                                <td class="ra-hide-sm">
                                    <span class="muted small"><?= e($fmtDateTime($s->createdAt)) ?></span>
                                </td>
                                <td>
                                    <?= e((string) ($s->submitterName ?? '—')) ?>
                                </td>
                                <td class="ra-hide-sm">
                                    <span class="muted small"><?= e((string) ($s->submitterEmail ?? '—')) ?></span>
                                </td>
                                <td>
                                    <span class="<?= e($badgeClass($s->statusBadgeVariant())) ?>">
                                        <?= e($s->statusLabel()) ?>
                                    </span>
                                </td>
                                <td class="text-end ra-actions-cell">
                                    <a href="<?= e($base) ?>/<?= (int) $s->id ?>"
                                       class="ra-icon-btn"
                                       title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

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

    var selectAll = document.getElementById('ra-select-all');
    var checkboxes = document.querySelectorAll('.ra-row-checkbox');
    var bulkBar = document.getElementById('ra-bulk-bar');
    var bulkCount = document.getElementById('ra-bulk-count');

    if (!selectAll || !checkboxes.length || !bulkBar) return;

    function updateBar() {
        var checked = document.querySelectorAll('.ra-row-checkbox:checked').length;
        if (checked > 0) {
            bulkBar.classList.remove('d-none');
            bulkCount.textContent = checked + ' selected';
        } else {
            bulkBar.classList.add('d-none');
        }
    }

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
        updateBar();
    });

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', updateBar);
    });

    updateBar();
})();
</script>
