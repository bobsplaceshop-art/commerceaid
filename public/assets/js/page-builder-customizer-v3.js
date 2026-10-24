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
    var draggedId = null;

    // ============================================================
    // Phase 1: Client-side tree. Built once on load from the DOM
    // rows. Every row carries data-block-id and data-parent-id.
    // The tree is the source of truth for phases 2-5; the DOM is a
    // rendering of the tree, not the other way around.
    // ============================================================
    function buildTree() {
        if (!list) return { byId: {}, roots: [] };
        var rows = list.querySelectorAll('.ra-customizer-block');
        var byId = {};
        var roots = [];

        rows.forEach(function (row) {
            var id       = parseInt(row.getAttribute('data-block-id'), 10);
            var parentId = parseInt(row.getAttribute('data-parent-id'), 10) || 0;
            byId[id] = {
                id: id,
                parentId: parentId,
                children: []
            };
        });

        rows.forEach(function (row) {
            var id       = parseInt(row.getAttribute('data-block-id'), 10);
            var parentId = parseInt(row.getAttribute('data-parent-id'), 10) || 0;
            if (parentId === 0) {
                roots.push(byId[id]);
            } else if (byId[parentId]) {
                byId[parentId].children.push(byId[id]);
            }
        });

        return { byId: byId, roots: roots };
    }

    if (list) {
        window.__builderTree = buildTree();
        console.log('[customizer] tree built:', window.__builderTree);
    }

    // ============================================================
    // Phase 2: Drag updates the tree; the DOM is re-rendered from
    // the tree. No direct DOM mutation during dragover. The
    // tree's roots and each node's children array are the single
    // source of truth.
    // ============================================================

    // Move a node to the position described by (targetParentId,
    // beforeId). Removes from old parent first.
    function moveNode(draggedId, targetParentId, beforeId) {
        var tree = window.__builderTree;
        if (!tree) return;

        // Find and detach from old parent.
        var oldParent = null;
        var node = tree.byId[draggedId];
        if (!node) return;

        if (node.parentId === 0) {
            var idx = tree.roots.indexOf(node);
            if (idx >= 0) tree.roots.splice(idx, 1);
        } else if (tree.byId[node.parentId]) {
            var parentNode = tree.byId[node.parentId];
            var pidx = parentNode.children.indexOf(node);
            if (pidx >= 0) parentNode.children.splice(pidx, 1);
        }

        // Insert into new position.
        node.parentId = targetParentId;
        var destination = (targetParentId === 0)
            ? tree.roots
            : (tree.byId[targetParentId]
                ? tree.byId[targetParentId].children
                : tree.roots);

        if (beforeId === 0) {
            destination.push(node);
        } else {
            var beforeNode = tree.byId[beforeId];
            var bidx = destination.indexOf(beforeNode);
            if (bidx >= 0) {
                destination.splice(bidx, 0, node);
            } else {
                destination.push(node);
            }
        }
    }

    // Re-render the sidebar rows from the tree. Uses the same
    // markup show.php produces, so CSS continues to apply.
    // (The full HTML row generator lives here in Phase 2; Phase
    // 3 replaces this with a JSON-driven re-render.)
    function rerenderSidebar() {
        // Phase 2: leave the DOM as-is after drop. The next page
        // load rebuilds the sidebar from the server. Phase 3 adds
        // the client-side rerender so the visual order matches the
        // tree immediately after drop.
        console.log('[customizer] tree after drop:',
            JSON.stringify(window.__builderTree.roots, null, 0).length + ' chars');
    }

    if (list) {
        list.querySelectorAll('.ra-customizer-block').forEach(function (tile) {
            tile.addEventListener('dragstart', function (e) {
                draggedId = parseInt(tile.getAttribute('data-block-id'), 10);
                tile.style.opacity = '0.4';
                e.dataTransfer.setData('text/plain', String(draggedId));
            });
            tile.addEventListener('dragend', function () {
                tile.style.opacity = '';
                draggedId = null;
            });
            tile.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (!draggedId) return;
                var targetId = parseInt(tile.getAttribute('data-block-id'), 10);
                if (draggedId === targetId) return;
                // Phase 2: drop above/below determines insertion
                // side; the parent of the drop target is inherited.
                // Cross-parent drag is Phase 3.
                var rect = tile.getBoundingClientRect();
                var isAbove = (e.clientY < rect.top + rect.height / 2);
                var targetParent = parseInt(tile.getAttribute('data-parent-id'), 10) || 0;
                moveNode(
                    draggedId,
                    targetParent,
                    isAbove ? targetId : 0
                );
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
            }).then(function (res) {
                if (!res.ok) {
                    console.error('[customizer] reorder failed: ' + res.status);
                    return;
                }
                rerenderSidebar();
                if (iframe) {
                    var currentSrc = iframe.getAttribute('src') || '';
                    var separator  = currentSrc.indexOf('?') !== -1 ? '&' : '?';
                    iframe.src = currentSrc + separator + 't=' + Date.now();
                }
            }).catch(function (err) {
                console.error('[customizer] reorder network error:', err);
            });
        });
    }
})();
