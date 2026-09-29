-- ============================================================
-- 0311_create_recently_viewed.sql
-- Recently-viewed product tracking for logged-in customers.
--
-- Guest recently-viewed state lives in the customer session
-- ($_SESSION['recently_viewed']), not in the database. This
-- migration creates the table for the customer-side history
-- only.
--
-- The table is:
--   commerce_recently_viewed - one row per (customer, product)
--
-- Design decisions (Stage 008G.2):
--   * One row per (store_id, customer_id, product_id). The
--     UNIQUE key means every view is an upsert. The 24-hour
--     dedup window described in the plan emerges from this
--     key, not from code. Re-viewing a product within 24h
--     (or after) simply updates viewed_at, which moves the
--     product to the top of the customer's list.
--   * No variant tracking. Recently-viewed is product-level.
--     A customer browsing a variable product has not chosen a
--     variant yet, so tracking a variant would be meaningless.
--     Same reasoning as the wishlist.
--   * No snapshot fields. The list renders live product data
--     at page load. There is no conversion, no order, no
--     historical state to preserve.
--   * No status enum. No soft delete. A cleared row is gone.
--     The audit trail lives in core_audit_logs.
--   * viewed_at updates on every view. MySQL's ON UPDATE
--     CURRENT_TIMESTAMP handles the bump automatically.
--     INSERT ... ON DUPLICATE KEY UPDATE viewed_at = NOW()
--     is not even needed - the column trigger does it.
--   * No created_at. The customer only cares about recency,
--     not first-view. Adding created_at would be a column
--     that no code reads.
--   * ON DELETE CASCADE from all three parents. Deleting a
--     customer or product removes the history that
--     referenced it.
--   * The idx_com_rv_customer index is (store_id,
--     customer_id, viewed_at). The primary read is
--     "WHERE store_id = ? AND customer_id = ? ORDER BY
--     viewed_at DESC LIMIT 50". MySQL uses this index
--     efficiently for that query - forward-scan with a
--     reverse read, or backward scan. Both are fast at
--     this scale.
--
-- See docs/stages/008g.2_recently-viewed-plan.md for the full
-- design.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_recently_viewed` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `viewed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_rv_store_customer_product`
    (`store_id`, `customer_id`, `product_id`),
  KEY `idx_com_rv_customer`
    (`store_id`, `customer_id`, `viewed_at`),

  CONSTRAINT `fk_com_rv_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rv_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rv_product`
    FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
