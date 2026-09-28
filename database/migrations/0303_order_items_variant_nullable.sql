-- =====================================================================
-- Stage 008E - order_items.variant_id nullable
-- =====================================================================
--
-- commerce_order_items.variant_id has a foreign key to
-- commerce_product_variants.id. Simple products have no variant
-- row. The original schema used variant_id = 0 as a sentinel,
-- but the FK rejects it because no variant with id = 0 exists.
--
-- Fix: make variant_id nullable, matching the shape of
-- product_id. Simple product lines store NULL. Variable product
-- lines store the real variant id. The FK's ON DELETE SET NULL
-- already assumes this behavior.
--
-- See docs/stages/008e_checkout-plan.md.
-- =====================================================================

ALTER TABLE `commerce_order_items`
    MODIFY COLUMN `variant_id` BIGINT UNSIGNED NULL DEFAULT NULL;
