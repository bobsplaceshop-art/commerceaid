/* =====================================================================
 * Media library page behaviors.
 * =====================================================================
 * Loaded only on /commerce/ca-admin/media.
 *
 * Responsibilities:
 *   - Delete modal open/close, using data-file-id and
 *     data-file-name from the tile button.
 *   - Detail (metadata editor) modal open/close.
 *   - Escape key closes any open modal.
 *   - Filter form: debounced search submit, immediate submit
 *     on select change.
 *
 * No AJAX. All mutations post and redirect.
 * ===================================================================== */
(function () {
    'use strict';

    // ---------- Delete modal ----------
    var deleteModalId  = 'ra-media-delete-modal';
    var deleteNameEl   = null;
    var deleteUsageEl  = null;
    var deleteFormEl   = null;

    function initDelete() {
        deleteNameEl  = document.getElementById('ra-media-delete-name');
        deleteUsageEl = document.getElementById('ra-media-delete-usage');
        deleteFormEl  = document.getElementById('ra-media-delete-form');
    }

    function openDelete(id, name) {
        if (!deleteNameEl) initDelete();
        var modal = document.getElementById(deleteModalId);
        if (!modal) return;

        deleteNameEl.textContent = name || 'this file';
        if (deleteUsageEl) deleteUsageEl.textContent = '';
        if (deleteFormEl) {
            deleteFormEl.action = '/commerce/ca-admin/media/' + encodeURIComponent(id) + '/delete';
        }
        modal.style.display = 'flex';
    }

    function closeDelete() {
        var modal = document.getElementById(deleteModalId);
        if (modal) modal.style.display = 'none';
    }

    // ---------- Detail modal ----------
    var detailModalId = 'ra-media-detail-modal';
    var detailNameEl  = null;
    var detailAltEl   = null;
    var detailFormEl  = null;

    function initDetail() {
        detailNameEl = document.getElementById('ra-media-detail-name');
        detailAltEl  = document.getElementById('ra-media-detail-alt');
        detailFormEl = document.getElementById('ra-media-detail-form');
    }

    function openDetail(id, name, altText) {
        if (!detailNameEl) initDetail();
        var modal = document.getElementById(detailModalId);
        if (!modal) return;

        if (detailNameEl) detailNameEl.textContent = name || 'this file';
        if (detailAltEl)  detailAltEl.value = altText || '';
        if (detailFormEl) {
            detailFormEl.action = '/commerce/ca-admin/media/' + encodeURIComponent(id) + '/meta';
        }
        modal.style.display = 'flex';

        // Focus the alt field for quick editing.
        if (detailAltEl) {
            setTimeout(function () { detailAltEl.focus(); }, 50);
        }
    }

    function closeDetail() {
        var modal = document.getElementById(detailModalId);
        if (modal) modal.style.display = 'none';
    }

    // ---------- Delegated click handlers ----------
    document.addEventListener('click', function (e) {
        var delBtn = e.target.closest('[data-ra-media-delete]');
        if (delBtn) {
            e.preventDefault();
            openDelete(
                delBtn.getAttribute('data-file-id'),
                delBtn.getAttribute('data-file-name')
            );
            return;
        }

        var detBtn = e.target.closest('[data-ra-media-detail]');
        if (detBtn) {
            e.preventDefault();
            openDetail(
                detBtn.getAttribute('data-file-id'),
                detBtn.getAttribute('data-file-name'),
                detBtn.getAttribute('data-file-alt')
            );
            return;
        }

        if (e.target.closest('[data-ra-media-delete-close]')) {
            e.preventDefault();
            closeDelete();
            return;
        }
        if (e.target.closest('[data-ra-media-detail-close]')) {
            e.preventDefault();
            closeDetail();
            return;
        }
    });

    // ---------- Escape key ----------
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeDelete();
        closeDetail();
    });

    // ---------- Filter form ----------
    var filterForm = document.getElementById('ra-media-filters');
    if (filterForm) {
        var searchInput = document.getElementById('ra-media-search');
        var debounce    = null;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () {
                    filterForm.submit();
                }, 350);
            });

            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(debounce);
                    filterForm.submit();
                }
            });
        }

        filterForm.querySelectorAll('[data-ra-filter]').forEach(function (el) {
            el.addEventListener('change', function () {
                filterForm.submit();
            });
        });
    }
})();
