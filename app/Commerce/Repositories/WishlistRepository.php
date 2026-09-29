<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\WishlistItem;

/**
 * Wishlist repository.
 *
 * Every row is scoped to (store_id, customer_id). The
 * unique key uniq_com_wl_store_customer_product makes the
 * heart button idempotent: adding twice is a no-op on the
 * second call.
 *
 * The table carries store_id directly (unlike
 * commerce_cart_items, which is isolated by the FK chain
 * through commerce_carts). Every query here filters on
 * store_id = ? explicitly.
 *
 * No soft delete. A removed row is gone.
 * No status enum. No conversion lifecycle.
 *
 * See docs/stages/008g.1_wishlist-plan.md.
 */
final class WishlistRepository extends CommerceRepository
{
    /**
     * Add a product to the customer's wishlist. Idempotent:
     * a duplicate add is silently ignored (INSERT IGNORE).
     *
     * Returns true if a new row was inserted, false if one
     * already existed.
     *
     * The variant_id column is reserved for a future
     * sub-stage. This method always writes 0.
     */
    public function add(int $customerId, int $productId): bool
    {
        $affected = $this->db->execute(
            "INSERT IGNORE INTO `commerce_wishlists`
                (`store_id`, `customer_id`, `product_id`, `variant_id`)
             VALUES (?, ?, ?, 0)",
            [$this->storeId(), $customerId, $productId]
        );
        return $affected > 0;
    }

    /**
     * Remove a product from the customer's wishlist. Idempotent:
     * removing a row that is not there is a no-op.
     *
     * Returns true if a row was deleted, false if none matched.
     */
    public function remove(int $customerId, int $productId): bool
    {
        $affected = $this->db->execute(
            "DELETE FROM `commerce_wishlists`
              WHERE `store_id` = ? AND `customer_id` = ?
                AND `product_id` = ?",
            [$this->storeId(), $customerId, $productId]
        );
        return $affected > 0;
    }

    /**
     * Whether the customer has this product wishlisted.
     */
    public function exists(int $customerId, int $productId): bool
    {
        $row = $this->db->selectOne(
            "SELECT 1 FROM `commerce_wishlists`
              WHERE `store_id` = ? AND `customer_id` = ?
                AND `product_id` = ?
              LIMIT 1",
            [$this->storeId(), $customerId, $productId]
        );
        return $row !== null;
    }

    /**
     * Return the list of product IDs the customer has
     * wishlisted, newest first. Used by the render() injection
     * in StorefrontController so product cards can pre-fill
     * their heart state without an extra AJAX round-trip.
     *
     * @return array<int, int>
     */
    public function productIdsFor(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT `product_id` FROM `commerce_wishlists`
              WHERE `store_id` = ? AND `customer_id` = ?
              ORDER BY `created_at` DESC, `id` DESC",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => (int) $r['product_id'], $rows);
    }

    /**
     * Full wishlist for the /account/wishlist page, newest
     * first.
     *
     * @return array<int, WishlistItem>
     */
    public function listFor(int $customerId): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_wishlists`
              WHERE `store_id` = ? AND `customer_id` = ?
              ORDER BY `created_at` DESC, `id` DESC",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => WishlistItem::fromRow($r), $rows);
    }

    /**
     * Number of items on the customer's wishlist. Used by the
     * account sidebar badge (unread count parallel).
     */
    public function countFor(int $customerId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_wishlists`
              WHERE `store_id` = ? AND `customer_id` = ?",
            [$this->storeId(), $customerId]
        );
    }

    /**
     * Bulk add: insert any of the given product IDs that are
     * not already on the customer's wishlist. Used by the
     * merge-on-login flow when a guest had items in their
     * session.
     *
     * Returns the number of rows actually inserted (skips
     * duplicates).
     *
     * @param array<int, int> $productIds
     */
    public function bulkAdd(int $customerId, array $productIds): int
    {
        if ($productIds === []) {
            return 0;
        }

        $storeId = $this->storeId();
        $inserted = 0;

        foreach ($productIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) {
                continue;
            }
            $affected = $this->db->execute(
                "INSERT IGNORE INTO `commerce_wishlists`
                    (`store_id`, `customer_id`, `product_id`, `variant_id`)
                 VALUES (?, ?, ?, 0)",
                [$storeId, $customerId, $pid]
            );
            if ($affected > 0) {
                $inserted++;
            }
        }

        return $inserted;
    }
}
