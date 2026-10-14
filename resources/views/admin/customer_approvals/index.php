<?php
/**
 * Customer approvals queue.
 *
 * Context (from CustomerApprovalController::index):
 *   $result         array  paginate() return: items, total, page, per_page, pages
 *   $currentFilter  string one of: pending, approved, rejected, all
 *   $counts         array{pending:int, approved:int, rejected:int, all:int}
 *                          counts for the filter tab badges
 *
 * Customer model fields used:
 *   $c->id               int
 *   $c->email            string
 *   $c->firstName        ?string
 *   $c->lastName         ?string
 *   $c->company          ?string
 *   $c->customerGroupId  ?int
 *   $c->approvalStatus   string  pending|approved|rejected
 *   $c->approvalReason   ?string
 *   $c->createdAt        ?string
 *   $c->fullName()       string
 *   $c->initials()       string
 *
 * Note on inline onclick attributes:
 *   All onclick attributes that carry a JSON-encoded payload use
 *   SINGLE quotes. The json_encode() output uses double quotes, so
 *   the attribute delimiter must not be a double quote. This
 *   matches the pattern in resources/views/admin/products/index.php.
 *
 *   Known limitation: customer names containing a single quote
 *   (e.g. "O'Brien") will still break the attribute. See Pattern 13
 *   in docs/REMEMBER.md. Fix by migrating all inline onclick
 *   attributes to data-* + addEventListener when it becomes a
 *   real problem.
 */

$result        = $result        ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$currentFilter = $currentFilter ?? 'pending';
$counts        = $counts        ?? ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];

$items = $result['items'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];

$pendingCount = (int) ($counts['pending'] ?? 0);

$filterTabs = [
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'all'      => 'All',
];

$tabUrl = function (string $key): string {
    return '?status=' . urlencode($key);
};

$pageUrl = function (int $p) use ($currentFilter): string {
    $q = ['status' => $currentFilter, 'page' => $p];
    return '?' . http_build_query(array_filter($q, fn ($v) => $v !== '' && $v !== 0));
};

$tabCount = function (string $key) use ($counts): int {
    return (int) ($counts[$key] ?? 0);
};

$statusBadge = function (string $status): string {
    return match ($status) {
        'pending'  => '<span class="ra-badge" style="background:rgba(245,158,11,0.15);color:#f59e0b;">Pending</span>',
        'approved' => '<span class="ra-badge ra-badge-success">Approved</span>',
        'rejected' => '<span class="ra-badge ra-badge-danger">Rejected</span>',
        default    => '<span class="ra-badge">' . e($status) . '</span>',
    };
};

