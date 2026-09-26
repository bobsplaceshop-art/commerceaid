-- ============================================================
-- 0100_create_commerce_stores.sql
-- Multi-tenant foundation. Every tenant-scoped commerce table
-- carries a `store_id` FK back to `commerce_stores.id`.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_stores` (
  `id`                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`                      VARCHAR(80)     NOT NULL,
  `name`                      VARCHAR(200)    NOT NULL,
  `legal_name`                VARCHAR(200)    NULL,
  `default_currency_id`       INT UNSIGNED    NULL,
  `locale`                    VARCHAR(10)     NOT NULL DEFAULT 'en_KE',
  `timezone`                  VARCHAR(64)     NOT NULL DEFAULT 'Africa/Nairobi',
  `date_format`               VARCHAR(20)     NOT NULL DEFAULT 'd/m/Y',
  `number_format`             VARCHAR(20)     NOT NULL DEFAULT '1,234.56',
  `weight_unit`               ENUM('kg','lb') NOT NULL DEFAULT 'kg',
  `dimension_unit`            ENUM('cm','in') NOT NULL DEFAULT 'cm',
  `logo_file_id`              BIGINT UNSIGNED NULL,
  `favicon_file_id`           BIGINT UNSIGNED NULL,
  `primary_color`             CHAR(7)         NULL,
  `secondary_color`           CHAR(7)         NULL,
  `tagline`                   VARCHAR(200)    NULL,
  `theme_slug`                VARCHAR(80)     NOT NULL DEFAULT 'modern',
  `order_prefix`              VARCHAR(10)     NOT NULL DEFAULT 'ORD-',
  `invoice_prefix`            VARCHAR(10)     NOT NULL DEFAULT 'INV-',
  `allow_guest_checkout`      TINYINT(1)      NOT NULL DEFAULT 1,
  `require_phone`             TINYINT(1)      NOT NULL DEFAULT 0,
  `require_company`           TINYINT(1)      NOT NULL DEFAULT 0,
  `abandoned_cart_enabled`    TINYINT(1)      NOT NULL DEFAULT 1,
  `abandoned_cart_threshold_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
  `low_stock_threshold_default`      INT UNSIGNED NOT NULL DEFAULT 5,
  `pwa_name`                  VARCHAR(100)    NULL,
  `pwa_short_name`            VARCHAR(50)     NULL,
  `pwa_theme_color`           CHAR(7)         NULL,
  `pwa_background_color`      CHAR(7)         NULL,
  `seo_default_title`         VARCHAR(200)    NULL,
  `seo_default_description`   VARCHAR(300)    NULL,
  `seo_default_og_file_id`    BIGINT UNSIGNED NULL,
  `status`                    ENUM('active','suspended','archived') NOT NULL DEFAULT 'active',
  `created_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`                BIGINT UNSIGNED NULL,
  `updated_by`                BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_store_slug` (`slug`),
  KEY `idx_com_store_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_store_domains` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `hostname`      VARCHAR(255)    NOT NULL,
  `is_primary`    TINYINT(1)      NOT NULL DEFAULT 0,
  `ssl_verified`  TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_sd_hostname` (`hostname`),
  KEY `idx_com_sd_store` (`store_id`),
  CONSTRAINT `fk_com_sd_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_store_users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `user_id`       BIGINT UNSIGNED NOT NULL,
  `is_default`    TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_su_store_user` (`store_id`, `user_id`),
  KEY `idx_com_su_user` (`user_id`),
  CONSTRAINT `fk_com_su_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_store_currencies` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `currency_code`     CHAR(3)         NOT NULL,
  `exchange_rate`     DECIMAL(18,8)   NOT NULL DEFAULT 1.00000000,
  `is_default`        TINYINT(1)      NOT NULL DEFAULT 0,
  `is_enabled`        TINYINT(1)      NOT NULL DEFAULT 1,
  `symbol_position`   ENUM('before','after') NOT NULL DEFAULT 'before',
  `decimal_places`    TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_sc_store_currency` (`store_id`, `currency_code`),
  KEY `idx_com_sc_default` (`store_id`, `is_default`),
  CONSTRAINT `fk_com_sc_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
