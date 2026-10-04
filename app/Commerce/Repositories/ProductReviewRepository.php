<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\ProductReview;

/**
 * Product review repository.
 *
 * Every row is scoped to (store_id, product_id). The
 * store_id comes from StoreContext; the product_id is a
 * parameter on the storefront-facing reads. Admin queries
 * scope by store_id only and list across products.
 *
 * The unique key uniq_com_rev_store_customer_product makes
 * "one review per customer per product" a database-level
 * invariant. create() relies on it: an attempt to insert a
 * second review for the same (customer, product) is caught by
 * findByCustomerAndProduct() at the service layer before the
 * insert runs. The unique key is the backstop.
 *
 * The status column gates storefront rendering. Reads that
 * feed the PDP filter to status = 'approved'. Reads that feed
 * the admin moderation queue filter to status = 'pending'.
 * Both use the same table.
 *
 * customer_id and order_id are nullable. A DSGVO Art. 17
 * erasure nulls customer_id without deleting the review, so
 * the rating remains valid for the product aggregate.
 * anonymizeForCustomer() implements that.
 *
 * No soft delete. A rejected or deleted review is removed.
 *
 * See docs/stages/008g.5_reviews-plan.md.
 */
final class ProductReviewRepository extends CommerceRepository
{
    // =================================================================
    // Reads - storefront
    // =================================================================

