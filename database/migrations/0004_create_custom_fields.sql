-- ============================================================
-- Recruit Aid — Custom Fields and Pipeline Stages
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_custom_fields` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type`   VARCHAR(60) NOT NULL,
    `field_key`     VARCHAR(80) NOT NULL,
    `label`         VARCHAR(190) NOT NULL,
    `input_type`    VARCHAR(40) NOT NULL,
    `is_required`   TINYINT(1) NOT NULL DEFAULT 0,
    `options`       JSON NULL,
    `constraints`   JSON NULL,
    `default_value` VARCHAR(500) NULL,
    `help_text`     VARCHAR(500) NULL,
    `sort_order`    INT NOT NULL DEFAULT 0,
    `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
    `is_system`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_by`    INT UNSIGNED NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_entity_key` (`entity_type`, `field_key`),
    KEY `idx_entity` (`entity_type`),
    KEY `idx_active` (`entity_type`, `is_active`),
    CONSTRAINT `fk_core_cf_created_by` FOREIGN KEY (`created_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `core_custom_field_values` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `custom_field_id` INT UNSIGNED NOT NULL,
    `entity_type`     VARCHAR(60) NOT NULL,
    `entity_id`       INT UNSIGNED NOT NULL,
    `value`           LONGTEXT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_field_entity` (`custom_field_id`, `entity_type`, `entity_id`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    CONSTRAINT `fk_core_cfv_field` FOREIGN KEY (`custom_field_id`) REFERENCES `core_custom_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

