<?php
/**
 * Subscriber detail. Stage 008I.
 *
 * Context (from AdminSubscriberController::show):
 *   $subscriber    Subscriber
 *   $allLists      array<int, SubscriberList>
 *   $memberListIds array<int, int>
 *   $events        array<int, EmailEvent>
 */

$subscriber    = $subscriber    ?? null;
$allLists      = $allLists      ?? [];
$memberListIds = $memberListIds ?? [];
$events        = $events        ?? [];

if ($subscriber === null) return;

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y \a\t H:i', $ts);
};

$badgeClass = function (string $variant): string {
    return match ($variant) {
        'success' => 'ra-badge ra-badge-success',
        'danger'  => 'ra-badge ra-badge-danger',
        'warning' => 'ra-badge ra-badge-warn',
        'info'    => 'ra-badge',
        'muted'   => 'ra-badge ra-badge-muted',
        default   => 'ra-badge',
    };
};
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/subscribers" class="text-decoration-none">Subscribers</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($subscriber->email) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($subscriber->displayName()) ?></h1>
        <p class="muted mb-0">
            <span class="<?= e($badgeClass($subscriber->statusBadgeVariant())) ?>">
                <?= e($subscriber->statusLabel()) ?>
            </span>
            &middot; subscribed <?= e($fmtDateTime($subscriber->consentAt ?? $subscriber->createdAt)) ?>
        </p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Email</div>
                <div style="font-weight:600;word-break:break-all;"><?= e($subscriber->email) ?></div>
                <?php if ($subscriber->name !== null && $subscriber->name !== ''): ?>
                    <div class="muted small mt-1"><?= e($subscriber->name) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Consent</div>
                <div><span class="muted small">Source:</span> <?= e($subscriber->consentSource ?? '—') ?></div>
                <div><span class="muted small">IP:</span> <code><?= e($subscriber->consentIp ?? '—') ?></code></div>
                <div class="muted small mt-1"><?= e($fmtDateTime($subscriber->consentAt)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Timeline</div>
                <div class="small">
                    <span class="muted">Confirmed:</span> <?= e($fmtDateTime($subscriber->confirmedAt)) ?><br>
                    <span class="muted">Unsubscribed:</span> <?= e($fmtDateTime($subscriber->unsubscribedAt)) ?><br>
                    <span class="muted">Soft bounces:</span> <?= (int) $subscriber->softBounceCount ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Lists</h2>
        <?php if (empty($allLists)): ?>
            <p class="muted mb-0">No lists configured.</p>
        <?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($allLists as $l): ?>
                    <li class="d-flex align-items-center gap-2 mb-2">
                        <?php if (in_array($l->id, $memberListIds, true)): ?>
                            <i class="bi bi-check-circle-fill" style="color:#16a34a;"></i>
                        <?php else: ?>
                            <i class="bi bi-circle" style="color:#cbd5e1;"></i>
                        <?php endif; ?>
                        <span><?= e($l->name) ?></span>
                        <?php if ($l->isDefault): ?>
                            <span class="ra-badge ra-badge-muted">Default</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Recent events</h2>
        <?php if (empty($events)): ?>
            <p class="muted mb-0">No events recorded.</p>
        <?php else: ?>
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:160px;">When</th>
                            <th style="width:140px;">Event</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                            <tr>
                                <td class="muted small"><?= e($fmtDateTime($e->createdAt)) ?></td>
                                <td>
                                    <span class="<?= e($badgeClass($e->eventBadgeVariant())) ?>">
                                        <?= e($e->eventLabel()) ?>
                                    </span>
                                </td>
                                <td class="muted small">
                                    <?php if ($e->eventData !== null && $e->eventData !== []): ?>
                                        <?= e(json_encode($e->eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$subscriber->isUnsubscribed() && !$subscriber->isSuppressed()): ?>
    <div class="ra-card mb-4">
        <div class="ra-card-body">
            <h2 class="h6 mb-3">Manual unsubscribe</h2>
            <p class="muted small mb-3">
                Use this when a customer contacts the store asking to be removed.
                The subscriber keeps their row, but stops receiving campaigns.
            </p>
            <form method="POST"
                  action="/commerce/ca-admin/subscribers/<?= (int) $subscriber->id ?>/unsubscribe"
                  class="row g-2 align-items-end"
                  onsubmit="return confirm('Unsubscribe this address?');">
                <?= csrf_field() ?>
                <div class="col-md-8">
                    <label class="muted small" for="sub-reason">Reason (optional)</label>
                    <input type="text" id="sub-reason" name="reason" class="form-control"
                           maxlength="255" placeholder="e.g. Customer request by email">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="ra-btn ra-btn-danger w-100">
                        <i class="bi bi-x-circle me-1"></i> Unsubscribe
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex gap-2 mb-4">
    <a href="/commerce/ca-admin/subscribers" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to subscribers
    </a>
</div>
