-- ============================================================
-- 0310_create_wishlists.sql
-- Wishlist storage for logged-in customers.
--
-- Guest wishlists live in the customer session
-- ($_SESSION['wishlist']), not in the database. This migration
-- creates the table for the customer-side wishlist only.
--
-- The table is:
--   commerce_wishlists  - one row per (customer, product)
--
-- Design decisions (Stage 008G.1):
--   * One row per (store_id, customer_id, product_id). A
--     customer can wishlist the same product only once.
--     Clicking the heart twice is idempotent.
--   * Products only, not variants. A wishlist is a bookmark,
--     not a purchase intent. The customer does not choose a
--     variant until they are ready to buy. The variant_id
--     column is reserved for a future sub-stage but no code
--     path reads or writes it in 008G.1.
--   * No snapshot fields. The wishlist carries no price or
--     product name. Live product data is the source of truth
--     at render time. The cart carries snapshots because a
--     cart converts to an order; the wishlist never does.
--   * No status enum. No conversion lifecycle. A row either
--     exists or it does not.
--   * No soft delete. A removed wishlist item is gone. The
--     audit trail lives in core_audit_logs, not here.
--   * ON DELETE CASCADE from all three parents. Deleting a
--     customer or product removes the wishlist entries that
--     referenced it.
--   * No updated_at. A wishlist row is insert-once or delete.
--     Nothing updates in place, so the column has no purpose.
--
-- See docs/stages/008g.1_wishlist-plan.md for the full design.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_wishlists` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `product_id`  BIGINT UNSIGNED NOT NULL,
  `variant_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_wl_store_customer_product`
    (`store_id`, `customer_id`, `product_id`),
  KEY `idx_com_wl_customer`
    (`store_id`, `customer_id`, `created_at`),

  CONSTRAINT `fk_com_wl_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_wl_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_wl_product`
    FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
