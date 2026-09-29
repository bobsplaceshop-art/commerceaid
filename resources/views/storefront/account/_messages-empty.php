<?php
/**
 * Empty state for a message center tab.
 *
 * Context:
 *   $tab string  the current tab. Drives the message text.
 */
$tab = $tab ?? 'system';

$copy = [
    'system' => [
        'title' => 'No system messages',
        'body'  => 'Account updates, policy changes, and welcome messages will appear here.',
    ],
    'order' => [
        'title' => 'No order updates',
        'body'  => 'When you place an order, the confirmation and any future status changes will appear here.',
    ],
    'promotion' => [
        'title' => 'No promotions',
        'body'  => 'Sale announcements and offers from the store will appear here when you opt in.',
    ],
];
$c = $copy[$tab] ?? $copy['system'];
?>

<div class="sf-account-empty">
    <div class="sf-account-empty-icon" aria-hidden="true">
        <i class="bi bi-envelope-open"></i>
    </div>
    <h2 class="sf-account-empty-title"><?= e($c['title']) ?></h2>
    <p class="sf-account-empty-body"><?= e($c['body']) ?></p>
    <div class="sf-account-empty-actions">
        <a href="/account/messages/preferences" class="sf-btn sf-btn-outline">
            <i class="bi bi-gear me-2" aria-hidden="true"></i>
            Message preferences
        </a>
    </div>
</div>
