<?php
/**
 * Coupon delete confirmation modal.
 *
 * Opened via JS: RA_DeleteCoupon.open(id, code)
 *
 * The modal calls the coupon delete-info endpoint before
 * enabling the submit button. If the coupon has redemptions,
 * the endpoint returns can_delete: false with a blocker
 * message; the modal replaces the Delete button with a note
 * pointing to the edit page so the merchant can disable the
 * coupon instead.
 */
?>
<div id="ra-coupon-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-close-coupon-modal></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-coupon-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-coupon-delete-title" class="h6 mb-0">Delete coupon</h3>
            <button type="button" class="ra-icon-btn" data-ra-close-coupon-modal aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete coupon <strong id="ra-coupon-delete-code">this coupon</strong>?
            </p>
            <div id="ra-coupon-delete-info" class="muted small"></div>
            <div id="ra-coupon-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-close-coupon-modal>
                Cancel
            </button>
            <form method="POST" id="ra-coupon-delete-form" action="" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger" id="ra-coupon-delete-submit">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modalId = 'ra-coupon-delete-modal';
    var codeEl, infoEl, warningEl, formEl, submitEl;

    function init() {
        codeEl     = document.getElementById('ra-coupon-delete-code');
        infoEl     = document.getElementById('ra-coupon-delete-info');
        warningEl  = document.getElementById('ra-coupon-delete-warning');
        formEl     = document.getElementById('ra-coupon-delete-form');
        submitEl   = document.getElementById('ra-coupon-delete-submit');
    }

    function open(id, code) {
        if (!codeEl) init();

        document.getElementById(modalId).style.display = 'flex';
        codeEl.textContent = code || 'this coupon';
        infoEl.textContent = 'Checking usage...';
        warningEl.style.display = 'none';
        warningEl.textContent = '';
        submitEl.disabled = true;
        submitEl.style.opacity = '0.5';

        formEl.action = '/commerce/ca-admin/coupons/' + id + '/delete';

        fetch('/commerce/ca-admin/coupons/' + id + '/delete-info', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) {
                infoEl.textContent = 'Could not load coupon details.';
                return;
            }

            if (data.can_delete) {
                infoEl.textContent = 'This coupon has never been redeemed.';
                submitEl.disabled = false;
                submitEl.style.opacity = '1';
            } else {
                infoEl.textContent = '';
                warningEl.textContent = data.blockers && data.blockers.length
                    ? data.blockers.join(' ')
                    : 'This coupon cannot be deleted.';
                warningEl.style.display = 'block';
                submitEl.disabled = true;
                submitEl.style.opacity = '0.5';
            }
        })
        .catch(function () {
            infoEl.textContent = 'Could not load coupon details.';
        });
    }

    function close() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.querySelectorAll('[data-ra-close-coupon-modal]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    // Delegated click on trigger buttons. Same reason as the
    // products modal - the code is HTML-escaped, not JSON
    // encoded, so no quoting rules to violate.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ra-delete-coupon]');
        if (!btn) return;
        e.preventDefault();
        var id   = btn.getAttribute('data-coupon-id');
        var code = btn.getAttribute('data-coupon-code') || 'this coupon';
        open(id, code);
    });
})();
</script>
