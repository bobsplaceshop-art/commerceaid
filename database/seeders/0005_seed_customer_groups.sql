-- ============================================================
-- 0005_seed_customer_groups.sql
-- Default customer groups every store should have.
-- Idempotent: safe to run multiple times.
--
-- Uses the first active store as the target. In a multi-store
-- install, this seeds groups for the first store only; others
-- are configured from the admin UI.
-- ============================================================

INSERT INTO `commerce_customer_groups`
  (`store_id`, `slug`, `name`, `description`, `is_default`, `is_b2b`, `requires_approval`, `sort_order`, `status`)
SELECT
  s.`id`,
  'retail',
  'Retail',
  'Default group for regular consumers.',
  1,
  0,
  0,
  0,
  'active'
FROM `commerce_stores` s
WHERE s.`status` = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_customer_groups` cg
     WHERE cg.`store_id` = s.`id` AND cg.`slug` = 'retail'
  )
LIMIT 1;

INSERT INTO `commerce_customer_groups`
  (`store_id`, `slug`, `name`, `description`, `is_default`, `is_b2b`, `requires_approval`, `sort_order`, `status`)
SELECT
  s.`id`,
  'wholesale',
  'Wholesale',
  'B2B buyers with negotiated pricing.',
  0,
  1,
  1,
  10,
  'active'
FROM `commerce_stores` s
WHERE s.`status` = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_customer_groups` cg
     WHERE cg.`store_id` = s.`id` AND cg.`slug` = 'wholesale'
  )
LIMIT 1;

INSERT INTO `commerce_customer_groups`
  (`store_id`, `slug`, `name`, `description`, `is_default`, `is_b2b`, `requires_approval`, `sort_order`, `status`)
SELECT
  s.`id`,
  'vip',
  'VIP',
  'Loyal customers with special pricing.',
  0,
  0,
  0,
  20,
  'active'
FROM `commerce_stores` s
WHERE s.`status` = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_customer_groups` cg
     WHERE cg.`store_id` = s.`id` AND cg.`slug` = 'vip'
  )
LIMIT 1;
