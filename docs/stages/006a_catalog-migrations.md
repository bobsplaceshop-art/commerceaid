# Stage 006A - Catalog Migrations

**Date:** 2026-09-25
**Status:** Complete

## Summary

Built the full database schema for the Commerce Aid catalog:
products, variants, categories, brands, tags, attributes, images,
and inventory with immutable movement ledger. 19 `commerce_*`
tables total (14 new in this stage, 4 store tables from Stage 003,
1 inventory ledger).

## Migrations Applied

| # | File | Tables Created |
|---|---|---|
| 0200 | create_products.sql | commerce_products, commerce_product_variants, commerce_product_images, commerce_product_videos, commerce_product_tags, commerce_product_tag_links, commerce_attribute_definitions, commerce_attribute_values, commerce_product_attribute_values, commerce_variant_attribute_values |
| 0201 | create_categories_brands.sql | commerce_categories, commerce_product_categories, commerce_brands + 3 ALTER FKs on commerce_products |
| 0202 | create_inventory.sql | commerce_inventory, commerce_inventory_movements |

## Design Decisions

### Product identity

- `store_id NOT NULL` on every tenant-scoped table
- `slug` unique per store (composite key `uniq_com_prod_store_slug`)
- `sku` unique per store, nullable (services/digital need no SKU)
- Both `status` (draft/published/archived) AND `deleted_at`
- FULLTEXT index on `name`, `short_description`, `description`

### Variants

- `commerce_product_variants.product_id` FK to parent
- `is_default` flag marks the fallback
- `sort_order` for display
- SKU unique per store (composite), nullable

### Images and videos

- `commerce_product_images.variant_id` NULL = product-level image
- `is_primary` flag marks the hero image
- `file_id` FK to `core_files` (INT UNSIGNED)
- Videos external URLs only - no upload

### Attributes

- `commerce_attribute_definitions` is store-independent vocabulary
- `commerce_attribute_values` holds values (Red, Blue, M, L)
- `is_variant_axis` flags variant-generating attributes
- Two junction tables for product-level and variant-level binding

### Categories

- Self-referential `parent_id` for hierarchical trees
- Cascade delete on parent
- `commerce_product_categories` junction for many-to-many
- `commerce_products.primary_category_id` records canonical category
- CHECK constraint `chk_com_cat_not_self_parent` was removed because MySQL 8 disallows CHECK referencing AUTO_INCREMENT. Enforcement moves to the Category model.

### Brands

- Flat list per store
- `logo_file_id` FK to `core_files`
- Optional `website_url`

### Inventory

- `commerce_inventory`: one row per sellable unit
- `variant_id = 0` sentinel for simple products
- `available` is a STORED GENERATED column: `quantity - reserved`
- `commerce_inventory_movements`: append-only ledger
  - 10 movement types (opening, adjustment, sale, return, refund,
    cancel, transfer_in, transfer_out, damage, stocktake)
  - `quantity_delta` (signed) + `quantity_after` (snapshot)
  - `reference_type` / `reference_id` polymorphic back-reference
  - No `deleted_at` - movements are never deleted

## Type Alignment

All FKs to `core_*` tables use `INT UNSIGNED` to match inherited
schema. All FKs to our own `commerce_*` tables use `BIGINT UNSIGNED`
to match our PKs.

Columns corrected after first migration attempt failed on type
mismatch:
- `commerce_products.og_image_file_id`, `.created_by`, `.updated_by`
- `commerce_product_variants.image_file_id`
- `commerce_product_images.file_id`
- `commerce_product_videos.thumbnail_file_id`
- `commerce_categories.image_file_id`
- `commerce_brands.logo_file_id`
- `commerce_inventory_movements.created_by`

## Verification

- `php bin/migrate.php` applied all three migrations
- SHOW TABLES returns 19 commerce tables
- core_migrations records 0200, 0201, 0202 as applied
- FKs from commerce_products to brands/categories/core_files present
- commerce_inventory.available is a GENERATED column
- CHECK constraint confirmed removed from commerce_categories

## Files Created

    database/migrations/0200_create_products.sql
    database/migrations/0201_create_categories_brands.sql
    database/migrations/0202_create_inventory.sql

## What's Next

Stage 006B - Models and Repositories for the catalog.
Plain DTO models matching the existing `User` pattern.
Commerce repositories extending `CommerceRepository` base class.
