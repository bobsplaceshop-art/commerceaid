<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">System Update</h1>
        <p class="muted mb-0">Apply pending database changes or schedule a maintenance window.</p>
    </div>
</div>

<?php if (empty($pending)): ?>
    <div class="ra-card text-center py-5">
        <i class="bi bi-check-circle" style="font-size:2.5rem;color:var(--ra-success);"></i>
        <h2 class="h5 mt-3">System is up to date</h2>
        <p class="muted">There are no pending database migrations.</p>
    </div>
<?php else: ?>
    <div class="ra-alert ra-alert-warning mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Database updates pending.</strong> You must run these migrations to ensure the application functions correctly.
    </div>

    <div class="row">
        <div class="col-md-7 mb-4">
            <div class="ra-card h-100">
                <h2 class="h5 mb-3">Pending Migrations</h2>
                <ul class="list-group list-group-flush mb-3" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($pending as $file): ?>
                        <li class="list-group-item bg-transparent text-light border-secondary px-0 font-monospace small">
                            <?= e($file) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <hr class="border-secondary">
                <p class="small muted mb-3">Always take a database backup before running migrations. The system will attempt to apply these in order.</p>
                <button type="button" class="ra-btn ra-btn-primary" data-bs-toggle="modal" data-bs-target="#ra-run-update-modal">
                    <i class="bi bi-play-circle me-1"></i> Run Migrations Now
                </button>
            </div>
        </div>

        <div class="col-md-5 mb-4">
            <div class="ra-card h-100">
                <h2 class="h5 mb-3">Schedule Maintenance</h2>
                <p class="small muted">Instead of running updates immediately, you can schedule them. A banner will appear on the storefront 24 hours before the scheduled time.</p>
                
                <form method="POST" action="/commerce/ca-admin/update/schedule">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Scheduled Time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control" value="<?= e($scheduledAt ? date('Y-m-d\TH:i', strtotime($scheduledAt)) : '') ?>">
                        <div class="form-text">Leave blank to clear the schedule.</div>
                    </div>
                    <button type="submit" class="ra-btn ra-btn-outline">Save Schedule</button>
                </form>
            </div>
        </div>
    </div>

    <?= view_partial('admin/update/_run-modal') ?>

<?php endif; ?>
