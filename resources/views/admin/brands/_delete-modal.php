<?php
/**
 * Brand delete confirmation modal.
 *
 * Opened via JS: RA_DeleteBrand.open(id, name)
 * Fetches /commerce/ca-admin/brands/{id}/delete-info for product count.
 */
?>
<div id="ra-brand-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_DeleteBrand.close()"></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-brand-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-brand-delete-title" class="h6 mb-0">Delete brand</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_DeleteBrand.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete <strong id="ra-brand-delete-name">this brand</strong>?
            </p>
            <div id="ra-brand-delete-info" class="muted small"></div>
            <div id="ra-brand-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DeleteBrand.close()">
                Cancel
            </button>
            <form method="POST" id="ra-brand-delete-form" action="">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.RA_DeleteBrand = (function () {
    var modalId = 'ra-brand-delete-modal';
    var nameEl, infoEl, warningEl, formEl;

    function init() {
        nameEl    = document.getElementById('ra-brand-delete-name');
        infoEl    = document.getElementById('ra-brand-delete-info');
        warningEl = document.getElementById('ra-brand-delete-warning');
        formEl    = document.getElementById('ra-brand-delete-form');
    }

    function open(id, name) {
        if (!nameEl) init();

        document.getElementById(modalId).style.display = 'flex';
        nameEl.textContent = name;
        infoEl.textContent = 'Loading...';
        warningEl.style.display = 'none';
        formEl.action = '/commerce/ca-admin/brands/' + id + '/delete';

        fetch('/commerce/ca-admin/brands/' + id + '/delete-info', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.product_count > 0) {
                infoEl.textContent = data.product_count
                    + ' product' + (data.product_count === 1 ? '' : 's')
                    + ' currently use this brand.';
                warningEl.textContent = 'Products will become unbranded. You can reassign them to another brand later.';
                warningEl.style.display = 'block';
            } else {
                infoEl.textContent = 'No products currently use this brand.';
            }
        })
        .catch(function () {
            infoEl.textContent = 'Could not load details.';
        });
    }

    function close() {
        document.getElementById(modalId).style.display = 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    return { open: open, close: close };
})();
</script>
