-- ============================================================
-- 0205_customer_and_order_tables.sql
-- Customer and order foundation for Commerce Aid.
--
-- Creates:
--   commerce_customers
--   commerce_customer_addresses
--   commerce_customer_password_resets
--   commerce_customer_sessions
--   commerce_orders
--   commerce_order_items
--   commerce_order_status_history
--   commerce_order_notes
--   commerce_order_taxes
--   commerce_order_shipments
--
-- Also adds default_net_days to commerce_customer_groups.
--
-- Design notes:
--   * Customers are SEPARATE from core_users (admin users). Different
--     lifecycle, different login endpoint, different session namespace.
--   * Orders store address snapshots (denormalized) because addresses
--     at order time are legal record and must never change retroactively.
--   * Order items store product_name, variant_label, sku snapshots
--     because products may be renamed or deleted later.
--   * variant_id = 0 sentinel for simple products.
--   * Guest orders use guest_email + NULL customer_id.
--   * Approval columns on customers support the B2B signup gate.
--   * PO columns on orders support net-terms B2B checkout.
-- ============================================================

-- ============================================================
-- CUSTOMERS
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_customers` (
  `id`                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`                    BIGINT UNSIGNED NOT NULL,
  `email`                       VARCHAR(190) NOT NULL,
  `password_hash`               VARCHAR(255) NULL,
  `first_name`                  VARCHAR(100) NULL,
  `last_name`                   VARCHAR(100) NULL,
  `phone`                       VARCHAR(50) NULL,
  `company`                     VARCHAR(200) NULL,
  `tax_id`                      VARCHAR(50) NULL,
  `date_of_birth`               DATE NULL,
  `email_verified_at`           DATETIME NULL,
  `accepts_marketing`           TINYINT(1) NOT NULL DEFAULT 0,
  `customer_group_id`           BIGINT UNSIGNED NULL,
  `default_billing_address_id`  BIGINT UNSIGNED NULL,
  `default_shipping_address_id` BIGINT UNSIGNED NULL,
  `status`                      ENUM('active','disabled','archived') NOT NULL DEFAULT 'active',
  `approval_status`             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `approval_reason`             VARCHAR(500) NULL,
  `approved_at`                 DATETIME NULL,
  `approved_by`                 INT UNSIGNED NULL,
  `rejected_at`                 DATETIME NULL,
  `last_login_at`               DATETIME NULL,
  `created_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cust_store_email` (`store_id`, `email`),
  KEY `idx_com_cust_store_status` (`store_id`, `status`),
  KEY `idx_com_cust_store_approval` (`store_id`, `approval_status`),
  KEY `idx_com_cust_group` (`customer_group_id`),
  CONSTRAINT `fk_com_cust_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cust_group`
    FOREIGN KEY (`customer_group_id`) REFERENCES `commerce_customer_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_customer_addresses` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`       BIGINT UNSIGNED NOT NULL,
  `customer_id`    BIGINT UNSIGNED NOT NULL,
  `label`          VARCHAR(60) NULL,
  `type`           ENUM('billing','shipping','both') NOT NULL DEFAULT 'both',
  `first_name`     VARCHAR(100) NULL,
  `last_name`      VARCHAR(100) NULL,
  `company`        VARCHAR(200) NULL,
  `address_line_1` VARCHAR(255) NOT NULL,
  `address_line_2` VARCHAR(255) NULL,
  `city`           VARCHAR(120) NOT NULL,
  `state`          VARCHAR(120) NULL,
  `postal_code`    VARCHAR(20) NULL,
  `country_code`   CHAR(2) NOT NULL,
  `phone`          VARCHAR(50) NULL,
  `email`          VARCHAR(190) NULL,
  `is_default`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_caddr_customer` (`customer_id`),
  KEY `idx_com_caddr_country` (`store_id`, `country_code`),
  CONSTRAINT `fk_com_caddr_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_caddr_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_customer_password_resets` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,
  `token_hash`  CHAR(64) NOT NULL,
  `expires_at`  DATETIME NOT NULL,
  `used_at`     DATETIME NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cpr_token` (`token_hash`),
  KEY `idx_com_cpr_customer` (`customer_id`, `expires_at`),
  CONSTRAINT `fk_com_cpr_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_customer_sessions` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`   BIGINT UNSIGNED NOT NULL,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `session_id`    VARCHAR(128) NOT NULL,
  `ip`            VARBINARY(16) NULL,
  `user_agent`    VARCHAR(500) NULL,
  `last_seen_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cs_session` (`session_id`),
  KEY `idx_com_cs_customer` (`customer_id`, `last_seen_at`),
  CONSTRAINT `fk_com_cs_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cs_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ORDERS
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_orders` (
  `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`                 BIGINT UNSIGNED NOT NULL,
  `order_number`             VARCHAR(40) NOT NULL,
  `customer_id`              BIGINT UNSIGNED NULL,
  `guest_email`              VARCHAR(190) NULL,
  `currency_code`            CHAR(3) NOT NULL,
  `exchange_rate`            DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,

  `status`                   ENUM('pending_payment','paid','processing','packed','shipped','delivered','completed','cancelled','failed','refunded','partially_refunded','on_hold') NOT NULL DEFAULT 'pending_payment',
  `payment_status`           ENUM('unpaid','paid','partially_refunded','refunded','failed') NOT NULL DEFAULT 'unpaid',
  `fulfilment_status`        ENUM('unfulfilled','partially_fulfilled','fulfilled','returned') NOT NULL DEFAULT 'unfulfilled',

  `subtotal`                 DECIMAL(18,4) NOT NULL DEFAULT 0,
  `discount_total`           DECIMAL(18,4) NOT NULL DEFAULT 0,
  `tax_total`                DECIMAL(18,4) NOT NULL DEFAULT 0,
  `shipping_total`           DECIMAL(18,4) NOT NULL DEFAULT 0,
  `grand_total`              DECIMAL(18,4) NOT NULL DEFAULT 0,
  `paid_total`               DECIMAL(18,4) NOT NULL DEFAULT 0,
  `refunded_total`           DECIMAL(18,4) NOT NULL DEFAULT 0,

  `customer_note`            TEXT NULL,
  `internal_note`            TEXT NULL,

  `billing_address_json`     JSON NULL,
  `shipping_address_json`    JSON NULL,

  `shipping_method_key`      VARCHAR(60) NULL,
  `shipping_method_label`    VARCHAR(120) NULL,

  `payment_provider_key`     VARCHAR(50) NULL,
  `payment_reference`        VARCHAR(120) NULL,
  `po_number`                VARCHAR(60) NULL,
  `net_due_at`               DATETIME NULL,

  `coupon_code`              VARCHAR(60) NULL,
  `ip`                       VARBINARY(16) NULL,
  `user_agent`               VARCHAR(500) NULL,

  `placed_at`                DATETIME NULL,
  `paid_at`                  DATETIME NULL,
  `completed_at`             DATETIME NULL,
  `cancelled_at`             DATETIME NULL,
  `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_order_store_number` (`store_id`, `order_number`),
  KEY `idx_com_order_store_status` (`store_id`, `status`, `created_at`),
  KEY `idx_com_order_customer` (`customer_id`, `created_at`),
  KEY `idx_com_order_guest_email` (`store_id`, `guest_email`),
  KEY `idx_com_order_payment` (`payment_provider_key`, `payment_reference`),
  KEY `idx_com_order_po` (`store_id`, `po_number`),
  KEY `idx_com_order_net_due` (`store_id`, `net_due_at`),
  CONSTRAINT `fk_com_order_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_order_customer`
    FOREIGN KEY (`customer_id`) REFERENCES `commerce_customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_order_items` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`            BIGINT UNSIGNED NOT NULL,
  `order_id`            BIGINT UNSIGNED NOT NULL,
  `product_id`          BIGINT UNSIGNED NULL,
  `variant_id`          BIGINT UNSIGNED NULL,
  `product_name`        VARCHAR(255) NOT NULL,
  `variant_label`       VARCHAR(255) NULL,
  `sku`                 VARCHAR(80) NULL,
  `quantity`            INT UNSIGNED NOT NULL,
  `unit_price`          DECIMAL(18,4) NOT NULL,
  `line_subtotal`       DECIMAL(18,4) NOT NULL,
  `line_discount`       DECIMAL(18,4) NOT NULL DEFAULT 0,
  `line_tax`            DECIMAL(18,4) NOT NULL DEFAULT 0,
  `line_total`          DECIMAL(18,4) NOT NULL,
  `tax_class_id`        BIGINT UNSIGNED NULL,
  `tax_rate_percent`    DECIMAL(6,3) NULL,
  `fulfilment_status`   ENUM('unfulfilled','fulfilled','returned') NOT NULL DEFAULT 'unfulfilled',
  `fulfilled_quantity`  INT UNSIGNED NOT NULL DEFAULT 0,
  `returned_quantity`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_oi_order` (`order_id`),
  KEY `idx_com_oi_product` (`product_id`, `variant_id`),
  CONSTRAINT `fk_com_oi_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_oi_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_oi_product`
    FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_com_oi_variant`
    FOREIGN KEY (`variant_id`) REFERENCES `commerce_product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_order_status_history` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`    BIGINT UNSIGNED NOT NULL,
  `order_id`    BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(40) NULL,
  `to_status`   VARCHAR(40) NOT NULL,
  `changed_by`  INT UNSIGNED NULL,
  `note`        VARCHAR(500) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_osh_order` (`order_id`, `created_at`),
  CONSTRAINT `fk_com_osh_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_osh_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_osh_user`
    FOREIGN KEY (`changed_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_order_notes` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`             BIGINT UNSIGNED NOT NULL,
  `order_id`             BIGINT UNSIGNED NOT NULL,
  `note`                 TEXT NOT NULL,
  `is_customer_visible`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`           INT UNSIGNED NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_on_order` (`order_id`, `created_at`),
  CONSTRAINT `fk_com_on_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_on_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_on_user`
    FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_order_taxes` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`       BIGINT UNSIGNED NOT NULL,
  `order_id`       BIGINT UNSIGNED NOT NULL,
  `tax_class_id`   BIGINT UNSIGNED NULL,
  `tax_rate_id`    BIGINT UNSIGNED NULL,
  `label`          VARCHAR(120) NOT NULL,
  `rate_percent`   DECIMAL(6,3) NOT NULL,
  `amount`         DECIMAL(18,4) NOT NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_otax_order` (`order_id`),
  CONSTRAINT `fk_com_otax_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_otax_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_order_shipments` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`        BIGINT UNSIGNED NOT NULL,
  `order_id`        BIGINT UNSIGNED NOT NULL,
  `carrier`         VARCHAR(80) NULL,
  `tracking_number` VARCHAR(120) NULL,
  `tracking_url`    VARCHAR(500) NULL,
  `shipped_at`      DATETIME NULL,
  `delivered_at`    DATETIME NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_oship_order` (`order_id`),
  CONSTRAINT `fk_com_oship_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_oship_order`
    FOREIGN KEY (`order_id`) REFERENCES `commerce_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- NET TERMS ON CUSTOMER GROUPS
-- ============================================================

ALTER TABLE `commerce_customer_groups`
  ADD COLUMN `default_net_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `requires_approval`;
