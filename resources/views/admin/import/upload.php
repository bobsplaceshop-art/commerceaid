<?php
/**
 * Import upload. Stage 016 Session 1.
 *
 * Context (from ImportController::showUpload):
 *   $handlers  array<string, string>  entity key => label
 *   $errors    array<string, string>  field => message
 *   $old       array<string, mixed>   prior input
 */

$handlers = $handlers ?? [];
$errors   = $errors   ?? [];
$old      = $old      ?? [];

$err = fn(string $k) => isset($errors[$k])
    ? '<div class="text-danger small mt-1">' . e((string) $errors[$k]) . '</div>'
    : '';
$val = fn(string $k, string $d = '') => e((string) ($old[$k] ?? $d));
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import">Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">Upload</li>
    </ol>
</nav>

<h1 class="h3 mb-3">Upload CSV</h1>

<div class="ra-card">
    <div class="ra-card-body">
        <form method="POST" action="/commerce/ca-admin/import/upload" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">What are you importing?</label>
                <select name="entity" class="form-select" required>
                    <option value="">— choose an entity —</option>
                    <?php foreach ($handlers as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $val('entity') === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Each entity imports independently. Import brands before products,
                    and categories before products.
                </div>
                <?= $err('entity') ?>
            </div>

            <div class="mb-3">
                <label class="form-label">CSV file</label>
                <input type="file" name="csv" class="form-control" accept=".csv" required>
                <div class="form-text">
                    UTF-8, comma-separated, first row is the header. Maximum 10 MB.
                </div>
                <?= $err('csv') ?>
            </div>

            <div class="d-flex gap-2">
                <a href="/commerce/ca-admin/import" class="ra-btn ra-btn-outline">Cancel</a>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-upload me-1"></i> Upload
                </button>
            </div>
        </form>
    </div>
</div>
