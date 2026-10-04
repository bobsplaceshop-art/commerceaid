-- ============================================================
-- 0319_create_product_reviews.sql
-- Product reviews for the storefront.
--
-- Design decisions (Stage 008G.5):
--   * One review per (store_id, customer_id, product_id).
--     The unique key enforces it. A customer cannot review
--     the same product twice; the form detects an existing
--     review and shows it in edit mode.
--   * Any logged-in customer can submit. No purchase
--     required. Reviews from customers with a delivered or
--     completed order containing the product are marked
--     verified_purchase = 1.
--   * Ratings are 1-5. TINYINT UNSIGNED. Range checked at
--     the application layer; MySQL does not enforce it.
--   * Title is optional. Body is required.
--   * Moderation: default status is 'pending'. Only approved
--     reviews render on the storefront. Admin approves or
--     rejects.
--   * No photos in v1. Deferred to a future sub-stage.
--   * order_id records the order that qualified the review
--     as verified, when one exists. Nullable. ON DELETE SET
--     NULL so the review survives an order purge.
--   * customer_id is nullable so a DSGVO Art. 17 erasure
--     can anonymize a review without deleting it — the
--     rating remains valid for the product's aggregate.
--     When customer_id is NULL, the rendering layer shows
--     "Anonymous" as the author.
--   * No soft delete. A rejected or deleted review is
--     removed. The audit trail lives in core_audit_logs.
--
-- See docs/stages/008g.5_reviews-plan.md for the full
-- design.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_product_reviews` (
  `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`           BIGINT UNSIGNED NOT NULL,
  `product_id`         BIGINT UNSIGNED NOT NULL,
  `customer_id`        BIGINT UNSIGNED NULL,
  `order_id`           BIGINT UNSIGNED NULL,
  `rating`             TINYINT UNSIGNED NOT NULL,
  `title`              VARCHAR(150) NULL,
  `body`               TEXT NOT NULL,
  `verified_purchase`  TINYINT(1) NOT NULL DEFAULT 0,
  `status`             ENUM('pending','approved','rejected')
                       NOT NULL DEFAULT 'pending',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_rev_store_customer_product`
    (`store_id`, `customer_id`, `product_id`),
  KEY `idx_com_rev_store_product_status`
    (`store_id`, `product_id`, `status`, `created_at`),
  KEY `idx_com_rev_store_status_created`
    (`store_id`, `status`, `created_at`),
  KEY `idx_com_rev_product_rating`
    (`store_id`, `product_id`, `status`, `rating`),

  CONSTRAINT `fk_com_rev_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rev_product`
    FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rev_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_rev_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
