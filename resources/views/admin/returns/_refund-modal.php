<?php
/**
 * Process refund modal. Stage 008H Session 3.
 *
 * Confirmation-only. The refund amount is computed on the
 * show page from the current line quantities and passed to
 * the modal via data-amount. The controller's refund action
 * recomputes the same amount from the persisted lines inside
 * the transaction; the modal's number is a preview.
 *
 * Posts to /commerce/ca-admin/returns/{id}/refund.
 */
?>

<div id="ra-refund-return-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-refund-return-close></div>
    <div class="ra-modal-panel" role="dialog" aria-modal="true" aria-labelledby="ra-refund-return-title">
        <div class="ra-modal-header">
            <h2 class="h6 mb-0" id="ra-refund-return-title">Process refund</h2>
            <button type="button" class="ra-icon-btn" aria-label="Close" data-ra-refund-return-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" id="ra-refund-return-form" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="return" id="ra-refund-return-filter" value="open">

            <div class="ra-modal-body">
                <p class="muted small mb-3">
                    You are about to refund
                    <strong id="ra-refund-return-number">this return</strong>.
                </p>

                <div class="ra-card mb-3" style="background:var(--ra-surface-2);">
                    <div class="ra-card-body py-3">
                        <div class="muted small" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Refund amount</div>
                        <div style="font-weight:600;font-size:1.4rem;">
                            <span id="ra-refund-amount">0.00</span>
                            <span id="ra-refund-currency" class="muted" style="font-size:.9rem;">EUR</span>
                        </div>
                    </div>
                </div>

                <p class="muted small mb-0" id="ra-refund-provider-note">
                    This records the refund against the order and updates
                    the customer's order history. The actual money movement
                    is handled by the payment provider in a later stage.
                </p>
            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-refund-return-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-cash-coin me-1"></i> Process refund
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal     = document.getElementById('ra-refund-return-modal');
    var form      = document.getElementById('ra-refund-return-form');
    var numberEl  = document.getElementById('ra-refund-return-number');
    var amountEl  = document.getElementById('ra-refund-amount');
    var currEl    = document.getElementById('ra-refund-currency');
    var filterEl  = document.getElementById('ra-refund-return-filter');

    if (!modal || !form) return;

    var ACTION_TEMPLATE = '/commerce/ca-admin/returns/__ID__/refund';

    var PROVIDER_NOTES = {
        'stripe': 'This refund will be sent to Stripe for processing. ' +
                  'The customer receives the money within 5-10 business days.',
        'paypal': 'This refund will be sent to PayPal for processing. ' +
                  'The customer receives the money within 3-5 business days.'
    };
    var OFFLINE_NOTE = 'This records the refund against the order and ' +
                       "updates the customer's order history.";

    var noteEl = document.getElementById('ra-refund-provider-note');

    function open(id, number, amount, currency, provider) {
        numberEl.textContent = number || 'this return';
        amountEl.textContent = amount || '0.00';
        currEl.textContent   = currency || 'EUR';

        if (noteEl) {
            noteEl.textContent = PROVIDER_NOTES[provider] || OFFLINE_NOTE;
        }

        var params = new URLSearchParams(window.location.search);
        filterEl.value = params.get('return') || 'open';

        form.setAttribute('action', ACTION_TEMPLATE.replace('__ID__', String(id)));

        modal.style.display = '';
        document.body.style.overflow = 'hidden';
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    modal.querySelectorAll('[data-ra-refund-return-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            close();
        }
    });

    window.RA_RefundReturn = { open: open, close: close };

    document.querySelectorAll('[data-ra-refund-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            open(
                btn.getAttribute('data-request-id'),
                btn.getAttribute('data-request-number'),
                btn.getAttribute('data-amount'),
                btn.getAttribute('data-currency'),
                btn.getAttribute('data-provider') || ''
            );
        });
    });
})();
</script>
