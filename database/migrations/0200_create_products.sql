-- ============================================================
-- 0200_create_products.sql
-- Product catalog: products, variants, images, and junction
-- tables for categories/tags/attributes.
--
-- All tenant-scoped tables carry store_id NOT NULL with FK to
-- commerce_stores. Composite unique keys are per-store.
--
-- Design decisions (Stage 006A):
--   * status (draft/published/archived) AND deleted_at (soft)
--   * SKU unique per store (composite)
--   * slug unique per store (composite)
--   * variant_id = 0 sentinel for simple products in inventory
--   * variant_id NULL in junction tables (semantic: "product-level")
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_products` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`               BIGINT UNSIGNED NOT NULL,
  `slug`                   VARCHAR(200) NOT NULL,
  `sku`                    VARCHAR(80) NULL,
  `name`                   VARCHAR(255) NOT NULL,
  `short_description`      VARCHAR(500) NULL,
  `description`            MEDIUMTEXT NULL,
  `brand_id`               BIGINT UNSIGNED NULL,
  `primary_category_id`    BIGINT UNSIGNED NULL,
  `product_type`           ENUM('simple','variable') NOT NULL DEFAULT 'simple',
  `price`                  DECIMAL(18,4) NOT NULL DEFAULT 0,
  `compare_at_price`       DECIMAL(18,4) NULL,
  `cost_price`             DECIMAL(18,4) NULL,
  `tax_class_id`           BIGINT UNSIGNED NULL,
  `tax_status`             ENUM('taxable','none') NOT NULL DEFAULT 'taxable',
  `is_featured`            TINYINT(1) NOT NULL DEFAULT 0,
  `is_new`                 TINYINT(1) NOT NULL DEFAULT 0,
  `weight`                 DECIMAL(10,3) NULL,
  `length`                 DECIMAL(10,2) NULL,
  `width`                  DECIMAL(10,2) NULL,
  `height`                 DECIMAL(10,2) NULL,
  `requires_shipping`      TINYINT(1) NOT NULL DEFAULT 1,
  `stock_status`           ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
  `manage_stock`           TINYINT(1) NOT NULL DEFAULT 1,
  `backorders_allowed`     TINYINT(1) NOT NULL DEFAULT 0,
  `low_stock_threshold`    INT UNSIGNED NULL,
  `seo_title`              VARCHAR(200) NULL,
  `seo_description`        VARCHAR(300) NULL,
  `seo_keywords`           VARCHAR(300) NULL,
  `og_image_file_id`       INT UNSIGNED NULL,
  `status`                 ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at`           DATETIME NULL,
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by`             INT UNSIGNED NULL,
  `updated_by`             INT UNSIGNED NULL,
  `deleted_at`             DATETIME NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_prod_store_slug` (`store_id`, `slug`),
  UNIQUE KEY `uniq_com_prod_store_sku` (`store_id`, `sku`),
  KEY `idx_com_prod_store_status` (`store_id`, `status`),
  KEY `idx_com_prod_store_brand` (`store_id`, `brand_id`),
  KEY `idx_com_prod_store_cat` (`store_id`, `primary_category_id`),
  KEY `idx_com_prod_store_featured` (`store_id`, `is_featured`, `status`),
  KEY `idx_com_prod_published` (`store_id`, `published_at`),
  KEY `idx_com_prod_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_com_prod_search` (`name`, `short_description`, `description`),
  CONSTRAINT `fk_com_prod_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product variants (for variable products)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_product_variants` (
  `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`               BIGINT UNSIGNED NOT NULL,
  `product_id`             BIGINT UNSIGNED NOT NULL,
  `sku`                    VARCHAR(80) NULL,
  `barcode`                VARCHAR(80) NULL,
  `price`                  DECIMAL(18,4) NOT NULL,
  `compare_at_price`       DECIMAL(18,4) NULL,
  `cost_price`             DECIMAL(18,4) NULL,
  `weight`                 DECIMAL(10,3) NULL,
  `length`                 DECIMAL(10,2) NULL,
  `width`                  DECIMAL(10,2) NULL,
  `height`                 DECIMAL(10,2) NULL,
  `image_file_id`          INT UNSIGNED NULL,
  `stock_status`           ENUM('in_stock','out_of_stock','backorder') NOT NULL DEFAULT 'in_stock',
  `manage_stock`           TINYINT(1) NOT NULL DEFAULT 1,
  `backorders_allowed`     TINYINT(1) NOT NULL DEFAULT 0,
  `low_stock_threshold`    INT UNSIGNED NULL,
  `is_default`             TINYINT(1) NOT NULL DEFAULT 0,
  `status`                 ENUM('active','archived') NOT NULL DEFAULT 'active',
  `sort_order`             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_var_store_sku` (`store_id`, `sku`),
  KEY `idx_com_var_product` (`product_id`),
  KEY `idx_com_var_barcode` (`store_id`, `barcode`),
  KEY `idx_com_var_status` (`store_id`, `status`),
  KEY `idx_com_var_default` (`product_id`, `is_default`),
  CONSTRAINT `fk_com_var_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_var_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product images
-- variant_id NULL = product-level image; NOT NULL = variant image
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_product_images` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `variant_id`    BIGINT UNSIGNED NULL,
  `file_id`       INT UNSIGNED NOT NULL,
  `alt_text`      VARCHAR(255) NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_primary`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_pi_product` (`product_id`, `sort_order`),
  KEY `idx_com_pi_variant` (`variant_id`, `sort_order`),
  KEY `idx_com_pi_primary` (`product_id`, `is_primary`),
  CONSTRAINT `fk_com_pi_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pi_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pi_variant` FOREIGN KEY (`variant_id`) REFERENCES `commerce_product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pi_file` FOREIGN KEY (`file_id`) REFERENCES `core_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product videos (external URLs — no file upload)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_product_videos` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `url`           VARCHAR(500) NOT NULL,
  `thumbnail_file_id` INT UNSIGNED NULL,
  `title`         VARCHAR(200) NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_com_pv_product` (`product_id`, `sort_order`),
  CONSTRAINT `fk_com_pv_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pv_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pv_thumb` FOREIGN KEY (`thumbnail_file_id`) REFERENCES `core_files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Product tags (store-scoped, reusable across products)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_product_tags` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `slug`          VARCHAR(80) NOT NULL,
  `name`          VARCHAR(120) NOT NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_tag_store_slug` (`store_id`, `slug`),
  CONSTRAINT `fk_com_tag_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_product_tag_links` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `product_id`    BIGINT UNSIGNED NOT NULL,
  `tag_id`        BIGINT UNSIGNED NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_ptl_prod_tag` (`product_id`, `tag_id`),
  KEY `idx_com_ptl_tag` (`tag_id`),
  CONSTRAINT `fk_com_ptl_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_ptl_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_ptl_tag` FOREIGN KEY (`tag_id`) REFERENCES `commerce_product_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Attribute vocabulary (store-independent)
