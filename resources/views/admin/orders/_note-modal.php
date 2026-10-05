<?php
/**
 * Order internal note modal.
 *
 * Opened from the "Add note" buttons in admin/orders/show.php via
 * data-ra-note-open. The button carries data-order-id.
 */
?>
<div id="ra-order-note-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-note-modal-close></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-order-note-title">
        <div class="ra-modal-header">
            <h3 id="ra-order-note-title" class="h6 mb-0">Add internal note</h3>
            <button type="button" class="ra-icon-btn" data-ra-note-modal-close aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" id="ra-order-note-form" action="">
            <?= csrf_field() ?>
            <div class="ra-modal-body">
                <p class="mb-3">
                    Note on order <strong id="ra-order-note-number">this order</strong>.
                </p>
                <div class="mb-3">
                    <label for="ra-order-note-body" class="form-label">Note <span class="req">*</span></label>
                    <textarea id="ra-order-note-body"
                              name="note"
                              class="form-control"
                              rows="5"
                              maxlength="5000"
                              required
                              placeholder="Internal note for this order. Not visible to the customer."></textarea>
                    <div class="form-text">Max 5000 characters. Internal only.</div>
                </div>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-note-modal-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-journal-plus me-1"></i> Save note
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modalId  = 'ra-order-note-modal';
    var formId   = 'ra-order-note-form';
    var numberId = 'ra-order-note-number';

    function openModal(orderId, orderNumber) {
        var modal  = document.getElementById(modalId);
        var form   = document.getElementById(formId);
        var number = document.getElementById(numberId);

        form.action = '/commerce/ca-admin/orders/' + orderId + '/note';
        number.textContent = orderNumber;
        modal.style.display = 'flex';

        var body = document.getElementById('ra-order-note-body');
        if (body) body.focus();
    }

    function closeModal() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-ra-note-open]');
        if (trigger) {
            e.preventDefault();
            openModal(
                trigger.getAttribute('data-order-id'),
                trigger.getAttribute('data-order-number')
            );
            return;
        }
        if (e.target.closest('[data-ra-note-modal-close]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
