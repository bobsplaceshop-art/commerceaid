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

<div class="ra-modal" id="ra-customer-group-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_NavModal.close('ra-customer-group-modal')"></div>
    <div class="ra-modal-panel" role="dialog" aria-modal="true">
        <form method="POST" action="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/group">
            <?= csrf_field() ?>
            <div class="ra-modal-header">
                <h2 class="h6 mb-0">Change customer group</h2>
                <button type="button" class="ra-icon-btn" onclick="RA_NavModal.close('ra-customer-group-modal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="ra-modal-body">
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
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" onclick="RA_NavModal.close('ra-customer-group-modal')">Cancel</button>
                <button type="submit" class="ra-btn ra-btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
