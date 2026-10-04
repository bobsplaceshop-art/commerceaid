-- ============================================================
-- 0318_add_store_font_slugs.sql
-- Adds three nullable font selection columns to
-- commerce_stores.
--
-- Each column holds a font slug from
-- CorporateAid\Commerce\Support\Fonts\FontCatalog, or
-- NULL to use the platform default (Inter body,
-- Playfair Display headings, Dancing Script accents).
--
-- The storefront reads these columns and emits
-- @font-face rules pointing at the downloaded WOFF2
-- files under public/assets/fonts/{slug}/.
--
-- Nullable because most merchants will not change the
-- defaults. A missing value means "use the platform
-- default stack", which is exactly what the current
-- :root CSS already provides.
--
-- See docs/stages/008g.4c_font-manager-plan.md (to be
-- written at stage close).
-- ============================================================

ALTER TABLE `commerce_stores`
  ADD COLUMN `font_body_slug`    VARCHAR(80) NULL
    AFTER `pwa_background_color`,
  ADD COLUMN `font_display_slug` VARCHAR(80) NULL
    AFTER `font_body_slug`,
  ADD COLUMN `font_script_slug`  VARCHAR(80) NULL
    AFTER `font_display_slug`;
