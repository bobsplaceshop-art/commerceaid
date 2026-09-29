<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\CustomerNotification;

/**
 * Customer notification repository.
 *
 * Every row is scoped to (store_id, customer_id). The
 * controller always resolves the customer from the
 * authenticated session before calling any method here. The
 * repository trusts the customer ID it is given - it does not
 * verify ownership against the request.
 *
 * The table carries store_id directly. Every query filters on
 * store_id = ? explicitly, same as the wishlist and
 * recently-viewed tables.
 *
 * No soft delete. A deleted notification is gone. A future
 * cleanup job can purge read notifications older than N days.
 *
 * The message center reads rows by category for the tabs and
 * by unread status for the badge. Both indexes on the table
 * cover the query paths:
 *
 *   idx_com_cn_customer_unread
 *     (store_id, customer_id, read_at, created_at)
 *   idx_com_cn_customer_category
 *     (store_id, customer_id, category, created_at)
 *
 * See docs/stages/008g.3_message-center-plan.md.
 */
final class CustomerNotificationRepository extends CommerceRepository
{
    // =================================================================
    // Writes
    // =================================================================

    /**
     * Insert a new notification. Returns the new row ID.
     *
     * Called only by CustomerNotificationService. The service is
     * the sole producer; no controller or other repository
     * writes directly.
     */
    public function create(
        int $customerId,
        string $category,
        string $title,
        ?string $body = null,
        ?string $link = null
    ): int {
        return (int) $this->db->insert(
            "INSERT INTO `commerce_customer_notifications`
                (`store_id`, `customer_id`, `category`, `title`, `body`, `link`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $customerId,
                $category,
                $title,
                $body,
                $link,
            ]
        );
    }

    /**
     * Mark a single notification as read. The customer_id in
     * the WHERE clause is a safety check - a customer cannot
     * mark another customer's notification read even if they
     * guess the ID.
     *
     * Returns true if a row was updated, false if no matching
     * unread notification was found (either already read, or
     * not owned by this customer, or not existing).
     */
    public function markRead(int $notificationId, int $customerId): bool
    {
        if ($notificationId <= 0 || $customerId <= 0) {
            return false;
        }

        $affected = $this->db->execute(
            "UPDATE `commerce_customer_notifications`
                SET `read_at` = CURRENT_TIMESTAMP
              WHERE `id` = ?
                AND `store_id` = ?
                AND `customer_id` = ?
                AND `read_at` IS NULL",
            [$notificationId, $this->storeId(), $customerId]
        );

        return $affected > 0;
    }

    /**
     * Mark every unread notification for the customer as read.
     *
     * Returns the number of rows that were actually updated.
     * Rows that were already read are not counted.
     */
    public function markAllRead(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        return $this->db->execute(
            "UPDATE `commerce_customer_notifications`
                SET `read_at` = CURRENT_TIMESTAMP
              WHERE `store_id` = ?
                AND `customer_id` = ?
                AND `read_at` IS NULL",
            [$this->storeId(), $customerId]
        );
    }

    // =================================================================
    // Reads
    // =================================================================

