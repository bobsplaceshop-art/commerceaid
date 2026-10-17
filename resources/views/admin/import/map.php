<?php
/**
 * Import map screen. Stage 016.
 *
 * The merchant maps each handler field to a CSV column.
 * One select per field. Required fields must map; optional
 * fields may be left unmapped.
 *
 * Context (from ImportController::showMap):
 *   $batch    array<string,mixed>
 *   $handler  ImportHandler
 *   $header   array<int, string>
 *   $rowCount int
 *   $mapping  array<string, string>  field => csv_header
 *   $errors   array<string, string>
 */

$batch    = $batch    ?? [];
$handler  = $handler  ?? null;
$header   = $header   ?? [];
$rowCount = (int) ($rowCount ?? 0);
$mapping  = $mapping  ?? [];
$errors   = $errors   ?? [];

$batchId = (int) ($batch['id'] ?? 0);
$entityLabel = $handler !== null ? $handler->entityLabel() : '';
$fields      = $handler !== null ? $handler->fields() : [];

$err = fn(string $k) => isset($errors[$k])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$k]) . '</div>'
    : '';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import">Import</a></li>
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import/<?= $batchId ?>">#<?= $batchId ?></a></li>
        <li class="breadcrumb-item active" aria-current="page">Map columns</li>
    </ol>
</nav>

<h1 class="h3 mb-3">Map columns: <?= e((string) ($batch['filename'] ?? '')) ?></h1>

<div class="alert alert-info mb-3">
    <strong><?= e($entityLabel) ?></strong>
    &mdash; <?= $rowCount ?> data row<?= $rowCount === 1 ? '' : 's' ?> in the file.
    Match each field to a column from your CSV. Required fields must be mapped.
</div>

<?= $err('map') ?>

<div class="ra-card">
    <div class="ra-card-body">
        <form method="POST" action="/commerce/ca-admin/import/<?= $batchId ?>/map">
            <?= csrf_field() ?>

            <table class="table align-middle">
                <thead>
                    <tr>
                        <th style="width:30%;">Field</th>
                        <th style="width:40%;">CSV column</th>
                        <th>Hint</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fields as $fieldName => $fieldDef): ?>
                        <?php
                        $required = !empty($fieldDef['required']);
                        $current  = (string) ($mapping[$fieldName] ?? '');
                        ?>
                        <tr>
                            <td>
                                <strong><?= e((string) $fieldDef['label']) ?></strong>
                                <?php if ($required): ?>
                                    <span class="ra-badge ra-badge-danger">required</span>
                                <?php endif; ?>
                                <div class="text-muted small">
                                    <code><?= e($fieldName) ?></code>
                                </div>
                            </td>
                            <td>
                                <select
                                    name="map[<?= e($fieldName) ?>]"
                                    class="form-select"
                                    <?= $required ? '' : '' ?>
                                >
                                    <option value="">
                                        <?= $required ? '— choose a column —' : '— do not import —' ?>
                                    </option>
                                    <?php foreach ($header as $col): ?>
                                        <option value="<?= e($col) ?>" <?= $current === $col ? 'selected' : '' ?>>
                                            <?= e($col) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <div class="text-muted small"><?= e((string) $fieldDef['hint']) ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="d-flex gap-2 mt-3">
                <a href="/commerce/ca-admin/import/<?= $batchId ?>" class="ra-btn ra-btn-outline">Cancel</a>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-arrow-right me-1"></i> Save mapping
                </button>
            </div>
        </form>
    </div>
</div>
