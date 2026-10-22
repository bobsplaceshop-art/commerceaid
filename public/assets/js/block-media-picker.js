/**
 * Single-select media picker for block config fields.
 *
 * Wires the "Choose image" button in each `file`-type
 * config field to the shared picker modal. On pick,
 * the target hidden input is set to the file id, the
 * preview is updated, and the modal closes.
 *
 * The picker is shared across every block on the page.
 * The active target field id is tracked in a module-level
 * variable so the click handler knows where to write.
 */
(function () {
    'use strict';

    var modalEl = document.getElementById('ra-block-media-picker');
    if (!modalEl) return;

    var activeTargetId = null;

    // Open button handlers. Two attributes so the picker works
    // on both the Page Builder ([data-block-media-open]) and
    // the Settings page ([data-settings-media-open]). One modal,
    // one handler, both entry points.
    document.querySelectorAll('[data-block-media-open], [data-settings-media-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeTargetId = btn.getAttribute('data-block-media-open')
                         || btn.getAttribute('data-settings-media-open');
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        });
    });

    // Clear button handlers.
    document.querySelectorAll('[data-settings-media-clear]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-settings-media-clear');
            var input = document.getElementById(targetId);
            if (input) input.value = '';

            // Clear the preview and hide the Remove button.
            var field = input ? input.closest('.ra-file-field') : null;
            if (field) {
                var preview = field.querySelector('.ra-file-preview');
                if (preview) {
                    preview.innerHTML = '<div style="width:80px; height:80px; background:#0f172a; border:1px dashed #2d3748; border-radius:4px; display:flex; align-items:center; justify-content:center; color:#64748b;"><i class="bi bi-image"></i></div>';
                }
                btn.style.display = 'none';
            }
        });
    });

    // Pick handler. One listener on the modal body handles
    // every tile through delegation.
    modalEl.addEventListener('click', function (e) {
        var tile = e.target.closest('[data-block-media-pick]');
        if (!tile) return;

        var fileId = tile.getAttribute('data-file-id');
        if (!activeTargetId) return;

        var input = document.getElementById(activeTargetId);
        if (input) {
            input.value = fileId;

            // Update the preview image in place.
            var field = input.closest('.ra-file-field');
            if (field) {
                var preview = field.querySelector('.ra-file-preview');
                if (preview) {
                    var imgSrc = tile.getAttribute('data-file-url');
                    if (imgSrc) {
                        // For the settings field, /media/{uuid}/md.jpg
                        // becomes /media/{uuid}/thumb.jpg.
                        imgSrc = imgSrc.replace('/md.jpg', '/thumb.jpg');
                        preview.innerHTML = '<img src="' + imgSrc + '" alt="Preview" style="width:80px; height:80px; object-fit:cover; border-radius:4px;">';
                    }
                }

                // Show the Remove button (it's hidden until a
                // file has been selected).
                var removeBtn = field.querySelector('[data-settings-media-clear]');
                if (removeBtn) {
                    removeBtn.style.display = 'inline-flex';
                }
            }
        }

        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    });
})();
