<?php
/**
 * Customer groups list.
 *
 * @var array $groups           [{group: CustomerGroup, override_count: int, customer_count: int}, ...]
 * @var bool  $includeArchived
 * @var int   $totalActive
 */
$groups          = $groups          ?? [];
$includeArchived = $includeArchived ?? false;
$totalActive     = $totalActive     ?? 0;
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Customer Groups</h1>
        <p class="muted mb-0">
            <?= (int) $totalActive ?> active group<?= $totalActive === 1 ? '' : 's' ?>
            &middot; control what different buyers see and pay
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="?archived=<?= $includeArchived ? '0' : '1' ?>" class="ra-btn ra-btn-outline">
            <?php if ($includeArchived): ?>
                <i class="bi bi-eye-slash me-1"></i> Hide archived
            <?php else: ?>
                <i class="bi bi-eye me-1"></i> Show archived
            <?php endif; ?>
        </a>
        <a href="/commerce/ca-admin/customer-groups/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New group
        </a>
    </div>
</div>

<?php if (empty($groups)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-people" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No customer groups yet</h2>
            <p class="muted small mb-3">
                Customer groups let you give different buyers different prices
                (Retail, Wholesale, VIP, Staff).
            </p>
            <a href="/commerce/ca-admin/customer-groups/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create your first group
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th class="ra-hide-sm" style="width:180px;">Slug</th>
                        <th class="ra-hide-sm" style="width:120px;">Type</th>
                        <th style="width:110px;text-align:right;">Customers</th>
                        <th style="width:110px;text-align:right;">Overrides</th>
                        <th style="width:100px;">Status</th>
                        <th style="width:180px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $row): ?>
                        <?php
                        $g = $row['group'];
                        $isArchived = $g->isArchived();
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <i class="bi bi-people" style="color:var(--ra-muted,#94a3b8);"></i>
                                    <a href="/commerce/ca-admin/customer-groups/<?= (int) $g->id ?>/edit"
                                       class="text-decoration-none <?= $isArchived ? 'text-muted text-decoration-line-through' : '' ?>">
                                        <?= e($g->name) ?>
                                    </a>
                                    <?php if ($g->isDefault): ?>
                                        <span class="ra-badge ra-badge-success" style="font-size:.62rem;">Default</span>
                                    <?php endif; ?>
                                    <?php if ($g->isB2b): ?>
                                        <span class="ra-badge" style="font-size:.62rem;background:rgba(139,92,246,0.15);color:#8b5cf6;">B2B</span>
                                    <?php endif; ?>
                                    <?php if ($g->requiresApproval): ?>
                                        <span class="ra-badge" style="font-size:.62rem;background:rgba(245,158,11,0.15);color:#f59e0b;">Approval required</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="ra-hide-sm"><code class="muted small"><?= e($g->slug) ?></code></td>
                            <td class="ra-hide-sm">
                                <span class="muted small">
                                    <?= $g->isB2b ? 'B2B' : 'Retail' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="muted small"><?= (int) $row['customer_count'] ?></span>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/customer-groups/<?= (int) $g->id ?>/prices"
                                   class="text-decoration-none">
                                    <?= (int) $row['override_count'] ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($isArchived): ?>
                                    <span class="ra-badge ra-badge-muted">Archived</span>
                                <?php else: ?>
                                    <span class="ra-badge ra-badge-success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end ra-actions-cell">
                                <?php if ($isArchived): ?>
                                    <form method="POST" action="/commerce/ca-admin/customer-groups/<?= (int) $g->id ?>/restore" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-icon-btn" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="/commerce/ca-admin/customer-groups/<?= (int) $g->id ?>/prices"
                                       class="ra-icon-btn" title="Price list">
                                        <i class="bi bi-tags"></i>
                                    </a>
                                    <a href="/commerce/ca-admin/customer-groups/<?= (int) $g->id ?>/edit"
                                       class="ra-icon-btn" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if (!$g->isDefault): ?>
                                        <form method="POST" action="/commerce/ca-admin/customer-groups/<?= (int) $g->id ?>/archive" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="ra-icon-btn" title="Archive"
                                                    onclick="return confirm('Archive this group?');">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
