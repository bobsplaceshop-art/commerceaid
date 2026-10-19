<?php
$avg = $reviewAggregate['average'] ?? 0;
$count = $reviewAggregate['count'] ?? 0;
$dist = $reviewAggregate['distribution'] ?? [];
$starLabels = [5 => 'Excellent', 4 => 'Good', 3 => 'Average', 2 => 'Poor', 1 => 'Terrible'];
?>
<div class="sf-reviews-header">
    <div class="sf-reviews-score-block">
        <div class="sf-reviews-score-number"><?= number_format($avg, 1) ?></div>
        <div class="sf-reviews-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star<?= $i <= round($avg) ? '-fill' : '' ?>"></i>
            <?php endfor; ?>
        </div>
        <div class="sf-reviews-count"><?= (int) $count ?> <?= $count === 1 ? 'review' : 'reviews' ?></div>
    </div>

    <div class="sf-reviews-distribution-block">
        <?php for ($i = 5; $i >= 1; $i--): ?>
            <?php
                $starCount = $dist[$i] ?? 0;
                $percent = $count > 0 ? ($starCount / $count) * 100 : 0;
            ?>
            <div class="sf-reviews-bar-row">
                <span class="sf-reviews-bar-label"><?= $starLabels[$i] ?></span>
                <div class="sf-reviews-bar-track">
                    <div class="sf-reviews-bar-fill" style="width: <?= $percent ?>%;"></div>
                </div>
                <span class="sf-reviews-bar-count"><?= (int) $starCount ?></span>
            </div>
        <?php endfor; ?>
    </div>

    <div class="sf-reviews-action-block">
        <button type="button" class="sf-review-cta" data-bs-toggle="collapse" data-bs-target="#review-form-wrapper">
            Write a review
        </button>
    </div>
</div>
