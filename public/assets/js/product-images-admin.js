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
                    // Server response shape (MediaController::upload):
                    //   { ok: true, id, uuid, url, name, mime, size,
                    //     dimensions, variants: [...] }
                    // The previous version read data.status and
                    // data.file_id, which do not exist. Every
                    // upload silently failed.
                    if (data && data.ok === true && data.id) {
                        appendNewImage({
                            fileId: data.id,
                            uuid:   data.uuid || '',
                            name:   data.name || '',
                            alt:    ''
                        });
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

    /**
     * Append a new image to the pending set and render a
     * placeholder tile in the gallery grid.
     *
     * payload: {
     *   fileId: int,
     *   uuid:   string,
     *   name:   string,
     *   alt:    string
     * }
     *
     * The placeholder tile carries:
     *   - a thumbnail from /media/{uuid}/card.webp
     *   - a "Pending - save to keep" badge
     *   - alt text input named new_images_meta[<fileId>][alt_text]
     *   - swatch select named new_images_meta[<fileId>][attribute_value_id]
     *   - a hidden new_images_order[] input carrying the fileId
     *   - a Remove button that removes the tile and its inputs
     *
     * On save, ProductController::syncImages() inserts a
     * product_images row for the fileId, applies the alt/swatch
     * from new_images_meta[<fileId>][...], and the next page
     * load renders the persisted tile.
     */
    function appendNewImage(payload) {
        var fileId = parseInt(payload && payload.fileId, 10);
        if (!fileId || fileId <= 0) return;

        var uuid = (payload && payload.uuid) || '';
        var name = (payload && payload.name) || '';
        var alt  = (payload && payload.alt)  || '';

        var container = qs('[data-ra-new-images-container]');
        if (!container) return;

        // Dedup - the picker might be reopened and the same file
        // selected twice, or the uploader might process the same
        // file twice (retry, double-click).
        var existing = container.querySelector('input[value="' + fileId + '"]');
        if (existing) return;

        // 1. Register the file in the pending container.
        var hidden = document.createElement('input');
        hidden.type  = 'hidden';
        hidden.name  = 'new_images_order[]';
        hidden.value = String(fileId);
        container.appendChild(hidden);

        // 2. Ensure the gallery grid exists.
        var grid = qs('[data-ra-image-grid]');
        if (!grid) {
            // Create the grid inside the gallery card if the
            // product had no images yet (the initial page
            // load rendered the empty state).
            var galleryCard = qs('[data-ra-gallery-card]');
            if (!galleryCard) return;

            var cardBody = galleryCard.querySelector('.ra-card-body');
            if (!cardBody) return;

            grid = document.createElement('div');
            grid.className = 'ra-image-grid';
            grid.setAttribute('data-ra-image-grid', '');
            grid.setAttribute('data-next-sort', '0');
            cardBody.appendChild(grid);

            // Reveal the gallery card.
            galleryCard.hidden = false;
            var emptyState = qs('[data-ra-empty-state]');
            if (emptyState) emptyState.hidden = true;
        }

        // 3. Build the placeholder tile.
        var tile = document.createElement('div');
        tile.className = 'ra-image-tile is-pending';
        tile.setAttribute('data-ra-image-tile', '');
        tile.setAttribute('data-image-id', '');
        tile.setAttribute('data-ra-pending-file-id', String(fileId));
        tile.setAttribute('draggable', 'true');

        var thumbUrl = uuid
            ? '/media/' + encodeURIComponent(uuid) + '/card.webp'
            : '';

        var thumbHtml = thumbUrl
            ? '<img src="' + thumbUrl + '" alt="" loading="lazy">'
            : '<div class="ra-image-tile-thumb-fallback"><i class="bi bi-image"></i></div>';

        // Note: name attributes here are dynamic strings built from
        // fileId (which is already an int). No user content is
        // interpolated. name and alt are placed via setAttribute /
        // textContent below, not via string concatenation.
        tile.innerHTML =
            '<div class="ra-image-tile-handle" title="Drag to reorder">' +
                '<i class="bi bi-grip-vertical" aria-hidden="true"></i>' +
            '</div>' +
            '<div class="ra-image-tile-thumb">' +
                '<picture>' + thumbHtml + '</picture>' +
                '<span class="ra-image-tile-badge ra-image-tile-badge-pending">' +
                    '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Pending' +
                '</span>' +
            '</div>' +
            '<div class="ra-image-tile-body">' +
                /* No primary radio on a pending tile - the
                   image cannot be primary until it is saved.
                   On the next page load the persisted tile
                   carries the working radio. */
                '<div class="ra-image-tile-field">' +
                    '<label class="ra-image-tile-label">Alt text</label>' +
                    '<input type="text" ' +
                           'name="new_images_meta[' + fileId + '][alt_text]" ' +
                           'class="form-control form-control-sm" ' +
                           'maxlength="255" ' +
                           'placeholder="Describe this image">' +
                '</div>' +
                '<div class="ra-image-tile-actions">' +
                    '<button type="button" ' +
                            'class="ra-btn ra-btn-outline ra-btn-sm" ' +
                            'data-ra-remove-pending>' +
                        '<i class="bi bi-trash me-1" aria-hidden="true"></i> Remove' +
                    '</button>' +
                '</div>' +
            '</div>';

        // Set the alt input value via DOM (avoids any quoting issue).
        var altInput = tile.querySelector('input[name^="new_images_meta["]');
        if (altInput && alt) altInput.value = alt;

        grid.appendChild(tile);

        // 4. Update the pending count on the uploader.
        var uploader = qs('[data-ra-image-uploader]');
        if (uploader) {
            var count = qsa('input[name="new_images_order[]"]', container).length;
            var pending = uploader.querySelector('[data-ra-uploader-pending]');
            if (!pending) {
                pending = document.createElement('div');
                pending.setAttribute('data-ra-uploader-pending', '');
                pending.className = 'ra-image-uploader-pending';
                uploader.appendChild(pending);
            }
            pending.textContent = count +
                ' new image' + (count === 1 ? '' : 's') +
                ' queued. Save to add them to the gallery.';
        }
    }

    // Delegated handler for the pending tile's Remove button.
    // Removes the tile, its meta inputs, and the file id from
    // new_images_order[]. Nothing to add to delete_images[]
    // because the row has not been saved yet.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ra-remove-pending]');
        if (!btn) return;
        e.preventDefault();

        var tile = btn.closest('[data-ra-image-tile]');
        if (!tile) return;

        var fileId = tile.getAttribute('data-ra-pending-file-id');
        if (!fileId) return;

        // Remove the tile's meta inputs (alt, swatch) - they are
        // inside the tile, so removing the tile handles them.
        // Remove the hidden new_images_order[] entry from the
        // container.
        var container = qs('[data-ra-new-images-container]');
        if (container) {
            var orderInput = container.querySelector('input[value="' + fileId + '"]');
            if (orderInput) orderInput.parentNode.removeChild(orderInput);
        }

        tile.parentNode.removeChild(tile);

        // Update the count.
        if (container) {
            var uploader = qs('[data-ra-image-uploader]');
            var count = qsa('input[name="new_images_order[]"]', container).length;
            var pending = uploader ? uploader.querySelector('[data-ra-uploader-pending]') : null;
            if (pending) {
                if (count === 0) {
                    pending.textContent = '';
                } else {
                    pending.textContent = count +
                        ' new image' + (count === 1 ? '' : 's') +
                        ' queued. Save to add them to the gallery.';
                }
            }
        }

        updateEmptyState();
    });

    // ---------- Picker modal ----------

    var pickerModal   = null;
    var pickerCount   = null;
    var pickerConfirm = null;
    var pickerConfirmLabel = null;
    var pickerHint    = null;

    // Picker mode. 'gallery' (default, multi-select) or
    // 'variant' (single-select, sets the slot's hidden
    // variant_images[<valueId>] input).
    var pickerMode    = 'gallery';
    var pickerTarget  = 0;

    function initPicker() {
        pickerModal = qs('[data-ra-picker-modal]');
        if (!pickerModal) return;

        pickerCount   = qs('[data-ra-picker-count]', pickerModal);
        pickerConfirm = qs('[data-ra-picker-confirm]', pickerModal);
        pickerConfirmLabel = qs('[data-ra-picker-confirm-label]', pickerModal);
        pickerHint    = qs('[data-ra-picker-hint]', pickerModal);

        // Checkbox change -> enforce single-select in variant
        // mode, then recount and toggle confirm button.
        pickerModal.addEventListener('change', function (e) {
            if (!e.target.matches('[data-ra-picker-checkbox]')) return;
            if (pickerMode === 'variant' && e.target.checked) {
                qsa('[data-ra-picker-checkbox]', pickerModal).forEach(function (other) {
                    if (other !== e.target) other.checked = false;
                });
            }
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
                if (pickerMode === 'variant' && cb.checked) {
                    qsa('[data-ra-picker-checkbox]', pickerModal).forEach(function (other) {
                        if (other !== cb) other.checked = false;
                    });
                }
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

    function openPicker(mode, target) {
        if (!pickerModal) return;

        pickerMode = (mode === 'variant') ? 'variant' : 'gallery';
        pickerTarget = (pickerMode === 'variant') ? parseInt(target, 10) || 0 : 0;

        if (pickerConfirmLabel) {
            pickerConfirmLabel.textContent = (pickerMode === 'variant')
                ? 'Use this image'
                : 'Add to gallery';
        }
        if (pickerHint) {
            pickerHint.textContent = (pickerMode === 'variant')
                ? 'Click an image to use it for this color value.'
                : 'Click an image to select it.';
        }

        pickerModal.style.display = 'flex';
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
            pickerCount.textContent = (pickerMode === 'variant')
                ? (n > 0 ? '1 selected' : '')
                : (n + ' selected');
        }
        if (pickerConfirm) {
            pickerConfirm.disabled = (n === 0);
        }
    }

    function confirmPicker() {
        var selected = qsa('[data-ra-picker-checkbox]:checked', pickerModal);
        if (!selected.length) return;

        if (pickerMode === 'variant') {
            confirmVariantPicker(selected[0]);
            closePicker();
            return;
        }

        // Gallery mode (multi-select).
        selected.forEach(function (cb) {
            var id = parseInt(cb.value, 10);
            if (!id || id <= 0) return;

            var tile = cb.closest('[data-ra-picker-tile]');
            var uuid = tile ? (tile.getAttribute('data-file-uuid') || '') : '';
            var name = tile ? (tile.getAttribute('data-file-name') || '') : '';
            var alt  = tile ? (tile.getAttribute('data-file-alt')  || '') : '';

            appendNewImage({
                fileId: id,
                uuid:   uuid,
                name:   name,
                alt:    alt
            });
        });

        closePicker();
    }

    /**
     * Variant mode confirm. Updates the slot in place:
     *   1. Hidden input variant_images[<target>] = <fileId>
     *   2. Thumbnail swapped to the picked image
     *   3. Remove button revealed
     *   4. Assign button label -> "Change"
     */
    function confirmVariantPicker(cb) {
        var fileId = parseInt(cb.value, 10);
        if (!fileId || fileId <= 0 || pickerTarget <= 0) return;

        var tile = cb.closest('[data-ra-picker-tile]');
        if (!tile) return;
        var uuid = tile.getAttribute('data-file-uuid') || '';
        if (!uuid) return;

        var slot = document.querySelector(
            '[data-ra-variant-slot][data-attribute-value-id="' + pickerTarget + '"]'
        );
        if (!slot) return;

        var input = slot.querySelector('[data-ra-variant-file-input]');
        if (input) input.value = String(fileId);

        var thumb = slot.querySelector('[data-ra-variant-slot-thumb]');
        if (thumb) {
            var img = document.createElement('img');
            img.src = '/media/' + encodeURIComponent(uuid) + '/md.webp';
            img.alt = '';
            img.loading = 'lazy';
            thumb.innerHTML = '';
            thumb.appendChild(img);
        }

        slot.classList.add('has-image');

        var removeBtn = slot.querySelector('[data-ra-variant-remove]');
        if (removeBtn) removeBtn.hidden = false;

        var assignBtn = slot.querySelector('[data-ra-variant-assign]');
        if (assignBtn) {
            // Update the trailing text node inside the button.
            var nodes = assignBtn.childNodes;
            for (var i = nodes.length - 1; i >= 0; i--) {
                if (nodes[i].nodeType === 3 && nodes[i].textContent.trim() !== '') {
                    nodes[i].textContent = 'Change';
                    break;
                }
            }
        }
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

        // Picker open trigger (gallery mode - default).
        qsa('[data-ra-open-picker]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                openPicker('gallery');
            });
        });

        // Variant slot assign buttons (variant mode).
        qsa('[data-ra-variant-assign]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var valueId = parseInt(btn.getAttribute('data-attribute-value-id'), 10);
                if (valueId > 0) openPicker('variant', valueId);
            });
        });

        // Variant slot remove buttons. The buttons are in the
        // initial DOM (rendered server-side, hidden when no
        // image). Bind directly - no delegation needed.
        qsa('[data-ra-variant-remove]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var valueId = parseInt(btn.getAttribute('data-attribute-value-id'), 10);
                if (valueId <= 0) return;

                var slot = btn.closest('[data-ra-variant-slot]');
                if (!slot) return;

                var input = slot.querySelector('[data-ra-variant-file-input]');
                if (input) input.value = '0';

                var thumb = slot.querySelector('[data-ra-variant-slot-thumb]');
                if (thumb) {
                    var hex = thumb.getAttribute('data-hex') || '';
                    thumb.innerHTML = '';
                    if (hex) {
                        var fill = document.createElement('span');
                        fill.className = 'ra-variant-slot-fill';
                        fill.style.backgroundColor = hex;
                        thumb.appendChild(fill);
                    } else {
                        var empty = document.createElement('div');
                        empty.className = 'ra-variant-slot-empty';
                        empty.innerHTML = '<i class="bi bi-image" aria-hidden="true"></i>';
                        thumb.appendChild(empty);
                    }
                }

                slot.classList.remove('has-image');
                btn.hidden = true;

                var assignBtn = slot.querySelector('[data-ra-variant-assign]');
                if (assignBtn) {
                    var nodes = assignBtn.childNodes;
                    for (var i = nodes.length - 1; i >= 0; i--) {
                        if (nodes[i].nodeType === 3 && nodes[i].textContent.trim() !== '') {
                            nodes[i].textContent = 'Assign';
                            break;
                        }
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
