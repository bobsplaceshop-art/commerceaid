<?php
/**
 * Return submission form at /account/returns/new/{orderNumber}.
 *
 * Context (from ReturnController::create):
 *   $customer     Customer
 *   $order        Order
 *   $lines        array<int, array{item: OrderItem, outstanding: int}>
 *   $windowCloses ?string
 *
 * On validation failure, the controller flashes the error message
 * and old input, then redirects back to this form. The form reads
 * the old input from $_SESSION['_old'] and repopulates.
 *
 * See docs/stages/008h_returns-refunds-plan.md.
 */
$customer     = $customer     ?? null;
$order        = $order        ?? null;
$lines        = $lines        ?? [];
$windowCloses = $windowCloses ?? null;

if ($customer === null || $order === null) {
    return;
}

$old       = $_SESSION['_old'] ?? [];
$oldLines  = is_array($old['lines'] ?? null) ? $old['lines'] : [];
$oldReason = (string) ($old['reason_code'] ?? 'changed_mind');
$oldNote   = (string) ($old['reason_note'] ?? '');

$reasons = [
    'changed_mind'     => 'I changed my mind',
    'damaged'          => 'The item arrived damaged',
    'wrong_item'       => 'I received the wrong item',
    'not_as_described' => 'The item does not match the description',
];
?>
<div class="sf-account-panel" data-sf-page="account-return-form">

    <h1 class="sf-auth-heading">Start a return</h1>
    <p class="sf-auth-subhead">
        Order
        <a href="/account/orders/<?= e($order->orderNumber) ?>" class="sf-link">
            <?= e($order->orderNumber) ?>
        </a>
    </p>

    <?php if ($windowCloses !== null): ?>
        <p class="sf-form-help">
            The return window for this order closes on
            <strong><?= e(date('F j, Y', strtotime($windowCloses))) ?></strong>.
        </p>
    <?php endif; ?>

    <form method="POST" action="/account/returns" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="order_number" value="<?= e($order->orderNumber) ?>">

        <section class="sf-return-section">
            <h2 class="sf-return-section-title">Items to return</h2>

            <div class="sf-return-lines">
                <?php foreach ($lines as $row): ?>
                    <?php
                    /** @var \CorporateAid\Commerce\Models\OrderItem $item */
                    $item        = $row['item'];
                    $max         = (int) $row['outstanding'];
                    $postedQty   = (int) ($oldLines[$item->id]['qty'] ?? 0);
                    $initialQty  = $postedQty > 0 ? min($postedQty, $max) : 0;
                    ?>
                    <div class="sf-return-line">
                        <div class="sf-return-line-main">
                            <div class="sf-return-line-name"><?= e($item->productName) ?></div>
                            <?php if ($item->variantLabel !== null && $item->variantLabel !== ''): ?>
                                <div class="sf-return-line-variant"><?= e($item->variantLabel) ?></div>
                            <?php endif; ?>
                            <?php if ($item->sku !== null && $item->sku !== ''): ?>
                                <div class="sf-return-line-sku">
                                    SKU <code><?= e($item->sku) ?></code>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sf-return-line-qty">
                            <label class="sf-field-label" for="qty-<?= (int) $item->id ?>">
                                Quantity
                            </label>
                            <input type="number"
                                   class="sf-input"
                                   id="qty-<?= (int) $item->id ?>"
                                   name="lines[<?= (int) $item->id ?>][qty]"
                                   value="<?= (int) $initialQty ?>"
                                   min="0"
                                   max="<?= $max ?>"
                                   step="1">
                            <span class="sf-form-help">
                                of <?= $max ?> returnable
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="sf-return-section">
            <h2 class="sf-return-section-title">
                Why are you returning these items? <span class="req">*</span>
            </h2>

            <div class="sf-return-reasons">
                <?php foreach ($reasons as $code => $label): ?>
                    <label class="sf-check">
                        <input type="radio"
                               name="reason_code"
                               value="<?= e($code) ?>"
                               <?= $oldReason === $code ? 'checked' : '' ?>>
                        <span class="sf-check-label"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <p class="sf-form-help">
                <a href="/legal/widerruf" class="sf-link" target="_blank" rel="noopener">
                    Read the full withdrawal policy
                </a>
            </p>
        </section>

        <section class="sf-return-section">
            <h2 class="sf-return-section-title">Additional notes</h2>
            <div class="sf-field">
                <textarea name="reason_note"
                          class="sf-input"
                          rows="4"
                          maxlength="1000"
                          placeholder="Anything else we should know? (optional)"><?= e($oldNote) ?></textarea>
            </div>
        </section>

        <div class="sf-form-actions" style="display:flex;gap:0.75rem;">
            <a href="/account/returns" class="sf-btn sf-btn-outline" style="flex:1;justify-content:center;">
                Cancel
            </a>
            <button type="submit" class="sf-btn sf-btn-primary" style="flex:2;justify-content:center;">
                Submit return request
            </button>
        </div>
    </form>

</div>
