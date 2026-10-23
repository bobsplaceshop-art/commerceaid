-- =====================================================================
-- Add a card image column to categories.
-- =====================================================================
-- The existing image_file_id column is the wide 3:1 banner, shown
-- on the category detail page header. That image is too large for
-- the small 1:1 cards on the home page category grid and the
-- category index card grid. This migration adds a second, optional
-- card_image_file_id column so the merchant can upload a square
-- image for those smaller surfaces.
--
-- When card_image_file_id is NULL, the storefront falls back to
-- image_file_id (existing behavior). No break.
-- =====================================================================

ALTER TABLE `commerce_categories`
  ADD COLUMN `card_image_file_id` INT UNSIGNED NULL
    AFTER `image_file_id`,
  ADD CONSTRAINT `fk_com_cat_card_image`
    FOREIGN KEY (`card_image_file_id`) REFERENCES `core_files` (`id`)
    ON DELETE SET NULL;
