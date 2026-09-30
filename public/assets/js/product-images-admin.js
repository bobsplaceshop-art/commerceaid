/* =====================================================================
 * Product images admin behaviors.
 * =====================================================================
 * Loaded only on /commerce/ca-admin/products/{id}/edit.
 *
 * Responsibilities:
 *   1. Upload zone - drag/drop, click-to-browse, fetch POST to
 *      /commerce/ca-admin/media/upload, progress bar, append
 *      new_images_order[] entries on success.
 *   2. Picker modal - open/close, checkbox selection, count
 *      update, confirm appends selected file ids.
 *   3. Tile removal - hide tile, append delete_images[] entry,
 *      update empty-state visibility.
 *   4. Drag reorder - HTML5 DnD on tiles, rewrites sort_order
 *      hidden inputs on drop.
 *   5. Primary selection - radio click updates the .is-primary
 *      class and [data-ra-primary-badge] visibility.
 *   6. Empty-state toggle - shows/hides the empty card based on
 *      tile count.
 *
 * CSRF: read from the main form's hidden _csrf input. This
 * page always has a form, so the meta-tag fallback from
 * Pattern 29 does not apply here.
 * ===================================================================== */
(function () {
    'use strict';

    // ---------- Utilities ----------

    function csrf() {
        var input = document.querySelector('#ra-product-form input[name="_csrf"]');
        return input ? input.value : '';
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    // ---------- Upload zone ----------

    function initUploadZone() {
        var uploader = qs('[data-ra-image-uploader]');
        if (!uploader) return;

        var drop      = qs('[data-ra-uploader-drop]', uploader);
        var input     = qs('[data-ra-uploader-input]', uploader);
        var browse    = qs('[data-ra-uploader-browse]', uploader);
        var progress  = qs('[data-ra-uploader-progress]', uploader);
        var bar       = qs('[data-ra-uploader-progress-bar]', uploader);
        var uploadUrl = uploader.getAttribute('data-upload-url');
        var context   = uploader.getAttribute('data-context') || 'product-image';

        if (!drop || !input || !uploadUrl) return;

        function setDragOver(on) {
            drop.classList.toggle('is-dragover', !!on);
        }

        ['dragenter', 'dragover'].forEach(function (evt) {
            drop.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                setDragOver(true);
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            drop.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                setDragOver(false);
            });
        });

        drop.addEventListener('drop', function (e) {
            var files = e.dataTransfer ? e.dataTransfer.files : null;
            if (files && files.length) {
                uploadFiles(files);
            }
        });

        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                uploadFiles(input.files);
            }
        });

        if (browse) {
            browse.addEventListener('click', function (e) {
                e.preventDefault();
                input.click();
            });
        }

        function uploadFiles(files) {
            var total = files.length;
            var done  = 0;

            if (progress) progress.hidden = false;
            if (bar)      bar.style.width = '0%';

            Array.prototype.slice.call(files).forEach(function (file) {
                var fd = new FormData();
                fd.append('file', file);
                fd.append('context', context);
                fd.append('_csrf', csrf());

                fetch(uploadUrl, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    done++;
                    if (bar) {
                        bar.style.width = Math.round((done / total) * 100) + '%';
                    }
                    if (data && data.status === 'ok' && data.file_id) {
                        appendNewImage(data.file_id);
                    } else {
                        console.warn('Upload failed', data);
                    }
                    if (done === total) {
                        setTimeout(function () {
                            if (progress) progress.hidden = true;
                            if (bar)      bar.style.width = '0%';
                        }, 400);
                    }
                })
                .catch(function (err) {
                    done++;
                    console.error('Upload error', err);
                    if (done === total) {
                        if (progress) progress.hidden = true;
                        if (bar)      bar.style.width = '0%';
                    }
                });
            });

            input.value = '';
        }
    }

    // ---------- New image append ----------
    // Called by the uploader after each successful upload, and by
    // the picker on confirm.
    //
    // Adds a hidden <input name="new_images_order[]" value="<id>">
    // to the container. syncImages() reads these in order.
    //
    // Note: the uploaded file does NOT appear in the gallery
    // immediately. The tile for it renders after the next save
    // and reload. This is a deliberate trade for a simpler
    // server contract - no staging row, no orphan handling.

    function appendNewImage(fileId) {
        var container = qs('[data-ra-new-images-container]');
        if (!container) return;

        // Dedup - the picker might be reopened and the same file
        // selected twice.
        var existing = container.querySelector('input[value="' + fileId + '"]');
        if (existing) return;

        var input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'new_images_order[]';
        input.value = fileId;
        container.appendChild(input);

        // Show a pending indicator on the uploader so the operator
        // knows the image is queued. The real tile appears after
        // the next save.
        var uploader = qs('[data-ra-image-uploader]');
        if (uploader) {
            var pending = uploader.querySelector('[data-ra-uploader-pending]');
            if (!pending) {
                pending = document.createElement('div');
                pending.setAttribute('data-ra-uploader-pending', '');
                pending.className = 'ra-image-uploader-pending';
                uploader.appendChild(pending);
            }
            pending.textContent = container.children.length +
                ' image' + (container.children.length === 1 ? '' : 's') +
                ' queued. Save to add them to the gallery.';
        }
    }

    // ---------- Picker modal ----------

    var pickerModal   = null;
    var pickerCount   = null;
    var pickerConfirm = null;

    function initPicker() {
        pickerModal = qs('[data-ra-picker-modal]');
        if (!pickerModal) return;

        pickerCount   = qs('[data-ra-picker-count]', pickerModal);
        pickerConfirm = qs('[data-ra-picker-confirm]', pickerModal);

        // Checkbox change -> recount, toggle confirm button.
        pickerModal.addEventListener('change', function (e) {
            if (!e.target.matches('[data-ra-picker-checkbox]')) return;
            recountPicker();
        });

        // Click anywhere on a picker tile toggles its checkbox.
        pickerModal.addEventListener('click', function (e) {
            var label = e.target.closest('.ra-picker-tile-label');
            if (!label) return;
            if (e.target.tagName === 'INPUT') return; // native toggle
            var cb = label.querySelector('[data-ra-picker-checkbox]');
            if (cb) {
                cb.checked = !cb.checked;
                recountPicker();
            }
        });

        // Cancel and backdrop close.
        qsa('[data-ra-picker-close]', pickerModal).forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                closePicker();
            });
        });

        // Confirm.
        if (pickerConfirm) {
            pickerConfirm.addEventListener('click', function (e) {
                e.preventDefault();
                confirmPicker();
            });
        }

        // Escape key.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && pickerModal.style.display === 'flex') {
                closePicker();
            }
        });
    }

    function openPicker() {
        if (!pickerModal) return;
        pickerModal.style.display = 'flex';
        // Reset selection on open.
        qsa('[data-ra-picker-checkbox]', pickerModal).forEach(function (cb) {
            cb.checked = false;
        });
        recountPicker();
    }

    function closePicker() {
        if (!pickerModal) return;
        pickerModal.style.display = 'none';
    }

    function recountPicker() {
        if (!pickerModal) return;
        var selected = qsa('[data-ra-picker-checkbox]:checked', pickerModal);
        var n = selected.length;

        if (pickerCount) {
            pickerCount.textContent = n + ' selected';
        }
        if (pickerConfirm) {
            pickerConfirm.disabled = (n === 0);
        }
    }

    function confirmPicker() {
        var selected = qsa('[data-ra-picker-checkbox]:checked', pickerModal);
        if (!selected.length) return;

        selected.forEach(function (cb) {
            var id = parseInt(cb.value, 10);
            if (id > 0) appendNewImage(id);
        });

        closePicker();
    }

    // ---------- Tile removal ----------

    function initTileRemoval() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-ra-remove-tile]');
            if (!btn) return;
            e.preventDefault();

            var tile = btn.closest('[data-ra-image-tile]');
            if (!tile) return;

            var id = parseInt(tile.getAttribute('data-image-id'), 10);
            if (!id) return;

            // Confirm remove - images already saved can be re-added
            // from the picker, but a batch save is expensive.
            if (!window.confirm('Remove this image from the gallery?')) {
                return;
            }

            // Append a delete_images[] input to the container.
            var container = qs('[data-ra-delete-images-container]');
            if (container) {
                var input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'delete_images[]';
                input.value = id;
                container.appendChild(input);
            }

            // Hide the tile. Do not remove from DOM - sort order
            // inputs on the other tiles remain valid.
            tile.style.display = 'none';

            updateEmptyState();
        });
    }

    // ---------- Drag reorder ----------

    function initDragReorder() {
        var grid = qs('[data-ra-image-grid]');
        if (!grid) return;

        var dragging = null;

        grid.addEventListener('dragstart', function (e) {
            var tile = e.target.closest('[data-ra-image-tile]');
            if (!tile) return;
            dragging = tile;
            tile.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                // Some browsers require data to be set.
                try { e.dataTransfer.setData('text/plain', 'tile'); } catch (err) {}
            }
        });

        grid.addEventListener('dragend', function () {
            if (dragging) dragging.classList.remove('is-dragging');
            dragging = null;
            rewriteSortOrders(grid);
        });

        grid.addEventListener('dragover', function (e) {
            if (!dragging) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';

            var target = e.target.closest('[data-ra-image-tile]');
            if (!target || target === dragging) return;

            var rect = target.getBoundingClientRect();
            var after = (e.clientY - rect.top) > (rect.height / 2);

            if (after) {
                target.parentNode.insertBefore(dragging, target.nextSibling);
            } else {
                target.parentNode.insertBefore(dragging, target);
            }
        });
    }

    function rewriteSortOrders(grid) {
        var tiles = qsa('[data-ra-image-tile]', grid);
        tiles.forEach(function (tile, idx) {
            var input = qs('[data-ra-sort-order-input]', tile);
            if (input) input.value = idx;
        });
    }

    // ---------- Primary selection ----------

    function initPrimaryRadio() {
        document.addEventListener('change', function (e) {
            if (!e.target.matches('[data-ra-primary-radio]')) return;

            var tiles = qsa('[data-ra-image-tile]');
            tiles.forEach(function (tile) {
                var radio = qs('[data-ra-primary-radio]', tile);
                var badge = qs('[data-ra-primary-badge]', tile);
                var isPrimary = radio && radio.checked;
                tile.classList.toggle('is-primary', isPrimary);
                if (badge) badge.hidden = !isPrimary;
            });
        });
    }

    // ---------- Empty state ----------

    function updateEmptyState() {
        var grid = qs('[data-ra-image-grid]');
        if (!grid) return;

        var visible = qsa('[data-ra-image-tile]', grid).filter(function (t) {
            return t.style.display !== 'none';
        });

        var gallery = qs('[data-ra-gallery-card]');
        var empty   = qs('[data-ra-empty-state]');
        var hasAny  = visible.length > 0;

        if (gallery) gallery.hidden = !hasAny;
        if (empty)   empty.hidden   = hasAny;
    }

    // ---------- Wire it all ----------

    function boot() {
        initUploadZone();
        initPicker();
        initTileRemoval();
        initDragReorder();
        initPrimaryRadio();

        // Picker open trigger.
        qsa('[data-ra-open-picker]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openPicker();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
