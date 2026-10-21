/**
 * Page builder drag-and-drop.
 *
 * Native HTML5 drag API. No external library. The reorder
 * POSTs to the URL in the container's data attribute,
 * sending the new block id order. The server rewrites
 * sort_order for every block on the page.
 *
 * Failure handling: if the POST fails, the DOM is reloaded
 * from the server so the visual state matches reality.
 * Optimistic updates without a fallback are a recipe for
 * silent divergence between the UI and the DB.
 */
(function () {
    'use strict';

    var list = document.getElementById('ra-blocks-list');
    if (!list) return;

    var reorderUrl = list.getAttribute('data-reorder-url');
    var csrf = list.getAttribute('data-csrf');
    var tiles = list.querySelectorAll('.ra-block-tile');
    var dragged = null;

    tiles.forEach(function (tile) {
        tile.addEventListener('dragstart', function (e) {
            dragged = tile;
            tile.style.opacity = '0.4';
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                // Firefox requires setData to trigger drag events.
                e.dataTransfer.setData('text/plain', tile.getAttribute('data-block-id'));
            }
        });

        tile.addEventListener('dragend', function () {
            tile.style.opacity = '';
            dragged = null;
        });

        tile.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragged || dragged === tile) return;

            var rect = tile.getBoundingClientRect();
            var midpoint = rect.top + rect.height / 2;
            if (e.clientY < midpoint) {
                tile.parentNode.insertBefore(dragged, tile);
            } else {
                tile.parentNode.insertBefore(dragged, tile.nextSibling);
            }
        });

        tile.addEventListener('drop', function (e) {
            e.preventDefault();
            saveOrder();
        });
    });

    function saveOrder() {
        var ids = [];
        list.querySelectorAll('.ra-block-tile').forEach(function (t) {
            ids.push(parseInt(t.getAttribute('data-block-id'), 10));
        });

        var body = new URLSearchParams();
        body.append('_csrf', csrf);
        ids.forEach(function (id, i) {
            body.append('ids[' + i + ']', String(id));
        });

        fetch(reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: body.toString(),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('Server responded ' + res.status);
            // Order saved. Update the visible sort numbers so the
            // admin sees the new positions without reloading.
            list.querySelectorAll('.ra-block-tile').forEach(function (t, i) {
                var meta = t.querySelector('.text-muted.small');
                if (meta) {
                    meta.textContent = meta.textContent.replace(/order \d+/, 'order ' + (i + 1));
                }
            });
        })
        .catch(function (err) {
            console.error('[page-builder] reorder failed:', err);
            alert('Could not save the new order. The page will reload.');
            window.location.reload();
        });
    }
})();
