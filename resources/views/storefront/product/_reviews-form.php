<?php
/**
 * Review submission form.
 *
 * Three states:
 *   - Logged in, no existing review: empty form.
 *   - Logged in, existing review (any status): prefilled edit form.
 *     Shows a status banner for pending/rejected reviews.
 *   - Not logged in: a "Sign in to review" call-to-action.
 *
 * @var \CorporateAid\Commerce\Models\Product $product
 * @var ?\CorporateAid\Commerce\Models\ProductReview $customerReview
 * @var ?int $customerId    null for guests
 */
$customerId     = $customerId     ?? null;
$customerReview = $customerReview ?? null;
$isLoggedIn     = $customerId !== null && $customerId > 0;

$isEdit   = $isLoggedIn && $customerReview !== null;
$status   = $isEdit ? (string) $customerReview->status : null;
$rating   = $isEdit ? (int) $customerReview->rating : 0;
$title    = $isEdit ? (string) ($customerReview->title ?? '') : '';
$body     = $isEdit ? (string) $customerReview->body : '';
?>

<div class="sf-reviews-form-block">

    <?php if (!$isLoggedIn): ?>
        <div class="sf-reviews-signin">
            <p class="sf-reviews-signin-text">
                Have you used this product? Share your experience
                with other shoppers.
            </p>
            <a href="/account/login?intended=<?= e(rawurlencode('/products/' . $product->slug . '#reviews')) ?>"
               class="sf-btn sf-btn-outline">
                <i class="bi bi-person me-2" aria-hidden="true"></i>
                Sign in to write a review
            </a>
        </div>
    <?php else: ?>

        <?php if ($isEdit && $status === 'pending'): ?>
            <div class="sf-reviews-status sf-reviews-status-pending">
                <i class="bi bi-clock-history" aria-hidden="true"></i>
                Your review is awaiting moderation. You can edit it
                below before it is approved.
            </div>
        <?php elseif ($isEdit && $status === 'rejected'): ?>
            <div class="sf-reviews-status sf-reviews-status-rejected">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                Your review was not approved. You can edit and resubmit it.
            </div>
        <?php elseif ($isEdit && $status === 'approved'): ?>
            <div class="sf-reviews-status sf-reviews-status-approved">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                Your review is live. Editing it will send it back for moderation.
            </div>
        <?php endif; ?>

        <form method="POST"
              action="/products/<?= e(rawurlencode($product->slug)) ?>/review"
              class="sf-reviews-form"
              id="sf-reviews-form">

            <?= csrf_field() ?>

            <div class="sf-reviews-field">
                <label class="sf-reviews-label" for="sf-review-rating">
                    Your rating <span class="req">*</span>
                </label>
                <div class="sf-reviews-rating-picker" role="radiogroup" aria-label="Rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <label class="sf-reviews-rating-option">
                            <input type="radio"
                                   name="rating"
                                   value="<?= $i ?>"
                                   <?= $rating === $i ? 'checked' : '' ?>
                                   required>
                            <span class="sf-reviews-rating-star">
                                <i class="bi bi-star-fill" aria-hidden="true"></i>
                            </span>
                            <span class="sf-reviews-rating-num"><?= $i ?></span>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="sf-reviews-field">
                <label class="sf-reviews-label" for="sf-review-title">
                    Title <span class="sf-reviews-optional">(optional)</span>
                </label>
                <input type="text"
                       id="sf-review-title"
                       name="title"
                       class="sf-reviews-input"
                       maxlength="150"
                       value="<?= e($title) ?>"
                       placeholder="Summarise your experience">
            </div>

            <div class="sf-reviews-field">
                <label class="sf-reviews-label" for="sf-review-body">
                    Your review <span class="req">*</span>
                </label>
                <textarea id="sf-review-body"
                          name="body"
                          class="sf-reviews-textarea"
                          rows="5"
                          maxlength="5000"
                          required
                          placeholder="What did you like or dislike? How did it fit?"><?= e($body) ?></textarea>
            </div>

            <button type="submit" class="sf-btn sf-btn-primary">
                <i class="bi bi-send me-2" aria-hidden="true"></i>
                <?= $isEdit ? 'Update review' : 'Submit review' ?>
            </button>

        </form>
    <?php endif; ?>

</div>
