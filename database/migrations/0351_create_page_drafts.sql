CREATE TABLE `commerce_page_drafts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`     BIGINT UNSIGNED NOT NULL,
  `theme_slug`   VARCHAR(80) NOT NULL,
  `page_slug`    VARCHAR(120) NOT NULL,
  `block_id`     BIGINT UNSIGNED NULL, -- NULL for pending additions
  `block_type`   VARCHAR(40) NOT NULL,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `config_json`  JSON NOT NULL,
  `status`       ENUM('pending_add', 'pending_update', 'pending_delete') NOT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_pd_store_page_theme` (`store_id`, `page_slug`, `theme_slug`),
  KEY `idx_com_pd_block_id` (`block_id`),
  CONSTRAINT `fk_com_pd_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
