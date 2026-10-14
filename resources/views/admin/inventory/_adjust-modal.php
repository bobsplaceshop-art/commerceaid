<?php
/**
 * Stock adjustment modal.
 *
 * Uses the shared .ra-modal* system so the panel renders in
 * the current theme. The three classes .ra-modal,
 * .ra-modal-backdrop, .ra-modal-panel, .ra-modal-header,
 * .ra-modal-body, .ra-modal-footer are defined in setup.css.
 * The class-level rules are theme-aware: --ra-surface,
 * --ra-border, --ra-text switch with the current data-theme.
 *
 * Every element id and form field name is preserved from the
 * previous version. The JavaScript at the bottom of the file
 * is unchanged.
 */
?>
<div id="ra-adjust-modal" class="ra-modal" style="display:none;">
    <div id="ra-adjust-backdrop" class="ra-modal-backdrop"></div>

    <div class="ra-modal-panel" role="dialog" aria-labelledby="ra-adjust-title">
        <div class="ra-modal-header">
            <h3 id="ra-adjust-title" class="h6 mb-0">Adjust stock</h3>
            <button type="button" id="ra-adjust-close-x" class="ra-icon-btn" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" action="/commerce/ca-admin/inventory/adjust" id="ra-adjust-form">
            <?= csrf_field() ?>

            <div class="ra-modal-body">
                <div class="mb-3">
                    <div class="muted small mb-1">Product</div>
                    <div class="fw-semibold" id="ra-adjust-product-name">Loading...</div>
                    <div class="muted small mt-1" id="ra-adjust-variant-label"></div>
                </div>

                <input type="hidden" name="product_id" id="ra-adjust-product-id" value="">
                <input type="hidden" name="variant_id" id="ra-adjust-variant-id" value="0">

                <div id="ra-adjust-current-box" class="mb-3" style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3); border-radius:8px; padding:.85rem 1rem; font-size:.9rem;">
                    Loading current stock...
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="ra-adjust-newqty" class="form-label">New quantity</label>
                        <input type="number" name="new_quantity" id="ra-adjust-newqty"
                               class="form-control"
                               min="0" step="1" required autocomplete="off">
                        <div class="form-text" id="ra-adjust-delta">Enter the actual stock on hand.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="ra-adjust-type" class="form-label">Reason type</label>
                        <select name="movement_type" id="ra-adjust-type" class="form-select">
                            <option value="adjustment">Adjustment</option>
                            <option value="stocktake">Stocktake</option>
                            <option value="damage">Damage</option>
                            <option value="transfer_in">Transfer in</option>
                            <option value="transfer_out">Transfer out</option>
                            <option value="return">Return</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="ra-adjust-reason" class="form-label">Notes</label>
                    <textarea name="reason" id="ra-adjust-reason" rows="2" class="form-control"
                              placeholder="Optional - a short explanation of why"></textarea>
                </div>
            </div>

            <div class="ra-modal-footer">
                <button type="button" id="ra-adjust-cancel" class="ra-btn ra-btn-outline">
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Save adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modalId = 'ra-adjust-modal';

    function byId(id) { return document.getElementById(id); }

    function open(productId, variantId, productName, variantLabel) {
        var modal = byId(modalId);
        if (!modal) { console.error('Adjust modal: container not found'); return; }

        byId('ra-adjust-product-name').textContent = productName || 'Product';
        var vLabel = byId('ra-adjust-variant-label');
        if (variantLabel) {
            vLabel.textContent = variantLabel;
            vLabel.style.display = 'block';
        } else {
            vLabel.style.display = 'none';
        }

        byId('ra-adjust-product-id').value = productId;
        byId('ra-adjust-variant-id').value = variantId;

        var currentBox = byId('ra-adjust-current-box');
        currentBox.textContent = 'Loading current stock...';

        var qtyInput = byId('ra-adjust-newqty');
        qtyInput.value = '';
        qtyInput.dataset.current = '0';

        var deltaEl = byId('ra-adjust-delta');
        deltaEl.textContent = 'Enter the actual stock on hand.';
        deltaEl.style.color = '';

        modal.style.display = 'flex';

        var controller = new AbortController();
        var timeoutId = setTimeout(function () { controller.abort(); }, 5000);

        fetch('/commerce/ca-admin/inventory/' + productId + '/adjust-info?variant_id=' + variantId, {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        })
        .then(function (r) {
            clearTimeout(timeoutId);
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (data) {
            var current = parseInt(data.quantity, 10) || 0;
            var reserved = parseInt(data.reserved, 10) || 0;
            currentBox.innerHTML = 'Current stock: <strong>' + current + '</strong>' +
                (reserved > 0 ? ' (' + reserved + ' reserved)' : '');
            qtyInput.value = current;
            qtyInput.dataset.current = String(current);
            updateDelta();
            qtyInput.focus();
        })
        .catch(function (err) {
            clearTimeout(timeoutId);
            console.error('Adjust modal fetch failed:', err);
            currentBox.innerHTML = '<span style="color:#dc2626;">Could not load current stock. You can still enter a new quantity.</span>';
            qtyInput.dataset.current = '0';
            qtyInput.focus();
        });
    }

    function close() {
        var modal = byId(modalId);
        if (modal) modal.style.display = 'none';
    }

    function updateDelta() {
        var qtyInput = byId('ra-adjust-newqty');
        var deltaEl = byId('ra-adjust-delta');
        var current = parseInt(qtyInput.dataset.current || '0', 10);
        var next = parseInt(qtyInput.value, 10);

        if (isNaN(next)) {
            deltaEl.textContent = 'Enter the actual stock on hand.';
            deltaEl.style.color = '';
            return;
        }

        var delta = next - current;
        if (delta === 0) {
            deltaEl.textContent = 'No change.';
            deltaEl.style.color = '';
        } else if (delta > 0) {
            deltaEl.textContent = 'Increase by +' + delta;
            deltaEl.style.color = '#16a34a';
        } else {
            deltaEl.textContent = 'Decrease by ' + delta;
            deltaEl.style.color = '#dc2626';
        }
    }

    function init() {
        var cancelBtn = byId('ra-adjust-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', close);

        var closeX = byId('ra-adjust-close-x');
        if (closeX) closeX.addEventListener('click', close);

        var backdrop = byId('ra-adjust-backdrop');
        if (backdrop) backdrop.addEventListener('click', close);

        var qtyInput = byId('ra-adjust-newqty');
        if (qtyInput) qtyInput.addEventListener('input', updateDelta);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.RA_AdjustStock = { open: open, close: close };
})();
</script>
