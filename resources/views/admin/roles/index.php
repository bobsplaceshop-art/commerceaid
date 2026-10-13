<?php
/**
 * Roles list.
 *
 * Context (from AdminRoleController::index):
 *   $result array{items,total,page,per_page,pages}
 *   $tab    'all' | 'system' | 'custom'
 *   $q      string
 *   $counts array{all:int,system:int,custom:int}
 *
 * Each item is a row from RoleRepository::paginate():
 *   id, name, slug, description, is_system, store_id,
 *   created_at, user_count, permission_count
 */
$result = $result ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$tab    = $tab    ?? 'all';
$q      = $q      ?? '';
$counts = $counts ?? ['all' => 0, 'system' => 0, 'custom' => 0];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$tabs = [
    'all'    => 'All',
    'system' => 'System',
    'custom' => 'Custom',
];

$pageUrl = function (int $p) use ($tab, $q): string {
    $params = ['type' => $tab, 'page' => $p];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
};

$tabUrl = function (string $key) use ($q): string {
    $params = ['type' => $key];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
};

$clearUrl = '/commerce/ca-admin/roles';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Roles</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> role<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                &middot; filtered
                <a href="<?= e($clearUrl) ?>" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/roles/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New role
        </a>
    </div>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="Role type">
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
        <form method="GET" action="/commerce/ca-admin/roles" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?= e($tab) ?>">
            <div class="col-md-9">
                <label class="muted small" for="role-q">Search</label>
                <input type="text" id="role-q" name="q" class="form-control"
                       placeholder="Name or slug"
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
            <i class="bi bi-shield-lock" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?php if ($q !== '' || $tab !== 'all'): ?>
                    No roles match these filters
                <?php else: ?>
                    No roles yet
                <?php endif; ?>
            </h2>
            <p class="muted small mb-3">
                <?php if ($q !== '' || $tab !== 'all'): ?>
                    Try clearing filters or adjusting your search.
                <?php else: ?>
                    Create a custom role to give team members scoped access.
                <?php endif; ?>
            </p>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                <a href="<?= e($clearUrl) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <a href="/commerce/ca-admin/roles/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New role
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th class="ra-hide-sm" style="width:120px;">Scope</th>
                        <th class="ra-hide-sm" style="width:120px;">Type</th>
                        <th style="width:100px;">Users</th>
                        <th class="ra-hide-sm" style="width:120px;">Permissions</th>
                        <th style="width:120px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $r): ?>
                        <?php
                        $rid       = (int) ($r['id'] ?? 0);
                        $rname     = (string) ($r['name'] ?? '');
                        $rslug     = (string) ($r['slug'] ?? '');
                        $rdesc     = (string) ($r['description'] ?? '');
                        $isSystem  = ((int) ($r['is_system'] ?? 0)) === 1;
                        $storeId   = $r['store_id'];
                        $users     = (int) ($r['user_count'] ?? 0);
                        $perms     = (int) ($r['permission_count'] ?? 0);
                        $canDelete = !$isSystem && $users === 0;
                        ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/roles/<?= $rid ?>/edit"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($rname) ?>
                                </a>
                                <div class="muted small">
                                    <code class="muted small" style="background:none;padding:0;font-size:.85em;"><?= e($rslug) ?></code>
                                    <?php if ($rdesc !== ''): ?>
                                        &middot; <?= e($rdesc) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="ra-hide-sm">
                                <?php if ($storeId === null): ?>
                                    <span class="ra-badge ra-badge-muted">Global</span>
                                <?php else: ?>
                                    <span class="ra-badge ra-badge-muted">This store</span>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm">
                                <?php if ($isSystem): ?>
                                    <span class="ra-badge ra-badge-warning">System</span>
                                <?php else: ?>
                                    <span class="ra-badge ra-badge-muted">Custom</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $users ?></td>
                            <td class="ra-hide-sm"><?= $perms ?></td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/roles/<?= $rid ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($canDelete): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/roles/<?= $rid ?>/delete"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn" title="Delete"
                                                onclick="return confirm('Delete this role? This cannot be undone.');">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php elseif ($isSystem): ?>
                                    <span class="ra-icon-btn" style="cursor:not-allowed;opacity:.4;"
                                          title="System roles cannot be deleted">
                                        <i class="bi bi-lock"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="ra-icon-btn" style="cursor:not-allowed;opacity:.4;"
                                          title="Reassign users first">
                                        <i class="bi bi-lock"></i>
                                    </span>
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
