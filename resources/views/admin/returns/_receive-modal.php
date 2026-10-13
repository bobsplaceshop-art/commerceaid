<?php
/**
 * Mark return received modal. Stage 008H Session 3.
 *
 * One row per return line. The "actually received" input
 * defaults to the requested quantity, so a merchant who
 * receives everything just clicks Submit. A merchant who
 * received fewer adjusts the input and the refund on that
 * line is reduced accordingly.
 *
 * Context:
 *   $items      array<int,ReturnRequestItem>
 *   $orderItems array<int,OrderItem> keyed by order_item_id
 *
 * Posts received[<order_item_id>] = <qty> to
 * /commerce/ca-admin/returns/{id}/receive.
 */

$items      = $items      ?? [];
$orderItems = $orderItems ?? [];
?>

<div id="ra-receive-return-modal" class="ra-modal" style="display:none;">
    <div class="ra-modal-backdrop" data-ra-receive-return-close></div>
    <div class="ra-modal-panel ra-modal-panel-wide" role="dialog" aria-modal="true" aria-labelledby="ra-receive-return-title">
        <div class="ra-modal-header">
            <h2 class="h6 mb-0" id="ra-receive-return-title">Mark return received</h2>
            <button type="button" class="ra-icon-btn" aria-label="Close" data-ra-receive-return-close>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <form method="POST" id="ra-receive-return-form" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="return" id="ra-receive-return-filter" value="open">

            <div class="ra-modal-body">
                <p class="muted small mb-3">
                    Marking
                    <strong id="ra-receive-return-number">this return</strong>
                    as received. Adjust any line the warehouse did not
                    receive in full. Lines not changed are received as
                    requested.
                </p>

                <?php if (empty($items)): ?>
                    <p class="muted mb-0">This return has no line items.</p>
                <?php else: ?>
                    <div class="ra-table-responsive-wrap" data-responsive="scroll">
                        <table class="ra-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th style="width:120px;text-align:right;">Requested</th>
                                    <th style="width:140px;text-align:right;">Actually received</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $line): ?>
                                    <?php
                                    $oi   = $orderItems[$line->orderItemId] ?? null;
                                    $name = $oi !== null ? (string) $oi->productName : 'Line #' . (int) $line->orderItemId;
                                    $sub  = $oi !== null && $oi->variantLabel !== null && $oi->variantLabel !== ''
                                        ? (string) $oi->variantLabel
                                        : '';
                                    ?>
                                    <tr>
                                        <td>
                                            <div><?= e($name) ?></div>
                                            <?php if ($sub !== ''): ?>
                                                <div class="muted small"><?= e($sub) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;"><?= (int) $line->quantity ?></td>
                                        <td style="text-align:right;">
                                            <input type="number"
                                                   name="received[<?= (int) $line->orderItemId ?>]"
                                                   class="form-control form-control-sm"
                                                   style="max-width:110px;margin-left:auto;"
                                                   min="0"
                                                   max="<?= (int) $line->quantity ?>"
                                                   step="1"
                                                   value="<?= (int) $line->quantity ?>"
                                                   inputmode="numeric">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="muted small mt-2 mb-0">
                        The refund is recomputed from the received quantities.
                        A line set to zero contributes nothing to the refund.
                    </p>
                <?php endif; ?>
            </div>

            <div class="ra-modal-footer">
                <button type="button" class="ra-btn ra-btn-outline" data-ra-receive-return-close>
                    Cancel
                </button>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-box-seam me-1"></i> Mark received
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    var modal    = document.getElementById('ra-receive-return-modal');
    var form     = document.getElementById('ra-receive-return-form');
    var numberEl = document.getElementById('ra-receive-return-number');
    var filterEl = document.getElementById('ra-receive-return-filter');

    if (!modal || !form) return;

    var ACTION_TEMPLATE = '/commerce/ca-admin/returns/__ID__/receive';

    function open(id, number) {
        numberEl.textContent = number || 'this return';

        var params = new URLSearchParams(window.location.search);
        filterEl.value = params.get('return') || 'open';

        form.setAttribute('action', ACTION_TEMPLATE.replace('__ID__', String(id)));

        modal.style.display = '';
        document.body.style.overflow = 'hidden';
    }

    function close() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    modal.querySelectorAll('[data-ra-receive-return-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            close();
        }
    });

    window.RA_ReceiveReturn = { open: open, close: close };

    document.querySelectorAll('[data-ra-receive-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            open(
                btn.getAttribute('data-request-id'),
                btn.getAttribute('data-request-number')
            );
        });
    });
})();
</script>
