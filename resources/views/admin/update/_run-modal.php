<div class="ra-modal-backdrop" id="ra-run-update-modal" style="display:none;">
    <div class="ra-modal">
        <div class="ra-modal-header">
            <h2 class="ra-modal-title">Confirm Update</h2>
            <button type="button" class="ra-modal-close" data-ra-modal-close="ra-run-update-modal">&times;</button>
        </div>
        <div class="ra-modal-body">
            <p>Are you sure you want to run the pending migrations?</p>
            <div class="ra-alert ra-alert-danger">
                <strong>Warning:</strong> This will alter your database schema. Ensure you have a recent backup. This action cannot be undone.
            </div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-modal-close="ra-run-update-modal">Cancel</button>
            <form method="POST" action="/commerce/ca-admin/update/run" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">Yes, run migrations</button>
            </form>
        </div>
    </div>
</div>
