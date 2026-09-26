<?php
/**
 * Reset-to-pending modal.
 *
 * Opened from index.php via:
 *   onclick='RA_ResetCustomer.open(id, name)'
 *
 * Unlike reject, the reason is optional here. The admin is
 * restoring access, not denying it. The reason field is present
 * so that when there IS a reason, it can be recorded on the
 * history row.
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

<div id="ra-reset-customer-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-reset-close></div>
    <div class="ra-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ra-reset-modal-title">
        <div class="ra-modal-header">
            <h2 class="h6 mb-0" id="ra-reset-modal-title">Reset to pending review</h2>
            <button type="button" class="ra-icon-btn" aria-label="Close" data-ra-reset-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" id="ra-reset-customer-form" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="return" id="ra-reset-return" value="pending">

            <div class="ra-modal-body">
                <p class="muted small mb-3">
                    You are about to reset
                    <strong id="ra-reset-customer-name">this customer</strong>
                    to pending review.
                </p>
                <p class="muted small mb-3">
                    They will need to be approved again before they can place
                    orders. Any prior approval or rejection state is cleared
                    from the customer record, but the full history is
                    preserved.
                </p>

                <label for="ra-reset-reason" class="form-label small mb-1">
                    Reason <span class="muted">(optional)</span>
                </label>
                <textarea id="ra-reset-reason"
                          name="reason"
                          class="form-control form-control-sm"
                          rows="3"
                          maxlength="500"
                          placeholder="For example: customer appealed, re-reviewing."></textarea>
                <div class="muted small mt-1">
                    Recorded on the history timeline. Admin-only.
                </div>
            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-reset-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to pending
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal    = document.getElementById('ra-reset-customer-modal');
    var form     = document.getElementById('ra-reset-customer-form');
    var nameEl   = document.getElementById('ra-reset-customer-name');
    var reasonEl = document.getElementById('ra-reset-reason');
    var returnEl = document.getElementById('ra-reset-return');

    if (!modal || !form) return;

    var ACTION_TEMPLATE = '/commerce/ca-admin/customer-approvals/__ID__/reset';

    function open(id, name) {
        reasonEl.value = '';
        nameEl.textContent = name || 'this customer';

        var params = new URLSearchParams(window.location.search);
        var status = params.get('status') || 'pending';
        returnEl.value = status;

        form.setAttribute('action', ACTION_TEMPLATE.replace('__ID__', String(id)));

        modal.style.display = '';
        document.body.style.overflow = 'hidden';

        setTimeout(function () {
            if (reasonEl) reasonEl.focus();
        }, 50);
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    modal.querySelectorAll('[data-ra-reset-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            close();
        }
    });

    window.RA_ResetCustomer = {
        open: open,
        close: close
    };
})();
</script>
