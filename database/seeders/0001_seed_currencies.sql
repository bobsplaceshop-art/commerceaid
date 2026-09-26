-- ============================================================
-- 0001_seed_currencies.sql
-- Reference list of currencies. is_active = 1 for those we ship
-- as ready-to-use. Operators enable/disable per store via
-- commerce_store_currencies.
-- ============================================================

INSERT INTO `core_currencies` (`code`, `name`, `symbol`, `decimal_places`, `is_active`) VALUES
    ('KES', 'Kenyan Shilling',   'KSh',  2, 1),
    ('USD', 'US Dollar',         '$',    2, 1),
    ('EUR', 'Euro',              '€',    2, 1),
    ('GBP', 'British Pound',     '£',    2, 1),
    ('ZAR', 'South African Rand','R',    2, 1),
    ('NGN', 'Nigerian Naira',    '₦',    2, 1),
    ('UGX', 'Ugandan Shilling',  'USh',  0, 1),
    ('TZS', 'Tanzanian Shilling','TSh',  0, 1),
    ('INR', 'Indian Rupee',      '₹',    2, 1),
    ('AED', 'UAE Dirham',        'د.إ',  2, 1)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `symbol` = VALUES(`symbol`),
    `decimal_places` = VALUES(`decimal_places`),
    `is_active` = VALUES(`is_active`);