    /**
     * Find a single notification by ID, scoped to the customer.
     * Returns null if the notification does not exist, is not
     * in this store, or belongs to a different customer.
     */
    public function findById(int $notificationId, int $customerId): ?CustomerNotification
    {
        if ($notificationId <= 0 || $customerId <= 0) {
            return null;
        }

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_customer_notifications`
              WHERE `id` = ?
                AND `store_id` = ?
                AND `customer_id` = ?
              LIMIT 1",
            [$notificationId, $this->storeId(), $customerId]
        );

        return $row !== null ? CustomerNotification::fromRow($row) : null;
    }

    /**
     * List notifications for the customer, newest first.
     *
     * When $category is null, returns all categories mixed
     * (used by an "all" tab if one is added later). When
     * $category is set, filters to that category.
     *
     * @return array<int, CustomerNotification>
     */
    public function listFor(
        int $customerId,
        ?string $category = null,
        int $limit = 50
    ): array {
        if ($customerId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 200));
        $storeId = $this->storeId();

        if ($category !== null && $category !== '') {
            $rows = $this->db->select(
                "SELECT * FROM `commerce_customer_notifications`
                  WHERE `store_id` = ? AND `customer_id` = ?
                    AND `category` = ?
                  ORDER BY `created_at` DESC, `id` DESC
                  LIMIT {$limit}",
                [$storeId, $customerId, $category]
            );
        } else {
            $rows = $this->db->select(
                "SELECT * FROM `commerce_customer_notifications`
                  WHERE `store_id` = ? AND `customer_id` = ?
                  ORDER BY `created_at` DESC, `id` DESC
                  LIMIT {$limit}",
                [$storeId, $customerId]
            );
        }

        return array_map(fn ($r) => CustomerNotification::fromRow($r), $rows);
    }

    /**
     * Most recent unread notifications for the customer. Used
     * by the header bell dropdown. Ordered newest first.
     *
     * @return array<int, CustomerNotification>
     */
    public function unreadFor(int $customerId, int $limit = 5): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 20));

        $rows = $this->db->select(
            "SELECT * FROM `commerce_customer_notifications`
              WHERE `store_id` = ?
                AND `customer_id` = ?
                AND `read_at` IS NULL
              ORDER BY `created_at` DESC, `id` DESC
              LIMIT {$limit}",
            [$this->storeId(), $customerId]
        );

        return array_map(fn ($r) => CustomerNotification::fromRow($r), $rows);
    }

    /**
     * Count of unread notifications for the customer. Used by
     * the account sidebar badge.
     */
    public function unreadCount(int $customerId): int
    {
        if ($customerId <= 0) {
            return 0;
        }

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_customer_notifications`
              WHERE `store_id` = ?
                AND `customer_id` = ?
                AND `read_at` IS NULL",
            [$this->storeId(), $customerId]
        );
    }

    /**
     * Count of notifications per category for the customer.
     * Used by the message center tabs to show "Order Updates (3)"
     * and similar.
     *
     * Returns a map: category => count. Categories with zero
     * rows are present with a count of 0.
     *
     * @return array<string, int>
     */
    public function countsByCategory(int $customerId): array
    {
        $out = ['system' => 0, 'order' => 0, 'promotion' => 0];

        if ($customerId <= 0) {
            return $out;
        }

        $rows = $this->db->select(
            "SELECT `category`, COUNT(*) AS cnt
               FROM `commerce_customer_notifications`
              WHERE `store_id` = ? AND `customer_id` = ?
              GROUP BY `category`",
            [$this->storeId(), $customerId]
        );

        foreach ($rows as $row) {
            $cat = (string) $row['category'];
            if (isset($out[$cat])) {
                $out[$cat] = (int) $row['cnt'];
            }
        }

        return $out;
    }

    /**
     * Count of unread notifications per category. Used by the
     * tab badges to show "Order Updates (2)" with unread-only
     * counts.
     *
     * @return array<string, int>
     */
    public function unreadCountsByCategory(int $customerId): array
    {
        $out = ['system' => 0, 'order' => 0, 'promotion' => 0];

        if ($customerId <= 0) {
            return $out;
        }

        $rows = $this->db->select(
            "SELECT `category`, COUNT(*) AS cnt
               FROM `commerce_customer_notifications`
              WHERE `store_id` = ?
                AND `customer_id` = ?
                AND `read_at` IS NULL
              GROUP BY `category`",
            [$this->storeId(), $customerId]
        );

        foreach ($rows as $row) {
            $cat = (string) $row['category'];
            if (isset($out[$cat])) {
                $out[$cat] = (int) $row['cnt'];
            }
        }

        return $out;
    }
}
