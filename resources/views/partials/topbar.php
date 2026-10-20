<?php
/**
 * Admin topbar.
 *
 * Left: mobile hamburger + breadcrumbs
 * Right: theme toggle, settings shortcut, user menu
 *
 * Context:
 *   $user  - current User (may be null)
 *   $title - page title for breadcrumb
 */
$user  = $user  ?? null;
$title = $title ?? null;
?>
<header class="ra-topbar">
    <div class="d-flex align-items-center gap-3" style="min-width:0;">
        <button type="button" class="ra-icon-btn ra-hamburger" data-ra-sidebar-toggle aria-label="Toggle menu">
            <i class="bi bi-list" style="font-size:1.15rem;"></i>
        </button>

        <div class="ra-breadcrumbs d-none d-md-flex">
            <a href="/commerce/ca-admin">Admin</a>
            <?php if (!empty($title) && $title !== 'Dashboard'): ?>
                <span>/</span>
                <span><?= e($title) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <script src="<?= versioned_asset('/js/admin-search.js') ?>"></script>

        <div class="ra-search-wrapper" style="position:relative; width:300px;">
            <form method="GET" action="/commerce/ca-admin/search" style="margin:0;">
                <input type="text" name="q" class="ra-search" placeholder="Search admin..." value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off" style="width:100%;">
            </form>
        </div>

        <button type="button" class="ra-icon-btn" data-ra-theme-toggle title="Theme">
            <i class="bi bi-sun-fill" style="display:none;" data-ra-icon-light></i>
            <i class="bi bi-moon-stars-fill" data-ra-icon-dark></i>
            <i class="bi bi-display" style="display:none;" data-ra-icon-system></i>
        </button>

        <?php if (\CorporateAid\Core\App::$hasPendingUpdates): ?>
            <a href="/commerce/ca-admin/update" class="ra-icon-btn" title="Database updates pending">
                <i class="bi bi-exclamation-triangle" style="color:#ef4444;"></i>
            </a>
        <?php endif; ?>
        <?php if ($user !== null && $user->can('core.settings.manage')): ?>
            <a href="/commerce/ca-admin/store-settings" class="ra-icon-btn" title="Settings">
                <i class="bi bi-gear"></i>
            </a>
        <?php endif; ?>

        <?php if ($user !== null): ?>
            <button type="button" class="ra-user-menu" data-ra-user-menu aria-label="User menu">
                <div class="ra-avatar"><?= e(strtoupper(substr($user->name, 0, 1))) ?></div>
                <div class="d-none d-md-block" style="line-height:1.2;">
                    <div class="small"><?= e($user->name) ?></div>
                    <div class="muted" style="font-size:.72rem;"><?= e($user->email) ?></div>
                </div>
                <i class="bi bi-chevron-down muted small"></i>
            </button>
        <?php endif; ?>
    </div>

    <?= view_partial('partials/theme-dropdown') ?>

    <?php if ($user !== null): ?>
        <?= view_partial('partials/user-dropdown', ['user' => $user]) ?>
    <?php endif; ?>
</header>


