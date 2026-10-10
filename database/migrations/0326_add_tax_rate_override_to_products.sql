-- ============================================================
-- 0326_add_tax_rate_override_to_products.sql
--
-- Per-product tax rate override (Stage 008K.5, session 3
-- follow-up).
--
-- Purpose:
--   A merchant may need a specific product to carry a
--   different tax rate than its tax class would apply.
--   Examples:
--     * A book sold under the German 7% reduced rate.
--     * A government-subsidised staple that is zero-rated
--       for a specific period.
--     * A promotional item the merchant wants to sell at a
--       temporarily reduced rate without creating a new
--       tax class.
--
-- Semantics:
--   NULL                - use the tax class's rate for the
--                         destination. This is the default
--                         and the behavior before this
--                         column existed.
--   0.000 to 100.000    - use this rate percent for the
--                         product, regardless of the class's
--                         rate for the destination. The
--                         is_inclusive and is_shipping_taxable
--                         flags are still taken from the
--                         resolved class rate, so the store's
--                         inclusive-pricing convention is
--                         unchanged.
--
--   tax_status = 'none' still wins: an exempt product ignores
--   the override and resolves to a zero rate. "Exempt" is a
--   stronger statement than "a different rate".
--
-- Precedence in TaxService::resolveRateForLine():
--   1. tax_status = 'none'              -> exempt (zero rate)
--   2. tax_rate_override_percent set    -> use this rate
--   3. tax class rate for the destination
--   4. zero rate (no class, no rate)
--
-- See docs/stages/008k.5_order-pricing-engine-plan.md.
-- ============================================================

ALTER TABLE `commerce_products`
  ADD COLUMN `tax_rate_override_percent` DECIMAL(6,3) NULL
    COMMENT 'Per-product tax rate override. NULL uses the class rate.'
    AFTER `tax_status`;
