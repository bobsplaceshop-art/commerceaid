<?php
/**
 * Category delete confirmation modal.
 *
 * Opened via JS: RA_DeleteCategory.open(id, name)
 * Fetches /commerce/ca-admin/categories/{id}/delete-info for
 * dependent counts, shows them, and requires confirmation.
 */
?>
<div id="ra-cat-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_DeleteCategory.close()"></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-cat-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-cat-delete-title" class="h6 mb-0">Delete category</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_DeleteCategory.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete <strong id="ra-cat-delete-name">this category</strong>?
            </p>
            <div id="ra-cat-delete-info" class="muted small"></div>
            <div id="ra-cat-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DeleteCategory.close()">
                Cancel
            </button>
            <form method="POST" id="ra-cat-delete-form" action="">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<style>
.ra-modal {
    position: fixed; inset: 0; z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}
.ra-modal-backdrop {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(2px);
}
.ra-modal-panel {
    position: relative;
    background: var(--ra-card-bg, #ffffff);
    border: 1px solid var(--ra-border, #e5e9f2);
    border-radius: 14px;
    width: 100%; max-width: 440px;
    box-shadow: 0 20px 60px -20px rgba(15, 23, 42, 0.35);
    overflow: hidden;
}
.ra-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--ra-border, #e5e9f2);
}
.ra-modal-body {
    padding: 1.25rem;
}
.ra-modal-footer {
    display: flex; align-items: center; justify-content: flex-end;
    gap: .5rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--ra-border, #e5e9f2);
}
.ra-modal-footer form { margin: 0; }
</style>

<script>
window.RA_DeleteCategory = (function () {
    var modalId = 'ra-cat-delete-modal';
    var nameEl, infoEl, warningEl, formEl;

    function init() {
        nameEl    = document.getElementById('ra-cat-delete-name');
        infoEl    = document.getElementById('ra-cat-delete-info');
        warningEl = document.getElementById('ra-cat-delete-warning');
        formEl    = document.getElementById('ra-cat-delete-form');
    }

    function open(id, name) {
        if (!nameEl) init();

        document.getElementById(modalId).style.display = 'flex';
        nameEl.textContent = name;
        infoEl.textContent = 'Loading...';
        warningEl.style.display = 'none';
        formEl.action = '/commerce/ca-admin/categories/' + id + '/delete';

        fetch('/commerce/ca-admin/categories/' + id + '/delete-info', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var lines = [];
            if (data.product_count > 0) {
                lines.push(data.product_count + ' product' + (data.product_count === 1 ? '' : 's') + ' in this category');
            }
            if (data.child_count > 0) {
                lines.push(data.child_count + ' subcategor' + (data.child_count === 1 ? 'y' : 'ies'));
            }
            infoEl.textContent = lines.length ? 'This will affect: ' + lines.join(', ') + '.' : 'This category is not used by any products.';

            if (data.product_count > 0 || data.child_count > 0) {
                warningEl.textContent = 'Products will keep their other category assignments. Subcategories will become top-level.';
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
