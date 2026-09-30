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
 *   $variantAxes    array<array{id:int, name:string, slug:string,
 *                              values:array<array{id:int, value:string,
 *                                                hex:?string}>}>
 *                   The product's variant axes (Color, Size). Empty
 *                   for simple products or when no category maps a
 *                   variant-axis attribute.
 *
 * The tab is a container. It renders:
 *
 *   1. An upload zone (JS wired, no server-side form action).
 *   2. A reorderable gallery grid, one tile per image.
 *   3. A picker row that opens the media library modal.
 *   4. An empty state when there are no images.
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
 *   new_images_order[]    (file ids, appended by the uploader)
 *   delete_images[]       (image ids to remove, appended by JS)
 *
 * The write path is ProductController::syncImages().
 */

$productImages = $productImages ?? [];
$mediaLibrary  = $mediaLibrary  ?? [];
$variantAxes   = $variantAxes   ?? [];

// Flatten the axis values into a single list for the tile dropdown.
// Each entry carries the axis name for grouping in the select.
$allAxisValues = [];
foreach ($variantAxes as $axis) {
    if (empty($axis['values'])) {
        continue;
    }
    foreach ($axis['values'] as $value) {
        $allAxisValues[] = [
            'id'    => (int) ($value['id'] ?? 0),
            'value' => (string) ($value['value'] ?? ''),
            'hex'   => $value['hex'] ?? null,
            'axis'  => (string) ($axis['name'] ?? ''),
        ];
    }
}

$isEmpty = count($productImages) === 0;
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
                            'entry'         => $entry,
                            'index'         => $idx,
                            'axisValues'    => $allAxisValues,
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
               The picker modal is included here. It is a sibling of
               the main product form, NOT a nested form. The modal
               body renders the store's media library as a selectable
               grid. On confirm, the JS appends file ids to
               new_images_order[] and closes the modal.
               ============================================================ */ ?>
        <?= view_partial('admin.products._image-picker-modal', [
            'mediaLibrary' => $mediaLibrary,
        ]) ?>

        <?php /* ============================================================
               Hidden inputs appended by JS.
               new_images_order[] : file ids in display order.
               delete_images[]    : image ids to remove on save.
               These are populated at runtime; the empty containers
               below are placeholders for the JS.
               ============================================================ */ ?>
        <div data-ra-new-images-container hidden></div>
        <div data-ra-delete-images-container hidden></div>

        <style>
            .ra-image-uploader {
                border: 2px dashed var(--ra-border, #e5e9f2);
                border-radius: .5rem;
                padding: 1.5rem 1rem;
                text-align: center;
                transition: border-color .15s, background-color .15s;
            }
            .ra-image-uploader.is-dragover {
                border-color: var(--ra-primary, #7c3aed);
                background-color: rgba(124, 58, 237, .04);
            }
            .ra-image-uploader-icon {
                font-size: 2rem;
                color: #9aa3b3;
                display: block;
                margin-bottom: .5rem;
            }
            .ra-image-uploader-title {
                font-weight: 600;
                font-size: 1.05rem;
            }
            .ra-image-uploader-hint {
                color: #6b7280;
                margin-top: .15rem;
            }
            .ra-image-uploader-browse {
                background: none;
                border: none;
                padding: 0;
                color: var(--ra-primary, #7c3aed);
                text-decoration: underline;
                cursor: pointer;
            }
            .ra-image-uploader-meta {
                color: #9aa3b3;
                font-size: .8rem;
                margin-top: .5rem;
            }
            .ra-image-uploader-input {
                position: absolute;
                width: 1px; height: 1px;
                opacity: 0;
                pointer-events: none;
            }
            .ra-image-uploader-progress {
                margin-top: 1rem;
                height: 4px;
                background: #eef1f6;
                border-radius: 4px;
                overflow: hidden;
            }
            .ra-image-uploader-progress-bar {
                height: 100%;
                width: 0;
                background: var(--ra-primary, #7c3aed);
                transition: width .2s;
            }
            .ra-image-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }
        </style>

    <?php endif; ?>

</div>
