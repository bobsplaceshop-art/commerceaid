-- ============================================================
-- 0317_add_attribute_value_to_product_images.sql
-- Adds an attribute_value_id column to
-- commerce_product_images.
--
-- The schema already supports per-variant image assignment
-- via the existing variant_id column. That represents a
-- full combination (Red + M). But the storefront product
-- page's swatch selector needs an image per attribute value
-- ("Red", "M"), not per combination - a customer picking
-- "Red" wants to see a Red image before they have picked a
-- size.
--
-- This column is distinct from variant_id:
--
--   variant_id is set when an image is specific to one
--   combination. The value is a commerce_product_variants
--   row.
--
--   attribute_value_id is set when an image is a swatch
--   preview for one attribute value. The value is a
--   commerce_attribute_values row.
--
-- Both are nullable and independent. An image can be a
-- general gallery image (both null), a swatch preview
-- (attribute_value_id set), a combination override
-- (variant_id set), or - in a rare case - both.
--
-- ON DELETE SET NULL: if an attribute value is deleted,
-- the image survives as a general gallery image rather
-- than cascading away. The swatch just loses its preview.
--
-- See docs/stages/008g.4a.2_product-media-plan.md.
-- ============================================================

ALTER TABLE `commerce_product_images`
  ADD COLUMN `attribute_value_id` INT UNSIGNED NULL
    AFTER `variant_id`,
  ADD CONSTRAINT `fk_com_pi_attribute_value`
    FOREIGN KEY (`attribute_value_id`)
    REFERENCES `commerce_attribute_values` (`id`)
    ON DELETE SET NULL;

-- Index to support swatch preview lookups:
-- SELECT * FROM commerce_product_images
--  WHERE product_id = ? AND attribute_value_id = ?
CREATE INDEX `idx_com_pi_attribute_value`
  ON `commerce_product_images` (`product_id`, `attribute_value_id`);
