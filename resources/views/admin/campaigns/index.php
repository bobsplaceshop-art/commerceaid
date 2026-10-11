<?php
/**
 * Campaigns list. Stage 008I.
 *
 * Context:
 *   $result array{items,total,page,per_page,pages}
 *   $tab    'all' | 'draft' | 'sending' | 'sent' | 'cancelled'
 *   $q      string
 *   $counts array{all:int,draft:int,sending:int,sent:int,cancelled:int}
 */
$result = $result ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$tab    = $tab    ?? 'all';
$q      = $q      ?? '';
$counts = $counts ?? ['all' => 0, 'draft' => 0, 'sending' => 0, 'sent' => 0, 'cancelled' => 0];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$tabs = [
    'all'       => 'All',
    'draft'     => 'Drafts',
    'sending'   => 'Sending',
    'sent'      => 'Sent',
    'cancelled' => 'Cancelled',
];

$tabUrl = function (string $key) use ($q): string {
    $params = ['status' => $key];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
};

$pageUrl = function (int $p) use ($tab, $q): string {
    $params = ['status' => $tab, 'page' => $p];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
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
        <h1 class="h3 mb-1">Campaigns</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> campaign<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                &middot; filtered
                <a href="/commerce/ca-admin/campaigns" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
    <a href="/commerce/ca-admin/campaigns/create" class="ra-btn ra-btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New campaign
    </a>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="Campaign status">
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
        <form method="GET" action="/commerce/ca-admin/campaigns" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($tab) ?>">
            <div class="col-md-9">
                <label class="muted small" for="camp-q">Search</label>
                <input type="text" id="camp-q" name="q" class="form-control"
                       placeholder="Subject"
                       value="<?= e($q) ?>">
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
            <i class="bi bi-envelope-at" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?php if ($q !== '' || $tab !== 'all'): ?>
                    No campaigns match these filters
                <?php else: ?>
                    No campaigns yet
                <?php endif; ?>
            </h2>
            <p class="muted small mb-3">
                <?php if ($q !== '' || $tab !== 'all'): ?>
                    Try clearing filters or adjusting your search.
                <?php else: ?>
                    Compose a campaign, choose a subscriber list, and send.
                <?php endif; ?>
            </p>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                <a href="/commerce/ca-admin/campaigns" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <a href="/commerce/ca-admin/campaigns/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New campaign
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th class="ra-hide-sm" style="width:140px;">Status</th>
                        <th style="width:120px;text-align:right;">Sent</th>
                        <th class="ra-hide-sm" style="width:160px;">Created</th>
                        <th style="width:110px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $c): ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/campaigns/<?= (int) $c->id ?>"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($c->subject) ?>
                                </a>
                                <?php if ($c->preheader !== null && $c->preheader !== ''): ?>
                                    <div class="muted small"><?= e($c->preheader) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm">
                                <span class="<?= e($badgeClass($c->statusBadgeVariant())) ?>">
                                    <?= e($c->statusLabel()) ?>
                                </span>
                            </td>
                            <td style="text-align:right;"><?= (int) $c->sentCount ?></td>
                            <td class="ra-hide-sm muted small">
                                <?= e($fmtDateTime($c->createdAt)) ?>
                            </td>
                            <td class="text-end">
                                <?php if ($c->canEdit()): ?>
                                    <a href="/commerce/ca-admin/campaigns/<?= (int) $c->id ?>/edit"
                                       class="ra-icon-btn" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                                <a href="/commerce/ca-admin/campaigns/<?= (int) $c->id ?>"
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
