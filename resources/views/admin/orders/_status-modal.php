<?php
/**
 * Order status transition modal.
 *
 * Opened from the action buttons in admin/orders/show.php via
 * data-ra-transition-open. The button carries:
 *   data-transition-to        the target status
 *   data-transition-label     the human label for the target
 *   data-order-id             the order id (for the form action)
 *   data-order-number         the order number (for the dialog)
 *
 * One modal is reused for every transition. The script reads
 * the attributes and sets the form's action and the hidden
 * status field.
 *
 * @var \CorporateAid\Commerce\Models\Order $order
 */
$order = $order ?? null;
if ($order === null) {
    return;
}
?>
<div id="ra-order-status-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-modal-close></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-order-status-title">
        <div class="ra-modal-header">
            <h3 id="ra-order-status-title" class="h6 mb-0">Move order</h3>
            <button type="button" class="ra-icon-btn" data-ra-modal-close aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" id="ra-order-status-form" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="status" id="ra-order-status-target" value="">
            <div class="ra-modal-body">
                <p class="mb-3">
                    Move order <strong><?= e($order->orderNumber) ?></strong> to
                    <strong id="ra-order-status-label-target">the new status</strong>?
                </p>
                <div class="mb-3">
                    <label for="ra-order-status-note" class="form-label">Note (optional)</label>
                    <textarea id="ra-order-status-note"
                              name="note"
                              class="form-control"
                              rows="3"
                              maxlength="500"
                              placeholder="Reason for this transition (max 500 characters)."></textarea>
                    <div class="form-text">Visible to admins in the order status history.</div>
                </div>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-modal-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modalId    = 'ra-order-status-modal';
    var formId     = 'ra-order-status-form';
    var targetId   = 'ra-order-status-target';
    var labelId    = 'ra-order-status-label-target';

    function openModal(orderId, targetStatus, targetLabel) {
        var modal = document.getElementById(modalId);
        var form  = document.getElementById(formId);
        var target = document.getElementById(targetId);
        var label = document.getElementById(labelId);

        form.action = '/commerce/ca-admin/orders/' + orderId + '/status';
        target.value = targetStatus;
        label.textContent = targetLabel;
        modal.style.display = 'flex';
    }

    function closeModal() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-ra-transition-open]');
        if (trigger) {
            e.preventDefault();
            openModal(
                trigger.getAttribute('data-order-id'),
                trigger.getAttribute('data-transition-to'),
                trigger.getAttribute('data-transition-label')
            );
            return;
        }
        if (e.target.closest('[data-ra-modal-close]')) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });
})();
</script>
