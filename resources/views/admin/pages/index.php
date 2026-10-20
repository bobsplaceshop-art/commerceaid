<?php
/** @var array<int, array{slug:string,label:string,blockCount:int,isHome:bool}> $pages */
?>
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="h3 mb-1">Pages</h1>
        <p class="muted mb-0">Edit the homepage and add custom pages to your storefront.</p>
    </div>
    <a href="/commerce/ca-admin/pages/create" class="ra-btn ra-btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New page
    </a>
</div>

<?php if (empty($pages)): ?>
    <div class="ra-card text-center py-5">
        <p class="muted mb-0">No pages yet.</p>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>Slug</th>
                        <th style="text-align:right;">Blocks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td data-col="full">
                            <div class="fw-bold"><?= e($page['label']) ?></div>
                            <?php if ($page['isHome']): ?>
                                <div class="text-muted small">The main storefront page</div>
                            <?php endif; ?>
                        </td>
                        <td data-col="half">
                            <code class="text-muted"><?= e($page['slug']) ?></code>
                        </td>
                        <td data-col="half" style="text-align:right;">
                            <?= (int) $page['blockCount'] ?>
                        </td>
                        <td data-col="half">
                            <div class="d-flex gap-2">
                                <a href="/commerce/ca-admin/pages/<?= e($page['slug']) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                                <?php if (!$page['isHome']): ?>
                                    <form method="POST" action="/commerce/ca-admin/pages/<?= e($page['slug']) ?>/delete" style="margin:0;" onsubmit="return confirm('Delete this page and all its blocks?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-btn ra-btn-sm" style="background:transparent;border:1px solid var(--ra-danger);color:var(--ra-danger);">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
