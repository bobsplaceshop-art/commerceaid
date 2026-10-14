<?php
/**
 * One sellable unit row in the per-product inventory table.
 *
 * Vars (from InventoryItemController::show):
 *   @var array<string,mixed> $u  decorated unit row
 */
$u = $u ?? [];

$inventoryId = (int) ($u['inventory_id'] ?? 0);
$label       = (string) ($u['variant_summary'] ?? '');
$sku         = (string) ($u['variant_sku'] ?? ($u['product_sku'] ?? ''));
$quantity    = (int) ($u['quantity'] ?? 0);
$reserved    = (int) ($u['reserved'] ?? 0);
$available   = (int) ($u['available'] ?? 0);
$statusLabel = (string) ($u['status_label'] ?? 'In stock');
$statusClass = (string) ($u['status_class'] ?? 'ra-badge-success');
$isSimple    = ((int) ($u['variant_id'] ?? 0)) === 0;
?>

<tr data-inventory-id="<?= $inventoryId ?>">
    <td data-label="Variant" data-col="full">
        <?php if ($isSimple): ?>
            <span class="muted small">Simple product</span>
        <?php elseif ($label !== ''): ?>
            <?= e($label) ?>
        <?php else: ?>
            <span class="muted small">Variant #<?= (int) ($u['variant_id'] ?? 0) ?></span>
        <?php endif; ?>
    </td>
    <td data-label="SKU" data-col="half" class="ra-hide-sm">
        <code class="muted small"><?= e($sku !== '' ? $sku : '—') ?></code>
    </td>
    <td data-label="On hand" data-col="half" class="text-end" style="width:140px;">
        <input type="number"
               class="form-control text-end"
               name="adjustments[<?= $inventoryId ?>]"
               value="<?= $quantity ?>"
               min="0"
               step="1"
               inputmode="numeric"
               aria-label="Quantity on hand">
    </td>
    <td data-label="Reserved" data-col="half" class="text-end ra-hide-sm"><?= $reserved ?></td>
    <td data-label="Available" data-col="half" class="text-end ra-hide-sm">
        <strong><?= $available ?></strong>
    </td>
    <td data-label="Status" data-col="half">
        <span class="ra-badge <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
    </td>
</tr>
