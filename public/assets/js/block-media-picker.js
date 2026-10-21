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

    // Open button handlers.
    document.querySelectorAll('[data-block-media-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            activeTargetId = btn.getAttribute('data-block-media-open');
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        });
    });

    // Clear button handlers.
    document.querySelectorAll('[data-block-media-clear]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-block-media-clear');
            var input = document.getElementById(targetId);
            if (input) input.value = '';
            // Reload to update the preview tile (removes the image).
            window.location.reload();
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
        if (input) input.value = fileId;

        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();

        // Reload so the preview tile updates with the new image.
        window.location.reload();
    });
})();
