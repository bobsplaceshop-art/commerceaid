# Stage 006F - Product Variations

**Date:** 2026-09-26
**Status:** Complete

## Summary

Full variations system for variable products. Attributes are global
vocabulary mapped to categories; products inherit their attributes from
their categories; variations auto-generate from the Cartesian product of
those attribute values. The variations page supports search, group
filtering, bulk actions, pagination, and destructive regenerate with
type-to-confirm protection.

This closes the catalog build. Stage 006 is now complete.

## Files Created

- app/Commerce/Repositories/ProductVariantRepository.php
- app/Commerce/Repositories/AttributeRepository.php
- app/Commerce/Repositories/CategoryAttributeRepository.php
- app/Commerce/Controllers/Admin/AttributeController.php
- resources/views/admin/attributes/index.php
- resources/views/admin/attributes/edit.php
- resources/views/admin/attributes/_delete-modal.php
- resources/views/admin/products/variants.php
- database/migrations/0203_create_category_attributes.sql

## Files Modified

- app/Commerce/Controllers/Admin/ProductController.php (variant methods,
  helpers, regenerate)
- app/Commerce/Controllers/Admin/CategoryController.php (attribute
  mapping UI, attach/detach methods)
- app/Commerce/CommerceServiceProvider.php (three new repository
  bindings)
- resources/views/admin/categories/edit.php (attributes section,
  sticky action bar)
- resources/views/admin/products/_sidebar.php (Manage variations button)
- resources/views/admin/products/edit.php (removed variants tab)
- resources/views/partials/sidebar.php (unlock Attributes)
- routes/commerce.php (attribute routes, category-attribute mapping
  routes, variant routes)
- public/assets/css/setup.css (sticky action bar styles)

## Files Deleted

- resources/views/admin/products/_tab_variants.php (moved to a full page)

## Architecture Decisions

### Attribute vocabulary is global

commerce_attribute_definitions has no store_id. Color is Color across
all stores. What varies per store is which attributes are mapped to
which categories, and which values each attribute has.

### Category declares its attributes

commerce_category_attributes is a junction with direction Category ->
Attribute. A category declares which attributes are relevant to
products in it. Multiple categories can reference the same attribute.
Deleting a category cascades only its mappings; the attribute survives.

### Product inherits attributes from categories

A variable product's variant axes are the union of variant-axis
attributes (is_variant_axis = 1) across every category the product
belongs to. No per-product attribute selection.

### Variations auto-generate on first visit

When a variable product's variations page loads and the product has no
variations yet, and all mapped variant axes have at least one value,
the Cartesian product is inserted as new variants with the product's
base price and zero stock.

### Drift detection

When the current expected combinations differ from existing variations,
the page shows an amber warning listing missing and obsolete counts, with
a Regenerate button. Regenerate wipes all variations and rebuilds (with
type-to-confirm protection).

### Incomplete axes block generation

If a mapped variant axis has zero values, generation is blocked. The page
shows an explicit warning listing each axis missing values, with a link
to add them.

### Variations are archived, not deleted

Variations auto-generate; deleting one means it regenerates. Archive
(status = archived) hides the variation from the storefront but keeps
its SKU, price, and stock intact for restoration.

## Variations Page Features

- Count summary (total + matching when filtered)
- Search by SKU or attribute value (400ms debounce)
- Group filter chips for the first axis (Red (6), Blue (6))
- Bulk actions toolbar: Enable, Disable, Set price for selected rows
- Row checkboxes + select-all with indeterminate state
- Pagination (25 rows per page, preserves filters)
- Sticky action bar with Save all variations
- Regenerate confirmation (type the word regenerate)
- Incomplete axes warning (link to the attribute)
- Drift warning banner

## Attribute Management

Full CRUD for attribute definitions and their values:

- Attribute list with value chips
- Edit form with values section (add/remove values)
- Colour attribute type with hex picker + 26 quick swatches
- "Used by categories" read-only list on the attribute edit page
- Category edit page has "Attributes for this category" section with
  grouped dropdown (Variant axes / Informational)

## Verification

End-to-end tested:

- Attribute CRUD (create Color, Size, add values)
- Category attribute mapping (add/remove)
- Variable product creation with category assignment
- Auto-generation of 12 variations (3 Colors x 4 Sizes)
- Search narrows results
- Group chips filter by first axis
- Combined search + filter
- Clear filters restores full list
- Bulk actions: Enable, Disable, Set price
- Save persists changes
- Drift warning appears after removing a mapped attribute
- Incomplete axis warning appears for attributes with no values
- Regenerate wipes and rebuilds

## What's Next

Stage 006G - Inventory overview.

Small module: dashboard tiles for low-stock / out-of-stock counts, a
movement history view with filters, and a bulk stock adjustment form.
Uses everything already built (InventoryRepository).

Then Stage 007 - B2B and customer groups.
