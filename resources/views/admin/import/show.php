<?php
/**
 * Import batch detail. Stage 016 Session 2a.
 *
 * Shows the batch metadata, the row status counts, the
 * flash messages, and the primary action button for the
 * batch's current status.
 *
 * Context (from ImportController::show):
 *   $batch      array<string,mixed>
 *   $rowCounts  array<string, int>
 *   $handlers   array<string, string>  entity => label
 *   $flashOk    ?string
 *   $flashErr   array<string, string>
 */

$batch     = $batch     ?? [];
$rowCounts = $rowCounts ?? [];
$handlers  = $handlers  ?? [];
$flashOk   = $flashOk   ?? null;
$flashErr  = $flashErr  ?? [];

$batchId = (int) ($batch['id'] ?? 0);
$status  = (string) ($batch['status'] ?? '');
$entity  = (string) ($batch['entity'] ?? '');
$entityLabel = $handlers[$entity] ?? $entity;

$total   = (int) ($batch['rows_total'] ?? 0);
$valid   = (int) ($batch['rows_valid'] ?? 0);
$invalid = (int) ($batch['rows_invalid'] ?? 0);

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y H:i', $ts);
};

$statusBadgeClass = function (string $s): string {
    return match ($s) {
        'committed'           => 'ra-badge ra-badge-success',
        'partially_committed' => 'ra-badge ra-badge-warn',
        'rolled_back'         => 'ra-badge ra-badge-muted',
        'cancelled'           => 'ra-badge ra-badge-muted',
        default               => 'ra-badge',
    };
};

$rowStatusBadgeClass = function (string $s): string {
    return match ($s) {
        'committed'   => 'ra-badge ra-badge-success',
        'valid'       => 'ra-badge',
        'invalid'     => 'ra-badge ra-badge-danger',
        'rolled_back' => 'ra-badge ra-badge-muted',
        default       => 'ra-badge',
    };
};
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import">Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">#<?= $batchId ?></li>
    </ol>
</nav>

<h1 class="h3 mb-3">Import batch #<?= $batchId ?></h1>

<?php if ($flashOk): ?>
    <div class="ra-alert ra-alert-success"><?= e((string) $flashOk) ?></div>
<?php endif; ?>
<?php foreach ($flashErr as $msg): ?>
    <div class="ra-alert ra-alert-error"><?= e((string) $msg) ?></div>
<?php endforeach; ?>

<div class="ra-card mb-3">
    <div class="ra-card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Entity</dt>
            <dd class="col-sm-9"><?= e($entityLabel) ?></dd>

            <dt class="col-sm-3">File</dt>
            <dd class="col-sm-9"><?= e((string) ($batch['filename'] ?? '')) ?></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9"><span class="<?= e($statusBadgeClass($status)) ?>"><?= e($status) ?></span></dd>

            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9"><?= e($fmtDateTime($batch['created_at'] ?? null)) ?></dd>

            <?php if ($total > 0): ?>
                <dt class="col-sm-3">Rows</dt>
                <dd class="col-sm-9">
                    <?= $total ?> total:
                    <?= $valid ?> valid,
                    <?= $invalid ?> invalid
                </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<?php if (!empty($rowCounts)): ?>
    <div class="ra-card mb-3">
        <div class="ra-card-body">
            <h2 class="h5 mb-2">Row status</h2>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($rowCounts as $st => $cnt): ?>
                    <div class="<?= e($rowStatusBadgeClass($st)) ?>"><?= e($st) ?>: <?= (int) $cnt ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="ra-card mb-3">
    <div class="ra-card-body">
        <h2 class="h5 mb-2">Next step</h2>
        <?php if ($status === 'uploaded'): ?>
            <p class="mb-2">The file is uploaded but the columns are not mapped yet. Map each entity field to a CSV column.</p>
            <a href="/commerce/ca-admin/import/<?= $batchId ?>/map" class="ra-btn ra-btn-primary">
                <i class="bi bi-diagram-3 me-1"></i> Map columns
            </a>
        <?php elseif ($status === 'mapped'): ?>
            <p class="mb-2">Columns are mapped. Validate the batch to check each row against the handler's rules. Nothing is written to the entity tables yet.</p>
            <form method="POST" action="/commerce/ca-admin/import/<?= $batchId ?>/validate" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-check2-square me-1"></i> Validate batch
                </button>
            </form>
            <a href="/commerce/ca-admin/import/<?= $batchId ?>/map" class="ra-btn ra-btn-outline">Edit mapping</a>
        <?php elseif ($status === 'validated'): ?>
            <p class="mb-2">The batch is validated (<?= $valid ?> valid, <?= $invalid ?> invalid). Review the preview and commit.</p>
            <a href="/commerce/ca-admin/import/<?= $batchId ?>/preview" class="ra-btn ra-btn-primary">
                <i class="bi bi-eye me-1"></i> Preview and commit
            </a>
            <?php if ($invalid > 0): ?>
                <a href="/commerce/ca-admin/import/<?= $batchId ?>/failed.csv" class="ra-btn ra-btn-outline">
                    <i class="bi bi-download me-1"></i> Failed rows CSV
                </a>
            <?php endif; ?>
        <?php elseif ($status === 'committed' || $status === 'partially_committed'): ?>
            <p class="mb-2">
                This batch has been committed.
                <?php if (!empty($rowCounts['committed'])): ?>
                    <?= (int) $rowCounts['committed'] ?> row<?= (int) $rowCounts['committed'] === 1 ? '' : 's' ?> committed.
                <?php endif; ?>
                <?php if (!empty($rowCounts['invalid'])): ?>
                    <?= (int) $rowCounts['invalid'] ?> row<?= (int) $rowCounts['invalid'] === 1 ? '' : 's' ?> failed.
                <?php endif; ?>
            </p>
            <form method="POST" action="/commerce/ca-admin/import/<?= $batchId ?>/rollback" class="d-inline"
                  onsubmit="return confirm('Roll back this batch? The committed rows will be reversed.');">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Roll back batch
                </button>
            </form>
            <?php if (!empty($rowCounts['invalid'])): ?>
                <a href="/commerce/ca-admin/import/<?= $batchId ?>/failed.csv" class="ra-btn ra-btn-outline">
                    <i class="bi bi-download me-1"></i> Failed rows CSV
                </a>
            <?php endif; ?>
        <?php elseif ($status === 'rolled_back'): ?>
            <p class="mb-0">This batch has been rolled back. The entity rows it created were deleted or restored.</p>
        <?php else: ?>
            <p class="mb-0">This batch is in the <strong><?= e($status) ?></strong> state.</p>
        <?php endif; ?>
    </div>
</div>

<a href="/commerce/ca-admin/import" class="ra-btn ra-btn-outline">
    <i class="bi bi-arrow-left me-1"></i> Back to list
</a>
