<?php
/**
 * Shipping method / zone delete confirmation modal.
 *
 * Opened via delegated click on [data-shipping-delete]. The
 * trigger carries:
 *   data-shipping-type="method" | "zone"
 *   data-shipping-id="N"
 *
 * The modal fetches /commerce/ca-admin/shipping/{type}/{id}/delete-info
 * and either enables the submit button (can_delete: true) or
 * shows the blockers and disables it.
 *
 * The label comes from the delete-info response. For a method
 * the label is the method's name. For a zone the label is a
 * country / region summary.
 */
?>
<div id="ra-shipping-delete-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-close-shipping-delete></div>
    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-shipping-delete-title">
        <div class="ra-modal-header">
            <h3 id="ra-shipping-delete-title" class="h6 mb-0">Delete</h3>
            <button type="button" class="ra-icon-btn" data-ra-close-shipping-delete aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="ra-modal-body">
            <p class="mb-3" id="ra-shipping-delete-question">
                Delete this item?
            </p>
            <div id="ra-shipping-delete-info" class="muted small"></div>
            <div id="ra-shipping-delete-warning" class="ra-alert ra-alert-warning mt-3" style="display:none;"></div>
        </div>
        <div class="ra-modal-footer">
            <button type="button" class="ra-btn ra-btn-outline" data-ra-close-shipping-delete>
                Cancel
            </button>
            <form method="POST" id="ra-shipping-delete-form" action="" style="margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-danger" id="ra-shipping-delete-submit">
                    <i class="bi bi-trash me-1"></i> Delete
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal      = document.getElementById('ra-shipping-delete-modal');
    var titleEl    = document.getElementById('ra-shipping-delete-title');
    var questionEl = document.getElementById('ra-shipping-delete-question');
    var infoEl     = document.getElementById('ra-shipping-delete-info');
    var warningEl  = document.getElementById('ra-shipping-delete-warning');
    var formEl     = document.getElementById('ra-shipping-delete-form');
    var submitEl   = document.getElementById('ra-shipping-delete-submit');

    if (!modal || !formEl) return;

    function setQuestion(type, label) {
        var noun = type === 'method' ? 'shipping method' : 'shipping zone';
        questionEl.innerHTML = 'Delete ' + noun + ' <strong></strong>?';
        questionEl.querySelector('strong').textContent = label || '';
    }

    function open(type, id) {
        modal.style.display = 'flex';
        titleEl.textContent = type === 'method' ? 'Delete shipping method' : 'Delete shipping zone';
        questionEl.textContent = 'Loading...';
        infoEl.textContent = '';
        warningEl.style.display = 'none';
        warningEl.textContent = '';
        submitEl.disabled = true;
        submitEl.style.opacity = '0.5';

        formEl.action = '/commerce/ca-admin/shipping/'
            + (type === 'method' ? 'method' : 'zone')
            + '/' + id + '/delete';

        fetch('/commerce/ca-admin/shipping/'
                + (type === 'method' ? 'method' : 'zone')
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
                    infoEl.textContent = type === 'method'
                        ? 'This method has not been used by recent orders.'
                        : 'This zone can be deleted.';
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

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-shipping-delete]');
        if (btn) {
            e.preventDefault();
            var type = btn.getAttribute('data-shipping-type') || '';
            var id   = btn.getAttribute('data-shipping-id') || '';
            if (type && id) open(type, id);
            return;
        }
        if (e.target.closest('[data-ra-close-shipping-delete]')) {
            e.preventDefault();
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') close();
    });
})();
</script>
