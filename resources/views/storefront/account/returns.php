<?php
/**
 * Customer returns landing page at /account/returns.
 *
 * Context (from ReturnController::index):
 *   $customer   Customer
 *   $tab        string  apply|processing|closed
 *   $counts     array{processing:int, closed:int}
 *   $processing array<int, ReturnRequest>
 *   $closed     array<int, ReturnRequest>
 *   $eligible   array<int, Order>  (only populated when tab=apply)
 */
$customer   = $customer   ?? null;
$tab        = $tab        ?? 'apply';
$counts     = $counts     ?? ['processing' => 0, 'closed' => 0];
$processing = $processing ?? [];
$closed     = $closed     ?? [];
$eligible   = $eligible   ?? [];

if ($customer === null) {
    return;
}

// Headline total depends on the tab. The apply tab's count is
// the number of eligible orders, not the number of requests.
$headlineCount = match ($tab) {
    'processing' => count($processing),
    'closed'     => count($closed),
    default      => count($eligible),
};
$headlineNoun = match ($tab) {
    'processing' => $headlineCount === 1 ? 'open return' : 'open returns',
    'closed'     => $headlineCount === 1 ? 'closed return' : 'closed returns',
    default      => $headlineCount === 1 ? 'order you can return' : 'orders you can return',
};
?>
<div class="sf-account-panel" data-sf-page="account-returns">

    <header class="sf-orders-header">
        <h1 class="sf-orders-title">Returns</h1>
        <p class="sf-orders-subhead">
            <?= $headlineCount ?> <?= e($headlineNoun) ?>
        </p>
    </header>

    <?= view_partial('storefront.account._return-tabs', [
        'activeTab' => $tab,
        'counts'    => $counts,
    ]) ?>

    <?php if ($tab === 'apply'): ?>

        <?php if ($eligible === []): ?>
            <div class="sf-orders-empty">
                <div class="sf-orders-empty-icon">
                    <i class="bi bi-arrow-return-left" aria-hidden="true"></i>
                </div>
                <h2 class="sf-orders-empty-title">Nothing to return</h2>
                <p class="sf-orders-empty-body">
                    When you have an order with items you can return,
                    it will appear here.
                </p>
                <a href="/account/orders" class="sf-btn sf-btn-primary">
                    View my orders
                </a>
            </div>
        <?php else: ?>
            <div class="sf-orders-list">
                <?php foreach ($eligible as $o): ?>
                    <div class="sf-order-row">
                        <div class="sf-order-row-main">
                            <div class="sf-order-row-header">
                                <a href="/account/orders/<?= e($o->orderNumber) ?>"
                                   class="sf-order-row-number">
                                    <?= e($o->orderNumber) ?>
                                </a>
                            </div>
                            <div class="sf-order-row-meta">
                                <span>
                                    <?= $o->placedAt !== null
                                        ? e(date('M j, Y', strtotime($o->placedAt)))
                                        : '—' ?>
                                </span>
                                <span class="sf-order-row-dot" aria-hidden="true">&middot;</span>
                                <span>
                                    <?= e(number_format($o->grandTotal, 2)) ?> <?= e($o->currencyCode) ?>
                                </span>
                            </div>
                        </div>
                        <div class="sf-order-row-actions">
                            <a href="/account/returns/new/<?= e($o->orderNumber) ?>"
                               class="sf-btn sf-btn-primary sf-btn-sm">
                                Start a return
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'processing'): ?>

        <?php if ($processing === []): ?>
            <div class="sf-orders-empty">
                <div class="sf-orders-empty-icon">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                </div>
                <h2 class="sf-orders-empty-title">No open returns</h2>
                <p class="sf-orders-empty-body">
                    Returns you have started will appear here while
                    they are being reviewed.
                </p>
            </div>
        <?php else: ?>
            <div class="sf-orders-list">
                <?php foreach ($processing as $r): ?>
                    <?= view_partial('storefront.account._return-row', ['r' => $r]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>

        <?php if ($closed === []): ?>
            <div class="sf-orders-empty">
                <div class="sf-orders-empty-icon">
                    <i class="bi bi-check2-circle" aria-hidden="true"></i>
                </div>
                <h2 class="sf-orders-empty-title">No closed returns</h2>
                <p class="sf-orders-empty-body">
                    Completed and cancelled returns will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="sf-orders-list">
                <?php foreach ($closed as $r): ?>
                    <?= view_partial('storefront.account._return-row', ['r' => $r]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>
