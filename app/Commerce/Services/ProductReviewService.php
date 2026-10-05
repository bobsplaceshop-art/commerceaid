<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services;

use CorporateAid\Commerce\Models\ProductReview;
use CorporateAid\Commerce\Repositories\OrderRepository;
use CorporateAid\Commerce\Repositories\ProductReviewRepository;

/**
 * Product review service.
 *
 * Orchestrates the review lifecycle: validation, the
 * "one review per customer per product" check, the
 * verified-purchase lookup, and delegation to the repository
 * for reads and writes.
 *
 * Validation rules (locked in the plan, Decision 3):
 *   - rating   integer 1-5, required
 *   - title    optional, 150 chars max
 *   - body     required, 5000 chars max
 *
 * The service treats every submission as either create or
 * edit. If the customer already has a review for this
 * product (findByCustomerAndProduct), the submission updates
 * it and resets status to 'pending'. If not, it creates a new
 * one. The unique key in the schema is the backstop.
 *
 * Verified purchase: the customer has an order in
 * commerce_orders with status IN ('delivered', 'completed')
 * whose commerce_order_items contains this product_id. The
 * query lives in OrderRepository::findVerifiedPurchaseOrderId()
 * because it queries the order tables. The order_id is stored
 * for audit. If the order is later refunded, the badge stays
 * - it recorded that a purchase happened.
 *
 * See docs/stages/008g.5_reviews-plan.md.
 */
final class ProductReviewService
{
    public const MAX_TITLE_LEN = 150;
    public const MAX_BODY_LEN  = 5000;

    public function __construct(
        private ProductReviewRepository $reviews,
        private OrderRepository $orders
    ) {
    }

    // =================================================================
    // Submission
    // =================================================================

    /**
     * Submit (create or update) a review.
     *
     * @return array{
     *   ok: bool,
     *   id?: int,
     *   is_edit?: bool,
     *   errors?: array<int,string>
     * }
     */
    public function submit(
        int $productId,
        int $customerId,
        int $rating,
        string $body,
        ?string $title = null
    ): array {
        $errors = [];

        if ($productId <= 0) {
            $errors[] = 'Invalid product.';
        }
        if ($customerId <= 0) {
            $errors[] = 'You must be signed in to review.';
        }
        if ($rating < 1 || $rating > 5) {
            $errors[] = 'Please choose a rating between 1 and 5 stars.';
        }

        $body = trim($body);
        if ($body === '') {
            $errors[] = 'Please write a short review.';
        } elseif (mb_strlen($body) > self::MAX_BODY_LEN) {
            $errors[] = 'Your review is too long. Please keep it under '
                . self::MAX_BODY_LEN . ' characters.';
        }

        if ($title !== null) {
            $title = trim($title);
            if ($title === '') {
                $title = null;
            } elseif (mb_strlen($title) > self::MAX_TITLE_LEN) {
                $errors[] = 'Your title is too long. Please keep it under '
                    . self::MAX_TITLE_LEN . ' characters.';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $existing = $this->reviews->findByCustomerAndProduct(
            $customerId,
            $productId
        );

        if ($existing !== null) {
            $this->reviews->update($existing->id, [
                'rating' => $rating,
                'title'  => $title,
                'body'   => $body,
            ]);
            return ['ok' => true, 'id' => $existing->id, 'is_edit' => true];
        }

        $orderId  = $this->orders->findVerifiedPurchaseOrderId($customerId, $productId);
        $verified = $orderId !== null;

        $id = $this->reviews->create(
            productId: $productId,
            customerId: $customerId,
            rating: $rating,
            body: $body,
            title: $title,
            orderId: $orderId,
            verifiedPurchase: $verified
        );

        if ($id <= 0) {
            return ['ok' => false, 'errors' => ['Could not save your review. Please try again.']];
        }

        return ['ok' => true, 'id' => $id, 'is_edit' => false];
    }

    // =================================================================
    // Storefront reads
    // =================================================================

    /**
     * The full review package for a product page.
     *
     * @return array{
     *   reviews: array<int, ProductReview>,
     *   count: int,
     *   average: ?float,
     *   distribution: array<int, int>
     * }
     */
    public function forProduct(int $productId, int $limit = 10, int $offset = 0): array
    {
        return [
            'reviews'      => $this->reviews->listApprovedForProduct($productId, $limit, $offset),
            'count'        => $this->reviews->countApprovedForProduct($productId),
            'average'      => $this->reviews->averageForProduct($productId),
            'distribution' => $this->reviews->distributionForProduct($productId),
        ];
    }

    /**
     * The current customer's review for a product, or null.
     */
    public function customerReview(int $productId, ?int $customerId): ?ProductReview
    {
        if ($customerId === null || $customerId <= 0 || $productId <= 0) {
            return null;
        }

        return $this->reviews->findByCustomerAndProduct($customerId, $productId);
    }

    // =================================================================
    // Moderation
    // =================================================================

    public function approve(int $reviewId): bool
    {
        return $this->reviews->updateStatus($reviewId, 'approved');
    }

    public function reject(int $reviewId): bool
    {
        return $this->reviews->updateStatus($reviewId, 'rejected');
    }

    public function delete(int $reviewId): bool
    {
        return $this->reviews->delete($reviewId);
    }

    // =================================================================
    // DSGVO
    // =================================================================

    /**
     * Anonymize a customer's reviews on account deletion.
     * Returns the number of rows affected.
     */
    public function anonymizeForCustomer(int $customerId): int
    {
        return $this->reviews->anonymizeForCustomer($customerId);
    }
}