    /**
     * Approved reviews for a product, newest first. Used by
     * the Reviews tab on the product detail page.
     *
     * @return array<int, ProductReview>
     */
    public function listApprovedForProduct(
        int $productId,
        int $limit = 10,
        int $offset = 0
    ): array {
        if ($productId <= 0) {
            return [];
        }

        $limit  = max(1, min($limit, 50));
        $offset = max(0, $offset);

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_reviews`
              WHERE `store_id` = ?
                AND `product_id` = ?
                AND `status` = 'approved'
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$limit} OFFSET {$offset}",
            [$this->storeId(), $productId]
        );

        return array_map(fn ($r) => ProductReview::fromRow($r), $rows);
    }

    /**
     * Count of approved reviews for a product.
     */
    public function countApprovedForProduct(int $productId): int
    {
        if ($productId <= 0) {
            return 0;
        }

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_reviews`
              WHERE `store_id` = ?
                AND `product_id` = ?
                AND `status` = 'approved'",
            [$this->storeId(), $productId]
        );
    }

    /**
     * Average rating for a product, or null when there are no
     * approved reviews.
     */
    public function averageForProduct(int $productId): ?float
    {
        if ($productId <= 0) {
            return null;
        }

        $avg = $this->db->scalar(
            "SELECT AVG(`rating`) FROM `commerce_product_reviews`
              WHERE `store_id` = ?
                AND `product_id` = ?
                AND `status` = 'approved'",
            [$this->storeId(), $productId]
        );

        return $avg === null || $avg === false ? null : (float) $avg;
    }

    /**
     * Rating distribution for a product: how many 5-star, 4-
     * star, ..., 1-star reviews exist. Used by the summary
     * block on the Reviews tab.
     *
     * Always returns all five keys. A missing rating is 0.
     *
     * @return array<int, int>  keyed by rating (5, 4, 3, 2, 1)
     */
    public function distributionForProduct(int $productId): array
    {
        $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        if ($productId <= 0) {
            return $out;
        }

        $rows = $this->db->select(
            "SELECT `rating`, COUNT(*) AS cnt
               FROM `commerce_product_reviews`
              WHERE `store_id` = ?
                AND `product_id` = ?
                AND `status` = 'approved'
              GROUP BY `rating`",
            [$this->storeId(), $productId]
        );

        foreach ($rows as $r) {
            $rating = (int) $r['rating'];
            if ($rating >= 1 && $rating <= 5) {
                $out[$rating] = (int) $r['cnt'];
            }
        }

        return $out;
    }

    /**
     * The customer's own review for a product, of any status.
     * Used by the submission form to detect edit mode and by
     * the service to enforce "one review per customer".
     */
    public function findByCustomerAndProduct(
        int $customerId,
        int $productId
    ): ?ProductReview {
        if ($customerId <= 0 || $productId <= 0) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_product_reviews`
              WHERE `store_id` = ?
                AND `customer_id` = ?
                AND `product_id` = ?
              LIMIT 1",
            [$this->storeId(), $customerId, $productId]
        );

        return $row !== null ? ProductReview::fromRow($row) : null;
    }

    /**
     * Single review by id, scoped to the store.
     */
    public function findById(int $id): ?ProductReview
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_product_reviews`
              WHERE `id` = ? AND `store_id` = ?
              LIMIT 1",
            [$id, $this->storeId()]
        );

        return $row !== null ? ProductReview::fromRow($row) : null;
    }

    // =================================================================
    // Reads - admin
    // =================================================================

    /**
     * Reviews awaiting moderation, oldest first so the queue
     * drains in submission order.
     *
     * @return array<int, ProductReview>
     */
    public function listPending(int $limit = 25, int $offset = 0): array
    {
        $limit  = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_reviews`
              WHERE `store_id` = ? AND `status` = 'pending'
              ORDER BY `created_at` ASC, `id` ASC
              LIMIT {$limit} OFFSET {$offset}",
            [$this->storeId()]
        );

        return array_map(fn ($r) => ProductReview::fromRow($r), $rows);
    }

    /**
     * Reviews by status, newest first. Used by the moderation
     * list when the moderator filters to approved or rejected.
     *
     * @return array<int, ProductReview>
     */
    public function listByStatus(
        string $status,
        int $limit = 25,
        int $offset = 0
    ): array {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return [];
        }

        $limit  = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $rows = $this->db->select(
            "SELECT * FROM `commerce_product_reviews`
              WHERE `store_id` = ? AND `status` = ?
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$limit} OFFSET {$offset}",
            [$this->storeId(), $status]
        );

        return array_map(fn ($r) => ProductReview::fromRow($r), $rows);
    }

    public function countPending(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_reviews`
              WHERE `store_id` = ? AND `status` = 'pending'",
            [$this->storeId()]
        );
    }

    public function countByStatus(string $status): int
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return 0;
        }

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_product_reviews`
              WHERE `store_id` = ? AND `status` = ?",
            [$this->storeId(), $status]
        );
    }

    // =================================================================
    // Writes
    // =================================================================

    /**
     * Insert a new review. Returns the new row id, or 0 on
     * invalid input.
     *
     * The unique key on (store_id, customer_id, product_id)
     * will throw a PDOException if a duplicate insert runs.
     * The caller must check findByCustomerAndProduct() first.
     *
     * @param ?int $orderId  null when the customer had not
     *                       purchased the product
     */
    public function create(
        int $productId,
        int $customerId,
        int $rating,
        string $body,
        ?string $title = null,
        ?int $orderId = null,
        bool $verifiedPurchase = false
    ): int {
        if ($productId <= 0 || $customerId <= 0 || $rating < 1 || $rating > 5) {
            return 0;
        }

        return (int) $this->db->insert(
            "INSERT INTO `commerce_product_reviews`
                (`store_id`, `product_id`, `customer_id`, `order_id`,
                 `rating`, `title`, `body`, `verified_purchase`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [
                $this->storeId(),
                $productId,
                $customerId,
                $orderId,
                $rating,
                $title,
                $body,
                $verifiedPurchase ? 1 : 0,
            ]
        );
    }

    /**
     * Update an existing review. Only the fields provided are
     * updated. A customer edit resets the status to 'pending'
     * so the change is re-moderated.
     *
     * @param array{rating?:int, title?:?string, body?:string} $fields
     */
    public function update(int $id, array $fields): void
    {
        if ($id <= 0 || $fields === []) {
            return;
        }

        $sets   = [];
        $params = [];

        if (array_key_exists('rating', $fields)) {
            $rating = (int) $fields['rating'];
            if ($rating < 1 || $rating > 5) {
                return;
            }
            $sets[]   = "`rating` = ?";
            $params[] = $rating;
        }
        if (array_key_exists('title', $fields)) {
            $sets[]   = "`title` = ?";
            $params[] = $fields['title'];
        }
        if (array_key_exists('body', $fields)) {
            $sets[]   = "`body` = ?";
            $params[] = (string) $fields['body'];
        }

        if ($sets === []) {
            return;
        }

        // A customer edit sends the review back to moderation.
        $sets[] = "`status` = 'pending'";

        $params[] = $id;
        $params[] = $this->storeId();

        $this->db->execute(
            "UPDATE `commerce_product_reviews`
                SET " . implode(', ', $sets) . "
              WHERE `id` = ? AND `store_id` = ?",
            $params
        );
    }

    /**
     * Set the status of a review. Called by the moderation
     * queue for approve / reject.
     *
     * Returns true when the row was updated, false when the
     * id was invalid, the status was invalid, or the row was
     * not found.
     */
    public function updateStatus(int $id, string $status): bool
    {
        if ($id <= 0) {
            return false;
        }
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return false;
        }

        $affected = $this->db->execute(
            "UPDATE `commerce_product_reviews`
                SET `status` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [$status, $id, $this->storeId()]
        );

        return $affected > 0;
    }

    /**
     * Hard delete a review. Called by the moderation queue's
     * delete action.
     */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $affected = $this->db->execute(
            "DELETE FROM `commerce_product_reviews`
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );

        return $affected > 0;
    }

    /**
     * DSGVO Art. 17 anonymization. Nulls customer_id and
     * replaces the review body. The rating survives — it is
     * still valid data for the product aggregate. The title
     * is nulled. verified_purchase and order_id are kept,
     * because they record the transaction, not the customer.
     *
     * Returns the number of rows updated.
     */
    public function anonymizeForCustomer(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        return $this->db->execute(
            "UPDATE `commerce_product_reviews`
                SET `customer_id` = NULL,
                    `title` = NULL,
                    `body` = '[This review was removed at the author''s request]'
              WHERE `store_id` = ? AND `customer_id` = ?",
            [$this->storeId(), $customerId]
        );
    }
}
