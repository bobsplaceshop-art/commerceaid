<?php
/**
 * Admin submission detail. Stage 009 Session 4.
 *
 * Route: GET /commerce/ca-admin/forms/{id}/submissions/{sid}
 *
 * Context (from AdminFormController::submissionDetail):
 *   $form       Form
 *   $submission FormSubmission
 *   $fields     array<int, FormField>  in sort order
 */

$form       = $form       ?? null;
$submission = $submission ?? null;
$fields     = $fields     ?? [];

if ($form === null || $submission === null) {
    return;
}

$base     = '/commerce/ca-admin/forms/' . (int) $form->id . '/submissions';
$detail   = $base . '/' . (int) $submission->id;

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y H:i', $ts);
};

$badgeClass = function (string $variant): string {
    return match ($variant) {
        'success' => 'ra-badge ra-badge-success',
        'danger'  => 'ra-badge ra-badge-danger',
        'warning' => 'ra-badge ra-badge-warn',
        'info'    => 'ra-badge',
        'muted'   => 'ra-badge ra-badge-muted',
        default   => 'ra-badge',
    };
};

$statusOptions = [
    'new'      => 'New',
    'read'     => 'Read',
    'spam'     => 'Spam',
    'archived' => 'Archived',
];
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0" style="font-size:.85rem;">
                <li class="breadcrumb-item"><a href="/commerce/ca-admin/forms">Forms</a></li>
                <li class="breadcrumb-item"><a href="/commerce/ca-admin/forms/<?= (int) $form->id ?>/edit"><?= e($form->name) ?></a></li>
                <li class="breadcrumb-item"><a href="<?= e($base) ?>">Submissions</a></li>
                <li class="breadcrumb-item active" aria-current="page">#<?= (int) $submission->id ?></li>
            </ol>
        </nav>
        <h1 class="h3 mb-1">
            Submission #<?= (int) $submission->id ?>
            <span class="<?= e($badgeClass($submission->statusBadgeVariant())) ?> ms-2">
                <?= e($submission->statusLabel()) ?>
            </span>
        </h1>
        <p class="muted mb-0">
            Received <?= e($fmtDateTime($submission->createdAt)) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e($base) ?>" class="ra-btn ra-btn-outline">
            <i class="bi bi-arrow-left me-1"></i> Back to list
        </a>
    </div>
</div>

<div class="row g-3">

    <div class="col-lg-7">
        <div class="ra-card mb-3">
            <div class="ra-card-body">
                <h2 class="h6 mb-3">Submitted values</h2>
                <dl class="row mb-0">
                    <?php foreach ($fields as $field): ?>
                        <?php $value = $submission->value($field->fieldKey); ?>
                        <dt class="col-sm-4 muted small text-truncate" title="<?= e($field->label) ?>">
                            <?= e($field->label) ?>
                        </dt>
                        <dd class="col-sm-8">
                            <?php if ($value === ''): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <div style="white-space:pre-wrap;word-break:break-word;"><?= e($value) ?></div>
                            <?php endif; ?>
                        </dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>

        <div class="ra-card mb-3">
            <div class="ra-card-body">
                <h2 class="h6 mb-3">Internal notes</h2>
                <textarea name="notes"
                          form="ra-sub-status-form"
                          class="form-control"
                          rows="5"
                          placeholder="Add a note about this submission (visible to admins only)"><?= e((string) $submission->notes) ?></textarea>
                <p class="muted small mt-2 mb-0">
                    Notes are saved when you update the status.
                </p>
            </div>
        </div>
    </div>

    <div class="col-lg-5">

        <div class="ra-card mb-3">
            <div class="ra-card-body">
                <h2 class="h6 mb-3">Status</h2>
                <form method="POST" action="<?= e($detail) ?>/status" id="ra-sub-status-form">
                    <?= csrf_field() ?>
                    <div class="mb-2">
                        <select name="status" class="form-select form-select-sm">
                            <?php foreach ($statusOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $submission->status === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="ra-btn ra-btn-primary ra-btn-sm w-100">
                        Update status
                    </button>
                </form>
            </div>
        </div>

        <div class="ra-card mb-3">
            <div class="ra-card-body">
                <h2 class="h6 mb-3">Contact</h2>
                <dl class="row mb-0 small">
                    <dt class="col-5 muted">Name</dt>
                    <dd class="col-7"><?= e((string) ($submission->submitterName ?? '—')) ?></dd>

                    <dt class="col-5 muted">Email</dt>
                    <dd class="col-7 text-break"><?= e((string) ($submission->submitterEmail ?? '—')) ?></dd>

                    <dt class="col-5 muted">IP</dt>
                    <dd class="col-7"><?= e((string) ($submission->submitterIp ?? '—')) ?></dd>

                    <?php if ($submission->customerId !== null): ?>
                        <dt class="col-5 muted">Customer</dt>
                        <dd class="col-7">
                            <a href="/commerce/ca-admin/customers/<?= (int) $submission->customerId ?>">
                                #<?= (int) $submission->customerId ?>
                            </a>
                        </dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if (!empty($submission->userAgent)): ?>
            <div class="ra-card mb-3">
                <div class="ra-card-body">
                    <h2 class="h6 mb-3">Client</h2>
                    <p class="small muted mb-0" style="word-break:break-all;">
                        <?= e((string) $submission->userAgent) ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="ra-card">
            <div class="ra-card-body">
                <h2 class="h6 mb-3 text-danger">Danger zone</h2>
                <p class="muted small mb-3">
                    Deleting a submission is permanent. There is no undo.
                </p>
                <button type="button"
                        class="ra-btn ra-btn-danger ra-btn-sm w-100"
                        data-ra-sub-delete>
                    <i class="bi bi-trash me-1"></i> Delete submission
                </button>
            </div>
        </div>

    </div>

</div>

<div class="ra-modal" id="ra-sub-delete-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-sub-delete-close></div>
    <div class="ra-modal-panel">
        <div class="ra-modal-header">
            <h3 class="h6 mb-0">Delete submission #<?= (int) $submission->id ?>?</h3>
            <button type="button" class="ra-icon-btn" data-ra-sub-delete-close aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-0">
                This will permanently delete the submission and its stored values.
                The customer will not be notified.
            </p>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-sub-delete-close>
                Cancel
            </button>
            <form method="POST" action="<?= e($detail) ?>/delete" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    Delete submission
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal = document.getElementById('ra-sub-delete-modal');
    var openBtn = document.querySelector('[data-ra-sub-delete]');
    var closeBtns = document.querySelectorAll('[data-ra-sub-delete-close]');

    if (!modal || !openBtn) return;

    function open()  { modal.style.display = 'flex'; }
    function close() { modal.style.display = 'none'; }

    openBtn.addEventListener('click', open);
    closeBtns.forEach(function (b) { b.addEventListener('click', close); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') close();
    });
})();
</script>
