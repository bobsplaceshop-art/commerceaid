<?php
/**
 * Customer order history at /account/orders.
 *
 * Context (from AccountController::orders):
 *   $customer  Customer
 *   $result    array{items,total,page,per_page,pages}
 *   $tab       string  active tab (unpaid|to_ship|shipped|completed|cancelled|all)
 *   $counts    array<string,int>
 */
$customer = $customer ?? null;
$result   = $result   ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1];
$tab      = $tab      ?? 'all';
$counts   = $counts   ?? [];

if ($customer === null) {
    return;
}

$orders = $result['items'];
$total  = (int) $result['total'];
$page   = (int) $result['page'];
$pages  = (int) $result['pages'];

$emptyCopy = [
    'all'       => 'You have not placed any orders yet.',
    'unpaid'    => 'No orders waiting for payment.',
    'to_ship'   => 'No orders waiting to ship.',
    'shipped'   => 'No shipped orders.',
    'completed' => 'No completed orders yet.',
    'cancelled' => 'No cancelled orders.',
];
$emptyMessage = $emptyCopy[$tab] ?? $emptyCopy['all'];

$pageUrl = function (int $p) use ($tab): string {
    return '/account/orders?' . http_build_query(['tab' => $tab, 'page' => $p]);
};
?>
<div class="sf-orders-wrap" data-sf-page="account-orders">

    <header class="sf-orders-header">
        <h1 class="sf-orders-title">My Orders</h1>
        <p class="sf-orders-subhead">
            <?= $total ?> order<?= $total === 1 ? '' : 's' ?>
            <?php if ($tab !== 'all'): ?>
                in <?= e(str_replace('_', ' ', $tab)) ?>
            <?php endif; ?>
        </p>
    </header>

    <?= view_partial('storefront.account._order-tabs', [
        'activeTab' => $tab,
        'counts'    => $counts,
    ]) ?>

    <?php if ($orders === []): ?>
        <div class="sf-orders-empty">
            <div class="sf-orders-empty-icon">
                <i class="bi bi-receipt" aria-hidden="true"></i>
            </div>
            <h2 class="sf-orders-empty-title"><?= e($emptyMessage) ?></h2>
            <p class="sf-orders-empty-body">
                When you place an order, it will appear here.
            </p>
            <a href="/products" class="sf-btn sf-btn-primary">
                Browse products
            </a>
        </div>

    <?php else: ?>
        <div class="sf-orders-list">
            <?php foreach ($orders as $o): ?>
                <?= view_partial('storefront.account._order-row', ['o' => $o]) ?>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="sf-orders-pagination" aria-label="Orders pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= e($pageUrl($page - 1)) ?>" class="sf-btn sf-btn-outline sf-btn-sm">
                        Previous
                    </a>
                <?php endif; ?>
                <span class="sf-orders-page-indicator">
                    Page <?= $page ?> of <?= $pages ?>
                </span>
                <?php if ($page < $pages): ?>
                    <a href="<?= e($pageUrl($page + 1)) ?>" class="sf-btn sf-btn-outline sf-btn-sm">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

</div>
