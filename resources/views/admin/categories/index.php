<?php
/**
 * Categories list - tree view.
 *
 * @var array $tree            Nested tree of ['category' => Category, 'children' => [...]]
 * @var bool  $includeArchived
 * @var int   $totalActive
 */
$includeArchived = $includeArchived ?? false;
$totalActive     = $totalActive     ?? 0;

/**
 * Render a tree of categories as table rows.
 * Defined before the markup so it's callable in the table body.
 */
$renderTree = function (array $nodes, int $depth) use (&$renderTree): void {
    foreach ($nodes as $node):
        $category    = $node['category'];
        $hasChildren = !empty($node['children']);
        $isArchived  = $category->status === 'archived';
        ?>
        <tr>
            <td data-label="Name" data-col="full">
                <div style="padding-left:<?= $depth * 1.5 ?>rem;" class="d-flex align-items-center gap-2">
                    <?php if ($depth > 0): ?>
                        <i class="bi bi-arrow-return-right text-muted" style="font-size:.75rem;opacity:.5;"></i>
                    <?php endif; ?>
                    <i class="bi <?= $hasChildren ? 'bi-folder2-open' : 'bi-folder2' ?>" style="color:var(--ra-muted,#94a3b8);"></i>
                    <span class="<?= $isArchived ? 'text-muted text-decoration-line-through' : '' ?>">
                        <?= e($category->name) ?>
                    </span>
                </div>
            </td>
            <td data-label="Slug" data-col="half"><code class="muted small"><?= e($category->slug) ?></code></td>
            <td data-label="Status" data-col="half">
                <?php if ($isArchived): ?>
                    <span class="ra-badge ra-badge-muted">Archived</span>
                <?php else: ?>
                    <span class="ra-badge ra-badge-success">Active</span>
                <?php endif; ?>
            </td>
            <td data-label="Actions" data-col="full" class="text-end">
                <?php if ($isArchived): ?>
                    <form method="POST" action="/commerce/ca-admin/categories/<?= (int) $category->id ?>/restore" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="ra-icon-btn" title="Restore">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <a href="/commerce/ca-admin/categories/<?= (int) $category->id ?>/edit"
                       class="ra-icon-btn" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="POST" action="/commerce/ca-admin/categories/<?= (int) $category->id ?>/archive" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="ra-icon-btn" title="Archive">
                            <i class="bi bi-archive"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <button type="button" class="ra-icon-btn"
                        onclick='RA_DeleteCategory.open(<?= (int) $category->id ?>, <?= json_encode($category->name) ?>)'
                        title="Delete">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
        <?php
        if ($hasChildren) {
            $renderTree($node['children'], $depth + 1);
        }
    endforeach;
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Categories</h1>
        <p class="muted mb-0">
            <?= $totalActive ?> active
            <?php if ($includeArchived): ?>
                &middot; showing archived
            <?php endif; ?>
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
        <a href="/commerce/ca-admin/categories/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New category
        </a>
    </div>
</div>

<?php if (empty($tree)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-diagram-3" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No categories yet</h2>
            <p class="muted small mb-3">Group your products with categories.</p>
            <a href="/commerce/ca-admin/categories/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create your first category
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
                <table class="ra-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th style="width:180px;">Slug</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:180px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $renderTree($tree, 0); ?>
            </tbody>
        </table>
                </div>
    </div>
<?php endif; ?>

<?= view_partial('admin/categories/_delete-modal') ?>
