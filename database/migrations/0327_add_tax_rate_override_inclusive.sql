-- ============================================================
-- 0327_add_tax_rate_override_inclusive.sql
--
-- Adds an explicit inclusive/exclusive flag for the
-- per-product tax rate override introduced in migration
-- 0326.
--
-- Why this exists:
--   In 0326, the override inherits is_inclusive from the
--   tax class's rate for the destination. If the class has
--   no rate for the destination (a class without a
--   country-specific rate), the override fell through to
--   is_inclusive = false. That forced exclusive pricing on
--   a store whose convention is inclusive. The customer
--   saw a total 19% above the displayed price, and the
--   PAngV note still read "inkl." - contradictory and,
--   in Germany, wrong.
--
-- This migration gives the merchant an explicit choice.
--
-- Semantics (three-state):
--   NULL - inherit from the class's rate for the
--          destination. Falls back to false if no rate
--          exists for the destination.
--   0    - exclusive. The price is net, tax is added at
--          checkout. PAngV note reads "zzgl."
--   1    - inclusive. The price is gross, tax is inside.
--          PAngV note reads "inkl."
--
-- Precedence in TaxService::resolveRateForLine():
--   1. tax_status = 'none'              -> exempt (zero rate)
--   2. tax_rate_override_percent set:
--        is_inclusive from tax_rate_override_inclusive
--        if not NULL, else from the class rate, else false
--   3. class rate for the destination
--   4. zero rate (no class, no rate)
--
-- See docs/stages/008k.5_order-pricing-engine-plan.md.
-- ============================================================

ALTER TABLE `commerce_products`
  ADD COLUMN `tax_rate_override_inclusive` TINYINT(1) NULL
    COMMENT 'Override inclusive flag: NULL=inherit, 0=exclusive, 1=inclusive'
    AFTER `tax_rate_override_percent`;
