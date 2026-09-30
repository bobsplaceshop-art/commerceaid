<?php
/**
 * One slot in the variant-images card. Renders the currently
 * assigned image (or an empty state) for a single color value.
 *
 * Scope (from _tab_images.php):
 *   $slot  array{
 *     axis_name: string,
 *     attribute_value_id: int,
 *     value: string,
 *     color_hex: ?string,
 *     assigned_image: ?array{image: ProductImage, media: MediaItem}
 *   }
 *
 * The slot carries:
 *   - A thumbnail or placeholder fill.
 *   - The axis and value label ("Color: Red").
 *   - A hidden input: variant_images[<attribute_value_id>] = <file_id>
 *     (0 means no assignment).
 *   - An "Assign" button (opens the picker modal in variant mode).
 *   - A "Remove" button (sets the hidden input to 0 and hides the
 *     thumbnail).
 */

$slot = $slot ?? null;
if ($slot === null) {
    return;
}

$valueId    = (int) $slot['attribute_value_id'];
$value      = (string) $slot['value'];
$axisName   = (string) $slot['axis_name'];
$hex        = $slot['color_hex'] ?? null;
$assigned   = $slot['assigned_image'] ?? null;

$assignedFileId  = 0;
$assignedMedia   = null;
if ($assigned !== null) {
    $assignedFileId = (int) ($assigned['image']->fileId ?? 0);
    $assignedMedia  = $assigned['media'] ?? null;
}

$hasImage = $assignedFileId > 0 && $assignedMedia !== null;
$imageUrl = $hasImage
    ? $assignedMedia->variantUrl('md', 'webp')
    : null;
?>
<div class="ra-variant-slot<?= $hasImage ? ' has-image' : '' ?>"
     data-ra-variant-slot
     data-attribute-value-id="<?= $valueId ?>">

    <div class="ra-variant-slot-thumb" data-ra-variant-slot-thumb
         data-hex="<?= e((string) ($hex ?? '')) ?>">
        <?php if ($hasImage): ?>
            <img src="<?= e($imageUrl) ?>" alt="" loading="lazy">
        <?php elseif ($hex !== null && $hex !== ''): ?>
            <span class="ra-variant-slot-fill"
                  style="background-color: <?= e($hex) ?>"></span>
        <?php else: ?>
            <div class="ra-variant-slot-empty">
                <i class="bi bi-image" aria-hidden="true"></i>
            </div>
        <?php endif; ?>
    </div>

    <div class="ra-variant-slot-body">
        <div class="ra-variant-slot-label">
            <?= e($axisName) ?>: <strong><?= e($value) ?></strong>
        </div>

        <?php /* Hidden input carries the file id, or 0 when
               unassigned. The JS sets this when the operator
               picks or removes an image. */ ?>
        <input type="hidden"
               name="variant_images[<?= $valueId ?>]"
               value="<?= $assignedFileId ?>"
               data-ra-variant-file-input>

        <div class="ra-variant-slot-actions">
            <button type="button"
                    class="ra-btn ra-btn-outline ra-btn-sm"
                    data-ra-variant-assign
                    data-attribute-value-id="<?= $valueId ?>">
                <i class="bi bi-image me-1" aria-hidden="true"></i>
                <?= $hasImage ? 'Change' : 'Assign' ?>
            </button>

            <button type="button"
                    class="ra-btn ra-btn-outline ra-btn-sm"
                    data-ra-variant-remove
                    data-attribute-value-id="<?= $valueId ?>"
                    <?= $hasImage ? '' : 'hidden' ?>>
                <i class="bi bi-x-lg me-1" aria-hidden="true"></i>
                Remove
            </button>
        </div>
    </div>
</div>
