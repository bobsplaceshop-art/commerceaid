-- ============================================================
-- 0300_create_carts.sql
-- Cart storage for logged-in customers.
--
-- Guest carts live in the customer session
-- ($_SESSION['cart']), not in the database. This migration
-- creates the tables for the customer-side cart only.
--
-- The two tables are:
--   commerce_carts       - one row per customer per store
--   commerce_cart_items  - one row per product/variant in a cart
--
-- Design decisions (Stage 008D):
--   * One cart per customer per store (UNIQUE on store_id +
--     customer_id). A customer's cart is a singleton resource.
--   * Cart items carry snapshot fields (unit_price,
--     product_name, variant_label, sku) mirroring the same
--     fields on commerce_order_items. The snapshots carry
--     over into the order at checkout. The cart view compares
--     snapshots against live product state and flags drift.
--   * variant_id = 0 sentinel for simple products, matching
--     the convention used by commerce_inventory and
--     commerce_customer_group_prices.
--   * ON DELETE CASCADE from commerce_carts to
--     commerce_cart_items. Deleting a cart (rare - happens at
--     checkout conversion or customer deletion) removes its
--     items.
--   * ON DELETE CASCADE from commerce_customers to
--     commerce_carts. A deleted customer takes their cart
--     with them.
--   * No soft delete on cart items. A removed item is gone.
--     The audit trail lives in orders, not carts.
-- ============================================================

-- ------------------------------------------------------------
-- commerce_carts
-- ------------------------------------------------------------
-- One cart per customer per store. The status column supports
-- the checkout conversion lifecycle: 'active' while shopping,
-- 'converted' once an order is created from it, 'abandoned'
-- if the customer left it and a cleanup process marked it.
--
-- expires_at is nullable and unused in 008D. A future
-- abandoned-cart cleanup job can set it and purge old carts.
-- ============================================================
CREATE TABLE IF NOT EXISTS `commerce_carts` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `status`      ENUM('active','converted','abandoned') NOT NULL DEFAULT 'active',
  `expires_at`  DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cart_store_customer` (`store_id`, `customer_id`),
  KEY `idx_com_cart_status` (`store_id`, `status`),
  KEY `idx_com_cart_expires` (`expires_at`),

  CONSTRAINT `fk_com_cart_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cart_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- commerce_cart_items
-- ------------------------------------------------------------
-- One row per (product, variant) in a cart. Adding the same
-- product twice sums the quantity rather than creating two
-- rows - enforced by uniq_com_ci_cart_product_variant.
--
-- The snapshot fields (unit_price, product_name, variant_label,
-- sku) are set at add time and reflect the values at that
-- moment. They do not track live changes. The cart view
-- compares them against the current product/variant and
-- warns if either has drifted.
--
-- product_id is NOT NULL because the cart always knows which
-- product it references. variant_id defaults to 0 for simple
-- products. Both are BIGINT UNSIGNED to match commerce_products
-- and commerce_product_variants.
-- ============================================================
CREATE TABLE IF NOT EXISTS `commerce_cart_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cart_id`       BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `variant_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `quantity`      INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`    DECIMAL(18,4) NOT NULL DEFAULT 0,
  `product_name`  VARCHAR(255) NOT NULL,
  `variant_label` VARCHAR(255) NULL,
  `sku`           VARCHAR(80) NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_ci_cart_product_variant` (`cart_id`, `product_id`, `variant_id`),
  KEY `idx_com_ci_product` (`product_id`, `variant_id`),

  CONSTRAINT `fk_com_ci_cart`
    FOREIGN KEY (`cart_id`) REFERENCES `commerce_carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
