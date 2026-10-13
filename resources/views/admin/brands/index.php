<?php
/**
 * Brands list.
 *
 * @var array $brands           Array of Brand models
 * @var bool  $includeArchived
 * @var int   $totalActive
 */
$includeArchived = $includeArchived ?? false;
$totalActive     = $totalActive     ?? 0;
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Brands</h1>
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
        <a href="/commerce/ca-admin/brands/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New brand
        </a>
    </div>
</div>

<?php if (empty($brands)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-bookmark-star" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No brands yet</h2>
            <p class="muted small mb-3">Brands help shoppers find products from the makers they trust.</p>
            <a href="/commerce/ca-admin/brands/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create your first brand
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
                    <th style="width:220px;">Website</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:180px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($brands as $brand): ?>
                    <?php $isArchived = $brand->status === 'archived'; ?>
                    <tr>
                        <td data-label="Name" data-col="full">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-bookmark-star" style="color:var(--ra-muted,#94a3b8);"></i>
                                <span class="<?= $isArchived ? 'text-muted text-decoration-line-through' : '' ?>">
                                    <?= e($brand->name) ?>
                                </span>
                            </div>
                        </td>
                        <td data-label="Slug" data-col="half"><code class="muted small"><?= e($brand->slug) ?></code></td>
                        <td data-label="Website" data-col="half">
                            <?php if ($brand->websiteUrl !== null): ?>
                                <a href="<?= e($brand->websiteUrl) ?>" target="_blank" rel="noopener noreferrer"
                                   class="small text-decoration-none">
                                    <?= e(preg_replace('#^https?://#', '', $brand->websiteUrl)) ?>
                                    <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;"></i>
                                </a>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status" data-col="half">
                            <?php if ($isArchived): ?>
                                <span class="ra-badge ra-badge-muted">Archived</span>
                            <?php else: ?>
                                <span class="ra-badge ra-badge-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions" data-col="full" class="text-end">
                            <?php if ($isArchived): ?>
                                <form method="POST" action="/commerce/ca-admin/brands/<?= (int) $brand->id ?>/restore" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn" title="Restore">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <a href="/commerce/ca-admin/brands/<?= (int) $brand->id ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="/commerce/ca-admin/brands/<?= (int) $brand->id ?>/archive" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ra-icon-btn" title="Archive">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="ra-icon-btn"
                                    onclick='RA_DeleteBrand.open(<?= (int) $brand->id ?>, <?= json_encode($brand->name) ?>)'
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
                </div>
    </div>
<?php endif; ?>

<?= view_partial('admin/brands/_delete-modal') ?>
