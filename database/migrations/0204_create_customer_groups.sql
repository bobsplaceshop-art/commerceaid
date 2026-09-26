-- ============================================================
-- 0204_create_customer_groups.sql
-- B2B foundation: customer groups and their price lists.
--
-- Customer groups categorize buyers (Retail, Wholesale, VIP,
-- Staff). Each group can carry its own price list. Price lists
-- support tier pricing (buy 10+ at X, 50+ at Y).
--
-- Also adds min_order_quantity to products.
--
-- Design decisions (Stage 007A):
--   * One group per customer (industry standard)
--   * Price lists per group
--   * Tier pricing lives in the price list, not the product
--   * MOQ is per product
--   * variant_id = 0 sentinel for simple products
--   * Multiple tiers allowed per (group, product, variant)
-- ============================================================

-- ------------------------------------------------------------
-- Customer groups
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_customer_groups` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `slug`              VARCHAR(80) NOT NULL,
  `name`              VARCHAR(120) NOT NULL,
  `description`       VARCHAR(500) NULL,
  `is_default`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_b2b`            TINYINT(1) NOT NULL DEFAULT 0,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `status`            ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cg_store_slug` (`store_id`, `slug`),
  KEY `idx_com_cg_store_status` (`store_id`, `status`),
  KEY `idx_com_cg_default` (`store_id`, `is_default`),
  KEY `idx_com_cg_sort` (`store_id`, `sort_order`),
  CONSTRAINT `fk_com_cg_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Customer group price overrides with optional tiering
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_customer_group_prices` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `customer_group_id` BIGINT UNSIGNED NOT NULL,
  `product_id`        BIGINT UNSIGNED NOT NULL,
  `variant_id`        BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `price`             DECIMAL(18,4) NULL,
  `compare_at_price`  DECIMAL(18,4) NULL,
  `min_quantity`      INT UNSIGNED NOT NULL DEFAULT 1,
  `max_quantity`      INT UNSIGNED NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cgp_group_prod_var_min` (`customer_group_id`, `product_id`, `variant_id`, `min_quantity`),
  KEY `idx_com_cgp_group` (`customer_group_id`),
  KEY `idx_com_cgp_product` (`product_id`, `variant_id`),
  KEY `idx_com_cgp_store` (`store_id`),
  CONSTRAINT `fk_com_cgp_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cgp_group`
    FOREIGN KEY (`customer_group_id`) REFERENCES `commerce_customer_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cgp_product`
    FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Add min_order_quantity to products
-- ------------------------------------------------------------
ALTER TABLE `commerce_products`
  ADD COLUMN `min_order_quantity` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `low_stock_threshold`;
