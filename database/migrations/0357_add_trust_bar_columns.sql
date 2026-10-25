-- Add icon and subtitle columns to support trust bar items
-- in the header builder.
ALTER TABLE `commerce_navigation_items`
  ADD COLUMN `icon` VARCHAR(50) NULL AFTER `label`,
  ADD COLUMN `subtitle` VARCHAR(190) NULL AFTER `icon`;
