<?php
/**
 * Tax class create / edit modal.
 *
 * Opened via delegated click on [data-tax-class-open].
 *   data-tax-class-open="create"
 *   data-tax-class-open="edit"
 *     data-tax-class-id="1"
 *     data-tax-class-name="Standard"
 *     data-tax-class-default="1"|"0"
 *
 * The modal switches the form's action and the title
 * depending on the mode. Submits to POST /commerce/ca-admin/tax/classes
 * (create) or POST /commerce/ca-admin/tax/classes/{id} (edit).
 */
?>
<div id="ra-tax-class-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-close-tax-class></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-tax-class-title">
        <div class="ra-modal-header">
            <h3 id="ra-tax-class-title" class="h6 mb-0">Tax class</h3>
            <button type="button" class="ra-icon-btn" data-ra-close-tax-class aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" id="ra-tax-class-form" action="">
            <?= csrf_field() ?>
            <div class="ra-modal-body">
                <div class="mb-3">
                    <label class="muted small" for="tcf-name">
                        Name <span aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="tcf-name" name="name"
                           class="form-control"
                           maxlength="80"
                           required>
                    <div class="muted small mt-1">
                        Common examples: Standard, Reduced, Zero.
                    </div>
                </div>

                <div class="form-check form-switch">
                    <input type="hidden" name="is_default" value="0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="tcf-default" name="is_default" value="1">
                    <label class="form-check-label small" for="tcf-default">
                        <strong>Default class</strong>
                        <div class="muted" style="font-size:.75rem;">
                            Products without their own class use this one.
                        </div>
                    </label>
                </div>
            </div>
            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-close-tax-class>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary" id="ra-tax-class-submit">
                    <i class="bi bi-check2 me-1"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal     = document.getElementById('ra-tax-class-modal');
    var form      = document.getElementById('ra-tax-class-form');
    var titleEl   = document.getElementById('ra-tax-class-title');
    var nameEl    = document.getElementById('tcf-name');
    var defaultEl = document.getElementById('tcf-default');
    var submitEl  = document.getElementById('ra-tax-class-submit');

    if (!modal || !form) return;

    function open(mode, id, name, isDefault) {
        form.action = mode === 'edit'
            ? '/commerce/ca-admin/tax/classes/' + id
            : '/commerce/ca-admin/tax/classes';

        titleEl.textContent = mode === 'edit' ? 'Edit tax class' : 'New tax class';
        nameEl.value = name || '';
        defaultEl.checked = String(isDefault) === '1' || isDefault === true;
        submitEl.innerHTML = '<i class="bi bi-check2 me-1"></i> '
            + (mode === 'edit' ? 'Save changes' : 'Create class');

        modal.style.display = 'flex';
        setTimeout(function () { nameEl.focus(); }, 50);
    }

    function close() {
        modal.style.display = 'none';
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-tax-class-open]');
        if (trigger) {
            e.preventDefault();
            var mode   = trigger.getAttribute('data-tax-class-open');
            var id     = trigger.getAttribute('data-tax-class-id') || '';
            var name   = trigger.getAttribute('data-tax-class-name') || '';
            var isDef  = trigger.getAttribute('data-tax-class-default') || '0';
            open(mode, id, name, isDef);
            return;
        }
        if (e.target.closest('[data-ra-close-tax-class]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') close();
    });
})();
</script>
