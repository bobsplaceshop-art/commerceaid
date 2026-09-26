-- ============================================================
-- 0203_create_category_attributes.sql
-- Junction: which attributes apply to products in each category.
--
-- Direction: Category -> Attribute. A category DECLARES the
-- attributes relevant to its products. Attributes themselves are
-- global vocabulary (store-independent). Multiple categories can
-- reference the same attribute.
--
-- Deleting a category cascades its mapping rows only. The
-- attribute definition survives.
--
-- Constraint: attribute definitions cannot be deleted while any
-- category still references them (RESTRICT). The application
-- layer must remove mappings first, or the delete fails.
-- ============================================================

CREATE TABLE IF NOT EXISTS `commerce_category_attributes` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `category_id`   BIGINT UNSIGNED NOT NULL,
  `attribute_id`  INT UNSIGNED NOT NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_catattr_store_cat_attr` (`store_id`, `category_id`, `attribute_id`),
  KEY `idx_com_catattr_category` (`category_id`),
  KEY `idx_com_catattr_attribute` (`attribute_id`),
  KEY `idx_com_catattr_store` (`store_id`),
  CONSTRAINT `fk_com_catattr_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_catattr_category`
    FOREIGN KEY (`category_id`) REFERENCES `commerce_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_com_catattr_attribute`
    FOREIGN KEY (`attribute_id`) REFERENCES `commerce_attribute_definitions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
