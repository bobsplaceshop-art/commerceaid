<?php
/**
 * Admin orders list.
 *
 * Context (from AdminOrderController::index):
 *   $result      array  paginate() return: items, total, page, per_page, pages
 *   $filter      string the active tab: new | processing | shipped | done | all
 *   $q           string the search query (empty when not searching)
 *   $counts      array{new:int, processing:int, shipped:int, done:int, all:int}
 *   $customerMap array<int,\CorporateAid\Commerce\Models\Customer>
 *
 * Order model fields used:
 *   $o->id, $o->orderNumber, $o->customerId, $o->guestEmail,
 *   $o->status, $o->paymentStatus, $o->currencyCode,
 *   $o->grandTotal, $o->placedAt
 */

$result      = $result      ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$filter      = $filter      ?? 'new';
$q           = $q           ?? '';
$counts      = $counts      ?? ['new' => 0, 'processing' => 0, 'shipped' => 0, 'done' => 0, 'all' => 0];
$customerMap = $customerMap ?? [];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$filterTabs = [
    'new'        => 'New',
    'processing' => 'Processing',
    'shipped'    => 'Shipped',
    'done'       => 'Done',
    'all'        => 'All',
];

$tabUrl = function (string $key) use ($q): string {
    $params = ['status' => $key];
    if ($q !== '') {
        $params['q'] = $q;
    }
    return '?' . http_build_query($params);
};

$pageUrl = function (int $p) use ($filter, $q): string {
    $params = ['status' => $filter, 'page' => $p];
    if ($q !== '') {
        $params['q'] = $q;
    }
    return '?' . http_build_query($params);
};

$statusBadge = function (string $status): string {
    return match ($status) {
        'completed'                                              =>
            '<span class="ra-badge ra-badge-success">Completed</span>',
        'cancelled', 'failed'                                    =>
            '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">'
            . e(ucfirst(str_replace('_', ' ', $status))) . '</span>',
        'refunded', 'partially_refunded'                         =>
            '<span class="ra-badge ra-badge-muted">'
            . e(ucfirst(str_replace('_', ' ', $status))) . '</span>',
        'pending_payment'                                        =>
            '<span class="ra-badge ra-badge-warning">Pending payment</span>',
        'paid', 'processing', 'packed', 'shipped', 'delivered', 'on_hold' =>
            '<span class="ra-badge" style="background:rgba(37,99,235,0.12);color:#2563eb;">'
            . e(ucfirst(str_replace('_', ' ', $status))) . '</span>',
        default                                                  =>
            '<span class="ra-badge">' . e($status) . '</span>',
    };
};

$paymentBadge = function (string $status): string {
    return match ($status) {
        'paid'                                              =>
            '<span class="ra-badge ra-badge-success">Paid</span>',
        'unpaid'                                            =>
            '<span class="ra-badge ra-badge-warning">Unpaid</span>',
        'partially_refunded', 'refunded', 'failed'          =>
            '<span class="ra-badge ra-badge-muted">'
            . e(ucfirst(str_replace('_', ' ', $status))) . '</span>',
        default                                             =>
            '<span class="ra-badge">' . e($status) . '</span>',
    };
};

$fmtDate = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y', $ts);
};
?>
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Orders</h1>
        <p class="muted mb-0">
            <?= $total ?> order<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== ''): ?>
                matching &ldquo;<?= e($q) ?>&rdquo;
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
        <form method="GET" action="/commerce/ca-admin/orders" class="d-flex gap-2" role="search">
            <input type="hidden" name="status" value="<?= e($filter) ?>">
            <input type="search"
                   name="q"
                   value="<?= e($q) ?>"
                   placeholder="Search by order number or guest email"
                   class="form-control"
                   style="max-width:420px;">
            <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                <i class="bi bi-search me-1"></i> Search
            </button>
            <?php if ($q !== ''): ?>
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
            <i class="bi bi-receipt" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <?php if ($q !== ''): ?>
                <h2 class="h6 mt-3 mb-1">No orders match your search</h2>
                <p class="muted small mb-3">
                    Try a different order number, or clear the search to see all orders.
                </p>
                <a href="?status=<?= e($filter) ?>" class="ra-btn ra-btn-outline">Clear search</a>
            <?php else: ?>
                <h2 class="h6 mt-3 mb-1">No orders in this view</h2>
                <p class="muted small mb-3">
                    <?php if ($filter === 'new'): ?>
                        New orders will appear here as customers place them.
                    <?php else: ?>
                        Try a different tab.
                    <?php endif; ?>
                </p>
                <?php if ($filter !== 'all'): ?>
                    <a href="?status=all" class="ra-btn ra-btn-outline">View all orders</a>
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
                        <th style="width:180px;">Order</th>
                        <th class="ra-hide-sm" style="width:220px;">Customer</th>
                        <th style="width:120px;text-align:right;">Total</th>
                        <th class="ra-hide-sm" style="width:120px;">Payment</th>
                        <th style="width:160px;">Status</th>
                        <th class="ra-hide-sm" style="width:110px;">Placed</th>
                        <th style="width:100px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $o): ?>
                        <?php
                        $customer = $o->customerId !== null
                            ? ($customerMap[$o->customerId] ?? null)
                            : null;
                        if ($customer !== null) {
                            $customerName = (string) $customer->fullName();
                            if ($customerName === '') {
                                $customerName = (string) $customer->email;
                            }
                        } elseif ($o->guestEmail !== null && $o->guestEmail !== '') {
                            $customerName = (string) $o->guestEmail;
                        } else {
                            $customerName = 'Guest';
                        }
                        ?>
                        <tr>
                            <td>
                                <a href="/commerce/ca-admin/orders/<?= (int) $o->id ?>"
                                   class="text-decoration-none"
                                   style="font-weight:600;">
                                    <?= e($o->orderNumber) ?>
                                </a>
                            </td>
                            <td class="ra-hide-sm">
                                <div class="text-truncate" style="max-width:200px;">
                                    <?= e($customerName) ?>
                                </div>
                            </td>
                            <td style="text-align:right;">
                                <?= e(number_format($o->grandTotal, 2)) ?>
                                <span class="muted small"><?= e($o->currencyCode) ?></span>
                            </td>
                            <td class="ra-hide-sm"><?= $paymentBadge($o->paymentStatus) ?></td>
                            <td><?= $statusBadge($o->status) ?></td>
                            <td class="ra-hide-sm muted small">
                                <?= e($fmtDate($o->placedAt)) ?>
                            </td>
                            <td class="text-end">
                                <a href="/commerce/ca-admin/orders/<?= (int) $o->id ?>"
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
