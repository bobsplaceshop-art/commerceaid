<?php
/**
 * Legal pages list.
 *
 * Context (from LegalPageController::index):
 *   $pages  array<int, LegalPage>
 */
$pages = $pages ?? [];

// The four slugs required by the checkout gate.
$required = \CorporateAid\Commerce\Models\LegalPage::requiredSlugs();
$bySlug = [];
foreach ($pages as $p) {
    $bySlug[$p->slug] = $p;
}
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Legal Pages</h1>
        <p class="muted mb-0">
            Impressum, terms, withdrawal policy, and privacy policy.
            Required before checkout is available.
        </p>
    </div>
</div>

<?php if ($pages === []): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-file-earmark-text" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No legal pages yet</h2>
            <p class="muted small mb-0">
                Run the seeder to populate the four required pages.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Title</th>
                        <th class="ra-hide-sm" style="width:140px;">Status</th>
                        <th class="ra-hide-sm" style="width:170px;">Last updated</th>
                        <th style="width:100px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <?php
                        $isRequired = in_array($page->slug, $required, true);
                        ?>
                        <tr>
                            <td>
                                <code class="small"><?= e($page->slug) ?></code>
                                <?php if ($isRequired): ?>
                                    <span class="muted small">&middot; required</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/commerce/ca-admin/legal-pages/<?= e($page->slug) ?>/edit"
                                   class="text-decoration-none">
                                    <?= e($page->title) ?>
                                </a>
                            </td>
                            <td class="ra-hide-sm">
                                <?php if ($page->isPublished()): ?>
                                    <span class="ra-badge ra-badge-success">Published</span>
                                <?php else: ?>
                                    <span class="ra-badge ra-badge-muted">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm">
                                <span class="muted small">
                                    <?= $page->updatedAt !== null
                                        ? e(date('M j, Y', strtotime($page->updatedAt)))
                                        : '—' ?>
                                </span>
                            </td>
                            <td class="text-end ra-actions-cell">
                                <a href="/commerce/ca-admin/legal-pages/<?= e($page->slug) ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="/legal/<?= e($page->slug) ?>"
                                   target="_blank"
                                   class="ra-icon-btn" title="Preview on storefront">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
