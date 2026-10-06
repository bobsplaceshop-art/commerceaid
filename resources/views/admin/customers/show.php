<?php
/**
 * Admin customer detail.
 *
 * Context (from AdminCustomerController::show):
 *   $customer        Customer
 *   $addresses       array<int, CustomerAddress>
 *   $group           ?CustomerGroup
 *   $recentOrders    array{items,total,page,per_page,pages}
 *   $approvalHistory array<int, CustomerApprovalHistory>
 *   $groupOptions    array<int,array{id:int,name:string,is_default:int}>
 *
 * Approval actions (approve, reject, reset) POST to the
 * existing routes owned by CustomerApprovalController. This
 * page does not duplicate that workflow.
 */
$customer        = $customer        ?? null;
$addresses       = $addresses       ?? [];
$group           = $group           ?? null;
$recentOrders    = $recentOrders    ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 0];
$approvalHistory = $approvalHistory ?? [];
$groupOptions    = $groupOptions    ?? [];

if ($customer === null) {
    return;
}

$orders      = $recentOrders['items'];
$ordersTotal = (int) $recentOrders['total'];

$statusLabels = [
    'pending_payment'    => 'Pending payment',
    'paid'               => 'Paid',
    'processing'         => 'Processing',
    'packed'             => 'Packed',
    'shipped'            => 'Shipped',
    'delivered'          => 'Delivered',
    'completed'          => 'Completed',
    'cancelled'          => 'Cancelled',
    'failed'             => 'Failed',
    'refunded'           => 'Refunded',
    'partially_refunded' => 'Partially refunded',
    'on_hold'            => 'On hold',
];

$approvalBadge = match ($customer->approvalStatus) {
    'approved' => '<span class="ra-badge ra-badge-success">Approved</span>',
    'pending'  => '<span class="ra-badge ra-badge-warning">Pending</span>',
    'rejected' => '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Rejected</span>',
    default    => '<span class="ra-badge ra-badge-muted">' . e($customer->approvalStatus) . '</span>',
};

$accountBadge = match ($customer->status) {
    'active'   => '<span class="ra-badge ra-badge-success">Active</span>',
    'disabled' => '<span class="ra-badge" style="background:rgba(220,38,38,0.15);color:#dc2626;">Disabled</span>',
    'archived' => '<span class="ra-badge ra-badge-muted">Archived</span>',
    default    => '<span class="ra-badge ra-badge-muted">' . e($customer->status) . '</span>',
};

$fmtDateTime = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y \a\t H:i', $ts);
};

$fmtDate = function (?string $dt): string {
    if ($dt === null || $dt === '') return '—';
    $ts = strtotime($dt);
    return $ts === false ? '—' : date('M j, Y', $ts);
};
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <nav class="muted small mb-2" aria-label="Breadcrumb">
            <a href="/commerce/ca-admin/customers" class="text-decoration-none">Customers</a>
            <span class="mx-1">&rsaquo;</span>
            <span style="overflow-wrap:anywhere;"><?= e($customer->fullName() ?: $customer->email) ?></span>
        </nav>
        <h1 class="h3 mb-1" style="overflow-wrap:anywhere;"><?= e($customer->fullName() ?: $customer->email) ?></h1>
        <p class="muted mb-0">
            <?= e($customer->email) ?>
            <?php if ($customer->company !== null && $customer->company !== ''): ?>
                &middot; <?= e($customer->company) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?= $accountBadge ?>
        <?= $approvalBadge ?>
    </div>
</div>

