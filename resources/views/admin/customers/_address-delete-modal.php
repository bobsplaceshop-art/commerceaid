<?php
/**
 * Address delete confirmation modal.
 *
 * One modal per address. Included inside the address card loop on
 * admin/customers/show.php. The modal ID embeds the address id so
 * each modal is unique on the page.
 *
 * Context (from caller):
 *   $customer Customer
 *   $address  CustomerAddress
 *
 * POSTs to AdminCustomerController::addressDelete().
 */
$customer = $customer ?? null;
$address  = $address  ?? null;

if ($customer === null || $address === null) {
    return;
}

$modalId = 'ra-address-delete-modal-' . (int) $address->id;
$title   = $address->label !== null && $address->label !== ''
    ? $address->label
    : ucfirst($address->type);
?>

<div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST"
                  action="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/addresses/<?= (int) $address->id ?>/delete">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Delete address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Delete <strong><?= e($title) ?></strong>?
                    </p>
                    <div class="border rounded p-3 mb-3" style="border-color:var(--ra-border,#e5e1d8) !important;font-size:.9rem;">
                        <?= e($address->oneLine()) ?>
                    </div>
                    <?php if ($address->isDefault): ?>
                        <div class="ra-alert ra-alert-error mb-0" style="font-size:.9rem;">
                            This is the customer's default
                            <?= $address->isBilling() && $address->isShipping() ? 'billing and shipping' : ($address->isBilling() ? 'billing' : 'shipping') ?>
                            address. Deleting it will clear the default and the customer will need to pick a new one.
                        </div>
                    <?php else: ?>
                        <p class="muted small mb-0">
                            This cannot be undone.
                        </p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ra-btn ra-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="ra-btn ra-btn-danger">
                        Delete address
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
