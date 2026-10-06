<?php
/**
 * Customers list.
 *
 * Context (from AdminCustomerController::index):
 *   $result       array{items,total,page,per_page,pages}
 *   $tab          string   'all' | 'pending' | 'approved' | 'rejected'
 *   $state        string   'all' | 'active' | 'disabled' | 'archived'
 *   $q            string
 *   $groupId      int
 *   $counts       array<string,int>
 *   $groupOptions array<int,array{id:int,name:string,is_default:int}>
 */
$result       = $result       ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$tab          = $tab          ?? 'all';
$state        = $state        ?? 'all';
$q            = $q            ?? '';
$groupId      = $groupId      ?? 0;
$counts       = $counts       ?? ['all' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$groupOptions = $groupOptions ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$tabs = [
    'all'      => 'All',
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];

$states = [
    'all'      => 'Any account state',
    'active'   => 'Active',
    'disabled' => 'Disabled',
    'archived' => 'Archived',
];

$accountBadge = function (string $status): string {
    return match ($status) {
        'active'   => '<span class="ra-badge ra-badge-success">Active</span>',
        'disabled' => '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Disabled</span>',
        'archived' => '<span class="ra-badge ra-badge-muted">Archived</span>',
        default    => '<span class="ra-badge ra-badge-muted">' . e($status) . '</span>',
    };
};

$approvalBadge = function (string $status): string {
    return match ($status) {
        'approved' => '<span class="ra-badge ra-badge-success">Approved</span>',
        'pending'  => '<span class="ra-badge ra-badge-warning">Pending</span>',
        'rejected' => '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Rejected</span>',
        default    => '<span class="ra-badge ra-badge-muted">' . e($status) . '</span>',
    };
};

$pageUrl = function (int $p) use ($tab, $state, $q, $groupId): string {
    $params = ['status' => $tab, 'page' => $p];
    if ($state !== 'all') { $params['state'] = $state; }
    if ($q !== '')        { $params['q'] = $q; }
    if ($groupId > 0)     { $params['group'] = $groupId; }
    return '?' . http_build_query($params);
};

$clearUrl = '/commerce/ca-admin/customers';

$tabUrl = function (string $key) use ($state, $q, $groupId): string {
    $params = ['status' => $key];
    if ($state !== 'all') { $params['state'] = $state; }
    if ($q !== '')        { $params['q'] = $q; }
    if ($groupId > 0)     { $params['group'] = $groupId; }
    return '?' . http_build_query($params);
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Customers</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> customer<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== '' || $groupId > 0 || $state !== 'all' || $tab !== 'all'): ?>
                &middot; filtered
                <a href="<?= e($clearUrl) ?>" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="Approval status">
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
        <form method="GET" action="/commerce/ca-admin/customers" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($tab) ?>">
            <div class="col-md-5">
                <label class="muted small" for="cust-q">Search</label>
                <input type="text" id="cust-q" name="q" class="form-control"
                       placeholder="Name, email, or company"
                       value="<?= e($q) ?>">
            </div>
            <div class="col-md-3">
                <label class="muted small" for="cust-group">Group</label>
                <select id="cust-group" name="group" class="form-select">
                    <option value="">Any group</option>
                    <?php foreach ($groupOptions as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= $groupId === (int) $g['id'] ? 'selected' : '' ?>>
                            <?= e($g['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="muted small" for="cust-state">Account</label>
                <select id="cust-state" name="state" class="form-select">
                    <?php foreach ($states as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $state === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
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
                <?php if ($q !== '' || $groupId > 0 || $state !== 'all' || $tab !== 'all'): ?>
                    No customers match these filters
                <?php else: ?>
                    No customers yet
                <?php endif; ?>
            </h2>
            <p class="muted small mb-3">
                <?php if ($q !== '' || $groupId > 0 || $state !== 'all' || $tab !== 'all'): ?>
                    Try clearing filters or adjusting your search.
                <?php else: ?>
                    Customers will appear here as they register on the storefront.
                <?php endif; ?>
            </p>
            <?php if ($q !== '' || $groupId > 0 || $state !== 'all' || $tab !== 'all'): ?>
                <a href="<?= e($clearUrl) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th class="ra-hide-sm" style="width:200px;">Email</th>
                        <th class="ra-hide-sm" style="width:140px;">Group</th>
                        <th style="width:140px;">Account</th>
                        <th style="width:140px;">Approval</th>
                        <th class="ra-hide-sm" style="width:140px;">Registered</th>
                        <th style="width:100px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $c): ?>
                        <?php
                        $name = $c->fullName();
                        if ($name === '') {
                            $name = $c->email;
                        }
                        $g = null;
                        foreach ($groupOptions as $gg) {
                            if ((int) $gg['id'] === (int) ($c->customerGroupId ?? 0)) {
                                $g = $gg['name'];
                                break;
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/customers/<?= (int) $c->id ?>"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($name) ?>
                                </a>
                                <?php if ($c->company !== null && $c->company !== ''): ?>
                                    <div class="muted small"><?= e($c->company) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm muted small"><?= e($c->email) ?></td>
                            <td class="ra-hide-sm">
                                <?php if ($g !== null): ?>
                                    <?= e($g) ?>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $accountBadge($c->status) ?></td>
                            <td><?= $approvalBadge($c->approvalStatus) ?></td>
                            <td class="ra-hide-sm muted small">
                                <?= $c->createdAt !== null ? e(date('M j, Y', strtotime((string) $c->createdAt))) : '—' ?>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/customers/<?= (int) $c->id ?>"
                                   class="ra-icon-btn" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="/commerce/ca-admin/customers/<?= (int) $c->id ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
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