-- Color, Size, Material are shared across all stores
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_attribute_definitions` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`              VARCHAR(60) NOT NULL,
  `name`              VARCHAR(100) NOT NULL,
  `input_type`        ENUM('select','color','text','number') NOT NULL DEFAULT 'select',
  `is_variant_axis`   TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_attr_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `commerce_attribute_values` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attribute_id`  INT UNSIGNED NOT NULL,
  `slug`          VARCHAR(80) NOT NULL,
  `value`         VARCHAR(120) NOT NULL,
  `color_hex`     CHAR(7) NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_av_attr_slug` (`attribute_id`, `slug`),
  CONSTRAINT `fk_com_av_attr` FOREIGN KEY (`attribute_id`) REFERENCES `commerce_attribute_definitions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Which attribute values a product exposes (before variant generation)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_product_attribute_values` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`              BIGINT UNSIGNED NOT NULL,
  `product_id`            BIGINT UNSIGNED NOT NULL,
  `attribute_id`          INT UNSIGNED NOT NULL,
  `attribute_value_id`    INT UNSIGNED NOT NULL,
  `sort_order`            SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_pav_prod_attr_value` (`product_id`, `attribute_value_id`),
  KEY `idx_com_pav_attr_value` (`attribute_value_id`),
  CONSTRAINT `fk_com_pav_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pav_product` FOREIGN KEY (`product_id`) REFERENCES `commerce_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pav_attr` FOREIGN KEY (`attribute_id`) REFERENCES `commerce_attribute_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_pav_attr_value` FOREIGN KEY (`attribute_value_id`) REFERENCES `commerce_attribute_values` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Which attribute value combination defines each variant
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_variant_attribute_values` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`              BIGINT UNSIGNED NOT NULL,
  `variant_id`            BIGINT UNSIGNED NOT NULL,
  `attribute_id`          INT UNSIGNED NOT NULL,
  `attribute_value_id`    INT UNSIGNED NOT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_vav_variant_attr` (`variant_id`, `attribute_id`),
  KEY `idx_com_vav_value` (`attribute_value_id`),
  CONSTRAINT `fk_com_vav_store` FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_vav_variant` FOREIGN KEY (`variant_id`) REFERENCES `commerce_product_variants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_vav_attr` FOREIGN KEY (`attribute_id`) REFERENCES `commerce_attribute_definitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_vav_attr_value` FOREIGN KEY (`attribute_value_id`) REFERENCES `commerce_attribute_values` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
