-- ============================================================
-- 0335_create_form_fields.sql
--
-- The fields of a form. Stage 009.
--
-- field_key:
--   The submitted name. Lowercase, underscores, stable
--   across edits. Never changes once the field is saved,
--   or old submissions become orphaned.
--
-- input_type:
--   One of the twelve keys from InputTypeRegistry
--   (text, email, phone, number, date, url, longtext,
--    select, multiselect, checkbox, yesno, file).
--
-- options_json:
--   Array of {value, label} pairs for Select,
--   MultiSelect, and Checkbox group. Null for other
--   types.
--
-- constraints_json:
--   Type-specific constraints (min/max length, regex,
--   min/max number, date range). Shape matches the
--   CustomFields engine's constraints column.
--
-- step:
--   Which step of a multi-step form this field belongs
--   to. 1 by default. A single-step form has every
--   field at step 1 and the renderer suppresses step
--   navigation.
--
-- conditional_json:
--   A conditional rule object:
--     {"field":"other_key","operator":"equals","value":"x"}
--   Null means always visible. When a rule is present
--   the field is only shown when the rule matches.
--   Server-side the same rule gates whether the field's
--   validation runs.
--
-- Fields are hard-deleted. They have no separate audit
-- value; the submissions live in their own table.
--
-- See docs/stages/009_forms-plan.md.
-- ============================================================

CREATE TABLE `commerce_form_fields` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id`         BIGINT UNSIGNED NOT NULL,
  `form_id`          BIGINT UNSIGNED NOT NULL,
  `field_key`        VARCHAR(60) NOT NULL,
  `label`            VARCHAR(190) NOT NULL,
  `input_type`       VARCHAR(40) NOT NULL,
  `placeholder`      VARCHAR(190) NULL,
  `help_text`        VARCHAR(500) NULL,
  `is_required`      TINYINT(1) NOT NULL DEFAULT 0,
  `options_json`     JSON NULL,
  `constraints_json` JSON NULL,
  `default_value`    VARCHAR(500) NULL,
  `step`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `sort_order`       INT UNSIGNED NOT NULL DEFAULT 0,
  `conditional_json` JSON NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_com_fform_key` (`form_id`, `field_key`),
  KEY `idx_com_fform_step` (`form_id`, `step`, `sort_order`),
  CONSTRAINT `fk_com_fform_store`
    FOREIGN KEY (`store_id`) REFERENCES `commerce_stores` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_com_fform_form`
    FOREIGN KEY (`form_id`) REFERENCES `commerce_forms` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
