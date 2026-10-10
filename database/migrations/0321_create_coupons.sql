-- ============================================================
-- 0321_create_coupons.sql
-- Coupons and their redemption ledger.
--
-- Design decisions (Stage 008K.5):
--   * One coupon per order. Stacking is deferred.
--   * Three coupon types: percentage, fixed_amount,
--     free_shipping. The minimum industry set.
--   * applies_to = 'all_products' | 'specific_categories'.
--     Category scope is enforced by discounting only the
--     eligible line subtotals. Product-level scope is
--     deferred.
--   * validity is a DATETIME window. NULL means unbounded.
--     Comparisons use MySQL NOW() per the time discipline.
--   * usage_limit is global. per_customer_limit is per
--     customer_id (logged-in) or guest_email (guest).
--   * min_order_subtotal gates the coupon against the
--     undiscounted subtotal, matching industry convention.
--   * Redemptions are append-only. Never updated, never
--     deleted. Mirrors commerce_inventory_movements. A
--     coupon with redemptions cannot be deleted; it can
--     only be disabled (status = 'disabled').
--   * guest_email is set on redemptions from guest orders
--     so the per_customer_limit can be enforced by email.
--   * customer_id and guest_email are both nullable - a
--     guest order has no customer_id, and a logged-in
--     customer has no guest_email.
--
-- See docs/stages/008k.5_order-pricing-engine-plan.md for
-- the full design.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_coupons` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`            BIGINT UNSIGNED NOT NULL,
  `code`                VARCHAR(60) NOT NULL,
  `description`         VARCHAR(255) NULL,
  `type`                ENUM('percentage','fixed_amount','free_shipping')
                        NOT NULL,
  `value`               DECIMAL(18,4) NOT NULL DEFAULT 0,
  `valid_from`          DATETIME NULL,
  `valid_to`            DATETIME NULL,
  `min_order_subtotal`  DECIMAL(18,4) NULL,
  `usage_limit`         INT UNSIGNED NULL,
  `per_customer_limit`  INT UNSIGNED NULL,
  `applies_to`          ENUM('all_products','specific_categories')
                        NOT NULL DEFAULT 'all_products',
  `applies_to_ids_json` JSON NULL,
  `status`              ENUM('active','disabled')
                        NOT NULL DEFAULT 'active',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                        ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_coupons_store_code` (`store_id`, `code`),
  KEY `idx_com_coupons_store_status` (`store_id`, `status`, `created_at`),

  CONSTRAINT `fk_com_coupons_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_coupon_redemptions` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`     BIGINT UNSIGNED NOT NULL,
  `coupon_id`    BIGINT UNSIGNED NOT NULL,
  `customer_id`  BIGINT UNSIGNED NULL,
  `guest_email`  VARCHAR(190) NULL,
  `order_id`     BIGINT UNSIGNED NOT NULL,
  `amount`       DECIMAL(18,4) NOT NULL,
  `redeemed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_cr_coupon` (`coupon_id`, `redeemed_at`),
  KEY `idx_com_cr_customer` (`customer_id`, `coupon_id`),
  KEY `idx_com_cr_email` (`guest_email`, `coupon_id`),
  KEY `idx_com_cr_order` (`order_id`),

  CONSTRAINT `fk_com_cr_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_cr_coupon`
    FOREIGN KEY (`coupon_id`) REFERENCES `commerce_coupons` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_cr_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
