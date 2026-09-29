-- ============================================================
-- 0312_create_customer_notifications.sql
-- Customer-facing notifications for the storefront message
-- center.
--
-- This is NOT the same table as core_notifications. That table
-- is Platform-owned and targets core_users (admin identity).
-- commerce_customers is a different table with a different ID
-- space, and the two do not mix.
--
-- See docs/stages/008g.3_message-center-plan.md for the full
-- design and the reasoning behind the separate table.
--
-- Design decisions (Stage 008G.3):
--   * Commerce-owned. Every column is scoped to the store and
--     customer who receives the notification.
--   * Three categories via ENUM: 'system', 'order', 'promotion'.
--     The message center page presents them as tabs.
--   * No FK to orders. The link column carries a URL for
--     "view this order" or any other destination. If the
--     linked order is deleted, the notification still exists -
--     the link is just a URL.
--   * read_at NULL = unread. Indexed with customer and
--     created_at so the "unread count" query and the
--     "newest first" listing query are both covered by one
--     index prefix.
--   * ON DELETE CASCADE from both parents. A deleted customer
--     or store takes its notifications.
--   * No soft delete. Notifications are ephemeral. A future
--     cleanup job can purge read notifications older than N
--     days, but nothing does that today.
--   * No updated_at. A notification is create-once, then
--     either read (read_at set) or not. Nothing else updates.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_customer_notifications` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `category`    ENUM('system','order','promotion') NOT NULL DEFAULT 'system',
  `title`       VARCHAR(255) NOT NULL,
  `body`        TEXT NULL,
  `link`        VARCHAR(500) NULL,
  `read_at`     DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_cn_customer_unread`
    (`store_id`, `customer_id`, `read_at`, `created_at`),
  KEY `idx_com_cn_customer_category`
    (`store_id`, `customer_id`, `category`, `created_at`),

  CONSTRAINT `fk_com_cn_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_cn_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
