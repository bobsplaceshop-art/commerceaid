<?php
/**
 * Users list.
 *
 * Context (from AdminUserController::index):
 *   $result    array{items,total,page,per_page,pages}
 *   $tab       string   'active' | 'pending' | 'disabled' | 'all'
 *   $q         string
 *   $counts    array<string,int>
 *   $roleNames array<string,string>   slug => display name
 */
$result    = $result    ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$tab       = $tab       ?? 'active';
$q         = $q         ?? '';
$counts    = $counts    ?? ['active' => 0, 'pending' => 0, 'disabled' => 0, 'all' => 0];
$roleNames = $roleNames ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$tabs = [
    'active'   => 'Active',
    'pending'  => 'Pending',
    'disabled' => 'Disabled',
    'all'      => 'All',
];

$statusBadge = function (string $status): string {
    return match ($status) {
        'active'    => '<span class="ra-badge ra-badge-success">Active</span>',
        'pending'   => '<span class="ra-badge ra-badge-warning">Pending</span>',
        'disabled'  => '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Disabled</span>',
        'suspended' => '<span class="ra-badge ra-badge-muted">Suspended</span>',
        default     => '<span class="ra-badge ra-badge-muted">' . e($status) . '</span>',
    };
};

$pageUrl = function (int $p) use ($tab, $q): string {
    $params = ['status' => $tab, 'page' => $p];
    if ($q !== '') { $params['q'] = $q; }
    return '?' . http_build_query($params);
};

$tabUrl = function (string $key) use ($q): string {
    $params = ['status' => $key];
    if ($q !== '') { $params['q'] = $q; }
    return '?' . http_build_query($params);
};

$clearUrl = '/commerce/ca-admin/users';
$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return 'Never';
    $ts = strtotime($dt);
    return $ts === false ? 'Never' : date('M j, Y \a\t H:i', $ts);
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Users</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> user<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== '' || $tab !== 'active'): ?>
                &middot; filtered
                <a href="<?= e($clearUrl) ?>" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/users/invite" class="ra-btn ra-btn-primary">
            <i class="bi bi-person-plus me-1"></i> Invite user
        </a>
    </div>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="User status">
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
        <form method="GET" action="/commerce/ca-admin/users" class="row g-2 align-items-end ra-filter-bar">
            <input type="hidden" name="status" value="<?= e($tab) ?>">
            <div class="col-md-9">
                <label class="muted small" for="user-q">Search</label>
                <input type="text" id="user-q" name="q" class="form-control"
                       placeholder="Name or email"
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
            <i class="bi bi-people" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?php if ($q !== '' || $tab !== 'active'): ?>
                    No users match these filters
                <?php else: ?>
                    No active users
                <?php endif; ?>
            </h2>
            <p class="muted small mb-3">
                <?php if ($q !== '' || $tab !== 'active'): ?>
                    Try clearing filters or adjusting your search.
                <?php else: ?>
                    Invite the first member of your team.
                <?php endif; ?>
            </p>
            <?php if ($q !== '' || $tab !== 'active'): ?>
                <a href="<?= e($clearUrl) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <a href="/commerce/ca-admin/users/invite" class="ra-btn ra-btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Invite user
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th class="ra-hide-sm" style="width:200px;">Email</th>
                        <th class="ra-hide-sm" style="width:160px;">Role</th>
                        <th style="width:120px;">Status</th>
                        <th class="ra-hide-sm" style="width:180px;">Last login</th>
                        <th style="width:120px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $u): ?>
                        <?php
                        $primaryRoleSlug = $u->roleSlugs[0] ?? '';
                        $primaryRoleName = $primaryRoleSlug !== ''
                            ? ($roleNames[$primaryRoleSlug] ?? ucfirst(str_replace('_', ' ', $primaryRoleSlug)))
                            : '—';
                        ?>
                        <tr>
                            <td data-label="User" data-col="full">
                                <a href="/commerce/ca-admin/users/<?= (int) $u->id ?>/edit"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($u->name) ?>
                                </a>
                            </td>
                            <td data-label="Email" data-col="half" class="ra-hide-sm muted small"><?= e($u->email) ?></td>
                            <td data-label="Role" data-col="half" class="ra-hide-sm"><?= e($primaryRoleName) ?></td>
                            <td data-label="Status" data-col="half"><?= $statusBadge($u->status) ?></td>
                            <td data-label="Last login" data-col="half" class="ra-hide-sm muted small"><?= e($fmtDateTime($u->lastLoginAt)) ?></td>
                            <td data-label="Actions" data-col="full" class="text-end">
                                <a href="/commerce/ca-admin/users/<?= (int) $u->id ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($u->status === 'active'): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/users/<?= (int) $u->id ?>/deactivate"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn" title="Deactivate"
                                                onclick="return confirm('Deactivate this user? They will be signed out and cannot log in until reactivated.');">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                    </form>
                                <?php elseif ($u->status === 'disabled'): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/users/<?= (int) $u->id ?>/reactivate"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn" title="Reactivate">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
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
