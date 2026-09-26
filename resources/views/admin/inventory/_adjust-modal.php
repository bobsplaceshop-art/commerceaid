<?php
/**
 * Stock adjustment modal.
 */
?>
<div id="ra-adjust-modal" style="display:none; position:fixed; inset:0; z-index:9999; align-items:center; justify-content:center; background:rgba(11,18,32,0.85); backdrop-filter:blur(3px);">
    <div id="ra-adjust-backdrop" style="position:absolute; inset:0; cursor:pointer;"></div>

    <div style="position:relative; z-index:10000; width:100%; max-width:520px; max-height:calc(100vh - 2rem); background:#111c33; color:#e2e8f0; border-radius:14px; border:1px solid #1f2a44; box-shadow:0 25px 70px -20px rgba(0,0,0,0.7); overflow:hidden; display:flex; flex-direction:column; margin:1rem;">
        <div style="display:flex; align-items:center; justify-content:space-between; padding:1.25rem 1.5rem; border-bottom:1px solid #1f2a44; flex-shrink:0;">
            <h3 style="margin:0; font-size:1.05rem; font-weight:600;">Adjust stock</h3>
            <button type="button" id="ra-adjust-close-x" style="padding:.35rem; border:0; background:transparent; color:#94a3b8; cursor:pointer; font-size:1rem;" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" action="/commerce/ca-admin/inventory/adjust" id="ra-adjust-form" style="margin:0; display:flex; flex-direction:column; flex:1; min-height:0;">
            <?= csrf_field() ?>

            <div style="padding:1.5rem; overflow-y:auto; flex:1;">
                <div style="margin-bottom:1rem;">
                    <div style="color:#94a3b8; font-size:.85rem; margin-bottom:.25rem;">Product</div>
                    <div style="font-weight:600; font-size:1rem;" id="ra-adjust-product-name">Loading...</div>
                    <div style="color:#94a3b8; font-size:.85rem; margin-top:.15rem;" id="ra-adjust-variant-label"></div>
                </div>

                <input type="hidden" name="product_id" id="ra-adjust-product-id" value="">
                <input type="hidden" name="variant_id" id="ra-adjust-variant-id" value="0">

                <div id="ra-adjust-current-box" style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3); border-radius:8px; padding:.85rem 1rem; margin-bottom:1.25rem; font-size:.9rem;">
                    Loading current stock...
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.25rem;">
                    <div>
                        <label for="ra-adjust-newqty" style="display:block; color:#94a3b8; font-size:.85rem; margin-bottom:.4rem;">New quantity</label>
                        <input type="number" name="new_quantity" id="ra-adjust-newqty"
                               style="width:100%; background:#0b1424; border:1px solid #1f2a44; border-radius:8px; color:#e2e8f0; padding:.6rem .75rem; font-size:.95rem;"
                               min="0" step="1" required autocomplete="off">
                        <div style="color:#94a3b8; font-size:.78rem; margin-top:.35rem;" id="ra-adjust-delta">Enter the actual stock on hand.</div>
                    </div>
                    <div>
                        <label for="ra-adjust-type" style="display:block; color:#94a3b8; font-size:.85rem; margin-bottom:.4rem;">Reason type</label>
                        <select name="movement_type" id="ra-adjust-type"
                                style="width:100%; background:#0b1424; border:1px solid #1f2a44; border-radius:8px; color:#e2e8f0; padding:.6rem .75rem; font-size:.95rem;">
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
                    <label for="ra-adjust-reason" style="display:block; color:#94a3b8; font-size:.85rem; margin-bottom:.4rem;">Notes</label>
                    <textarea name="reason" id="ra-adjust-reason" rows="2"
                              placeholder="Optional - a short explanation of why"
                              style="width:100%; background:#0b1424; border:1px solid #1f2a44; border-radius:8px; color:#e2e8f0; padding:.6rem .75rem; font-size:.9rem; resize:vertical;"></textarea>
                </div>
            </div>

            <div style="display:flex; align-items:center; justify-content:flex-end; gap:.5rem; padding:1.25rem 1.5rem; border-top:1px solid #1f2a44; flex-shrink:0; background:#0b1424;">
                <button type="button" id="ra-adjust-cancel" style="background:transparent; border:1px solid #334155; color:#e2e8f0; border-radius:8px; padding:.55rem 1rem; font-weight:500; cursor:pointer;">
                    Cancel
                </button>
                <button type="submit" style="background:#2563eb; border:1px solid #2563eb; color:#fff; border-radius:8px; padding:.55rem 1rem; font-weight:600; cursor:pointer;">
                    <i class="bi bi-check-lg" style="margin-right:.25rem;"></i> Save adjustment
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

        // Set name and variant right away
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

        // Fetch current stock with a timeout
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
            currentBox.innerHTML = '<span style="color:#fca5a5;">Could not load current stock. You can still enter a new quantity.</span>';
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
            deltaEl.style.color = '#94a3b8';
        } else if (delta > 0) {
            deltaEl.textContent = 'Increase by +' + delta;
            deltaEl.style.color = '#22c55e';
        } else {
            deltaEl.textContent = 'Decrease by ' + delta;
            deltaEl.style.color = '#ef4444';
        }
    }

    // Wire up everything once DOM is ready
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
