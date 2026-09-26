-- ============================================================
-- 0207_add_approved_by_fk.sql
-- Adds the missing FK from commerce_customers.approved_by to
-- core_users.id. The original migration declared the column but
-- not the constraint.
-- ============================================================

ALTER TABLE `commerce_customers`
  ADD CONSTRAINT `fk_com_cust_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `core_users` (`id`) ON DELETE SET NULL;
