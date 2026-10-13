<?php
/**
 * Coupons list.
 *
 * Context (from AdminCouponController::index):
 *   $result array{items,total,page,per_page,pages}
 *   $tab    'all' | 'active' | 'disabled'
 *   $q      string
 *   $counts array{all:int,active:int,disabled:int}
 *
 * Each item is a row from CouponRepository::paginate():
 *   id, store_id, code, description, type, value,
 *   valid_from, valid_to, min_order_subtotal, usage_limit,
 *   per_customer_limit, applies_to, applies_to_ids_json,
 *   status, created_at, updated_at, redemption_count
 */
$result = $result ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 25, 'pages' => 0];
$tab    = $tab    ?? 'all';
$q      = $q      ?? '';
$counts = $counts ?? ['all' => 0, 'active' => 0, 'disabled' => 0];

$items = $result['items'];
$total = (int) $result['total'];
$page  = (int) $result['page'];
$pages = (int) $result['pages'];

$tabs = [
    'all'      => 'All',
    'active'   => 'Active',
    'disabled' => 'Disabled',
];

$pageUrl = function (int $p) use ($tab, $q): string {
    $params = ['status' => $tab, 'page' => $p];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
};

$tabUrl = function (string $key) use ($q): string {
    $params = ['status' => $key];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
};

$clearUrl = '/commerce/ca-admin/coupons';

$statusBadge = function (string $status): string {
    return match ($status) {
        'active'   => '<span class="ra-badge ra-badge-success">Active</span>',
        'disabled' => '<span class="ra-badge ra-badge-muted">Disabled</span>',
        default    => '<span class="ra-badge ra-badge-muted">' . e($status) . '</span>',
    };
};

$typeLabel = function (string $type): string {
    return match ($type) {
        'percentage'    => 'Percent off',
        'fixed_amount'  => 'Amount off',
        'free_shipping' => 'Free shipping',
        default         => $type,
    };
};

$valueLabel = function (array $c): string {
    $type  = (string) ($c['type'] ?? '');
    $value = (float) ($c['value'] ?? 0);
    return match ($type) {
        'percentage'    => rtrim(rtrim(number_format($value, 2), '0'), '.') . '%',
        'fixed_amount'  => number_format($value, 2),
        'free_shipping' => '—',
        default         => '—',
    };
};

$fmtDate = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y', $ts);
};

$validityLabel = function (array $c) use ($fmtDate): string {
    $from = $fmtDate($c['valid_from'] ?? null);
    $to   = $fmtDate($c['valid_to'] ?? null);
    if ($from === '—' && $to === '—') return 'No limits';
    if ($from === '—') return 'Until ' . $to;
    if ($to === '—') return 'From ' . $from;
    return $from . ' – ' . $to;
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Coupons</h1>
        <p class="muted mb-0">
            <?= (int) $total ?> coupon<?= $total === 1 ? '' : 's' ?>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                &middot; filtered
                <a href="<?= e($clearUrl) ?>" class="ms-1 text-decoration-none">Clear</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="/commerce/ca-admin/coupons/create" class="ra-btn ra-btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New coupon
        </a>
    </div>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="Coupon status">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="<?= e($tabUrl($key)) ?>"
               class="ra-admin-tab<?= $tab === $key ? ' is-active' : '' ?>">
                <?= e($label) ?>
                <span class="ra-admin-tab-count">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="ra-card mb-4">
    <div class="ra-card-body">
        <button type="button" class="ra-filter-toggle" data-ra-filter-toggle>
            <i class="bi bi-funnel"></i> Filters
        </button>
        <form method="GET" action="/commerce/ca-admin/coupons" class="row g-2 align-items-end ra-filter-bar">
            <input type="hidden" name="status" value="<?= e($tab) ?>">
            <div class="col-md-9">
                <label class="muted small" for="coupon-q">Search</label>
                <input type="text" id="coupon-q" name="q" class="form-control"
                       placeholder="Code or description"
                       value="<?= e($q) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="ra-btn ra-btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-ticket-perforated" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?php if ($q !== '' || $tab !== 'all'): ?>
                    No coupons match these filters
                <?php else: ?>
                    No coupons yet
                <?php endif; ?>
            </h2>
            <p class="muted small mb-3">
                <?php if ($q !== '' || $tab !== 'all'): ?>
                    Try clearing filters or adjusting your search.
                <?php else: ?>
                    Create a coupon to offer a discount at checkout.
                <?php endif; ?>
            </p>
            <?php if ($q !== '' || $tab !== 'all'): ?>
                <a href="<?= e($clearUrl) ?>" class="ra-btn ra-btn-outline">Clear filters</a>
            <?php else: ?>
                <a href="/commerce/ca-admin/coupons/create" class="ra-btn ra-btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New coupon
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th class="ra-hide-sm" style="width:140px;">Type</th>
                        <th style="width:110px;">Value</th>
                        <th class="ra-hide-sm" style="width:200px;">Validity</th>
                        <th style="width:80px;">Uses</th>
                        <th style="width:110px;">Status</th>
                        <th style="width:110px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $c): ?>
                        <?php
                        $cid    = (int) ($c['id'] ?? 0);
                        $code   = (string) ($c['code'] ?? '');
                        $desc   = (string) ($c['description'] ?? '');
                        $type   = (string) ($c['type'] ?? '');
                        $status = (string) ($c['status'] ?? '');
                        $uses   = (int) ($c['redemption_count'] ?? 0);
                        ?>
                        <tr>
                            <td data-label="Code" data-col="full">
                                <a href="/commerce/ca-admin/coupons/<?= $cid ?>/edit"
                                   class="text-decoration-none" style="font-weight:600;">
                                    <?= e($code) ?>
                                </a>
                                <?php if ($desc !== ''): ?>
                                    <div class="muted small"><?= e($desc) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Type" data-col="half" class="ra-hide-sm"><?= e($typeLabel($type)) ?></td>
                            <td data-label="Value" data-col="half"><?= e($valueLabel($c)) ?></td>
                            <td data-label="Validity" data-col="half" class="ra-hide-sm muted small"><?= e($validityLabel($c)) ?></td>
                            <td data-label="Uses" data-col="half"><?= $uses ?></td>
                            <td data-label="Status" data-col="half"><?= $statusBadge($status) ?></td>
                            <td data-label="Actions" data-col="full" class="text-end">
                                <a href="/commerce/ca-admin/coupons/<?= $cid ?>/edit"
                                   class="ra-icon-btn" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button"
                                        class="ra-icon-btn"
                                        title="Delete"
                                        data-ra-delete-coupon
                                        data-coupon-id="<?= $cid ?>"
                                        data-coupon-code="<?= e($code) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="mt-3 d-flex gap-2 justify-content-center" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="<?= e($pageUrl($page - 1)) ?>" class="ra-btn ra-btn-outline">&larr; Previous</a>
            <?php endif; ?>
            <span class="ra-btn ra-btn-outline" style="cursor:default;">
                Page <?= (int) $page ?> of <?= (int) $pages ?>
            </span>
            <?php if ($page < $pages): ?>
                <a href="<?= e($pageUrl($page + 1)) ?>" class="ra-btn ra-btn-outline">Next &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?= view_partial('admin.coupons._delete-modal') ?>
