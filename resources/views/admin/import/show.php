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
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import">Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">#<?= $batchId ?></li>
    </ol>
</nav>

<h1 class="h3 mb-3">Import batch #<?= $batchId ?></h1>

<?php if ($flashOk): ?>
    <div class="alert alert-success"><?= e((string) $flashOk) ?></div>
<?php endif; ?>
<?php foreach ($flashErr as $msg): ?>
    <div class="alert alert-danger"><?= e((string) $msg) ?></div>
<?php endforeach; ?>

<div class="ra-card mb-3">
    <div class="ra-card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Entity</dt>
            <dd class="col-sm-9"><?= e($entityLabel) ?></dd>

            <dt class="col-sm-3">File</dt>
            <dd class="col-sm-9"><?= e((string) ($batch['filename'] ?? '')) ?></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9"><span class="ra-badge"><?= e($status) ?></span></dd>

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
                    <div class="ra-badge"><?= e($st) ?>: <?= (int) $cnt ?></div>
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
            <p class="mb-2">The batch is validated (<?= $valid ?> valid, <?= $invalid ?> invalid). The preview screen and the commit action ship in Session 2b.</p>
            <button type="button" class="ra-btn ra-btn-primary" disabled>
                <i class="bi bi-cloud-arrow-up me-1"></i> Preview and commit (coming in Session 2b)
            </button>
        <?php else: ?>
            <p class="mb-0">This batch is in the <strong><?= e($status) ?></strong> state. Session 2b adds the commit, rollback, and failed-row export.</p>
        <?php endif; ?>
    </div>
</div>

<a href="/commerce/ca-admin/import" class="ra-btn ra-btn-outline">
    <i class="bi bi-arrow-left me-1"></i> Back to list
</a>
