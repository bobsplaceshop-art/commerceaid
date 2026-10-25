/**
 * Preview bridge. Runs inside the storefront iframe when the
 * parent loads /?customize_preview=1.
 *
 * Two jobs:
 *   1. Click on a block  -> tell the parent which block was clicked.
 *   2. Receive updateBlock -> swap that block's inner HTML.
 */
(function () {
    'use strict';

    document.querySelectorAll('.ra-preview-block').forEach(function (block) {
        block.addEventListener('click', function (e) {
            var blockId = block.getAttribute('data-block-id');
            window.parent.postMessage({ action: 'blockClicked', blockId: blockId }, '*');
            if (!e.target.closest('a, button, input, select, textarea')) {
                e.preventDefault();
            }
        });
        block.addEventListener('mouseenter', function () {
            block.style.outline = '2px dashed rgba(212, 175, 55, 0.6)';
        });
        block.addEventListener('mouseleave', function () {
            block.style.outline = '';
        });
    });

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || data.action !== 'updateBlock') return;
        var block = document.querySelector(
            '.ra-preview-block[data-block-id="' + data.blockId + '"]'
        );
        if (!block) return;
        if (typeof data.html === 'string') {
            block.innerHTML = data.html;
            
            // Replacing innerHTML on a flex container can cause
            // the browser's layout engine to leave the new
            // children at zero height until the next full page
            // load. Force a reflow on the next animation frame
            // so the children are fully parsed before we
            // calculate their layout.
            window.requestAnimationFrame(function () {
                void block.offsetHeight;
            });
        }
        if (data.reload === true) {
            window.location.reload();
        }
    });
})();
