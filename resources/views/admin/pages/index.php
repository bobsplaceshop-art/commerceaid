<?php
/** @var array<int, array{slug:string,label:string,blockCount:int,isHome:bool,hasDraft:bool}> $pages */
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
                            <div class="fw-bold">
                                <?= e($page['label']) ?>
                                <?php if (!empty($page['hasDraft'])): ?>
                                    <span class="ra-badge ra-badge-warning ms-2" title="You have unpublished changes to this page">
                                        <i class="bi bi-pencil-square me-1"></i> Draft
                                    </span>
                                <?php endif; ?>
                            </div>
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
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="/commerce/ca-admin/pages/<?= e($page['slug']) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                                <?php if (!empty($page['hasDraft'])): ?>
                                    <form method="POST" action="/commerce/ca-admin/pages/<?= e($page['slug']) ?>/discard-draft" style="margin:0;" onsubmit="return confirm('Discard the draft? The live site was never changed.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-btn ra-btn-sm" style="background:transparent;border:1px solid var(--ra-warning,#f59e0b);color:var(--ra-warning,#f59e0b);">
                                            <i class="bi bi-x-circle me-1"></i> Discard Draft
                                        </button>
                                    </form>
                                <?php endif; ?>
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
