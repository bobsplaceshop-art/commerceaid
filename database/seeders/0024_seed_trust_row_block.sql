-- =============================================================
-- 0024_seed_trust_row_block.sql
-- Places a trust-row block on the homepage, right after the
-- hero. The block renders the items from the header menu's
-- trust_bar column. Idempotent: only inserts if no trust-row
-- block exists on the homepage for this store.
-- =============================================================

INSERT INTO `commerce_page_blocks`
  (`store_id`, `page_slug`, `block_type`, `parent_id`,
   `sort_order`, `config_json`, `is_enabled`,
   `created_at`, `updated_at`)
SELECT
  s.`id`,
  'home',
  'trust-row',
  NULL,
  COALESCE(
    (SELECT MAX(`sort_order`) + 1 FROM `commerce_page_blocks`
      WHERE `store_id` = s.`id` AND `page_slug` = 'home'
        AND `parent_id` IS NULL AND `block_type` = 'hero'),
    10
  ),
  '{}',
  1,
  NOW(),
  NOW()
FROM `commerce_stores` s
WHERE NOT EXISTS (
  SELECT 1 FROM `commerce_page_blocks`
  WHERE `store_id` = s.`id`
    AND `page_slug` = 'home'
    AND `block_type` = 'trust-row'
);
