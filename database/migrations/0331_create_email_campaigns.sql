-- ============================================================
-- 0331_create_email_campaigns.sql
--
-- Newsletter campaigns. Stage 008I.
--
-- A campaign targets one subscriber list. Status moves
-- draft -> sending -> sent. 'scheduled' is reserved for
-- a future cron-based scheduler; drafts can be given a
-- scheduled_at and left in 'draft' until the send action
-- fires.
--
-- body_html is MEDIUMTEXT because a rich campaign can
-- exceed TEXT's 64 KB limit. body_text is optional; when
-- null, EmailService::htmlToText() derives it.
--
-- sent_count and failed_count are denormalised counters
-- updated by the queue worker as it drains
-- commerce_email_queue.
--
-- See docs/stages/008i_newsletter-plan.md.
-- ============================================================

CREATE TABLE `commerce_email_campaigns` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`      BIGINT UNSIGNED NOT NULL,
  `list_id`       BIGINT UNSIGNED NOT NULL,
  `subject`       VARCHAR(255) NOT NULL,
  `preheader`     VARCHAR(255) NULL,
  `body_html`     MEDIUMTEXT NOT NULL,
  `body_text`     MEDIUMTEXT NULL,
  `status`        ENUM('draft','scheduled','sending','sent','cancelled')
                  NOT NULL DEFAULT 'draft',
  `scheduled_at`  DATETIME NULL,
  `sent_at`       DATETIME NULL,
  `sent_count`    INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count`  INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by`    INT UNSIGNED NULL,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_com_camp_store_status` (`store_id`, `status`),
  CONSTRAINT `fk_com_camp_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_camp_list`
    FOREIGN KEY (`list_id`) REFERENCES `commerce_subscriber_lists` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_camp_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
