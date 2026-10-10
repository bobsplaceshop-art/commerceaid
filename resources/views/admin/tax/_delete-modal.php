<?php
/**
 * Tax class / rate delete confirmation modal.
 *
 * Opened via delegated click on [data-tax-delete]. The
 * trigger carries:
 *   data-tax-type="class" | "rate"
 *   data-tax-id="N"
 *
 * The modal fetches /commerce/ca-admin/tax/{type}/{id}/delete-info
 * and either enables the submit button (can_delete: true) or
 * shows the blockers and disables it.
 *
 * The label ("Delete tax class Standard?") comes from the
 * delete-info response so the modal does not need the display
 * name baked into the trigger.
 */
?>
<div id="ra-tax-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-close-tax-delete></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-tax-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-tax-delete-title" class="h6 mb-0">Delete</h3>
            <button type="button" class="ra-icon-btn" data-ra-close-tax-delete aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3" id="ra-tax-delete-question">
                Delete this item?
            </p>
            <div id="ra-tax-delete-info" class="muted small"></div>
            <div id="ra-tax-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-close-tax-delete>
                Cancel
            </button>
            <form method="POST" id="ra-tax-delete-form" action="" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger" id="ra-tax-delete-submit">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal      = document.getElementById('ra-tax-delete-modal');
    var titleEl    = document.getElementById('ra-tax-delete-title');
    var questionEl = document.getElementById('ra-tax-delete-question');
    var infoEl     = document.getElementById('ra-tax-delete-info');
    var warningEl  = document.getElementById('ra-tax-delete-warning');
    var formEl     = document.getElementById('ra-tax-delete-form');
    var submitEl   = document.getElementById('ra-tax-delete-submit');

    if (!modal || !formEl) return;

    function setQuestion(type, label) {
        var noun = type === 'class' ? 'tax class' : 'tax rate';
        questionEl.innerHTML = 'Delete ' + noun + ' <strong></strong>?';
        questionEl.querySelector('strong').textContent = label || '';
    }

    function open(type, id) {
        // Reset state.
        modal.style.display = 'flex';
        titleEl.textContent = type === 'class' ? 'Delete tax class' : 'Delete tax rate';
        questionEl.textContent = 'Loading...';
        infoEl.textContent = '';
        warningEl.style.display = 'none';
        warningEl.textContent = '';
        submitEl.disabled = true;
        submitEl.style.opacity = '0.5';

        formEl.action = '/commerce/ca-admin/tax/'
            + (type === 'class' ? 'class' : 'rate')
            + '/' + id + '/delete';

        fetch('/commerce/ca-admin/tax/'
                + (type === 'class' ? 'class' : 'rate')
                + '/' + id + '/delete-info',
              { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) {
                    infoEl.textContent = 'Could not load details.';
                    questionEl.textContent = 'Delete this item?';
                    return;
                }

                setQuestion(type, data.label || '');

                if (data.can_delete) {
                    infoEl.textContent = type === 'class'
                        ? 'This class has no rates and no products.'
                        : 'This rate is not referenced by recent orders.';
                    submitEl.disabled = false;
                    submitEl.style.opacity = '1';
                } else {
                    infoEl.textContent = '';
                    warningEl.textContent = data.blockers && data.blockers.length
                        ? data.blockers.join(' ')
                        : 'This item cannot be deleted.';
                    warningEl.style.display = 'block';
                    submitEl.disabled = true;
                    submitEl.style.opacity = '0.5';
                }
            })
            .catch(function () {
                infoEl.textContent = 'Could not load details.';
                questionEl.textContent = 'Delete this item?';
            });
    }

    function close() {
        modal.style.display = 'none';
    }

    // Delegated open: [data-tax-delete].
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tax-delete]');
        if (btn) {
            e.preventDefault();
            var type = btn.getAttribute('data-tax-type') || '';
            var id   = btn.getAttribute('data-tax-id') || '';
            if (type && id) open(type, id);
            return;
        }
        if (e.target.closest('[data-ra-close-tax-delete]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') close();
    });
})();
</script>
