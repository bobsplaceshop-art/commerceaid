<?php
/**
 * Media library picker modal.
 *
 * Renders the store's media library as a selectable grid.
 * On confirm, the JS appends the selected file IDs to
 * new_images_order[] inside the product editor's main form
 * and closes the modal.
 *
 * Scope (passed from edit.php):
 *
 *   $mediaLibrary  array<MediaItem>  — capped at 200 items
 *                  by ProductController::resolveMediaLibrary().
 *
 * Rendered ONCE per product edit page. Rendered AFTER
 * </form> in edit.php, alongside _action-forms.php and
 * _delete-modal.php. Never inside the form (Pattern 27).
 *
 * All behavior lives in product-images-admin.js. This
 * partial is markup only. No inline <script>, no onclick.
 *
 * The grid reuses .ra-media-grid and .ra-media-tile from
 * setup.css. Each tile carries a checkbox that the JS
 * reads on confirm. The picker tile has no action buttons
 * (unlike the media library grid tile) — the operator is
 * picking, not managing.
 */

$mediaLibrary = $mediaLibrary ?? [];
?>
<div id="ra-image-picker-modal" class="ra-modal" style="display:none;" data-ra-picker-modal>
    <div class="ra-modal-backdrop" data-ra-picker-close></div>
    <div class="ra-modal-panel ra-modal-panel-wide"
         role="dialog"
         aria-labelledby="ra-image-picker-title">

        <div class="ra-modal-header">
            <h3 id="ra-image-picker-title" class="h6 mb-0">
                Choose from library
            </h3>
            <button type="button"
                    class="ra-icon-btn"
                    data-ra-picker-close
                    aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="ra-modal-body">

            <?php if ($mediaLibrary === []): ?>

                <div class="text-center py-5">
                    <i class="bi bi-images"
                       style="font-size:2.5rem; color:#c9cfdb;"
                       aria-hidden="true"></i>
                    <div class="mt-2" style="font-weight:600;">
                        Your media library is empty
                    </div>
                    <div class="muted small mt-1">
                        Upload images from the product editor to
                        add them to your library.
                    </div>
                </div>

            <?php else: ?>

                <div class="mb-2 muted small">
                    Click an image to select it. Selected images
                    are added to the gallery when you confirm.
                </div>

                <ul class="ra-media-grid ra-picker-grid">
                    <?php foreach ($mediaLibrary as $item): ?>
                        <?php
                        $file = $item->file;
                        $isImage = $item->isImage();
                        $thumbUrl = $isImage ? $item->variantUrl('md', 'webp') : null;
                        $alt = $item->altText();
                        ?>
                        <li class="ra-media-tile ra-picker-tile"
                            data-ra-picker-tile
                            data-file-id="<?= (int) $file->id ?>"
                            data-file-name="<?= e($file->originalName) ?>">

                            <label class="ra-picker-tile-label">
                                <input type="checkbox"
                                       class="ra-picker-tile-checkbox"
                                       data-ra-picker-checkbox
                                       value="<?= (int) $file->id ?>">

                                <div class="ra-media-tile-thumb">
                                    <?php if ($thumbUrl !== null): ?>
                                        <img src="<?= e($thumbUrl) ?>"
                                             alt="<?= e($alt) ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="ra-media-tile-thumb-fallback">
                                            <i class="bi bi-file-earmark"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="ra-media-tile-body">
                                    <div class="ra-media-tile-name"
                                         title="<?= e($file->originalName) ?>">
                                        <?= e($file->originalName) ?>
                                    </div>
                                </div>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

            <?php endif; ?>

        </div>

        <div class="ra-modal-footer">
            <span class="muted small me-auto"
                  data-ra-picker-count>
                0 selected
            </span>
            <button type="button"
                    class="ra-btn ra-btn-outline"
                    data-ra-picker-close>
                Cancel
            </button>
            <button type="button"
                    class="ra-btn ra-btn-primary"
                    data-ra-picker-confirm
                    disabled>
                Add to gallery
            </button>
        </div>

    </div>
</div>
