<?php
/**
 * Group change modal.
 *
 * Included from admin/customers/show.php via view_partial.
 *
 * Context (from caller):
 *   $customer     Customer
 *   $groupOptions array<int,array{id:int,name:string,is_default:int}>
 *   $group        ?CustomerGroup   (current group, may be null)
 *
 * POSTs to AdminCustomerController::changeGroup().
 */
$customer     = $customer     ?? null;
$groupOptions = $groupOptions ?? [];
$group        = $group        ?? null;

if ($customer === null) {
    return;
}

$currentGid = (int) ($customer->customerGroupId ?? 0);
?>

<div class="modal fade" id="ra-customer-group-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST"
                  action="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/group">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Change customer group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="muted small mb-3">
                        Group controls the pricing, net terms, and
                        approval behaviour this customer sees.
                    </p>
                    <label class="muted small" for="cg-group">Group</label>
                    <select id="cg-group" name="group_id" class="form-select" required>
                        <option value="">Select a group…</option>
                        <?php foreach ($groupOptions as $g): ?>
                            <option value="<?= (int) $g['id'] ?>"
                                    <?= $currentGid === (int) $g['id'] ? 'selected' : '' ?>>
                                <?= e($g['name']) ?>
                                <?php if (!empty($g['is_default'])): ?>
                                    (default)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ra-btn ra-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="ra-btn ra-btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
