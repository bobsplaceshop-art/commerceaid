<?php
/**
 * Product editor - Images tab.
 *
 * Inherits scope from edit.php plus the extra variables passed
 * by ProductController::showEdit():
 *
 *   $productImages  array<array{image: ProductImage, media: MediaItem}>
 *                   Ordered by sort_order. Empty on create or when
 *                   the product has no images.
 *
 *   $mediaLibrary   array<MediaItem>
 *                   The store's full media library, for the picker.
 *
 *   $variantAxes    array<array{attribute_id:int, slug:string,
 *                              name:string, is_color_axis:bool,
 *                              values:array<array{
 *                                attribute_value_id:int,
 *                                value:string,
 *                                color_hex:?string,
 *                                sort_order:int,
 *                                variant_ids:array<int,int>
 *                              }>}>
 *                   The shape returned by
 *                   ProductVariantRepository::swatchAxesFor().
 *                   Each value carries attribute_value_id (NOT id).
 *                   Empty for simple products.
 *
 *   $variantImages  array<int, array{image: ProductImage, media: MediaItem}>
 *                   Keyed by attribute_value_id. The currently
 *                   assigned swatch image for each color value.
 *                   Empty when no variant images are assigned.
 *
 * The tab is a container. It renders:
 *
 *   1. An upload zone (JS wired, no server-side form action).
 *   2. A reorderable gallery grid, one tile per image.
 *   3. A picker row that opens the media library modal.
 *   4. A variant-images card (one slot per color value).
 *   5. An empty state when there are no images.
 *
 * All behavior lives in public/assets/js/product-images-admin.js.
 *
 * Every tile partial writes these hidden / form inputs inside
 * the main form (#ra-product-form):
 *
 *   images[<imageId>][sort_order]
 *   images[<imageId>][alt_text]
 *   images[<imageId>][is_primary]
 *   images[<imageId>][attribute_value_id]
 *
 *   new_images_order[]                (file ids, appended by JS)
 *   new_images_meta[<fileId>][...]    (per-new-image metadata)
 *   delete_images[]                   (image ids to remove)
 *   variant_images[<attribute_value_id>]  (variant assignments)
 *
 * The write path is ProductController::syncImages().
 *
 * IMPORTANT - variable scope:
 *
 *   This file is require'd by edit.php in the same scope as
 *   every other tab (_tab_basic, _tab_pricing, _tab_inventory,
 *   _tab_shipping, _tab_seo). Any variable defined here shadows
 *   the outer scope for the remaining requires.
 *
 *   edit.php defines $val (a closure) and $valBool (a closure)
 *   at the top. Every tab reads them. Do NOT assign to $val or
 *   $valBool in this file. The colorValues loop uses
 *   $axisValue as its iterator for exactly this reason.
 */

$productImages = $productImages ?? [];
$mediaLibrary  = $mediaLibrary  ?? [];
$variantAxes   = $variantAxes   ?? [];
$variantImages = $variantImages ?? [];

$isEmpty = count($productImages) === 0;

// Build the list of color-axis values. Only axes whose
// is_color_axis flag is true are considered (the server
// computes this from input_type = 'color' with a slug/name
// fallback). Size and other axes are ignored for swatch
// assignment.
//
// $variantImages is a map keyed by attribute_value_id.
//
// The value shape from swatchAxesFor() uses the key
// attribute_value_id, not id. The repository adds a
// values entry as:
//     ['attribute_value_id' => ..., 'value' => ...,
//      'color_hex' => ..., 'sort_order' => ..., 'variant_ids' => []]
//
// NOTE: the loop variable is named $axisValue, not $val.
// $val is the outer value-closure that edit.php defines and
// that every other tab depends on. Renaming this iterator
// prevents _tab_images.php from shadowing $val for the
// siblings that require after it.
$colorValues = [];
foreach ($variantAxes as $axis) {
    if (empty($axis['is_color_axis'])) {
        continue;
    }
    foreach (($axis['values'] ?? []) as $axisValue) {
        $valueId = (int) ($axisValue['attribute_value_id'] ?? 0);
        if ($valueId <= 0) {
            continue;
        }
        $assigned = $variantImages[$valueId] ?? null;
        $colorValues[] = [
            'axis_name'          => (string) ($axis['name'] ?? ''),
            'attribute_value_id' => $valueId,
            'value'              => (string) ($axisValue['value'] ?? ''),
            'color_hex'          => $axisValue['color_hex'] ?? null,
            'assigned_image'     => $assigned,
        ];
    }
}

