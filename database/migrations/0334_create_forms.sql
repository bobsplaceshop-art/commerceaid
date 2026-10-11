-- ============================================================
-- 0334_create_forms.sql
--
-- The forms table. Stage 009.
--
-- A form is a named collection of fields that a merchant
-- publishes at /forms/{slug}. Submissions are stored in
-- commerce_form_submissions, keyed by form id.
--
-- status:
--   draft      - visible in the admin only
--   published  - reachable at /forms/{slug}
--   archived   - hidden everywhere; any public URL 404s
--
-- notify_email:
--   Optional. If set, a copy of every submission is
--   emailed to that address in addition to firing the
--   form.submitted domain event.
--
-- honeypot_enabled:
--   Renders an off-screen field named 'website'. A bot
--   that fills every input trips it and the submission
--   is silently accepted without being stored. Same
--   pattern as the newsletter subscribe endpoint.
--
-- requires_auth:
--   When 1, the public GET and POST require a logged-in
--   customer. Default 0 (public forms are anonymous).
--
-- See docs/stages/009_forms-plan.md.
-- ============================================================

CREATE TABLE `commerce_forms` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`         BIGINT UNSIGNED NOT NULL,
  `name`             VARCHAR(120) NOT NULL,
  `slug`             VARCHAR(120) NOT NULL,
  `description`      VARCHAR(500) NULL,
  `status`           ENUM('draft','published','archived')
                     NOT NULL DEFAULT 'draft',
  `success_message`  TEXT NULL,
  `notify_email`     VARCHAR(190) NULL,
  `honeypot_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_auth`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_by`       INT UNSIGNED NULL,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_forms_store_slug` (`store_id`, `slug`),
  KEY `idx_com_forms_store_status` (`store_id`, `status`),
  CONSTRAINT `fk_com_forms_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_forms_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
