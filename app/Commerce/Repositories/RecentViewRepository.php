<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\RecentView;

/**
 * Recently-viewed repository.
 *
 * Every row is scoped to (store_id, customer_id). The unique
 * key uniq_com_rv_store_customer_product makes every view an
 * upsert: re-viewing a product updates viewed_at rather than
 * creating a duplicate.
 *
 * The table carries store_id directly (same as
 * commerce_wishlists). Every query filters on store_id = ?
 * explicitly.
 *
 * Bounded to MAX_ITEMS per customer, enforced on write. After
 * every record() the customer's rows are trimmed to the 50
 * most recent. Two queries per product page view (upsert +
 * trim). Documented as Decision 1 in the plan.
 *
 * No soft delete. A cleared row is gone.
 * No status enum. No conversion lifecycle.
 *
 * See docs/stages/008g.2_recently-viewed-plan.md.
 */
final class RecentViewRepository extends CommerceRepository
{
    /**
     * How many items to keep per customer. Rows past this
     * count are deleted on the next record() call.
     */
    public const MAX_ITEMS = 50;

    /**
     * Record a product view for the customer. Idempotent: if
     * the product is already in the customer's history, the
     * viewed_at timestamp is updated (via the schema's
     * ON UPDATE CURRENT_TIMESTAMP), moving the product to the
     * top of the list. If not, a new row is inserted.
     *
     * After the upsert, trim the customer's history to
     * MAX_ITEMS.
     *
     * Returns true if this was a new row, false if it was a
     * re-view of an existing row.
     */
    public function record(int $customerId, int $productId): bool
    {
        if ($customerId <= 0 || $productId <= 0) {
            return false;
        }

        $storeId = $this->storeId();

        $wasNew = $this->db->execute(
            "INSERT INTO `commerce_recently_viewed`
                (`store_id`, `customer_id`, `product_id`, `viewed_at`)
             VALUES (?, ?, ?, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                `viewed_at` = CURRENT_TIMESTAMP",
            [$storeId, $customerId, $productId]
        );

        // rowCount() for INSERT ... ON DUPLICATE KEY UPDATE:
        //   * 1 if a row was inserted
        //   * 2 if an existing row was updated
        //   * 0 if the update was a no-op (e.g. same viewed_at)
        //
        // We treat 1 as "new" and 2 as "re-view". The no-op
        // case (0) only happens if viewed_at did not change,
        // which cannot happen here because we always set it to
        // CURRENT_TIMESTAMP. So the boolean is accurate.
        $isNew = ($wasNew === 1);

        $this->trim($customerId);

        return $isNew;
    }

    /**
     * Trim the customer's history to the MAX_ITEMS most recent
     * rows. Deletes anything older.
     *
     * The nested subquery is required: MySQL 8 does not allow
     * a subquery on the target table of a DELETE without
     * materializing it first. The `AS keep` alias is required
     * by MySQL 8's parser.
     */
    public function trim(int $customerId): void
    {
        $storeId = $this->storeId();
        $keep    = self::MAX_ITEMS;

        $this->db->execute(
            "DELETE FROM `commerce_recently_viewed`
              WHERE `store_id` = ? AND `customer_id` = ?
                AND `id` NOT IN (
                    SELECT `id` FROM (
                        SELECT `id`
                          FROM `commerce_recently_viewed`
                         WHERE `store_id` = ? AND `customer_id` = ?
                         ORDER BY `viewed_at` DESC, `id` DESC
                         LIMIT {$keep}
                    ) AS keep_rows
                )",
            [$storeId, $customerId, $storeId, $customerId]
        );
    }

    /**
     * Full history for the customer, newest first.
     *
     * @return array<int, RecentView>
     */
    public function listFor(int $customerId, int $limit = self::MAX_ITEMS): array
    {
        $limit = max(1, min($limit, self::MAX_ITEMS));

        $rows = $this->db->select(
            "SELECT * FROM `commerce_recently_viewed`
              WHERE `store_id` = ? AND `customer_id` = ?
              ORDER BY `viewed_at` DESC, `id` DESC
              LIMIT {$limit}",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => RecentView::fromRow($r), $rows);
    }

    /**
     * Flat list of product IDs, newest first. Used by the
     * recently_viewed_product_ids() helper so product cards
     * and any future "recently viewed" widget can pre-fill
     * state without a separate AJAX round-trip.
     *
     * @return array<int, int>
     */
    public function productIdsFor(int $customerId, int $limit = self::MAX_ITEMS): array
    {
        $limit = max(1, min($limit, self::MAX_ITEMS));

        $rows = $this->db->select(
            "SELECT `product_id` FROM `commerce_recently_viewed`
              WHERE `store_id` = ? AND `customer_id` = ?
              ORDER BY `viewed_at` DESC, `id` DESC
              LIMIT {$limit}",
            [$this->storeId(), $customerId]
        );
        return array_map(fn ($r) => (int) $r['product_id'], $rows);
    }

    /**
     * Number of items in the customer's history. Capped at
     * MAX_ITEMS by trim(), so the maximum return value is
     * MAX_ITEMS.
     */
    public function countFor(int $customerId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_recently_viewed`
              WHERE `store_id` = ? AND `customer_id` = ?",
            [$this->storeId(), $customerId]
        );
    }

    /**
     * Delete the customer's entire history. Called by the
     * "Clear history" button on /account/recent.
     *
     * Returns the number of rows deleted.
     */
    public function clearFor(int $customerId): int
    {
        return $this->db->execute(
            "DELETE FROM `commerce_recently_viewed`
              WHERE `store_id` = ? AND `customer_id` = ?",
            [$this->storeId(), $customerId]
        );
    }

    /**
     * Bulk add for merge-on-login. Each product ID is upserted
     * with its position preserved - the session array is
     * newest-first, and iterating in that order with a
     * CURRENT_TIMESTAMP set on each row would give them all
     * roughly the same viewed_at.
     *
     * To preserve ordering, we deliberately walk the array
     * from oldest to newest so the last-processed (newest)
     * item ends up with the latest viewed_at.
     *
     * Returns the number of rows actually inserted (not
     * counting re-views that updated an existing row).
     *
     * @param array<int, int> $productIds newest-first
     */
    public function bulkAdd(int $customerId, array $productIds): int
    {
        if ($productIds === [] || $customerId <= 0) {
            return 0;
        }

        // Dedup while preserving order. Filter to positive
        // ints. Session arrays should already be clean, but
        // defensive here costs nothing.
        $seen = [];
        $clean = [];
        foreach ($productIds as $pid) {
            $pid = (int) $pid;
            if ($pid <= 0 || isset($seen[$pid])) {
                continue;
            }
            $seen[$pid] = true;
            $clean[] = $pid;
        }
        if ($clean === []) {
            return 0;
        }

        // Session array is newest-first. Iterate oldest-first
        // so the newest item is processed last.
        $clean = array_reverse($clean);

        $storeId = $this->storeId();
        $inserted = 0;

        foreach ($clean as $pid) {
            $affected = $this->db->execute(
                "INSERT INTO `commerce_recently_viewed`
                    (`store_id`, `customer_id`, `product_id`, `viewed_at`)
                 VALUES (?, ?, ?, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE
                    `viewed_at` = CURRENT_TIMESTAMP",
                [$storeId, $customerId, $pid]
            );
            if ($affected === 1) {
                $inserted++;
            }
        }

        $this->trim($customerId);

        return $inserted;
    }
}
