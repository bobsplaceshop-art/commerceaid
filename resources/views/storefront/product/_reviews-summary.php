<?php
/**
 * Reviews summary block.
 *
 * Renders the average rating, the total count, and a
 * distribution histogram (5-star down to 1-star).
 *
 * @var array{
 *   reviews: array<int, \CorporateAid\Commerce\Models\ProductReview>,
 *   count: int,
 *   average: ?float,
 *   distribution: array<int, int>
 * } $reviewAggregate
 */
$avg   = $reviewAggregate['average'] ?? null;
$count = (int) ($reviewAggregate['count'] ?? 0);
$dist  = $reviewAggregate['distribution'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

// No reviews at all — the summary block renders nothing.
// The form and the "no reviews yet" empty state handle
// the rest.
if ($count === 0) {
    return;
}
?>

<div class="sf-reviews-summary">
    <div class="sf-reviews-summary-score">
        <div class="sf-reviews-average">
            <?= e(number_format((float) $avg, 1)) ?>
        </div>
        <div class="sf-reviews-stars" aria-label="<?= e(number_format((float) $avg, 1)) ?> out of 5 stars">
            <?php
            $wholeStars = (int) round((float) $avg);
            for ($i = 1; $i <= 5; $i++):
                $cls = $i <= $wholeStars ? 'bi-star-fill' : 'bi-star';
                ?>
                <i class="bi <?= $cls ?>" aria-hidden="true"></i>
            <?php endfor; ?>
        </div>
        <div class="sf-reviews-count">
            <?= (int) $count ?> review<?= $count === 1 ? '' : 's' ?>
        </div>
    </div>

    <div class="sf-reviews-distribution">
        <?php for ($star = 5; $star >= 1; $star--): ?>
            <?php
            $rowCount = (int) ($dist[$star] ?? 0);
            $pct      = $count > 0 ? (int) round(($rowCount / $count) * 100) : 0;
            ?>
            <div class="sf-reviews-bar-row">
                <span class="sf-reviews-bar-label"><?= $star ?> star<?= $star === 1 ? '' : 's' ?></span>
                <div class="sf-reviews-bar-track">
                    <div class="sf-reviews-bar-fill" style="width: <?= $pct ?>%"></div>
                </div>
                <span class="sf-reviews-bar-count"><?= $rowCount ?></span>
            </div>
        <?php endfor; ?>
    </div>
</div>
