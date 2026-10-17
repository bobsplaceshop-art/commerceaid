<?php
/**
 * Import preview. Stage 016 Session 2b.
 *
 * Partitions the validated rows into would-create,
 * would-update, and invalid. The commit form is at the
 * bottom.
 *
 * Context (from ImportController::preview):
 *   $batch        array<string,mixed>
 *   $handler      ImportHandler
 *   $wouldCreate  array<int, array<string,mixed>>
 *   $wouldUpdate  array<int, array<string,mixed>>
 *   $invalid      array<int, array<string,mixed>>
 */

$batch       = $batch       ?? [];
$handler     = $handler     ?? null;
$wouldCreate = $wouldCreate ?? [];
$wouldUpdate = $wouldUpdate ?? [];
$invalid     = $invalid     ?? [];

$batchId = (int) ($batch['id'] ?? 0);
$entityLabel = $handler !== null ? $handler->entityLabel() : '';

$fieldLabels = [];
if ($handler !== null) {
    foreach ($handler->fields() as $name => $def) {
        $fieldLabels[$name] = $def['label'];
    }
}
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import">Import</a></li>
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import/<?= $batchId ?>">#<?= $batchId ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Preview</li>
    </ol>
</nav>

<h1 class="h3 mb-3">Preview: batch #<?= $batchId ?></h1>

<div class="ra-alert mb-3">
    <strong><?= e($entityLabel) ?></strong>
    &mdash; <?= count($wouldCreate) ?> would be created,
    <?= count($wouldUpdate) ?> would be updated,
    <?= count($invalid) ?> invalid.
    Nothing is written until you click Commit.
</div>

<?php if (!empty($invalid)): ?>
    <div class="ra-card mb-3">
        <div class="ra-card-body">
            <h2 class="h5 mb-2">Invalid rows (<?= count($invalid) ?>)</h2>
            <p class="muted small mb-3">
                These rows will not be committed. Fix them in your CSV and re-run the import.
                <a href="/commerce/ca-admin/import/<?= $batchId ?>/failed.csv" class="ms-1">Download as CSV</a>.
            </p>
            <div class="ra-table-responsive-wrap" data-responsive="scroll">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Row</th>
                            <th style="width:200px;">Natural key</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invalid as $row): ?>
                            <tr>
                                <td class="muted small"><?= (int) $row['row_number'] ?></td>
                                <td><code class="muted small"><?= e((string) $row['natural_key']) ?></code></td>
                                <td class="small"><?= e((string) ($row['error'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($wouldUpdate)): ?>
    <div class="ra-card mb-3">
        <div class="ra-card-body">
            <h2 class="h5 mb-2">Would update (<?= count($wouldUpdate) ?>)</h2>
            <p class="muted small mb-3">These rows already exist in the store and will be updated in place.</p>
            <div class="ra-table-responsive-wrap" data-responsive="scroll">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Row</th>
                            <th style="width:200px;">Natural key</th>
                            <th>Fields</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wouldUpdate as $row): ?>
                            <?php
                            $payload = json_decode((string) $row['payload_json'], true);
                            $payload = is_array($payload) ? $payload : [];
                            $parts = [];
                            foreach ($payload as $k => $v) {
                                $label = $fieldLabels[$k] ?? $k;
                                $parts[] = $label . ': ' . (string) $v;
                            }
                            ?>
                            <tr>
                                <td class="muted small"><?= (int) $row['row_number'] ?></td>
                                <td><code class="muted small"><?= e((string) $row['natural_key']) ?></code></td>
                                <td class="muted small"><?= e(implode(' | ', $parts)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($wouldCreate)): ?>
    <div class="ra-card mb-3">
        <div class="ra-card-body">
            <h2 class="h5 mb-2">Would create (<?= count($wouldCreate) ?>)</h2>
            <p class="muted small mb-3">These rows are new and will be inserted.</p>
            <div class="ra-table-responsive-wrap" data-responsive="scroll">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:80px;">Row</th>
                            <th style="width:200px;">Natural key</th>
                            <th>Fields</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wouldCreate as $row): ?>
                            <?php
                            $payload = json_decode((string) $row['payload_json'], true);
                            $payload = is_array($payload) ? $payload : [];
                            $parts = [];
                            foreach ($payload as $k => $v) {
                                $label = $fieldLabels[$k] ?? $k;
                                $parts[] = $label . ': ' . (string) $v;
                            }
                            ?>
                            <tr>
                                <td class="muted small"><?= (int) $row['row_number'] ?></td>
                                <td><code class="muted small"><?= e((string) $row['natural_key']) ?></code></td>
                                <td class="muted small"><?= e(implode(' | ', $parts)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="ra-card mb-3">
    <div class="ra-card-body">
        <h2 class="h5 mb-2">Commit</h2>
        <p class="muted small mb-3">
            Commit writes every valid row to the entity tables.
            The <?= count($invalid) ?> invalid rows will be skipped.
            You can roll back the committed rows later.
        </p>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/commerce/ca-admin/import/<?= $batchId ?>" class="ra-btn ra-btn-outline">Cancel</a>
            <form method="POST" action="/commerce/ca-admin/import/<?= $batchId ?>/commit" class="d-inline"
                  onsubmit="return confirm('Commit <?= count($wouldCreate) + count($wouldUpdate) ?> rows? This can be rolled back.');">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-cloud-arrow-up me-1"></i>
                    Commit <?= count($wouldCreate) + count($wouldUpdate) ?> rows
                </button>
            </form>
        </div>
    </div>
</div>

<a href="/commerce/ca-admin/import/<?= $batchId ?>" class="ra-btn ra-btn-outline">
    <i class="bi bi-arrow-left me-1"></i> Back to batch
</a>
