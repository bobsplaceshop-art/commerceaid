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

    // Page Settings modal. Opens the metadata editor
    // (title, meta description, status, noindex).
    var pageSettingsBtn = document.querySelector('[data-page-settings-open]');
    if (pageSettingsBtn && window.bootstrap) {
        pageSettingsBtn.addEventListener('click', function () {
            var modal = new bootstrap.Modal(document.getElementById('ra-page-settings-modal'));
            modal.show();
        });
    }

    // Mobile sidebar toggle. Below md the sidebar is a
    // slide-over drawer; this button opens and closes it.
    // Above md the button is display:none, so the handler
    // is a no-op and the sidebar stays permanently visible.
    var sidebarToggle = document.querySelector('[data-customizer-sidebar-toggle]');
    var sidebar       = document.querySelector('.ra-customizer-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            var isOpen = sidebar.classList.toggle('is-open');
            sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Tapping a block on mobile closes the drawer so the
        // preview is visible immediately.
        document.querySelectorAll('.ra-open-settings').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (window.innerWidth < 768) {
                    sidebar.classList.remove('is-open');
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    document.querySelectorAll('.device-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!wrapper) {
                console.error('[customizer] preview-wrapper missing');
                return;
            }

            // 1. Visual state: only the clicked button is active.
            document.querySelectorAll('.device-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');

            var device = btn.getAttribute('data-device') || 'desktop';

            // 2. Wrapper class. Removes both device classes,
            //    then adds the one that applies. The CSS in
            //    main-v2.css drives the wrapper's width from
            //    this class (or the data-active-device attr).
            wrapper.classList.remove('device-tablet', 'device-mobile');
            if (device === 'tablet') wrapper.classList.add('device-tablet');
            if (device === 'mobile') wrapper.classList.add('device-mobile');
            wrapper.setAttribute('data-active-device', device);

            // 3. The wrapper width is set by CSS. But the iframe
            //    inside does not always recompute its internal
            //    viewport when its parent's width changes. To
            //    force the browser to re-run the iframe's media
            //    queries, we reset the iframe's src. This is
            //    the only reliable cross-browser way to make
            //    the storefront inside render at the new width.
            var iframe = document.getElementById('preview-iframe');
            if (iframe) {
                var w = '100%';
                if (device === 'tablet') w = '768px';
                if (device === 'mobile') w = '375px';
                iframe.style.width = w;
                // Force the internal document to re-run.
                iframe.src = iframe.src;
            }
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
        var saveUrl    = form.getAttribute('data-save-url');
        var blockId    = form.getAttribute('data-block-id');
        var debounce;

        function persistAndPreview() {
            clearTimeout(debounce);
            debounce = setTimeout(function () {
                var formData = new FormData(form);

                // 1. Persist to the session draft via saveBlockConfig
                if (saveUrl) {
                    fetch(saveUrl, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).catch(function (err) {
                        console.error('[customizer] save failed:', err);
                    });
                }

                // 2. Fetch the rendered HTML and inject it
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

        form.addEventListener('input', persistAndPreview);
        form.addEventListener('change', persistAndPreview);
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
