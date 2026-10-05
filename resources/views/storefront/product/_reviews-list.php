<?php
/**
 * Approved review list.
 *
 * Renders up to N reviews. When there are more, the
 * "Load more" button fetches them via
 * GET /products/{slug}/reviews?offset=N.
 *
 * @var \CorporateAid\Commerce\Models\Product $product
 * @var array{
 *   reviews: array<int, \CorporateAid\Commerce\Models\ProductReview>,
 *   count: int,
 *   average: ?float,
 *   distribution: array<int, int>
 * } $reviewAggregate
 */

$reviews = $reviewAggregate['reviews'] ?? [];
$count   = (int) ($reviewAggregate['count'] ?? 0);
$shown   = count($reviews);

$authorName = function (\CorporateAid\Commerce\Models\ProductReview $r): string {
    if ($r->customerId === null) {
        return 'Anonymous';
    }

    try {
        $customers = \CorporateAid\Core\App::instance()
            ->container()
            ->get(\CorporateAid\Commerce\Repositories\CustomerRepository::class);
        $c = $customers->findById($r->customerId);
        if ($c === null) {
            return 'Anonymous';
        }
        $name = (string) $c->fullName();
        return $name !== '' ? $name : (string) $c->email;
    } catch (\Throwable) {
        return 'Anonymous';
    }
};
?>

<?php if ($reviews === []): ?>
    <div class="sf-product-reviews-empty">
        <i class="bi bi-chat-square-text" aria-hidden="true"></i>
        <p class="sf-product-reviews-empty-title">No reviews yet</p>
        <p class="sf-product-reviews-empty-body">
            Be the first to review this product. Share your
            experience to help other shoppers decide.
        </p>
    </div>
<?php else: ?>
    <ul class="sf-reviews-list" id="sf-reviews-list">
        <?php foreach ($reviews as $r): ?>
            <li class="sf-review-item">
                <header class="sf-review-header">
                    <div class="sf-review-author">
                        <span class="sf-review-avatar" aria-hidden="true">
                            <?= e(mb_substr($authorName($r), 0, 1)) ?>
                        </span>
                        <span class="sf-review-meta">
                            <span class="sf-review-author-name">
                                <?= e($authorName($r)) ?>
                            </span>
                            <?php if ($r->verifiedPurchase): ?>
                                <span class="sf-review-verified">
                                    <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                    Verified purchase
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="sf-review-date muted">
                        <?= e(date('M j, Y', strtotime((string) $r->createdAt))) ?>
                    </div>
                </header>

                <div class="sf-review-stars" aria-label="<?= (int) $r->rating ?> out of 5 stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi <?= $i <= $r->rating ? 'bi-star-fill' : 'bi-star' ?>" aria-hidden="true"></i>
                    <?php endfor; ?>
                </div>

                <?php if (!empty($r->title)): ?>
                    <h4 class="sf-review-title"><?= e($r->title) ?></h4>
                <?php endif; ?>

                <div class="sf-review-body">
                    <?= nl2br(e($r->body)) ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($shown < $count): ?>
        <div class="sf-reviews-load-more">
            <button type="button"
                    class="sf-btn sf-btn-outline"
                    id="sf-reviews-load-more"
                    data-slug="<?= e($product->slug) ?>"
                    data-offset="<?= $shown ?>"
                    data-total="<?= $count ?>">
                Load more reviews
            </button>
        </div>
    <?php endif; ?>
<?php endif; ?>
