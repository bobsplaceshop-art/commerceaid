<?php
/**
 * Setup hub sub-navigation.
 *
 * @var array  $tabMeta       [key => ['label' => ..., 'icon' => ..., 'real' => bool]]
 * @var string $currentTab
 * @var array  $missing       Mandatory fields still missing
 * @var bool   $setupComplete
 */
$tabMeta       = $tabMeta       ?? [];
$currentTab    = $currentTab    ?? 'business';
$missing       = $missing       ?? [];
$setupComplete = $setupComplete ?? false;
$missingCount  = count($missing);
?>
<nav class="ra-setup-nav" aria-label="Setup sections">
    <div class="ra-setup-nav-header">
        <div class="ra-setup-nav-title">Setup</div>
        <div class="ra-setup-nav-subtitle">
            <?php if ($setupComplete): ?>
                All required steps complete
            <?php else: ?>
                <?= $missingCount ?>
                item<?= $missingCount === 1 ? '' : 's' ?> still needed
            <?php endif; ?>
        </div>
    </div>

    <ul class="ra-setup-nav-list">
        <?php foreach ($tabMeta as $key => $meta): ?>
            <?php
            $active = ($key === $currentTab);
            $classes = ['ra-setup-nav-item'];
            if ($active)            $classes[] = 'is-active';
            if (!($meta['real'] ?? true)) $classes[] = 'is-stub';
            ?>
            <li>
                <a href="/commerce/ca-admin/setup/<?= e($key) ?>"
                   class="<?= e(implode(' ', $classes)) ?>">
                    <i class="bi <?= e($meta['icon']) ?>"></i>
                    <span><?= e($meta['label']) ?></span>
                    <?php if (!($meta['real'] ?? true)): ?>
                        <span class="ra-setup-nav-tag">Soon</span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="ra-setup-nav-footer">
        <a href="/commerce/ca-admin/setup/skip" class="ra-setup-nav-skip">
            <i class="bi bi-x-circle me-1"></i> Remind me later
        </a>
    </div>
</nav>
