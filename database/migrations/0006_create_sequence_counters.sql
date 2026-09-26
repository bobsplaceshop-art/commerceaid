-- ============================================================
-- 0006_create_sequence_counters.sql
-- Monotonic sequence counters for generated codes.
-- Schema only — no seed rows. Each product seeds its own keys.
-- ============================================================

CREATE TABLE IF NOT EXISTS `core_sequence_counters` (
    `name`       VARCHAR(60) NOT NULL,
    `last_value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
