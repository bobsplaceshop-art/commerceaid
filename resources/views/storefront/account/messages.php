<?php
/**
 * Message center. Rendered inside the account shell by
 * MessageController::index().
 *
 * Context:
 *   $tab           string  current tab: system | order | promotion
 *   $messages      array<int, CustomerNotification>
 *   $unreadCount   int     total unread
 *   $counts        array<string,int>  per-category total
 *   $unreadCounts  array<string,int>  per-category unread
 *   $flashOk       ?string
 *   $customer      ?Customer
 *
 * See docs/stages/008g.3_message-center-plan.md.
 */
$tab          = $tab          ?? 'system';
$messages     = $messages     ?? [];
$unreadCount  = (int) ($unreadCount ?? 0);
$counts       = $counts       ?? ['system' => 0, 'order' => 0, 'promotion' => 0];
$unreadCounts = $unreadCounts ?? ['system' => 0, 'order' => 0, 'promotion' => 0];
$flashOk      = $flashOk      ?? null;

$tabs = [
    'system'    => ['label' => 'System Messages', 'icon' => 'bi-info-circle'],
    'order'     => ['label' => 'Order Updates',   'icon' => 'bi-receipt'],
    'promotion' => ['label' => 'Promotions',      'icon' => 'bi-megaphone'],
];
?>

<div class="sf-account-page-header">
    <div>
        <h1 class="sf-account-page-title">Messages</h1>
        <p class="sf-account-page-subtitle">
            <?php if ($unreadCount > 0): ?>
                <?= (int) $unreadCount ?> unread
                <?= $unreadCount === 1 ? 'message' : 'messages' ?>.
            <?php else: ?>
                You're all caught up.
            <?php endif; ?>
        </p>
    </div>
    <a href="/account/messages/preferences"
       class="sf-recent-clear-btn"
       aria-label="Message preferences">
        <i class="bi bi-gear" aria-hidden="true"></i>
        <span>Preferences</span>
    </a>
</div>

<?php if ($flashOk !== null && $flashOk !== ''): ?>
    <div class="sf-account-flash sf-account-flash-ok" role="status">
        <?= e($flashOk) ?>
    </div>
<?php endif; ?>

<ul class="sf-messages-tabs" role="tablist">
    <?php foreach ($tabs as $key => $meta): ?>
        <?php
        $active = ($key === $tab);
        $count  = (int) ($unreadCounts[$key] ?? 0);
        ?>
        <li role="presentation">
            <a href="/account/messages?tab=<?= e($key) ?>"
               class="sf-messages-tab<?= $active ? ' is-active' : '' ?>"
               role="tab"
               aria-selected="<?= $active ? 'true' : 'false' ?>">
                <i class="bi <?= e($meta['icon']) ?>" aria-hidden="true"></i>
                <span><?= e($meta['label']) ?></span>
                <?php if ($count > 0): ?>
                    <span class="sf-messages-tab-badge"><?= $count ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if ($messages === []): ?>

    <?= view_partial('storefront.account._messages-empty', ['tab' => $tab]) ?>

<?php else: ?>

    <?php if ($unreadCounts[$tab] > 0): ?>
        <form method="POST"
              action="/account/messages/mark-all-read"
              class="sf-messages-mark-all">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <button type="submit" class="sf-recent-clear-btn">
                <i class="bi bi-check2-all" aria-hidden="true"></i>
                <span>Mark all read</span>
            </button>
        </form>
    <?php endif; ?>

    <ul class="sf-messages-list">
        <?php foreach ($messages as $message): ?>
            <?php
            /** @var \CorporateAid\Commerce\Models\CustomerNotification $message */
            $isUnread = !$message->isRead();
            ?>
            <li class="sf-message-row<?= $isUnread ? ' is-unread' : '' ?>">
                <div class="sf-message-icon" aria-hidden="true">
                    <i class="bi <?= e($message->icon()) ?>"></i>
                </div>
                <div class="sf-message-body">
                    <div class="sf-message-head">
                        <h2 class="sf-message-title"><?= e($message->title) ?></h2>
                        <time class="sf-message-time"
                              datetime="<?= e((string) $message->createdAt) ?>"
                              title="<?= e($message->absoluteTime()) ?>">
                            <?= e($message->relativeTime()) ?>
                        </time>
                    </div>
                    <?php if ($message->body !== null && $message->body !== ''): ?>
                        <p class="sf-message-text"><?= e($message->body) ?></p>
                    <?php endif; ?>
                    <div class="sf-message-actions">
                        <?php if ($message->link !== null && $message->link !== ''): ?>
                            <a href="<?= e($message->link) ?>" class="sf-message-link">
                                View
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($isUnread): ?>
                            <form method="POST"
                                  action="/account/messages/mark-read"
                                  class="sf-message-mark-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="notification_id" value="<?= (int) $message->id ?>">
                                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <button type="submit" class="sf-message-mark-btn">
                                    Mark read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

<?php endif; ?>