$fmtDate = function (?string $dt): string {
    if ($dt === null || $dt === '') return '-';
    $ts = strtotime($dt);
    if ($ts === false) return '-';
    return date('M j, Y', $ts);
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Customer Approvals</h1>
        <p class="muted mb-0">
            <?php if ($pendingCount > 0): ?>
                <?= $pendingCount ?> customer<?= $pendingCount === 1 ? '' : 's' ?> awaiting review
            <?php else: ?>
                No customers awaiting review
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body py-2">
        <ul class="nav nav-pills gap-2 flex-wrap">
            <?php foreach ($filterTabs as $key => $label): ?>
                <?php
                $isActive  = $currentFilter === $key;
                $count     = $tabCount($key);
                $showBadge = ($key === 'pending') ? ($count > 0) : true;
                ?>
                <li class="nav-item">
                    <a href="<?= e($tabUrl($key)) ?>"
                       class="ra-btn <?= $isActive ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                        <?= e($label) ?>
                        <?php if ($showBadge): ?>
                            <span class="ra-badge ms-1" style="font-size:.7rem;"><?= $count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-check2-circle" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($currentFilter === 'pending'): ?>
                <h2 class="h6 mt-3 mb-1">Queue is clear</h2>
                <p class="muted small mb-3">
                    No customers awaiting approval. New B2B signups will appear here.
                </p>
                <a href="?status=all" class="ra-btn ra-btn-outline">View all customers</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No customers in this view</h2>
                <p class="muted small mb-3">
                    Try a different filter.
                </p>
                <a href="/commerce/ca-admin/customer-approvals" class="ra-btn ra-btn-outline">Back to pending</a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th class="ra-hide-sm" style="width:220px;">Contact</th>
                        <th class="ra-hide-sm" style="width:140px;">Registered</th>
                        <th style="width:120px;">Status</th>
                        <th style="width:260px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $c): ?>
                        <tr>
                            <td data-label="Customer" data-col="full">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="ra-avatar" style="width:32px;height:32px;font-size:.8rem;">
                                        <?= e($c->initials()) ?>
                                    </div>
                                    <div style="min-width:0;">
                                        <div><?= e($c->fullName()) ?></div>
                                        <?php if ($c->company !== null && $c->company !== ''): ?>
                                            <div class="muted" style="font-size:.75rem;">
                                                <?= e($c->company) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Contact" data-col="half" class="ra-hide-sm">
                                <div class="muted small text-truncate" style="max-width:220px;">
                                    <?= e($c->email) ?>
                                </div>
                            </td>
                            <td data-label="Registered" data-col="half" class="ra-hide-sm">
                                <span class="muted small"><?= e($fmtDate($c->createdAt)) ?></span>
                            </td>
                            <td data-label="Status" data-col="half"><?= $statusBadge($c->approvalStatus) ?></td>
                            <td data-label="Actions" data-col="full" class="text-end ra-actions-cell">
                                <?php if ($c->approvalStatus === 'pending'): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/customer-approvals/<?= (int) $c->id ?>/approve"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return" value="<?= e($currentFilter) ?>">
                                        <button type="submit" class="ra-icon-btn" title="Approve"
                                                style="color:#16a34a;">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                    <button type="button"
                                            class="ra-icon-btn"
                                            title="Reject"
                                            style="color:#dc2626;"
                                            onclick='RA_RejectCustomer.open(<?= (int) $c->id ?>, <?= json_encode($c->fullName()) ?>)'>
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if ($c->approvalStatus === 'rejected'): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/customer-approvals/<?= (int) $c->id ?>/approve"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return" value="<?= e($currentFilter) ?>">
                                        <button type="submit" class="ra-icon-btn" title="Approve instead"
                                                style="color:#16a34a;">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($c->approvalStatus === 'approved' || $c->approvalStatus === 'rejected'): ?>
                                    <button type="button"
                                            class="ra-icon-btn"
                                            title="Reset to pending"
                                            onclick='RA_ResetCustomer.open(<?= (int) $c->id ?>, <?= json_encode($c->fullName()) ?>)'>
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                <?php endif; ?>

                                <button type="button"
                                        class="ra-icon-btn"
                                        title="View history"
                                        onclick='RA_ApprovalHistory.open(<?= (int) $c->id ?>, <?= json_encode($c->fullName()) ?>)'>
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            </td>
                        </tr>
                        <?php if ($c->approvalStatus === 'rejected' && $c->approvalReason !== null && $c->approvalReason !== ''): ?>
                            <tr>
                                <td colspan="5" data-col="full" style="background:rgba(220,38,38,0.04);">
                                    <div class="muted small">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Rejection reason (admin only):
                                        <?= e($c->approvalReason) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-3" aria-label="Pagination">
            <ul class="pagination pagination-sm justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($pages, $page + 2);

                if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl(1)) ?>">1</a></li>
                    <?php if ($start > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif;
                endif;

                for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                    </li>
                <?php endfor;

                if ($end < $pages): ?>
                    <?php if ($end < $pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e($pageUrl($pages)) ?>"><?= $pages ?></a></li>
                <?php endif; ?>

                <?php if ($page < $pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?= view_partial('admin.customer_approvals._reject-modal') ?>
<?= view_partial('admin.customer_approvals._reset-modal') ?>
<?= view_partial('admin.customer_approvals._history-modal') ?>
