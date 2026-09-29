<?php
/**
 * Message preferences page. Rendered inside the account shell by
 * MessageController::preferences().
 *
 * Context:
 *   $customer ?Customer
 *   $flashOk  ?string
 *
 * Two preferences only:
 *   notify_order_updates  - order placed, and future status
 *                           changes
 *   notify_promotions     - marketing messages
 *
 * System messages are not a preference. They always deliver.
 * The page explains this so the customer is not surprised by
 * a welcome message they cannot turn off.
 *
 * See docs/stages/008g.3_message-center-plan.md.
 */
$customer = $customer ?? null;
$flashOk  = $flashOk  ?? null;

$orderUpdates = $customer !== null ? $customer->notifyOrderUpdates : true;
$promotions   = $customer !== null ? $customer->notifyPromotions   : false;
?>

<div class="sf-account-page-header">
    <div>
        <h1 class="sf-account-page-title">Message Preferences</h1>
        <p class="sf-account-page-subtitle">
            Choose which types of messages you want to see.
        </p>
    </div>
    <a href="/account/messages" class="sf-recent-clear-btn">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <span>Back to messages</span>
    </a>
</div>

<?php if ($flashOk !== null && $flashOk !== ''): ?>
    <div class="sf-account-flash sf-account-flash-ok" role="status">
        <?= e($flashOk) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/account/messages/preferences" class="sf-message-prefs">
    <?= csrf_field() ?>

    <div class="sf-pref-row">
        <div class="sf-pref-info">
            <h2 class="sf-pref-title">
                <i class="bi bi-receipt" aria-hidden="true"></i>
                Order Updates
            </h2>
            <p class="sf-pref-desc">
                Receive a message when you place an order, and when
                your order status changes.
            </p>
        </div>
        <label class="sf-pref-toggle">
            <input type="hidden" name="notify_order_updates" value="0">
            <input type="checkbox"
                   name="notify_order_updates"
                   value="1"
                   <?= $orderUpdates ? 'checked' : '' ?>>
            <span class="sf-pref-toggle-track" aria-hidden="true"></span>
            <span class="sr-only">Enable order update notifications</span>
        </label>
    </div>

    <div class="sf-pref-row">
        <div class="sf-pref-info">
            <h2 class="sf-pref-title">
                <i class="bi bi-megaphone" aria-hidden="true"></i>
                Promotions
            </h2>
            <p class="sf-pref-desc">
                Sale announcements, offers, and marketing messages
                from the store. Off by default.
            </p>
        </div>
        <label class="sf-pref-toggle">
            <input type="hidden" name="notify_promotions" value="0">
            <input type="checkbox"
                   name="notify_promotions"
                   value="1"
                   <?= $promotions ? 'checked' : '' ?>>
            <span class="sf-pref-toggle-track" aria-hidden="true"></span>
            <span class="sr-only">Enable promotional notifications</span>
        </label>
    </div>

    <div class="sf-pref-row sf-pref-row-static">
        <div class="sf-pref-info">
            <h2 class="sf-pref-title">
                <i class="bi bi-info-circle" aria-hidden="true"></i>
                System Messages
            </h2>
            <p class="sf-pref-desc">
                Account updates and policy changes. These are
                operational and always delivered. They cannot be
                turned off.
            </p>
        </div>
        <div class="sf-pref-locked" aria-label="Always on">
            <i class="bi bi-lock-fill" aria-hidden="true"></i>
        </div>
    </div>

    <div class="sf-message-prefs-actions">
        <button type="submit" class="sf-btn sf-btn-primary">
            <i class="bi bi-check2 me-2" aria-hidden="true"></i>
            Save preferences
        </button>
    </div>
</form>

<p class="sf-message-prefs-note">
    <i class="bi bi-info-circle" aria-hidden="true"></i>
    Email delivery is not yet active. Preferences are stored
    now and will apply when email notifications launch.
</p>
