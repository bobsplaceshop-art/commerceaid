<?php
/**
 * Single-file media picker for block config fields.
 *
 * Self-contained. Renders a modal listing the store's media
 * library. Clicking a tile sets the target hidden input
 * (by ID) to the file's id, updates the preview, and
 * closes the modal.
 *
 * Does not share code with the product Images picker, which
 * is multi-select and tied to the product editor's form.
 * The two serve different intents; a 40-line single-select
 * picker is faster than retrofitting the 500-line product
 * picker.
 *
 * Vars:
 *   @var array<int, \CorporateAid\Commerce\Models\MediaItem> $mediaLibrary
 */
$mediaLibrary = $mediaLibrary ?? [];
?>
<div class="modal fade" id="ra-block-media-picker" tabindex="-1" aria-labelledby="ra-block-media-picker-label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="background:#1a2235; color:#e5e7eb; border:1px solid #2d3748;">
            <div class="modal-header" style="border-bottom:1px solid #2d3748;">
                <h5 class="modal-title" id="ra-block-media-picker-label">Choose an image</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($mediaLibrary === []): ?>
                    <p class="text-muted mb-0">
                        Your media library is empty. Upload images via Catalog → Media first.
                    </p>
                <?php else: ?>
                    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:.5rem;">
                        <?php foreach ($mediaLibrary as $item): ?>
                            <button type="button"
                                    class="ra-block-media-tile"
                                    data-block-media-pick
                                    data-file-id="<?= (int) $item->file->id ?>"
                                    data-file-url="/media/<?= e($item->file->uuid) ?>/md.jpg"
                                    style="padding:0; background:transparent; border:2px solid transparent; border-radius:4px; cursor:pointer; overflow:hidden;"
                                    onmouseover="this.style.borderColor='#d4af37';"
                                    onmouseout="this.style.borderColor='transparent';">
                                <img src="/media/<?= e($item->file->uuid) ?>/thumb.jpg"
                                     alt="<?= e($item->file->originalName ?? '') ?>"
                                     style="width:100%; height:auto; display:block;">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
