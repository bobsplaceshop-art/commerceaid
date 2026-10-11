<?php
/**
 * Reject return request modal. Stage 008H Session 3.
 *
 * Opened from show.php via:
 *   onclick / data-attribute -> window.RA_RejectReturn.open(id, number)
 *
 * Posts to /commerce/ca-admin/returns/{id}/reject with the
 * reason and the current filter back-pointer.
 */
?>

<div id="ra-reject-return-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-reject-return-close></div>
    <div class="ra-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ra-reject-return-title">
        <div class="ra-modal-header">
            <h2 class="h6 mb-0" id="ra-reject-return-title">Reject return</h2>
            <button type="button" class="ra-icon-btn" aria-label="Close" data-ra-reject-return-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" id="ra-reject-return-form" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="return" id="ra-reject-return-filter" value="open">

            <div class="ra-modal-body">
                <p class="muted small mb-3">
                    You are about to reject
                    <strong id="ra-reject-return-number">this request</strong>.
                </p>
                <p class="muted small mb-3">
                    The reason is stored for the audit trail and is visible
                    to admins only. The customer sees a generic status update,
                    not this text.
                </p>

                <label for="ra-reject-return-reason" class="form-label small mb-1">
                    Reason <span class="text-danger">*</span>
                </label>
                <textarea id="ra-reject-return-reason"
                          name="reason"
                          class="form-control form-control-sm"
                          rows="4"
                          maxlength="500"
                          required
                          placeholder="Why is this return being rejected?"></textarea>
                <div class="muted small mt-1">
                    Maximum 500 characters.
                </div>
            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-reject-return-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-x-lg me-1"></i> Reject return
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal     = document.getElementById('ra-reject-return-modal');
    var form      = document.getElementById('ra-reject-return-form');
    var numberEl  = document.getElementById('ra-reject-return-number');
    var reasonEl  = document.getElementById('ra-reject-return-reason');
    var filterEl  = document.getElementById('ra-reject-return-filter');

    if (!modal || !form) return;

    var ACTION_TEMPLATE = '/commerce/ca-admin/returns/__ID__/reject';

    function open(id, number) {
        reasonEl.value = '';
        numberEl.textContent = number || 'this request';

        // Preserve the filter back-pointer from the URL so the
        // controller can redirect to the same tab on validation
        // error.
        var params = new URLSearchParams(window.location.search);
        filterEl.value = params.get('return') || 'open';

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

    modal.querySelectorAll('[data-ra-reject-return-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            close();
        }
    });

    window.RA_RejectReturn = { open: open, close: close };

    // Wire caller buttons on show.php.
    document.querySelectorAll('[data-ra-reject-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            open(
                btn.getAttribute('data-request-id'),
                btn.getAttribute('data-request-number')
            );
        });
    });
})();
</script>
