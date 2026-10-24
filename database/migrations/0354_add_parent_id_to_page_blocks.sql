-- 0354_add_parent_id_to_page_blocks.sql
--
-- Adds parent_id to the two block tables so that a page can
-- represent a tree: sections that contain children, and
-- leaf elements (Divider, Spacer, Heading, etc.) that live
-- inside a section.
--
-- A NULL parent_id means the block is a root (top-level).
-- A non-null parent_id means the block is a child of the
-- referenced block. ON DELETE CASCADE handles the tree:
-- deleting a Section deletes its children.
--
-- The published and draft tables both gain the column. The
-- draft version has no FK to commerce_page_blocks because a
-- draft child can reference a negative draft parent id
-- before publish. The published version has the FK.
--
-- See docs/stages/023_page-builder-architecture.md, Tier
-- 0.4 (parent_id), and docs/ROADMAP.md Stage 023.

ALTER TABLE `commerce_page_blocks`
  ADD COLUMN `parent_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `page_slug`,
  ADD KEY `idx_com_pb_parent` (`store_id`, `page_slug`, `parent_id`, `sort_order`),
  ADD CONSTRAINT `fk_com_pb_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `commerce_page_blocks` (`id`)
    ON DELETE CASCADE;

ALTER TABLE `commerce_page_drafts`
  ADD COLUMN `parent_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `block_id`,
  ADD KEY `idx_com_pd_parent` (`store_id`, `page_slug`, `theme_slug`, `parent_id`, `sort_order`);
