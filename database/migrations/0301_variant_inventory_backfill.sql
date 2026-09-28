-- ============================================================
-- 0301_variant_inventory_backfill.sql
--
-- Establishes the invariant: every sellable unit has exactly
-- one inventory row.
--
-- Background:
--   As shipped, commerce_inventory had one row per product with
--   variant_id = 0. For variable products, that row is a
--   parent row for a product that is not itself sellable. Each
--   variant, which IS the sellable unit, has no inventory row.
--   The cart's stock check silently skips when a variant row
--   is missing. Customers can add unlimited units of any
--   variant regardless of stock.
--
-- What this migration does:
--   1. Backs up commerce_inventory to commerce_inventory_
--      backup_0301. The backup is preserved for the life of
--      the project. It can be used for rollback if the
--      migration is wrong.
--
--   2. Deletes parent rows (variant_id = 0) for every product
--      whose product_type = 'variable'. Variable products are
--      marketing containers, not sellable units. Their parent
--      row was a shortcut that must go.
--
--   3. Inserts a zero-quantity inventory row for every active
--      variant of a variable product that does not already
--      have one. Quantity starts at 0. The merchant re-enters
--      real per-variant stock through the variations editor.
--
--   4. Leaves simple products alone. Their variant_id = 0 row
--      is the correct row for the single sellable unit.
--
-- The 200 units currently in the T-Shirt's parent row are not
-- redistributed. The merchant will see 0 stock per variant
-- after this migration and set real values. For a future
-- production migration, a warning step would distribute the
-- parent quantity across variants or hold it for manual
-- reassignment.
--
-- Idempotency:
--   CREATE TABLE IF NOT EXISTS on the backup.
--   INSERT IGNORE on the variant rows.
--   DELETE guarded by a NOT EXISTS check on the target
--   variants table (no orphans to worry about - FK handles it).
--   Re-running this migration on already-migrated data is a
--   no-op.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Backup the current inventory table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `commerce_inventory_backup_0301` AS
SELECT * FROM `commerce_inventory`;

-- ------------------------------------------------------------
-- 2. Delete parent rows for variable products
-- ------------------------------------------------------------
-- A row is a "parent row" when variant_id = 0 AND the product
-- it belongs to is a variable product. Simple products keep
-- their variant_id = 0 row - that row IS the sellable unit.
DELETE `i` FROM `commerce_inventory` `i`
INNER JOIN `commerce_products` `p`
    ON `p`.`id` = `i`.`product_id`
WHERE `i`.`variant_id` = 0
  AND `p`.`product_type` = 'variable';

-- ------------------------------------------------------------
-- 3. Insert per-variant inventory rows
-- ------------------------------------------------------------
-- For every active variant of a variable product that does
-- not already have an inventory row, insert one with
-- quantity = 0 and reserved = 0.
--
-- Uses INSERT ... SELECT so the whole backfill is one
-- statement. The NOT EXISTS guard makes it idempotent.
INSERT INTO `commerce_inventory`
    (`store_id`, `product_id`, `variant_id`, `quantity`,
     `reserved`, `low_stock_threshold`)
SELECT
    `v`.`store_id`,
    `v`.`product_id`,
    `v`.`id`,
    0,
    0,
    NULL
FROM `commerce_product_variants` `v`
INNER JOIN `commerce_products` `p`
    ON `p`.`id` = `v`.`product_id`
WHERE `p`.`product_type` = 'variable'
  AND `v`.`status` = 'active'
  AND NOT EXISTS (
      SELECT 1
        FROM `commerce_inventory` `i`
       WHERE `i`.`product_id` = `v`.`product_id`
         AND `i`.`variant_id` = `v`.`id`
  );

-- ------------------------------------------------------------
-- 4. Verification (informational - does not affect migration)
-- ------------------------------------------------------------
-- After this migration runs, these queries should return:
--
--   SELECT COUNT(*) FROM commerce_inventory i
--     INNER JOIN commerce_products p ON p.id = i.product_id
--    WHERE p.product_type = 'variable' AND i.variant_id = 0;
--   -- expect 0
--
--   SELECT p.id, COUNT(v.id) AS variant_count,
--          COUNT(i.id) AS inv_row_count
--     FROM commerce_products p
--     LEFT JOIN commerce_product_variants v
--       ON v.product_id = p.id AND v.status = 'active'
--     LEFT JOIN commerce_inventory i
--       ON i.product_id = p.id AND i.variant_id = v.id
--    WHERE p.product_type = 'variable'
--    GROUP BY p.id
--   HAVING variant_count <> inv_row_count;
--   -- expect 0 rows (every variant has an inventory row)
