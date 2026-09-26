<?php
/**
 * Attribute delete confirmation modal.
 *
 * Opened via JS: RA_DeleteAttribute.open(id, name)
 */
?>
<div id="ra-attribute-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" onclick="RA_DeleteAttribute.close()"></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-attribute-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-attribute-delete-title" class="h6 mb-0">Delete attribute</h3>
            <button type="button" class="ra-icon-btn" onclick="RA_DeleteAttribute.close()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3">
                Delete <strong id="ra-attribute-delete-name">this attribute</strong>?
            </p>
            <div id="ra-attribute-delete-info" class="muted small"></div>
            <div id="ra-attribute-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" onclick="RA_DeleteAttribute.close()">
                Cancel
            </button>
            <form method="POST" id="ra-attribute-delete-form" action="">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
window.RA_DeleteAttribute = (function () {
    var modalId = 'ra-attribute-delete-modal';
    var nameEl, infoEl, warningEl, formEl;

    function init() {
        nameEl    = document.getElementById('ra-attribute-delete-name');
        infoEl    = document.getElementById('ra-attribute-delete-info');
        warningEl = document.getElementById('ra-attribute-delete-warning');
        formEl    = document.getElementById('ra-attribute-delete-form');
    }

    function open(id, name) {
        if (!nameEl) init();

        document.getElementById(modalId).style.display = 'flex';
        nameEl.textContent = name;
        infoEl.textContent = 'Loading...';
        warningEl.style.display = 'none';
        formEl.action = '/commerce/ca-admin/attributes/' + id + '/delete';

        fetch('/commerce/ca-admin/attributes/' + id + '/delete-info', {
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var parts = [];
            if (data.value_count > 0) {
                parts.push(data.value_count + ' value' + (data.value_count === 1 ? '' : 's'));
            }
            infoEl.textContent = parts.length
                ? 'This attribute has ' + parts.join(' and ') + '.'
                : 'This attribute has no values.';

            if (data.usage_count > 0) {
                warningEl.textContent = 'This attribute is used by ' + data.usage_count +
                    ' product' + (data.usage_count === 1 ? '' : 's') +
                    ' and cannot be deleted. Remove the attribute from those products first.';
                warningEl.style.display = 'block';
                formEl.querySelector('button[type="submit"]').disabled = true;
            } else {
                formEl.querySelector('button[type="submit"]').disabled = false;
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
