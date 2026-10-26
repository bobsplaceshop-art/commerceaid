-- =============================================================
-- 0025_seed_premium_homepage.sql
-- Upgrades the homepage blocks to premium spacing.
-- Idempotent: safe to re-run. Only updates a block's padding
-- if it currently has the schema default ('lg' or missing).
-- =============================================================

-- Set every content block on the homepage to 'xl' padding.
UPDATE `commerce_page_blocks`
   SET `config_json` = JSON_SET(
         COALESCE(`config_json`, '{}'),
         '$.padding_top',    'xl',
         '$.padding_bottom', 'xl'
       )
 WHERE `store_id` = 1
   AND `page_slug` = 'home'
   AND `block_type` IN (
       'category-grid',
       'product-grid',
       'promo-panel',
       'newsletter'
   )
   AND (
       JSON_EXTRACT(`config_json`, '$.padding_top') IS NULL
       OR JSON_EXTRACT(`config_json`, '$.padding_top') = 'lg'
   );

-- Trust row stays tight against the hero. Force 'sm'.
UPDATE `commerce_page_blocks`
   SET `config_json` = JSON_SET(
         COALESCE(`config_json`, '{}'),
         '$.padding_top',    'sm',
         '$.padding_bottom', 'sm'
       )
 WHERE `store_id` = 1
   AND `page_slug` = 'home'
   AND `block_type` = 'trust-row';