<!-- ============================ ACTION BAR ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex flex-wrap gap-2">

            <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/edit"
               class="ra-btn ra-btn-outline">
                <i class="bi bi-pencil me-1"></i> Edit profile
            </a>

            <button type="button" class="ra-btn ra-btn-outline"
                    data-bs-toggle="modal" data-bs-target="#ra-customer-group-modal">
                <i class="bi bi-people me-1"></i> Change group
            </button>

            <button type="button" class="ra-btn ra-btn-outline"
                    data-bs-toggle="modal" data-bs-target="#ra-customer-status-modal">
                <i class="bi bi-sliders me-1"></i>
                <?= $customer->status === 'active' ? 'Change account state' : 'Enable account' ?>
            </button>

            <?php if ($customer->approvalStatus !== 'approved'): ?>
                <form method="POST"
                      action="/commerce/ca-admin/customer-approvals/<?= (int) $customer->id ?>/approve"
                      class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Approve
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($customer->approvalStatus !== 'rejected'): ?>
                <form method="POST"
                      action="/commerce/ca-admin/customer-approvals/<?= (int) $customer->id ?>/reject"
                      class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-danger"
                            onclick="return confirm('Reject this customer? They can still log in but cannot place orders.');">
                        <i class="bi bi-x-lg me-1"></i> Reject
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($customer->approvalStatus !== 'pending'): ?>
                <form method="POST"
                      action="/commerce/ca-admin/customer-approvals/<?= (int) $customer->id ?>/reset"
                      class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="ra-btn ra-btn-outline"
                            onclick="return confirm('Reset this customer back to pending?');">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset to pending
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ============================ CONTACT ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Contact</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="muted small">Email</div>
                <div><?= e($customer->email) ?></div>
            </div>
            <div class="col-md-4">
                <div class="muted small">Phone</div>
                <div><?= $customer->phone !== null && $customer->phone !== '' ? e($customer->phone) : '—' ?></div>
            </div>
            <div class="col-md-4">
                <div class="muted small">Company</div>
                <div><?= $customer->company !== null && $customer->company !== '' ? e($customer->company) : '—' ?></div>
            </div>
            <?php if ($customer->taxId !== null && $customer->taxId !== ''): ?>
                <div class="col-md-4">
                    <div class="muted small">Tax ID</div>
                    <div><?= e($customer->taxId) ?></div>
                </div>
            <?php endif; ?>
            <div class="col-md-4">
                <div class="muted small">Last login</div>
                <div><?= e($fmtDateTime($customer->lastLoginAt)) ?></div>
            </div>
            <div class="col-md-4">
                <div class="muted small">Registered</div>
                <div><?= e($fmtDate($customer->createdAt)) ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================ GROUP ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="h6 mb-0">Group</h2>
            <button type="button" class="ra-btn ra-btn-outline ra-btn-sm"
                    data-bs-toggle="modal" data-bs-target="#ra-customer-group-modal">
                <i class="bi bi-arrow-left-right me-1"></i> Change
            </button>
        </div>
        <?php if ($group !== null): ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="muted small">Group</div>
                    <div><?= e($group->name) ?></div>
                </div>
                <?php if ($group->isDefault): ?>
                    <div class="col-md-6">
                        <div class="muted small">Type</div>
                        <div>Default group for new customers</div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="muted mb-0">No group assigned.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ ADDRESSES ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="h6 mb-0">Addresses (<?= count($addresses) ?>)</h2>
            <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/addresses/create"
               class="ra-btn ra-btn-primary ra-btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add address
            </a>
        </div>
        <?php if (empty($addresses)): ?>
            <p class="muted mb-0">No saved addresses.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($addresses as $a): ?>
                    <div class="col-md-6">
                        <div class="border rounded p-3" style="border-color:var(--ra-border,#e5e1d8) !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                                <div>
                                    <?php if ($a->label !== null && $a->label !== ''): ?>
                                        <strong><?= e($a->label) ?></strong>
                                    <?php else: ?>
                                        <strong><?= e(ucfirst($a->type)) ?></strong>
                                    <?php endif; ?>
                                    <?php if ($a->isDefault): ?>
                                        <span class="ra-badge ra-badge-muted ms-2">Default</span>
                                    <?php endif; ?>
                                </div>
                                <span class="ra-badge ra-badge-muted"><?= e(ucfirst($a->type)) ?></span>
                            </div>
                            <address class="mb-3" style="font-style:normal;line-height:1.6;">
                                <?php if ($a->recipientName() !== ''): ?>
                                    <?= e($a->recipientName()) ?><br>
                                <?php endif; ?>
                                <?php if ($a->company !== null && $a->company !== ''): ?>
                                    <?= e($a->company) ?><br>
                                <?php endif; ?>
                                <?= e($a->addressLine1) ?><br>
                                <?php if ($a->addressLine2 !== null && $a->addressLine2 !== ''): ?>
                                    <?= e($a->addressLine2) ?><br>
                                <?php endif; ?>
                                <?= e($a->postalCode ?? '') ?> <?= e($a->city) ?><br>
                                <?php if ($a->state !== null && $a->state !== ''): ?>
                                    <?= e($a->state) ?><br>
                                <?php endif; ?>
                                <?= e($a->countryCode) ?>
                            </address>

                            <div class="d-flex flex-wrap gap-2 pt-2 border-top" style="border-color:var(--ra-border,#e5e1d8) !important;">
                                <a href="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/addresses/<?= (int) $a->id ?>/edit"
                                   class="ra-btn ra-btn-outline ra-btn-sm">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </a>
                                <?php if (!$a->isDefault): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/customers/<?= (int) $customer->id ?>/addresses/<?= (int) $a->id ?>/default"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="ra-btn ra-btn-outline ra-btn-sm">
                                            <i class="bi bi-star me-1"></i> Set default
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="ra-btn ra-btn-outline ra-btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#ra-address-delete-modal-<?= (int) $a->id ?>">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <?= view_partial('admin/customers/_address-delete-modal', [
                        'customer' => $customer,
                        'address'  => $a,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ RECENT ORDERS ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <h2 class="h6 mb-0">Recent orders (<?= $ordersTotal ?>)</h2>
        </div>
        <?php if (empty($orders)): ?>
            <p class="muted mb-0">This customer has not placed any orders.</p>
        <?php else: ?>
            <div class="ra-table-responsive-wrap">
                <table class="ra-table">
                    <thead>
                        <tr>
                            <th style="width:180px;">Order</th>
                            <th class="ra-hide-sm" style="width:140px;">Date</th>
                            <th style="width:140px;text-align:right;">Total</th>
                            <th style="width:180px;">Status</th>
                            <th style="width:100px;text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <tr>
                                <td>
                                    <a href="/commerce/ca-admin/orders/<?= (int) $o->id ?>"
                                       class="text-decoration-none" style="font-weight:600;">
                                        <?= e($o->orderNumber) ?>
                                    </a>
                                </td>
                                <td class="ra-hide-sm muted small"><?= e($fmtDate($o->placedAt)) ?></td>
                                <td style="text-align:right;">
                                    <?= e(number_format($o->grandTotal, 2)) ?>
                                    <span class="muted small"><?= e($o->currencyCode) ?></span>
                                </td>
                                <td>
                                    <span class="ra-badge ra-badge-muted">
                                        <?= e($statusLabels[$o->status] ?? ucfirst(str_replace('_', ' ', $o->status))) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="/commerce/ca-admin/orders/<?= (int) $o->id ?>"
                                       class="ra-btn ra-btn-outline ra-btn-sm">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ APPROVAL HISTORY ============================ -->
<div class="ra-card mb-4">
    <div class="ra-card-body">
        <h2 class="h6 mb-3">Approval history</h2>
        <?php if (empty($approvalHistory)): ?>
            <p class="muted mb-0">No approval history yet.</p>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($approvalHistory as $h): ?>
                    <div class="d-flex gap-3">
                        <div style="flex-shrink:0;width:180px;" class="muted small">
                            <?= e($fmtDateTime($h->createdAt)) ?>
                        </div>
                        <div style="min-width:0;">
                            <div>
                                <span class="ra-badge ra-badge-<?= e($h->badgeVariant()) ?>">
                                    <?= e($h->actionLabel()) ?>
                                </span>
                            </div>
                            <div class="muted small mt-1">
                                by <?= e($h->actorDisplay()) ?>
                            </div>
                            <?php if ($h->hasReason()): ?>
                                <div class="mt-1"><?= nl2br(e((string) $h->reason)) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================ FOOTER ============================ -->
<div class="d-flex gap-2 mb-4">
    <a href="/commerce/ca-admin/customers" class="ra-btn ra-btn-outline">
        <i class="bi bi-arrow-left me-1"></i> Back to customers
    </a>
</div>

<?= view_partial('admin/customers/_group-modal', [
    'customer'     => $customer,
    'groupOptions' => $groupOptions,
    'group'        => $group,
]) ?>

<?= view_partial('admin/customers/_status-modal', [
    'customer' => $customer,
]) ?>
