-- =====================================================================
-- Navigation builder: menus, columns, items, and structured contact.
-- =====================================================================
-- Three admin surfaces share this data:
--   - Header builder  -> menu at location='header', no columns, items
--                        nest via parent_id.
--   - Footer builder  -> menu at location='footer', items grouped by
--                        column_id into headed columns.
--   - Menu builder    -> any menu at location='custom'.
--
-- Items can point at a resource (category/product/page/legal_page)
-- via resource_type + resource_id, or at a literal URL via url.
-- Exactly one of the two is set. The storefront resolves the
-- resource to an href at render time.
--
-- Contact details are store data, not navigation. They live in
-- their own table so the footer builder can render them as a
-- sibling of the navigation columns.
-- =====================================================================

CREATE TABLE `commerce_navigation_menus` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`   BIGINT UNSIGNED NOT NULL,
  `name`       VARCHAR(120) NOT NULL,
  `slug`       VARCHAR(120) NOT NULL,
  `location`   ENUM('header','footer','custom') NOT NULL DEFAULT 'custom',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nav_menu_store_slug` (`store_id`, `slug`),
  KEY `idx_nav_menu_store_location` (`store_id`, `location`, `is_active`),
  CONSTRAINT `fk_nav_menu_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `commerce_navigation_columns` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id`    BIGINT UNSIGNED NOT NULL,
  `column_key` VARCHAR(50) NOT NULL,
  `heading`    VARCHAR(120) NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_nav_col_menu_key` (`menu_id`, `column_key`),
  KEY `idx_nav_col_menu_sort` (`menu_id`, `sort_order`),
  CONSTRAINT `fk_nav_col_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `commerce_navigation_menus` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `commerce_navigation_items` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id`       BIGINT UNSIGNED NOT NULL,
  `column_id`     BIGINT UNSIGNED NULL,
  `parent_id`     BIGINT UNSIGNED NULL,
  `label`         VARCHAR(190) NOT NULL,
  `url`           VARCHAR(500) NULL,
  `resource_type` ENUM('category','product','page','legal_page') NULL,
  `resource_id`   BIGINT UNSIGNED NULL,
  `sort_order`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `is_highlight`  TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_nav_item_menu_sort`     (`menu_id`, `sort_order`),
  KEY `idx_nav_item_menu_col_sort` (`menu_id`, `column_id`, `sort_order`),
  KEY `idx_nav_item_parent`        (`parent_id`),
  KEY `idx_nav_item_resource`      (`resource_type`, `resource_id`),
  CONSTRAINT `fk_nav_item_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `commerce_navigation_menus` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_nav_item_col`
    FOREIGN KEY (`column_id`) REFERENCES `commerce_navigation_columns` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_nav_item_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `commerce_navigation_items` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `commerce_store_contact_details` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`   BIGINT UNSIGNED NOT NULL,
  `type`       ENUM('email','phone','address','custom') NOT NULL DEFAULT 'custom',
  `label`      VARCHAR(120) NOT NULL,
  `value`      VARCHAR(500) NOT NULL,
  `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_contact_store_sort` (`store_id`, `sort_order`),
  CONSTRAINT `fk_contact_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
