<?php
/**
 * Logs hub sub-navigation.
 *
 * @var array  $tabMeta     [key => ['label' => ..., 'icon' => ...]]
 * @var string $currentTab
 */
$tabMeta    = $tabMeta    ?? [];
$currentTab = $currentTab ?? 'overview';
?>
<nav class="ra-setup-nav" aria-label="Logs sections">
    <div class="ra-setup-nav-header">
        <div class="ra-setup-nav-title">Logs</div>
        <div class="ra-setup-nav-subtitle">
            Four views. Three audiences.
        </div>
    </div>

    <ul class="ra-setup-nav-list">
        <?php foreach ($tabMeta as $key => $meta): ?>
            <?php $active = ($key === $currentTab); ?>
            <li>
                <a href="/commerce/ca-admin/logs<?= $key === 'overview' ? '' : '/' . e($key) ?>"
                   class="ra-setup-nav-item<?= $active ? ' is-active' : '' ?>">
                    <i class="bi <?= e($meta['icon']) ?>"></i>
                    <span><?= e($meta['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="ra-setup-nav-footer">
        <p class="muted small mb-0">
            <strong>Activity</strong> is the merchant timeline.
            <strong>System</strong> and <strong>Audit</strong> are
            developer-facing.
        </p>
    </div>
</nav>
