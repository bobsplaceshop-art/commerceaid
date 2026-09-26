-- ============================================================
-- 0201_create_categories_brands.sql
-- Product categories (hierarchical) and brands.
--
-- Categories support self-referential parent_id for trees.
-- Brands are flat, one per product.
--
-- Both are store-scoped.
-- ============================================================

-- ------------------------------------------------------------
-- Categories - hierarchical tree per store
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_categories` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `parent_id`         BIGINT UNSIGNED NULL,
  `slug`              VARCHAR(120) NOT NULL,
  `name`              VARCHAR(200) NOT NULL,
  `description`       TEXT NULL,
  `image_file_id`     INT UNSIGNED NULL,
  `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `seo_title`         VARCHAR(200) NULL,
  `seo_description`   VARCHAR(300) NULL,
  `seo_keywords`      VARCHAR(300) NULL,
  `status`            ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_cat_store_slug` (`store_id`, `slug`),
  KEY `idx_com_cat_parent` (`parent_id`),
  KEY `idx_com_cat_store_status` (`store_id`, `status`),
  KEY `idx_com_cat_sort` (`store_id`, `parent_id`, `sort_order`),
  CONSTRAINT `fk_com_cat_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `commerce_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_cat_image` FOREIGN KEY (`image_file_id`) REFERENCES `core_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product <-> Category junction
-- A product can belong to many categories; the "primary"
-- category is tracked separately on commerce_products.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_product_categories` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `category_id`   BIGINT UNSIGNED NOT NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_pc_prod_cat` (`product_id`, `category_id`),
  KEY `idx_com_pc_category` (`category_id`),
  KEY `idx_com_pc_store` (`store_id`),
  CONSTRAINT `fk_com_pc_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pc_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pc_category` FOREIGN KEY (`category_id`) REFERENCES `commerce_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Brands - flat, one per product
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_brands` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`          BIGINT UNSIGNED NOT NULL,
  `slug`              VARCHAR(120) NOT NULL,
  `name`              VARCHAR(200) NOT NULL,
  `description`       TEXT NULL,
  `logo_file_id`      INT UNSIGNED NULL,
  `website_url`       VARCHAR(500) NULL,
  `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `seo_title`         VARCHAR(200) NULL,
  `seo_description`   VARCHAR(300) NULL,
  `seo_keywords`      VARCHAR(300) NULL,
  `status`            ENUM('active','archived') NOT NULL DEFAULT 'active',
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`        DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_brand_store_slug` (`store_id`, `slug`),
  KEY `idx_com_brand_store_status` (`store_id`, `status`),
  KEY `idx_com_brand_sort` (`store_id`, `sort_order`),
  CONSTRAINT `fk_com_brand_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_brand_logo` FOREIGN KEY (`logo_file_id`) REFERENCES `core_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Wire the FKs on commerce_products that reference brands and
-- categories. These columns were created in migration 0200 but
-- without FK constraints because the referenced tables didn't
-- exist yet.
-- ------------------------------------------------------------
ALTER TABLE `commerce_products`
  ADD CONSTRAINT `fk_com_prod_brand`
    FOREIGN KEY (`brand_id`) REFERENCES `commerce_brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_com_prod_primary_cat`
    FOREIGN KEY (`primary_category_id`) REFERENCES `commerce_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_com_prod_og_image`
    FOREIGN KEY (`og_image_file_id`) REFERENCES `core_files` (`id`) ON DELETE SET NULL;
