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

                <!-- Upload new image -->
                <div style="padding-bottom:1rem; border-bottom:1px solid #2d3748; margin-bottom:1rem;">
                    <label for="ra-block-media-upload" class="ra-btn ra-btn-outline ra-btn-sm" style="cursor:pointer;">
                        <i class="bi bi-cloud-arrow-up me-1"></i> Upload new image
                    </label>
                    <input type="file"
                           id="ra-block-media-upload"
                           accept="image/*"
                           style="display:none;">
                    <span class="text-muted small ms-2" id="ra-block-media-upload-status"></span>
                </div>

                <div id="ra-block-media-grid">
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
</div>

<script>
(function () {
    'use strict';

    var input  = document.getElementById('ra-block-media-upload');
    var status = document.getElementById('ra-block-media-upload-status');
    var grid   = document.getElementById('ra-block-media-grid');
    if (!input || !grid) return;

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) return;

        status.textContent = 'Uploading...';

        var formData = new FormData();
        formData.append('file', file);
        formData.append('context', 'general');

        // CSRF token from the meta tag
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        fetch('/commerce/ca-admin/media/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrf,
                'Accept': 'application/json'
            },
            body: formData,
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.ok || !data.file) {
                status.textContent = (data && data.message) ? data.message : 'Upload failed.';
                status.style.color = '#ef4444';
                return;
            }

            status.textContent = 'Uploaded. Click to select.';
            status.style.color = '#22c55e';

            var fileData = data.file;
            var tile = document.createElement('button');
            tile.type = 'button';
            tile.className = 'ra-block-media-tile';
            tile.setAttribute('data-block-media-pick', '');
            tile.setAttribute('data-file-id', String(fileData.id));
            tile.setAttribute('data-file-url', fileData.url || '');
            tile.style.padding = '0';
            tile.style.background = 'transparent';
            tile.style.border = '2px solid transparent';
            tile.style.borderRadius = '4px';
            tile.style.cursor = 'pointer';
            tile.style.overflow = 'hidden';
            tile.innerHTML = '<img src="' + (fileData.thumb_url || fileData.url || '') + '" style="width:100%;height:auto;display:block;">';

            // Insert at the top of the grid
            var innerGrid = grid.querySelector('div[style*="display:grid"]');
            if (innerGrid) {
                innerGrid.insertBefore(tile, innerGrid.firstChild);
            }

            // Clear the file input so the same file can be picked again if needed
            input.value = '';
        })
        .catch(function (err) {
            status.textContent = 'Upload failed: ' + err.message;
            status.style.color = '#ef4444';
        });
    });
})();
</script>
