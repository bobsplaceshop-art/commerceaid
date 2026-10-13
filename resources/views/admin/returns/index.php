<?php
/**
 * Admin returns list. Stage 008H Session 3.
 */

$result      = $result      ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$filter      = $filter      ?? 'open';
$q           = $q           ?? '';
$dateFrom    = $dateFrom    ?? '';
$dateTo      = $dateTo      ?? '';
$counts      = $counts      ?? ['open' => 0, 'received' => 0, 'closed' => 0, 'all' => 0];
$customerMap = $customerMap ?? [];
$orderMap    = $orderMap    ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$filterTabs = [
    'open'     => 'Open',
    'received' => 'Received',
    'closed'   => 'Closed',
    'all'      => 'All',
];

$tabUrl = function (string $key) use ($q, $dateFrom, $dateTo): string {
    $params = ['status' => $key];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return '?' . http_build_query($params);
};

$pageUrl = function (int $p) use ($filter, $q, $dateFrom, $dateTo): string {
    $params = ['status' => $filter, 'page' => $p];
    if ($q !== '')        $params['q'] = $q;
    if ($dateFrom !== '') $params['date_from'] = $dateFrom;
    if ($dateTo !== '')   $params['date_to'] = $dateTo;
    return '?' . http_build_query($params);
};

$fmtDate = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y', $ts);
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
        <h1 class="h3 mb-1">Returns</h1>
        <p class="muted mb-0">
            <?= $total ?> request<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== ''): ?>
                matching &ldquo;<?= e($q) ?>&rdquo;
            <?php elseif ($dateFrom !== '' || $dateTo !== ''): ?>
                in the selected date range
            <?php else: ?>
                in <?= e(strtolower($filterTabs[$filter] ?? $filter)) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-3">
    <div class="ra-card-body py-2">
        <ul class="nav nav-pills gap-2 flex-wrap mb-0">
            <?php foreach ($filterTabs as $key => $label): ?>
                <?php
                $isActive = $filter === $key;
                $count    = (int) ($counts[$key] ?? 0);
                ?>
                <li class="nav-item">
                    <a href="<?= e($tabUrl($key)) ?>"
                       class="ra-btn <?= $isActive ? 'ra-btn-primary' : 'ra-btn-outline' ?> ra-btn-sm">
                        <?= e($label) ?>
                        <span class="ra-badge ms-1" style="font-size:.7rem;"><?= $count ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body py-2">
        <form method="GET" action="/commerce/ca-admin/returns" class="d-flex gap-2 flex-wrap align-items-end" role="search">
            <input type="hidden" name="status" value="<?= e($filter) ?>">

            <div>
                <label for="ra-returns-q" class="form-label small mb-1">Request #</label>
                <input type="search"
                       id="ra-returns-q"
                       name="q"
                       value="<?= e($q) ?>"
                       placeholder="RMA-000123"
                       class="form-control form-control-sm"
                       style="max-width:220px;">
            </div>

            <div>
                <label for="ra-returns-from" class="form-label small mb-1">From</label>
                <input type="date"
                       id="ra-returns-from"
                       name="date_from"
                       value="<?= e($dateFrom) ?>"
                       class="form-control form-control-sm">
            </div>

            <div>
                <label for="ra-returns-to" class="form-label small mb-1">To</label>
                <input type="date"
                       id="ra-returns-to"
                       name="date_to"
                       value="<?= e($dateTo) ?>"
                       class="form-control form-control-sm">
            </div>

            <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                <i class="bi bi-search me-1"></i> Filter
            </button>
            <?php if ($q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="?status=<?= e($filter) ?>" class="ra-btn ra-btn-outline ra-btn-sm">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-arrow-counterclockwise" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($q !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <h2 class="h6 mt-3 mb-1">No returns match your filters</h2>
                <p class="muted small mb-3">
                    Try a different request number or clear the date range.
                </p>
                <a href="?status=<?= e($filter) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No returns in this view</h2>
                <p class="muted small mb-3">
                    <?php if ($filter === 'open'): ?>
                        New customer return requests will appear here for review.
                    <?php else: ?>
                        Try a different tab.
                    <?php endif; ?>
                </p>
                <?php if ($filter !== 'all'): ?>
                    <a href="?status=all" class="ra-btn ra-btn-outline">View all returns</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="scroll">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th style="width:180px;">Request</th>
                        <th class="ra-hide-sm" style="width:200px;">Customer</th>
                        <th class="ra-hide-sm" style="width:180px;">Order</th>
                        <th style="width:160px;">Reason</th>
                        <th style="width:160px;">Status</th>
                        <th class="ra-hide-sm" style="width:110px;">Requested</th>
                        <th style="width:100px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $r): ?>
                        <?php
                        $customer = $customerMap[$r->customerId] ?? null;
                        if ($customer !== null) {
                            $customerName = (string) $customer->fullName();
                            if ($customerName === '') {
                                $customerName = (string) $customer->email;
                            }
                        } else {
                            $customerName = 'Customer #' . (int) $r->customerId;
                        }

                        $order      = $orderMap[$r->orderId] ?? null;
                        $orderLabel = $order !== null ? $order->orderNumber : 'Order #' . (int) $r->orderId;
                        ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/returns/<?= (int) $r->id ?>"
                                   class="text-decoration-none"
                                   style="font-weight:600;">
                                    <?= e($r->requestNumber) ?>
                                </a>
                            </td>
                            <td class="ra-hide-sm">
                                <div class="text-truncate" style="max-width:180px;">
                                    <?= e($customerName) ?>
                                </div>
                            </td>
                            <td class="ra-hide-sm">
                                <?php if ($order !== null): ?>
                                    <a href="/commerce/ca-admin/orders/<?= (int) $order->id ?>"
                                       class="text-decoration-none">
                                        <?= e($orderLabel) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="muted"><?= e($orderLabel) ?> (deleted)</span>
                                <?php endif; ?>
                            </td>
                            <td class="muted small">
                                <?= e($r->reasonLabel()) ?>
                            </td>
                            <td>
                                <span class="<?= e($badgeClass($r->statusBadgeVariant())) ?>">
                                    <?= e($r->statusLabel()) ?>
                                </span>
                            </td>
                            <td class="ra-hide-sm muted small">
                                <?= e($fmtDate($r->requestedAt)) ?>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/returns/<?= (int) $r->id ?>"
                                   class="ra-btn ra-btn-outline ra-btn-sm">
                                    View
                                </a>
                            </td>
                        </tr>
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
                <?php endif;

                if ($page < $pages): ?>
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
