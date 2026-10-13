<?php
/**
 * Subscribers list. Stage 008I.
 *
 * Context (from AdminSubscriberController::index):
 *   $result  paginate() return
 *   $tab     'all' | 'confirmed' | 'pending' | 'unsubscribed' | 'suppressed'
 *   $source  '' | 'footer' | 'home' | 'account' | 'admin' | 'import'
 *   $q       string
 *   $counts  array{all:int, confirmed:int, pending:int, unsubscribed:int, suppressed:int}
 *   $lists   array<int, SubscriberList>
 */

$result = $result ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$tab    = $tab    ?? 'all';
$source = $source ?? '';
$q      = $q      ?? '';
$counts = $counts ?? ['all' => 0, 'confirmed' => 0, 'pending' => 0, 'unsubscribed' => 0, 'suppressed' => 0];
$lists  = $lists  ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$tabs = [
    'all'          => 'All',
    'confirmed'    => 'Confirmed',
    'pending'      => 'Pending',
    'unsubscribed' => 'Unsubscribed',
    'suppressed'   => 'Suppressed',
];

$sources = [
    ''         => 'All sources',
    'footer'   => 'Footer form',
    'home'     => 'Home page form',
    'account'  => 'Account preferences',
    'admin'    => 'Admin manual',
    'import'   => 'Import',
];

$tabUrl = function (string $key) use ($q, $source): string {
    $params = ['status' => $key];
    if ($q !== '')      $params['q'] = $q;
    if ($source !== '') $params['source'] = $source;
    return '?' . http_build_query($params);
};

$pageUrl = function (int $p) use ($tab, $q, $source): string {
    $params = ['status' => $tab, 'page' => $p];
    if ($q !== '')      $params['q'] = $q;
    if ($source !== '') $params['source'] = $source;
    return '?' . http_build_query($params);
};

$exportUrl = function () use ($tab, $q, $source): string {
    $params = ['status' => $tab];
    if ($q !== '')      $params['q'] = $q;
    if ($source !== '') $params['source'] = $source;
    return '/commerce/ca-admin/subscribers/export?' . http_build_query($params);
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
        <h1 class="h3 mb-1">Subscribers</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> subscriber<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== '' || $source !== '' || $tab !== 'all'): ?>
                &middot; filtered
                <a href="/commerce/ca-admin/subscribers" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e($exportUrl()) ?>" class="ra-btn ra-btn-outline">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
        <a href="/commerce/ca-admin/subscriber-lists" class="ra-btn ra-btn-outline">
            <i class="bi bi-list-ul me-1"></i> Lists
        </a>
    </div>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="Subscriber status">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e($tabUrl($key)) ?>"
               class="ra-admin-tab<?= $tab === $key ? ' is-active' : '' ?>">
                <?= e($label) ?>
                <span class="ra-admin-tab-count">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <button type="button" class="ra-filter-toggle" data-ra-filter-toggle>
            <i class="bi bi-funnel"></i> Filters
        </button>
        <form method="GET" action="/commerce/ca-admin/subscribers" class="row g-2 align-items-end ra-filter-bar">
            <input type="hidden" name="status" value="<?= e($tab) ?>">
            <div class="col-md-5">
                <label class="muted small" for="sub-q">Search</label>
                <input type="text" id="sub-q" name="q" class="form-control"
                       placeholder="Email or name"
                       value="<?= e($q) ?>">
            </div>
            <div class="col-md-4">
                <label class="muted small" for="sub-source">Source</label>
                <select id="sub-source" name="source" class="form-select">
                    <?php foreach ($sources as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $source === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="ra-btn ra-btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-envelope-paper" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?php if ($q !== '' || $source !== '' || $tab !== 'all'): ?>
                    No subscribers match these filters
                <?php else: ?>
                    No subscribers yet
                <?php endif; ?>
            </h2>
            <p class="muted small mb-3">
                <?php if ($q !== '' || $source !== '' || $tab !== 'all'): ?>
                    Try clearing filters or adjusting your search.
                <?php else: ?>
                    Subscribers appear here when customers sign up from the storefront.
                <?php endif; ?>
            </p>
            <?php if ($q !== '' || $source !== '' || $tab !== 'all'): ?>
                <a href="/commerce/ca-admin/subscribers" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Email</th>
                        <th class="ra-hide-sm" style="width:180px;">Name</th>
                        <th style="width:120px;">Status</th>
                        <th class="ra-hide-sm" style="width:140px;">Source</th>
                        <th class="ra-hide-sm" style="width:160px;">Subscribed</th>
                        <th style="width:100px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $s): ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/subscribers/<?= (int) $s->id ?>"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($s->email) ?>
                                </a>
                            </td>
                            <td class="ra-hide-sm">
                                <?= e($s->name ?? '') ?>
                            </td>
                            <td>
                                <span class="<?= e($badgeClass($s->statusBadgeVariant())) ?>">
                                    <?= e($s->statusLabel()) ?>
                                </span>
                            </td>
                            <td class="ra-hide-sm muted small">
                                <?= e($s->consentSource !== null && $s->consentSource !== ''
                                    ? ($sources[$s->consentSource] ?? $s->consentSource)
                                    : '—') ?>
                            </td>
                            <td class="ra-hide-sm muted small">
                                <?= e($fmtDateTime($s->consentAt ?? $s->createdAt)) ?>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/subscribers/<?= (int) $s->id ?>"
                                   class="ra-icon-btn" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-3 d-flex gap-2 justify-content-center" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e($pageUrl($page - 1)) ?>" class="ra-btn ra-btn-outline">&larr; Previous</a>
            <?php endif; ?>
            <span class="ra-btn ra-btn-outline" style="cursor:default;">
                Page <?= (int) $page ?> of <?= (int) $pages ?>
            </span>
            <?php if ($page < $pages): ?>
                <a href="<?= e($pageUrl($page + 1)) ?>" class="ra-btn ra-btn-outline">Next &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
