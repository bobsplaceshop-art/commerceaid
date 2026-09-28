<?php
/**
 * Tab strip for the customer order list.
 *
 * @var string $activeTab
 * @var array<string,int> $counts
 */
$activeTab = $activeTab ?? 'all';
$counts    = $counts    ?? [];

$tabs = [
    'unpaid'    => 'Unpaid',
    'to_ship'   => 'To Be Shipped',
    'shipped'   => 'Shipped',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'all'       => 'All',
];
?>
<nav class="sf-order-tabs" role="tablist" aria-label="Order status">
    <?php foreach ($tabs as $key => $label): ?>
        <?php
        $count = (int) ($counts[$key] ?? 0);
        // Hide empty tabs except "All" and the active tab.
        $isActive = $key === $activeTab;
        if ($count === 0 && !$isActive && $key !== 'all') {
            continue;
        }
        ?>
        <a href="/account/orders?tab=<?= e($key) ?>"
           class="sf-order-tab <?= $isActive ? 'is-active' : '' ?>"
           role="tab"
           aria-selected="<?= $isActive ? 'true' : 'false' ?>">
            <?= e($label) ?>
            <?php if ($count > 0): ?>
                <span class="sf-order-tab-count"><?= $count ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
