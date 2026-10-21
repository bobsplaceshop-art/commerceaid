/**
 * Page Builder - Live Editor Engine
 */
(function () {
    'use strict';

    var list = document.getElementById('ra-blocks-list');
    if (!list) return;

    var previewPane = document.getElementById('ra-preview-content');
    var csrf = list.getAttribute('data-csrf');
    var reorderUrl = list.getAttribute('data-reorder-url');
    
    // --- 1. Live Preview Engine ---
    var debounceTimer;

    document.querySelectorAll('.ra-live-config-form').forEach(function (form) {
        var previewUrl = form.getAttribute('data-preview-url');
        var blockId = form.getAttribute('data-block-id');
        
        // Listen for any input or change in the form
        form.addEventListener('input', function (e) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                triggerPreview(form, previewUrl, blockId);
            }, 400);
        });
        
        form.addEventListener('change', function (e) {
            clearTimeout(debounceTimer);
            triggerPreview(form, previewUrl, blockId);
        });
        
        // Handle form submit (Save button)
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var saveUrl = '/commerce/ca-admin/pages/' + list.getAttribute('data-page-slug') + '/blocks/' + blockId + '/config';
            var formData = new FormData(form);
            
            fetch(saveUrl, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) {
                if (res.redirected || res.ok) {
                    // Close settings on success
                    form.closest('.ra-builder-block-settings').style.display = 'none';
                }
            });
        });
    });

    function triggerPreview(form, url, blockId) {
        var formData = new FormData(form);
        
        fetch(url, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.html) {
                // Insert the HTML into the preview pane
                previewPane.innerHTML = data.html;
                
                // Highlight the block being edited (optional)
                previewPane.style.opacity = '0.5';
                setTimeout(function() { previewPane.style.opacity = '1'; }, 150);
            }
        })
        .catch(err => console.error('Preview failed:', err));
    }

    // --- 2. Toggle Settings Panes ---
    document.querySelectorAll('.ra-builder-toggle-settings').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var block = btn.closest('.ra-builder-block');
            var settings = block.querySelector('.ra-builder-block-settings');
            
            // Hide all others
            document.querySelectorAll('.ra-builder-block-settings').forEach(s => {
                if (s !== settings) s.style.display = 'none';
            });
            
            // Toggle this one
            if (settings.style.display === 'none') {
                settings.style.display = 'block';
                // Immediately trigger a preview for this block
                var form = settings.querySelector('form');
                if (form) {
                    triggerPreview(form, form.getAttribute('data-preview-url'), form.getAttribute('data-block-id'));
                }
            } else {
                settings.style.display = 'none';
            }
        });
    });
    
    document.querySelectorAll('.close-settings').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            btn.closest('.ra-builder-block-settings').style.display = 'none';
        });
    });

    // --- 3. Drag-and-Drop Reorder ---
    var dragged = null;
    list.querySelectorAll('.ra-builder-block').forEach(function (tile) {
        tile.addEventListener('dragstart', function (e) {
            dragged = tile;
            tile.style.opacity = '0.4';
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
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
    });

    list.addEventListener('drop', function (e) {
        e.preventDefault();
        var ids = [];
        list.querySelectorAll('.ra-builder-block').forEach(function (t) {
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
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
        });
    });

})();
