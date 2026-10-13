<?php
/**
 * Reviews moderation queue.
 *
 * @var array<int,\CorporateAid\Commerce\Models\ProductReview> $reviews
 * @var string $status          'pending' | 'approved' | 'rejected'
 * @var int    $page
 * @var int    $total
 * @var int    $totalPages
 * @var int    $pendingCnt      pending count for the badge
 * @var array<int,\CorporateAid\Commerce\Models\Customer> $customerMap
 * @var array<int,\CorporateAid\Commerce\Models\Product>  $productMap
 */

$statuses = [
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
];
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <div>
        <h1 class="h3 mb-1">Reviews</h1>
        <p class="muted mb-0">
            <?= (int) $total ?>
            <?= $status === 'pending' ? 'awaiting moderation' : strtolower($statuses[$status] ?? $status) ?>
            <?php if ($pendingCnt > 0 && $status !== 'pending'): ?>
                &middot; <?= (int) $pendingCnt ?> pending
            <?php endif; ?>
        </p>
    </div>
</div>

<div class="ra-card mb-4">
    <nav class="ra-admin-tabs" aria-label="Review status">
        <?php foreach ($statuses as $key => $label): ?>
            <a href="/commerce/ca-admin/reviews?status=<?= e($key) ?>"
               class="ra-admin-tab<?= $status === $key ? ' is-active' : '' ?>">
                <?= e($label) ?>
                <?php if ($key === 'pending' && $pendingCnt > 0): ?>
                    <span class="ra-admin-tab-count">(<?= (int) $pendingCnt ?>)</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if (empty($reviews)): ?>
    <div class="ra-card">
        <div class="ra-card-body text-center py-5">
            <i class="bi bi-chat-square-text" style="font-size:2.5rem;color:var(--ra-muted,#94a3b8);"></i>
            <h2 class="h6 mt-3 mb-1">
                <?php if ($status === 'pending'): ?>
                    No reviews awaiting moderation
                <?php elseif ($status === 'approved'): ?>
                    No approved reviews yet
                <?php else: ?>
                    No rejected reviews
                <?php endif; ?>
            </h2>
            <p class="muted small mb-0">
                <?php if ($status === 'pending'): ?>
                    When customers submit reviews, they will appear here for approval.
                <?php else: ?>
                    Switch to a different tab to see other reviews.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="ra-card">
        <div class="ra-table-responsive-wrap" data-responsive="cards">
            <table class="ra-table">
                <thead>
                    <tr>
                        <th style="width:220px;">Product</th>
                        <th style="width:180px;">Customer</th>
                        <th style="width:120px;">Rating</th>
                        <th>Review</th>
                        <th style="width:110px;">Date</th>
                        <th style="width:220px;text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                        <?php
                        $product = $productMap[$r->productId] ?? null;
                        $customer = $r->customerId !== null
                            ? ($customerMap[$r->customerId] ?? null)
                            : null;

                        if ($customer !== null) {
                            $authorName = (string) $customer->fullName();
                            if ($authorName === '') {
                                $authorName = (string) $customer->email;
                            }
                        } else {
                            $authorName = 'Anonymous';
                        }
                        ?>
                        <tr>
                            <td data-label="Product" data-col="full">
                                <?php if ($product !== null): ?>
                                    <a href="/products/<?= e(rawurlencode($product->slug)) ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-decoration-none">
                                        <?= e($product->name) ?>
                                        <i class="bi bi-box-arrow-up-right" style="font-size:.7rem;"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="muted">Product #<?= (int) $r->productId ?> (deleted)</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Customer" data-col="half">
                                <?= e($authorName) ?>
                                <?php if ($r->verifiedPurchase): ?>
                                    <div class="muted small">
                                        <i class="bi bi-patch-check-fill" style="color:#16a34a;"></i>
                                        Verified purchase
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Rating" data-col="half">
                                <span style="color:var(--ra-accent,#c6a15b);letter-spacing:.05em;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="bi <?= $i <= $r->rating ? 'bi-star-fill' : 'bi-star' ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </td>
                            <td data-label="Review" data-col="full">
                                <?php if (!empty($r->title)): ?>
                                    <div style="font-weight:600;margin-bottom:.25rem;">
                                        <?= e($r->title) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="muted small" style="max-width:420px;">
                                    <?= e(mb_strimwidth($r->body, 0, 160, '…')) ?>
                                </div>
                            </td>
                            <td class="muted small" data-label="Date" data-col="half">
                                <?= e(date('M j, Y', strtotime((string) $r->createdAt))) ?>
                            </td>
                            <td class="text-end">
                                <?php if ($status !== 'approved'): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/reviews/<?= (int) $r->id ?>/approve"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <button type="submit" class="ra-icon-btn" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status !== 'rejected'): ?>
                                    <form method="POST"
                                          action="/commerce/ca-admin/reviews/<?= (int) $r->id ?>/reject"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <button type="submit" class="ra-icon-btn" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <button type="button"
                                        class="ra-icon-btn"
                                        onclick="RA_DeleteReview.open(<?= (int) $r->id ?>, <?= json_encode($authorName, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)"
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="mt-3 d-flex gap-2 justify-content-center" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a href="?status=<?= e($status) ?>&page=<?= $page - 1 ?>" class="ra-btn ra-btn-outline">
                    &larr; Previous
                </a>
            <?php endif; ?>
            <span class="ra-btn ra-btn-outline" style="cursor:default;">
                Page <?= (int) $page ?> of <?= (int) $totalPages ?>
            </span>
            <?php if ($page < $totalPages): ?>
                <a href="?status=<?= e($status) ?>&page=<?= $page + 1 ?>" class="ra-btn ra-btn-outline">
                    Next &rarr;
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?= view_partial('admin/reviews/_delete-modal', ['status' => $status]) ?>
