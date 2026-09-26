<?php
/**
 * Reject customer modal.
 *
 * Opened from index.php via:
 *   onclick="RA_RejectCustomer.open(id, name)"
 *
 * The form's action is a template with __ID__ replaced at open
 * time. This avoids rendering one form per row.
 *
 * Closes via:
 *   - X button
 *   - Cancel button
 *   - Backdrop click
 *   - Escape key
 */
?>

<div id="ra-reject-customer-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-reject-close></div>
    <div class="ra-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ra-reject-modal-title">
        <div class="ra-modal-header">
            <h2 class="h6 mb-0" id="ra-reject-modal-title">Reject customer</h2>
            <button type="button" class="ra-icon-btn" aria-label="Close" data-ra-reject-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" id="ra-reject-customer-form" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="return" id="ra-reject-return" value="pending">

            <div class="ra-modal-body">
                <p class="muted small mb-3">
                    You are about to reject
                    <strong id="ra-reject-customer-name">this customer</strong>.
                </p>
                <p class="muted small mb-3">
                    The reason is stored for the audit trail and is visible
                    to admins only. The customer sees a generic
                    "pending review" message, not this text.
                </p>

                <label for="ra-reject-reason" class="form-label small mb-1">
                    Reason <span class="text-danger">*</span>
                </label>
                <textarea id="ra-reject-reason"
                          name="reason"
                          class="form-control form-control-sm"
                          rows="4"
                          maxlength="500"
                          required
                          placeholder="Why is this customer being rejected?"></textarea>
                <div class="muted small mt-1">
                    Maximum 500 characters.
                </div>
            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-reject-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-x-lg me-1"></i> Reject customer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal      = document.getElementById('ra-reject-customer-modal');
    var form       = document.getElementById('ra-reject-customer-form');
    var nameEl     = document.getElementById('ra-reject-customer-name');
    var reasonEl   = document.getElementById('ra-reject-reason');
    var returnEl   = document.getElementById('ra-reject-return');

    if (!modal || !form) return;

    var ACTION_TEMPLATE = '/commerce/ca-admin/customer-approvals/__ID__/reject';

    function open(id, name) {
        // Reset state on every open
        reasonEl.value = '';
        nameEl.textContent = name || 'this customer';

        // Read the current filter from the URL so the controller
        // can redirect back to the same tab after the POST.
        var params = new URLSearchParams(window.location.search);
        var status = params.get('status') || 'pending';
        returnEl.value = status;

        // Set the form action with the real customer id
        form.setAttribute('action', ACTION_TEMPLATE.replace('__ID__', String(id)));

        modal.style.display = '';
        document.body.style.overflow = 'hidden';

        // Focus the reason field after the modal is visible
        setTimeout(function () {
            if (reasonEl) reasonEl.focus();
        }, 50);
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    // Wire close triggers
    modal.querySelectorAll('[data-ra-reject-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    // Escape closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            close();
        }
    });

    // Expose the global API
    window.RA_RejectCustomer = {
        open: open,
        close: close
    };
})();
</script>
