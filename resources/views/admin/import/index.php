<?php
/**
 * Import batch list. Stage 016 Session 1.
 *
 * Context (from ImportController::index):
 *   $result    paginate() return
 *   $status    '' or a status key
 *   $entity    '' or an entity key
 *   $handlers  array<string, string>  entity key => label
 */

$result   = $result   ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$status   = $status   ?? '';
$entity   = $entity   ?? '';
$handlers = $handlers ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$statusTabs = [
    ''                    => 'All',
    'uploaded'            => 'Uploaded',
    'mapped'              => 'Mapped',
    'validated'           => 'Validated',
    'committing'          => 'Committing',
    'committed'           => 'Committed',
    'partially_committed' => 'Partially',
    'rolled_back'         => 'Rolled back',
    'cancelled'           => 'Cancelled',
];

$tabUrl = function (string $key) use ($entity): string {
    $params = [];
    if ($key !== '')      $params['status'] = $key;
    if ($entity !== '')   $params['entity'] = $entity;
    return '/commerce/ca-admin/import' . ($params === [] ? '' : '?' . http_build_query($params));
};

$entityUrl = function (string $key) use ($status): string {
    $params = [];
    if ($status !== '')   $params['status'] = $status;
    if ($key !== '')      $params['entity'] = $key;
    return '/commerce/ca-admin/import' . ($params === [] ? '' : '?' . http_build_query($params));
};

$pageUrl = function (int $p) use ($status, $entity): string {
    $params = ['page' => $p];
    if ($status !== '') $params['status'] = $status;
    if ($entity !== '') $params['entity'] = $entity;
    return '/commerce/ca-admin/import?' . http_build_query($params);
};

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y H:i', $ts);
};

$statusBadge = function (string $s): string {
    return match ($s) {
        'committed'           => 'ra-badge ra-badge-success',
        'partially_committed' => 'ra-badge ra-badge-warn',
        'rolled_back'         => 'ra-badge ra-badge-muted',
        'cancelled'           => 'ra-badge ra-badge-muted',
        default               => 'ra-badge',
    };
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Import</h1>
    <a href="/commerce/ca-admin/import/upload" class="ra-btn ra-btn-primary">
        <i class="bi bi-upload me-1"></i> Upload CSV
    </a>
</div>

<div class="ra-card mb-3">
    <nav class="ra-admin-tabs" aria-label="Batch status">
        <?php foreach ($statusTabs as $key => $label): ?>
            <a href="<?= e($tabUrl($key)) ?>"
               class="ra-admin-tab<?= $status === $key ? ' is-active' : '' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <form method="GET" action="/commerce/ca-admin/import" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Entity</label>
                <select name="entity" class="form-select" onchange="this.form.submit()">
                    <option value="">All entities</option>
                    <?php foreach ($handlers as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $entity === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($status !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status) ?>">
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="ra-card">
    <div class="ra-table-responsive-wrap" data-responsive="cards">
        <table class="ra-table">
            <thead>
                <tr>
                    <th style="width:80px;">#</th>
                    <th>Entity</th>
                    <th class="ra-hide-sm">File</th>
                    <th style="width:140px;">Status</th>
                    <th class="ra-hide-sm" style="width:140px;">Rows</th>
                    <th class="ra-hide-sm" style="width:160px;">Created</th>
                    <th style="width:100px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($items === []): ?>
                    <tr>
                        <td colspan="7" data-label="No batches" data-col="full" class="text-center py-4 muted">
                            No batches yet. Upload a CSV to start.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $batch): ?>
                        <tr>
                            <td data-label="#" data-col="half" class="muted small"><?= (int) $batch['id'] ?></td>
                            <td data-label="Entity" data-col="full">
                                <?= e($handlers[$batch['entity']] ?? $batch['entity']) ?>
                            </td>
                            <td data-label="File" data-col="half" class="ra-hide-sm muted small">
                                <?= e((string) $batch['filename']) ?>
                            </td>
                            <td data-label="Status" data-col="half">
                                <span class="<?= $statusBadge((string) $batch['status']) ?>">
                                    <?= e((string) $batch['status']) ?>
                                </span>
                            </td>
                            <td data-label="Rows" data-col="half" class="ra-hide-sm muted small">
                                <?= (int) $batch['rows_total'] ?>
                                <?php if ((int) $batch['rows_invalid'] > 0): ?>
                                    <span class="text-danger">(<?= (int) $batch['rows_invalid'] ?> failed)</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Created" data-col="half" class="ra-hide-sm muted small">
                                <?= e($fmtDateTime($batch['created_at'] ?? null)) ?>
                            </td>
                            <td data-label="Actions" data-col="full" class="text-end">
                                <a href="/commerce/ca-admin/import/<?= (int) $batch['id'] ?>" class="ra-icon-btn" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
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
