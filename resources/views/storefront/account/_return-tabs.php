<?php
/**
 * Tab strip for the customer return list.
 *
 * @var string $activeTab  apply | processing | closed
 * @var array<string,int> $counts
 */
$activeTab = $activeTab ?? 'apply';
$counts    = $counts    ?? [];

$tabs = [
    'apply'      => 'Start a return',
    'processing' => 'In processing',
    'closed'     => 'Closed',
];
?>
<nav class="sf-order-tabs" role="tablist" aria-label="Return tabs">
    <?php foreach ($tabs as $key => $label): ?>
        <?php
        $count    = (int) ($counts[$key] ?? 0);
        $isActive = $key === $activeTab;
        // Hide empty tabs except the apply tab and the active tab.
        // The apply tab always shows - it is the entry point for
        // starting a new return.
        if ($key !== 'apply' && $count === 0 && !$isActive) {
            continue;
        }
        ?>
        <a href="/account/returns?tab=<?= e($key) ?>"
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
