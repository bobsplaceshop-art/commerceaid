<?php
/**
 * Subscriber lists index. Stage 008I.
 *
 * Context:
 *   $lists  array<int, SubscriberList>
 *   $counts array<int, int>  list_id => member count
 */

$lists  = $lists  ?? [];
$counts = $counts ?? [];
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Subscriber lists</h1>
        <p class="muted mb-0">
            Named groups of subscribers. Campaigns target one list.
        </p>
    </div>
    <a href="/commerce/ca-admin/subscriber-lists/create" class="ra-btn ra-btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New list
    </a>
</div>

<?php if (empty($lists)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-list-ul" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No lists yet</h2>
            <p class="muted small mb-3">
                Create a list to group subscribers. The default list is created automatically for every store.
            </p>
            <a href="/commerce/ca-admin/subscriber-lists/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New list
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="ra-hide-sm" style="width:180px;">Slug</th>
                        <th class="ra-hide-sm">Description</th>
                        <th style="width:100px;">Members</th>
                        <th style="width:90px;">Default</th>
                        <th style="width:100px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lists as $l): ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/subscriber-lists/<?= (int) $l->id ?>/edit"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($l->name) ?>
                                </a>
                            </td>
                            <td class="ra-hide-sm"><code><?= e($l->slug) ?></code></td>
                            <td class="ra-hide-sm muted small"><?= e($l->description ?? '') ?></td>
                            <td><?= (int) ($counts[$l->id] ?? 0) ?></td>
                            <td>
                                <?php if ($l->isDefault): ?>
                                    <span class="ra-badge ra-badge-muted">Yes</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/subscriber-lists/<?= (int) $l->id ?>/edit"
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
<?php endif; ?>

<div class="d-flex gap-2 mb-4 mt-3">
    <a href="/commerce/ca-admin/subscribers" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to subscribers
    </a>
</div>
