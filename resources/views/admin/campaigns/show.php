<?php
/**
 * Campaign detail. Stage 008I.
 *
 * Context:
 *   $campaign EmailCampaign
 *   $list     ?SubscriberList
 *   $stats    array{sent:int,failed:int,pending:int,opens:int,clicks:int,bounces:int,unsubscribes:int}
 */
$campaign = $campaign ?? null;
$list     = $list     ?? null;
$stats    = $stats    ?? ['sent' => 0, 'failed' => 0, 'pending' => 0, 'opens' => 0, 'clicks' => 0, 'bounces' => 0, 'unsubscribes' => 0];

if ($campaign === null) return;

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
            <a href="/commerce/ca-admin/campaigns" class="text-decoration-none">Campaigns</a>
            <span class="mx-1">&rsaquo;</span>
            <span><?= e($campaign->subject) ?></span>
        </nav>
        <h1 class="h3 mb-1"><?= e($campaign->subject) ?></h1>
        <p class="muted mb-0">
            <span class="<?= e($badgeClass($campaign->statusBadgeVariant())) ?>">
                <?= e($campaign->statusLabel()) ?>
            </span>
            <?php if ($list !== null): ?>
                &middot; list: <?= e($list->name) ?>
            <?php endif; ?>
            <?php if ($campaign->sentAt !== null): ?>
                &middot; sent <?= e($fmtDateTime($campaign->sentAt)) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/commerce/ca-admin/campaigns/<?= (int) $campaign->id ?>/preview"
           class="ra-btn ra-btn-outline" target="_blank">
            <i class="bi bi-eye me-1"></i> Preview
        </a>
        <?php if ($campaign->canEdit()): ?>
            <a href="/commerce/ca-admin/campaigns/<?= (int) $campaign->id ?>/edit"
               class="ra-btn ra-btn-outline">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        <?php endif; ?>
        <?php if ($campaign->canSend()): ?>
            <form method="POST"
                  action="/commerce/ca-admin/campaigns/<?= (int) $campaign->id ?>/send"
                  class="d-inline"
                  onsubmit="return confirm('Send this campaign to the selected list? Once sent, it cannot be edited.');">
                <?= csrf_field() ?>
                <button type="submit" class="ra-btn ra-btn-primary">
                    <i class="bi bi-send me-1"></i> Send campaign
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Sent</div>
                <div style="font-weight:600;font-size:1.5rem;"><?= (int) $stats['sent'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Pending</div>
                <div style="font-weight:600;font-size:1.5rem;"><?= (int) $stats['pending'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Opens</div>
                <div style="font-weight:600;font-size:1.5rem;"><?= (int) $stats['opens'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="ra-card">
            <div class="ra-card-body">
                <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Clicks</div>
                <div style="font-weight:600;font-size:1.5rem;"><?= (int) $stats['clicks'] ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($stats['failed'] > 0 || $stats['bounces'] > 0 || $stats['unsubscribes'] > 0): ?>
    <div class="row g-3 mb-4">
        <?php if ($stats['failed'] > 0): ?>
            <div class="col-md-4">
                <div class="ra-card">
                    <div class="ra-card-body">
                        <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Failed</div>
                        <div style="font-weight:600;font-size:1.5rem;color:#dc2626;"><?= (int) $stats['failed'] ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($stats['bounces'] > 0): ?>
            <div class="col-md-4">
                <div class="ra-card">
                    <div class="ra-card-body">
                        <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Bounces</div>
                        <div style="font-weight:600;font-size:1.5rem;color:#f59e0b;"><?= (int) $stats['bounces'] ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($stats['unsubscribes'] > 0): ?>
            <div class="col-md-4">
                <div class="ra-card">
                    <div class="ra-card-body">
                        <div class="muted small mb-1" style="text-transform:uppercase;letter-spacing:.04em;font-size:.72rem;">Unsubscribes</div>
                        <div style="font-weight:600;font-size:1.5rem;"><?= (int) $stats['unsubscribes'] ?></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Body preview</h2>
        <?= view_partial('admin/campaigns/_body-preview', ['campaignId' => (int) $campaign->id]) ?>
        <p class="muted small mt-2 mb-0">
            Rendered exactly as the recipient's email client will show it.
        </p>
    </div>
</div>

<div class="d-flex gap-2 mb-4">
    <a href="/commerce/ca-admin/campaigns" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to campaigns
    </a>
</div>

<script src="<?= versioned_asset('js/campaign-preview.js') ?>"></script>
