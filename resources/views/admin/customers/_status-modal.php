<?php
/**
 * Account state change modal.
 *
 * Included from admin/customers/show.php via view_partial.
 *
 * Context (from caller):
 *   $customer Customer
 *
 * Renders conditional actions:
 *   - Enable account    when the customer is not active
 *   - Disable account   when the customer is not disabled
 *   - Archive customer  when the customer is not archived
 *
 * Each action POSTs to AdminCustomerController::changeStatus().
 */
$customer = $customer ?? null;

if ($customer === null) {
    return;
}

$isActive   = $customer->status === 'active';
$isDisabled = $customer->status === 'disabled';
$isArchived = $customer->status === 'archived';
$actionUrl  = '/commerce/ca-admin/customers/' . (int) $customer->id . '/status';
?>

<div class="modal fade" id="ra-customer-status-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change account state</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="muted small mb-3">
                    Disabling prevents the customer from signing in
                    or checking out. Their data is preserved. Archiving
                    hides them from the customer list without deleting
                    their history.
                </p>
                <div class="d-flex flex-column gap-2">

                    <?php if (!$isActive): ?>
                        <form method="POST" action="<?= e($actionUrl) ?>" class="d-grid">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="active">
                            <button type="submit" class="ra-btn ra-btn-primary">
                                <i class="bi bi-check-lg me-1"></i>
                                Enable account
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$isDisabled): ?>
                        <form method="POST" action="<?= e($actionUrl) ?>" class="d-grid">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="disabled">
                            <button type="submit" class="ra-btn ra-btn-danger">
                                <i class="bi bi-slash-circle me-1"></i>
                                Disable account
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (!$isArchived): ?>
                        <form method="POST" action="<?= e($actionUrl) ?>" class="d-grid">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="archived">
                            <button type="submit" class="ra-btn ra-btn-outline"
                                    onclick="return confirm('Archive this customer? Their history is preserved but they will be hidden from the customer list.');">
                                <i class="bi bi-archive me-1"></i>
                                Archive customer
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
