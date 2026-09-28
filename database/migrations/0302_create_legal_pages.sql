-- =====================================================================
-- Stage 008E - Legal pages
-- =====================================================================
--
-- Configurable legal pages (Impressum, AGB, Widerrufsbelehrung,
-- Datenschutzerklaerung) for the storefront. Required for German
-- e-commerce compliance (DDG § 5, BGB § 312i, EGBGB Art. 246a).
--
-- Body is MEDIUMTEXT (up to 16 MB) holding HTML rendered as-is
-- in the storefront legal view. No markdown parsing. The admin
-- edits HTML directly (or through a future rich text editor).
--
-- Slugs are per-store unique. A store can have its own Impressum
-- even if another store shares the same platform.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `commerce_legal_pages` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`     BIGINT UNSIGNED NOT NULL,
  `slug`         VARCHAR(60) NOT NULL,
  `title`        VARCHAR(190) NOT NULL,
  `body`         MEDIUMTEXT NOT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by`   INT UNSIGNED NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_lp_store_slug` (`store_id`, `slug`),
  KEY `idx_com_lp_store_published` (`store_id`, `is_published`),

  CONSTRAINT `fk_com_lp_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`) ON DELETE CASCADE,

  CONSTRAINT `fk_com_lp_user`
    FOREIGN KEY (`updated_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
