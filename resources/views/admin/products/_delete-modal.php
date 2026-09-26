<?php
/**
 * Product delete confirmation modal.
 *
 * Opened via JS: RA_DeleteProduct.open(id, name)
 */
?>
<div id="ra-product-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_DeleteProduct.close()"></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-product-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-product-delete-title" class="h6 mb-0">Delete product</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_DeleteProduct.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete <strong id="ra-product-delete-name">this product</strong>?
            </p>
            <div id="ra-product-delete-info" class="muted small"></div>
            <div id="ra-product-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;">
                Deleted products are hidden from the catalog and storefront. Historical orders keep their reference.
            </div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DeleteProduct.close()">
                Cancel
            </button>
            <form method="POST" id="ra-product-delete-form" action="">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.RA_DeleteProduct = (function () {
    var modalId = 'ra-product-delete-modal';
    var nameEl, infoEl, warningEl, formEl;

    function init() {
        nameEl    = document.getElementById('ra-product-delete-name');
        infoEl    = document.getElementById('ra-product-delete-info');
        warningEl = document.getElementById('ra-product-delete-warning');
        formEl    = document.getElementById('ra-product-delete-form');
    }

    function open(id, name) {
        if (!nameEl) init();

        document.getElementById(modalId).style.display = 'flex';
        nameEl.textContent = name;
        infoEl.textContent = 'Loading...';
        warningEl.style.display = 'none';
        formEl.action = '/commerce/ca-admin/products/' + id + '/delete';

        fetch('/commerce/ca-admin/products/' + id + '/delete-info', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 'published') {
                infoEl.textContent = 'This product is currently live on the storefront.';
                warningEl.style.display = 'block';
            } else {
                infoEl.textContent = 'Status: ' + data.status;
                warningEl.style.display = 'block';
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
