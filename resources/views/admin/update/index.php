<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">System Update</h1>
        <p class="muted mb-0">Apply pending database changes or manage the storefront status.</p>
    </div>
</div>

<?php if (!empty($pending)): ?>
    <div class="ra-alert ra-alert-warning mb-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Database updates pending.</strong> You must run these migrations to ensure the application functions correctly.
    </div>
<?php endif; ?>

<div class="row">
    <!-- Status Control -->
    <div class="col-md-6 mb-4">
        <div class="ra-card h-100">
            <h2 class="h5 mb-3">Storefront Status</h2>
            <form method="POST" action="/commerce/ca-admin/update/status">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="store_status" class="form-select mb-2">
                        <option value="live" <?= $storeStatus === 'live' ? 'selected' : '' ?>>Live (Accepting Orders)</option>
                        <option value="coming_soon" <?= $storeStatus === 'coming_soon' ? 'selected' : '' ?>>Coming Soon (Pre-launch holding page)</option>
                        <option value="maintenance" <?= $storeStatus === 'maintenance' ? 'selected' : '' ?>>Maintenance (503 Unavailable)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Public Message</label>
                    <textarea name="status_message" class="form-control" rows="3" placeholder="We are working hard to launch our new store. Please check back soon!"><?= e($statusMessage) ?></textarea>
                </div>
                <button type="submit" class="ra-btn ra-btn-primary">Save Status</button>
            </form>
        </div>
    </div>

    <!-- Migration Runner -->
    <div class="col-md-6 mb-4">
        <div class="ra-card h-100">
            <h2 class="h5 mb-3">Database Updates</h2>
            <?php if (empty($pending)): ?>
                <p class="text-success mb-0"><i class="bi bi-check-circle me-1"></i> System is up to date. No pending migrations.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush mb-3" style="max-height: 150px; overflow-y: auto;">
                    <?php foreach ($pending as $file): ?>
                        <li class="list-group-item bg-transparent text-light border-secondary px-0 font-monospace small">
                            <?= e($file) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="small muted mb-3">Always take a database backup before running migrations.</p>
                <button type="button" class="ra-btn ra-btn-primary" data-bs-toggle="modal" data-bs-target="#ra-run-update-modal">
                    <i class="bi bi-play-circle me-1"></i> Run Migrations Now
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Schedule Maintenance -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="ra-card">
            <h2 class="h5 mb-3">Schedule Maintenance</h2>
            <p class="small muted">You can schedule a future maintenance window. A banner will appear on the storefront 24 hours before the scheduled time.</p>
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
