<?php
/**
 * Confirmation modal for running pending migrations.
 * Uses Bootstrap's native modal API so z-index and backdrop
 * stacking are handled correctly.
 */
?>
<div class="modal fade" id="ra-run-update-modal" tabindex="-1" aria-labelledby="ra-run-update-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="background:#1a2235; color:#e5e7eb; border:1px solid #2d3748;">
            <div class="modal-header" style="border-bottom:1px solid #2d3748;">
                <h5 class="modal-title" id="ra-run-update-modal-label">Confirm Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to run the pending migrations?</p>
                <div style="background:rgba(239,68,68,0.1); border-left:3px solid #ef4444; padding:0.75rem 1rem; border-radius:4px; font-size:0.85rem; color:#fca5a5;">
                    <strong>Warning:</strong> This will alter your database schema. Ensure you have a recent backup. This action cannot be undone.
                </div>
                <div id="ra-run-migrations-status" style="display:none; margin-top:0.75rem; padding:0.6rem 0.8rem; background:rgba(245,158,11,0.1); border-left:3px solid #f59e0b; border-radius:4px; font-size:0.8rem; color:#f59e0b;">
                    <span id="ra-run-migrations-text">Running migrations... please wait.</span>
                </div>
            </div>
            <div class="modal-footer" style="border-top:1px solid #2d3748;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="/commerce/ca-admin/update/run" id="ra-run-migrations-form" style="margin:0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" id="ra-run-migrations-btn">Yes, run migrations</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ra-run-migrations-form');
    var btn = document.getElementById('ra-run-migrations-btn');
    var status = document.getElementById('ra-run-migrations-status');
    var text = document.getElementById('ra-run-migrations-text');

    if (!form || !btn) return;

    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.textContent = 'Running...';
        status.style.display = 'block';

        setTimeout(function () {
            text.textContent = 'Still running. Migrations can take up to a minute on the first run.';
        }, 5000);

        setTimeout(function () {
            text.textContent = 'Taking longer than usual. You can close this tab; the migration will continue on the server.';
        }, 30000);
    });
});
</script>
