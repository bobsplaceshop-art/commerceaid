-- ============================================================
-- 0006_seed_test_b2b_customer.sql
-- Test B2B customer in pending approval state.
--
-- Purpose: proves the customer approval queue works end to end
-- without requiring a storefront. Once Stage 008 ships the real
-- signup flow, this seeder becomes redundant and can be removed.
--
-- Idempotent: the email unique key (store_id, email) plus the
-- WHERE NOT EXISTS guard prevents duplicates on re-run.
--
-- Uses the first active store, same assumption as 0005.
-- ============================================================

INSERT INTO `commerce_customers`
  (`store_id`, `email`, `password_hash`, `first_name`, `last_name`,
   `phone`, `company`, `tax_id`, `accepts_marketing`,
   `customer_group_id`, `status`, `approval_status`, `approval_reason`)
SELECT
  s.`id`,
  'b2b-test@anayaglobal.test',
  NULL,
  'Sarah',
  'Njeri',
  '+254700000000',
  'Mombasa Traders Ltd',
  NULL,
  0,
  cg.`id`,
  'active',
  'pending',
  'Awaiting initial review. Test seed data.'
FROM `commerce_stores` s
INNER JOIN `commerce_customer_groups` cg
  ON cg.`store_id` = s.`id` AND cg.`slug` = 'wholesale'
WHERE s.`status` = 'active'
  AND NOT EXISTS (
    SELECT 1 FROM `commerce_customers` c
     WHERE c.`store_id` = s.`id`
       AND c.`email` = 'b2b-test@anayaglobal.test'
  )
LIMIT 1;
