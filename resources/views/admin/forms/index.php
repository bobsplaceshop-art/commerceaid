<?php
/**
 * Admin forms list. Stage 009.
 *
 * Context:
 *   $forms  array<int, Form>
 *   $total  int
 *   $page   int
 *   $pages  int
 *   $status string  'all'|'draft'|'published'|'archived'
 *   $search string
 */
$forms  = $forms  ?? [];
$total  = $total  ?? 0;
$page   = $page   ?? 1;
$pages  = $pages  ?? 0;
$status       = $status       ?? 'all';
$search       = $search       ?? '';
$statusCounts = $statusCounts ?? ['all' => 0, 'draft' => 0, 'published' => 0, 'archived' => 0];

$baseUrl = '/commerce/ca-admin/forms';
$qs      = function (array $overrides) use ($status, $search): string {
    $params = array_merge(
        ['status' => $status, 'search' => $search],
        $overrides
    );
    $params = array_filter($params, fn ($v) => $v !== '' && $v !== null);
    return $params === [] ? '' : '?' . http_build_query($params);
};

$tabs = [
    'all'       => 'All',
    'draft'     => 'Draft',
    'published' => 'Published',
    'archived'  => 'Archived',
];
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Forms</h1>
        <p class="muted mb-0">
            Collect information from customers. Publish a form, embed the URL, and receive submissions.
        </p>
    </div>
    <a href="/commerce/ca-admin/forms/create" class="ra-btn ra-btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New form
    </a>
</div>

<div class="ra-card mb-3">
    <div class="ra-card-body" style="padding-bottom:0;">
        <nav class="ra-tabs" role="tablist">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?= e($baseUrl . $qs(['status' => $key, 'page' => 1])) ?>"
                   class="ra-tab<?= $status === $key ? ' is-active' : '' ?>">
                    <?= e($label) ?>
                    <span class="ra-tab-count"><?= (int) ($statusCounts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<form method="GET" action="<?= e($baseUrl) ?>" class="ra-card mb-4">
    <input type="hidden" name="status" value="<?= e($status) ?>">
    <div class="ra-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-8">
                <label class="muted small" for="ff-search">Search</label>
                <input type="text"
                       id="ff-search"
                       name="search"
                       class="form-control"
                       value="<?= e($search) ?>"
                       placeholder="Form name or slug">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= e($baseUrl . $qs(['search' => ''])) ?>" class="ra-btn ra-btn-outline">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php if (empty($forms)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-ui-checks" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">No forms yet</h2>
            <p class="muted small mb-3">
                Create a form to start collecting submissions. Add fields, publish it, and share the public URL.
            </p>
            <a href="/commerce/ca-admin/forms/create" class="ra-btn ra-btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New form
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="ra-hide-sm" style="width:180px;">Slug</th>
                        <th class="ra-hide-sm" style="width:120px;">Status</th>
                        <th class="ra-hide-sm" style="width:120px;">Updated</th>
                        <th style="width:160px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($forms as $form): ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/forms/<?= (int) $form->id ?>/edit"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($form->name) ?>
                                </a>
                                <?php if ($form->description !== null && $form->description !== ''): ?>
                                    <div class="muted small"><?= e($form->description) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="ra-hide-sm"><code><?= e($form->slug) ?></code></td>
                            <td class="ra-hide-sm">
                                <span class="ra-badge ra-badge-<?= e($form->statusBadgeVariant()) ?>">
                                    <?= e($form->statusLabel()) ?>
                                </span>
                            </td>
                            <td class="ra-hide-sm muted small">
                                <?= e((string) ($form->updatedAt ?? '')) ?>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/forms/<?= (int) $form->id ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST"
                                      action="/commerce/ca-admin/forms/<?= (int) $form->id ?>/duplicate"
                                      style="display:inline;">
                                    <?= csrf_field() ?>
                                    <button type="submit"
                                            class="ra-icon-btn"
                                            title="Duplicate"
                                            onclick="return confirm('Duplicate this form? The copy will be a draft.');">
                                        <i class="bi bi-files"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="ra-pagination mt-3" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e($baseUrl . $qs(['page' => $page - 1])) ?>"
                   class="ra-pagination-link">&lsaquo; Previous</a>
            <?php endif; ?>
            <span class="ra-pagination-info">
                Page <?= (int) $page ?> of <?= (int) $pages ?> (<?= (int) $total ?> total)
            </span>
            <?php if ($page < $pages): ?>
                <a href="<?= e($baseUrl . $qs(['page' => $page + 1])) ?>"
                   class="ra-pagination-link">Next &rsaquo;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
