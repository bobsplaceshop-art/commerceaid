/**
 * Page Builder Customizer - parent window.
 *
 * No panelGlobal dependency. Panel is `panel-blocks` then
 * `panel-settings` on click. Device switching. Drag reorder.
 * Click-to-edit via postMessage. Live preview via AJAX +
 * updateBlock message back to the iframe.
 */
(function () {
    'use strict';

    var root = document.getElementById('ra-customizer');
    if (!root) return;

    var iframe          = document.getElementById('preview-iframe');
    var wrapper         = document.getElementById('preview-wrapper');
    var panelBlocks     = document.getElementById('panel-blocks');
    var panelSettings   = document.getElementById('panel-settings');
    var settingsContent = document.getElementById('settings-content');
    var backBtn         = document.getElementById('back-to-blocks');
    var csrf            = root.getAttribute('data-csrf');

    document.querySelectorAll('.device-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.device-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            wrapper.className = 'ra-preview-frame-wrapper';
            var device = btn.getAttribute('data-device');
            if (device === 'tablet') wrapper.classList.add('device-tablet');
            if (device === 'mobile') wrapper.classList.add('device-mobile');
        });
    });

    document.querySelectorAll('.ra-open-settings').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openSettingsFor(btn.getAttribute('data-block-id'));
        });
    });

    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || data.action !== 'blockClicked') return;
        openSettingsFor(data.blockId);
    });

    function openSettingsFor(blockId) {
        var tpl = document.getElementById('tpl-settings-' + blockId);
        if (!tpl) return;
        settingsContent.innerHTML = '';
        settingsContent.appendChild(tpl.content.cloneNode(true));
        panelBlocks.style.display = 'none';
        panelSettings.style.display = 'block';
        bindLiveForm(settingsContent.querySelector('form'));
    }

    if (backBtn) {
        backBtn.addEventListener('click', function () {
            panelSettings.style.display = 'none';
            panelBlocks.style.display = 'block';
        });
    }

    function bindLiveForm(form) {
        if (!form) return;
        var previewUrl = form.getAttribute('data-preview-url');
        var blockId    = form.getAttribute('data-block-id');
        var debounce;

        function sendPreview() {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                var formData = new FormData(form);
                fetch(previewUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data && typeof data.html === 'string' && iframe.contentWindow) {
                        iframe.contentWindow.postMessage({
                            action:  'updateBlock',
                            blockId: blockId,
                            html:    data.html
                        }, '*');
                    }
                })
                .catch(function (err) {
                    console.error('[customizer] preview failed:', err);
                });
            }, 400);
        }

        form.addEventListener('input', sendPreview);
        form.addEventListener('change', sendPreview);
    }

    var list = document.getElementById('ra-blocks-list');
    var dragged = null;

    if (list) {
        list.querySelectorAll('.ra-customizer-block').forEach(function (tile) {
            tile.addEventListener('dragstart', function (e) {
                dragged = tile;
                tile.style.opacity = '0.4';
                e.dataTransfer.setData('text/plain', tile.getAttribute('data-block-id'));
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
        });

        list.addEventListener('drop', function (e) {
            e.preventDefault();
            var ids = [];
            list.querySelectorAll('.ra-customizer-block').forEach(function (t) {
                ids.push(parseInt(t.getAttribute('data-block-id'), 10));
            });
            var body = new URLSearchParams();
            body.append('_csrf', csrf);
            ids.forEach(function (id, i) {
                body.append('ids[' + i + ']', String(id));
            });
            fetch(list.getAttribute('data-reorder-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function () {
                if (iframe && iframe.contentWindow) {
                    iframe.contentWindow.location.reload();
                }
            });
        });
    }
})();
