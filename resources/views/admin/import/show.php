<?php
/**
 * Import batch detail. Stage 016 Session 1 placeholder.
 *
 * Session 2 replaces this view with the map / validate /
 * preview / commit / rollback screens. For Session 1,
 * this shows the batch metadata and a "coming in Session 2"
 * note so the navigation from the list does not 404.
 *
 * Context (from ImportController::show):
 *   $batch  array<string,mixed>  the batch row
 */

$batch = $batch ?? [];
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/commerce/ca-admin/import">Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">#<?= (int) ($batch['id'] ?? 0) ?></li>
    </ol>
</nav>

<h1 class="h3 mb-3">Import batch #<?= (int) ($batch['id'] ?? 0) ?></h1>

<div class="ra-card mb-3">
    <div class="ra-card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Entity</dt>
            <dd class="col-sm-9"><?= e((string) ($batch['entity'] ?? '')) ?></dd>

            <dt class="col-sm-3">File</dt>
            <dd class="col-sm-9"><?= e((string) ($batch['filename'] ?? '')) ?></dd>

            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9"><span class="ra-badge"><?= e((string) ($batch['status'] ?? '')) ?></span></dd>

            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9"><?= e((string) ($batch['created_at'] ?? '')) ?></dd>
        </dl>
    </div>
</div>

<div class="alert alert-info">
    <strong>Session 2 of Stage 016.</strong>
    The column-mapping, validation, preview, commit, and rollback screens ship next.
    The uploaded file is stored and the batch row exists; nothing has been imported yet.
</div>

<a href="/commerce/ca-admin/import" class="ra-btn ra-btn-outline">
    <i class="bi bi-arrow-left me-1"></i> Back to list
</a>
