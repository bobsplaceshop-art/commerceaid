<?php
/**
 * User dropdown menu. Positioned by JS relative to the trigger.
 * Rendered by partials/topbar.php.
 */
$user = $user ?? null;
?>
<div id="ra-user-dropdown" class="ra-dropdown" style="min-width:230px;">
    <?php if ($user !== null): ?>
        <div class="ra-dropdown-header">
            <div class="small"><?= e($user->name) ?></div>
            <div class="muted" style="font-size:.78rem;"><?= e($user->email) ?></div>
        </div>
    <?php endif; ?>

    <?php /* Account pages deferred to a later stage */ ?>

    <form method="POST" action="/logout" style="margin:0;">
        <?= csrf_field() ?>
        <button type="submit" class="ra-dropdown-item" style="color:#fecaca;">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign out</span>
        </button>
    </form>
</div>
