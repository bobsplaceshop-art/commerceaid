-- ============================================================
-- 0328_create_subscribers.sql
--
-- Newsletter subscribers. Stage 008I.
--
-- One row per email address per store. The consent
-- columns record the double opt-in evidence required
-- by DSGVO Art. 7(1) and German UWG § 7.
--
-- status lifecycle:
--   pending       - address captured, confirmation sent
--   confirmed     - opt-in confirmed, receives campaigns
--   unsubscribed  - user chose to stop
--   suppressed    - system suppressed (hard bounce or
--                   3 consecutive soft bounces)
--
-- 'unsubscribed' is the user's choice.
-- 'suppressed' is the system's. Same end state, different
-- audit meaning.
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

CREATE TABLE `commerce_subscribers` (
  `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`             BIGINT UNSIGNED NOT NULL,
  `email`                VARCHAR(190) NOT NULL,
  `name`                 VARCHAR(120) NULL,
  `status`               ENUM('pending','confirmed','unsubscribed','suppressed')
                         NOT NULL DEFAULT 'pending',
  `consent_source`       VARCHAR(60) NULL,
  `consent_ip`           VARCHAR(45) NULL,
  `consent_user_agent`   VARCHAR(255) NULL,
  `consent_at`           DATETIME NULL,
  `confirmation_token`   VARCHAR(255) NULL,
  `confirmation_sent_at` DATETIME NULL,
  `confirmed_at`         DATETIME NULL,
  `unsubscribed_at`      DATETIME NULL,
  `unsubscribe_reason`   VARCHAR(255) NULL,
  `soft_bounce_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                         ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_subs_store_email` (`store_id`, `email`),
  KEY `idx_com_subs_store_status` (`store_id`, `status`),
  CONSTRAINT `fk_com_subs_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