$hasColorValues = count($colorValues) > 0;
?>
<div class="tab-pane fade<?= ($activeTab ?? 'basic') === 'images' ? ' show active' : '' ?>"
     id="tab-images"
     role="tabpanel"
     data-ra-images-tab>

    <?php if ($mode === 'create'): ?>

        <?php /* ============================================================
               On create: uploads are queued but not yet committed.
               The product row does not exist, so images cannot be
               assigned until the first save. This state is only
               visible on the /create form.
               ============================================================ */ ?>
        <div class="ra-alert ra-alert-info">
            <div style="font-size:1rem; font-weight:600; margin-bottom:.5rem;">
                <i class="bi bi-info-circle me-2"></i>
                Save the product first
            </div>
            <div class="small">
                Images can be added after the first save. The
                product needs an ID before images can be attached.
            </div>
        </div>

    <?php else: ?>

        <?php /* ============================================================
               Upload zone.
               ============================================================ */ ?>
        <div class="ra-card mb-3" data-ra-upload-zone>
            <div class="ra-card-body">
                <div class="ra-image-uploader"
                     data-ra-image-uploader
                     data-upload-url="/commerce/ca-admin/media/upload"
                     data-context="product-image"
                     data-product-id="<?= (int) ($product->id ?? 0) ?>">

                    <div class="ra-image-uploader-drop"
                         data-ra-uploader-drop>
                        <i class="bi bi-cloud-arrow-up ra-image-uploader-icon"
                           aria-hidden="true"></i>
                        <div class="ra-image-uploader-title">
                            Drop images here
                        </div>
                        <div class="ra-image-uploader-hint">
                            or <button type="button"
                                       class="ra-image-uploader-browse"
                                       data-ra-uploader-browse>
                                browse files
                            </button>
                        </div>
                        <div class="ra-image-uploader-meta">
                            PNG, JPEG, WebP. Max 5 MB each.
                            Large images are resized automatically.
                        </div>

                        <?php /* The file input is not named - uploads go
                               through fetch, not form submit. The uploaded
                               file id is appended to new_images_order[]. */ ?>
                        <input type="file"
                               accept="image/png,image/jpeg,image/webp"
                               multiple
                               class="ra-image-uploader-input"
                               data-ra-uploader-input>
                    </div>

                    <div class="ra-image-uploader-progress"
                         data-ra-uploader-progress
                         hidden>
                        <div class="ra-image-uploader-progress-bar"
                             data-ra-uploader-progress-bar></div>
                    </div>
                </div>
            </div>
        </div>

        <?php /* ============================================================
               Gallery grid.
               Rendered server-side from $productImages. The JS
               layer attaches drag-reorder and delete behavior.
               ============================================================ */ ?>
        <div class="ra-card mb-3" data-ra-gallery-card <?= $isEmpty ? 'hidden' : '' ?>>
            <div class="ra-card-header d-flex align-items-center justify-content-between">
                <h3 class="h6 mb-0">Gallery</h3>
                <div class="muted small">
                    Drag to reorder. Star sets the primary image.
                </div>
            </div>
            <div class="ra-card-body">
                <div class="ra-image-grid"
                     data-ra-image-grid
                     data-next-sort="<?= count($productImages) ?>">

                    <?php foreach ($productImages as $idx => $entry): ?>
                        <?= view_partial('admin.products._product-image-tile', [
                            'entry' => $entry,
                            'index' => $idx,
                        ]) ?>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>

        <?php /* ============================================================
               Empty state.
               ============================================================ */ ?>
        <div class="ra-card mb-3" data-ra-empty-state <?= $isEmpty ? '' : 'hidden' ?>>
            <div class="ra-card-body text-center py-5">
                <i class="bi bi-image" style="font-size:2.5rem; color:#c9cfdb;"
                   aria-hidden="true"></i>
                <div style="font-size:1.05rem; font-weight:600; margin-top:.75rem;">
                    No images yet
                </div>
                <div class="muted small mt-1">
                    Upload images above, or pick from your media library.
                </div>
            </div>
        </div>

        <?php /* ============================================================
               Picker row.
               ============================================================ */ ?>
        <div class="ra-card mb-3">
            <div class="ra-card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <div style="font-weight:600;">Or pick from your library</div>
                    <div class="muted small">
                        Reuse an image you already uploaded.
                    </div>
                </div>
                <button type="button"
                        class="ra-btn ra-btn-outline"
                        data-ra-open-picker>
                    <i class="bi bi-images me-1"></i> Choose from library
                </button>
            </div>
        </div>

        <?php /* ============================================================
               The picker modal is NOT included here. It renders in
               edit.php, after </form>, alongside the action forms
               and the delete modal. The pattern is uniform: every
               modal renders at the document level, never inside a
               form. See edit.php's tail.
               ============================================================ */ ?>

        <?php /* ============================================================
               Variant images.
               Only rendered when the product has at least one
               color axis (axis.is_color_axis = true). Renders
               one slot per color value. Each slot shows the
               currently assigned image or an empty state, plus
               an "Assign" trigger that opens the picker with
               that attribute value as the target.

               The assignment POST array is:
                 variant_images[<attribute_value_id>] = <file_id>

               syncImages() reads it after the gallery blocks.
               ============================================================ */ ?>
        <?php if ($hasColorValues): ?>
            <div class="ra-card mb-3" data-ra-variant-images-card>
                <div class="ra-card-header">
                    <h3 class="h6 mb-0">Variant images</h3>
                </div>
                <div class="ra-card-body">
                    <p class="muted small mb-3">
                        Assign a swatch image per color value. The
                        customer sees the image next to the color
                        swatch and on the product page when they
                        pick the color.
                    </p>
                    <div class="ra-variant-grid">
                        <?php foreach ($colorValues as $slot): ?>
                            <?= view_partial('admin.products._variant-image-slot', [
                                'slot' => $slot,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php /* ============================================================
               Hidden inputs appended by JS.
               new_images_order[] : file ids in display order.
               delete_images[]    : image ids to remove on save.
               These are populated at runtime; the empty containers
               below are placeholders for the JS.
               ============================================================ */ ?>
        <div data-ra-new-images-container hidden></div>
        <div data-ra-delete-images-container hidden></div>

    <?php endif; ?>

</div>
