<?php
/**
 * Customer order history at /account/orders.
 *
 * Context (from AccountController::orders):
 *   $customer  Customer
 *   $result    array{items, total, page, per_page, pages}
 *
 * First pass ships the minimal list so claimed guest orders
 * appear immediately. The full order detail view, reorder
 * button, and filters land in Stage 008F.
 *
 * See docs/stages/008e.2_guest-order-claim-plan.md and
 * docs/stages/008_storefront-plan.md.
 */
$customer = $customer ?? null;
$result   = $result   ?? ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'pages' => 1];

if ($customer === null) {
    return;
}

$orders = $result['items'];
$total  = (int) $result['total'];
$page   = (int) $result['page'];
$pages  = (int) $result['pages'];

$pageUrl = function (int $p): string {
    return '/account/orders?page=' . $p;
};
?>
<div class="sf-auth-wrap" data-sf-page="account-orders">
    <div class="sf-auth-card sf-auth-card-wide">

        <h1 class="sf-auth-heading">My Orders</h1>

        <?php if ($total === 0): ?>
            <p class="sf-auth-subhead">
                Your order history will appear here once you place an order.
            </p>

            <div style="text-align:center;padding:3rem 1rem;">
                <div style="font-size:3rem;color:var(--sf-gold, #C6A15B);opacity:0.5;">
                    <i class="bi bi-receipt"></i>
                </div>
                <h2 style="font-family:'Playfair Display',serif;font-size:1.125rem;font-weight:500;margin:1rem 0 0.5rem;color:var(--sf-charcoal, #111111);">
                    No orders yet
                </h2>
                <p style="color:var(--sf-muted, #777777);font-size:0.9375rem;margin:0 0 1.5rem;">
                    When you place your first order, it will appear here.
                </p>
                <a href="/products" class="sf-btn sf-btn-primary">
                    Browse products
                </a>
            </div>

        <?php else: ?>
            <p class="sf-auth-subhead">
                <?= $total ?> order<?= $total === 1 ? '' : 's' ?>
            </p>

            <div class="sf-table-responsive-wrap">
                <table class="sf-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th class="ra-hide-sm">Date</th>
                            <th style="text-align:right;">Total</th>
                            <th>Status</th>
                            <th style="width:100px;text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <?php
                            $statusLabels = [
                                'pending_payment' => 'Pending payment',
                                'paid'            => 'Paid',
                                'processing'      => 'Processing',
                                'packed'          => 'Packed',
                                'shipped'         => 'Shipped',
                                'delivered'       => 'Delivered',
                                'completed'       => 'Completed',
                                'cancelled'       => 'Cancelled',
                                'failed'          => 'Failed',
                                'refunded'        => 'Refunded',
                                'partially_refunded' => 'Partially refunded',
                                'on_hold'         => 'On hold',
                            ];
                            $statusLabel = $statusLabels[$o->status] ?? ucfirst(str_replace('_', ' ', $o->status));
                            ?>
                            <tr>
                                <td>
                                    <a href="/checkout/confirmation/<?= e($o->orderNumber) ?>"
                                       class="text-decoration-none">
                                        <?= e($o->orderNumber) ?>
                                    </a>
                                </td>
                                <td class="ra-hide-sm">
                                    <span class="muted small">
                                        <?= $o->placedAt !== null
                                            ? e(date('M j, Y', strtotime($o->placedAt)))
                                            : '—' ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <?= e(number_format($o->grandTotal, 2)) ?>
                                    <?= e($o->currencyCode) ?>
                                </td>
                                <td>
                                    <span class="muted small"><?= e($statusLabel) ?></span>
                                </td>
                                <td style="text-align:right;">
                                    <a href="/checkout/confirmation/<?= e($o->orderNumber) ?>"
                                       class="sf-btn sf-btn-outline"
                                       style="padding:.35rem .75rem;font-size:.8rem;">
                                        View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="mt-4" aria-label="Order history pagination">
                    <ul class="pagination pagination-sm justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $pages; $p++): ?>
                            <?php if ($p === 1 || $p === $pages || abs($p - $page) <= 2): ?>
                                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= e($pageUrl($p)) ?>"><?= $p ?></a>
                                </li>
                            <?php elseif (abs($p - $page) === 3): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= e($pageUrl($page + 1)) ?>">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>
