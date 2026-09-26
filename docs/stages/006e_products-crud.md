# Stage 006E - Products List & Editor

**Date:** 2026-09-26
**Status:** Complete

## Summary

The largest admin module in Stage 006. Full CRUD for products with
a tab-based editor, live-filtering product list, inventory ledger
integration, and full dark/light theme support.

Sub-stage 006E handles **simple products only**. Variable products
(with variants) arrive in 006F.

## Files Created

    app/Commerce/Controllers/Admin/ProductController.php    (~520 lines)
    resources/views/admin/products/index.php                (list + filters)
    resources/views/admin/products/edit.php                 (editor shell)
    resources/views/admin/products/_tab_basic.php
    resources/views/admin/products/_tab_pricing.php
    resources/views/admin/products/_tab_inventory.php
    resources/views/admin/products/_tab_shipping.php
    resources/views/admin/products/_tab_seo.php
    resources/views/admin/products/_sidebar.php
    resources/views/admin/products/_delete-modal.php

## Files Modified

    routes/commerce.php                          (product routes)
    resources/views/partials/sidebar.php         (unlock Products)
    public/assets/css/setup.css                  (form + table + theme CSS)

## Product CRUD

### Routes

    GET    /commerce/ca-admin/products                     list
    GET    /commerce/ca-admin/products/create              create
    POST   /commerce/ca-admin/products                     store
    GET    /commerce/ca-admin/products/{id}/edit           edit
    POST   /commerce/ca-admin/products/{id}                update
    POST   /commerce/ca-admin/products/{id}/publish        publish
    POST   /commerce/ca-admin/products/{id}/unpublish      revert to draft
    POST   /commerce/ca-admin/products/{id}/archive        archive
    POST   /commerce/ca-admin/products/{id}/restore        restore
    POST   /commerce/ca-admin/products/{id}/delete         soft-delete
    GET    /commerce/ca-admin/products/{id}/delete-info    JSON

### ProductController

Uses `CatalogService` for dropdown data and `ProductRepository` for
CRUD. Validation runs through the shared `Validator` helper.

Business rules enforced:
- Name required, max 255 chars
- Slug unique per store, URL-safe format
- SKU optional, unique per store when present
- Price must be >= 0
- Compare-at price must exceed selling price (when set)
- No negative stock quantities
- No negative weights or dimensions
- Tax status must be taxable or none
- Status must be draft, published, or archived

### Inventory integration

Simple products write to `commerce_inventory` with `variant_id = 0`.

- **On creation:** if manage_stock is on, an `opening` movement is
  logged with the initial quantity. Uses `InventoryRepository::adjust()`
  so the ledger is the source of truth.
- **On update:** if manage_stock is on and quantity changed, an
  `adjustment` movement is logged with the delta.

Stock status is auto-derived:
- quantity > 0 → in_stock
- quantity <= 0 and backorders_allowed → backorder
- quantity <= 0 and no backorders → out_of_stock

The user never enters stock status directly.

### Junction writes

- `category_ids[]` → `commerce_product_categories` via
  `syncCategories()` (replace-set semantics)
- `tag_ids[]` → `commerce_product_tag_links` via `syncTags()`
  (UI disabled for now; repository ready)

## Editor Design

Tab-based editor with a persistent right sidebar:

**Tabs (left column, 8/12 width):**
1. Basic — name, slug, SKU, descriptions, brand, categories
2. Pricing — price, compare-at, cost, tax status
3. Inventory — manage stock, quantity, threshold, backorders
4. Shipping — requires shipping, weight, dimensions
5. SEO — title, meta description, keywords, live search preview

**Sidebar (right column, 4/12 width):**
- Status dropdown (draft / published / archived)
- Save + Cancel buttons
- Visibility toggles (featured, new arrival)
- Actions: publish / unpublish / archive / restore / delete

Tab navigation is client-side (Bootstrap tabs). All fields submit
together in a single form.

## Products List

- **Live filtering** — typing in search debounces 350ms then
  submits. Dropdowns and checkboxes submit on change. No more
  "Apply" button.
- **Filters:** search (name + SKU), status, category, brand, featured
- **Pagination:** smart page ranges with ellipsis, preserves filters
  across pages
- **Empty state:** distinguishes "no products" vs "no matches"
- **Table columns:** product (with featured star), SKU, price (+
  strikethrough compare-at), status badge, stock management, actions
- **Actions:** edit, publish/unpublish, delete

## Theme Support

Dark and light mode both render correctly. Required adding
authoritative CSS variables in `setup.css` loaded after `app.css`:

    :root, [data-theme="light"] { --ra-fg: #0f172a; ... }
    [data-theme="dark"]         { --ra-fg: #e2e8f0; ... }

Plus forced text colors on admin pages so no element inherits
browser default. Fixed a case where form controls, tables, badges,
and headings all rendered with black text on dark backgrounds.

Also fixed:
- `.form-control` / `.form-select` / textarea theme-aware styling
- Table header/row/badge colorization
- Product editor tab navigation colorization
- Form label and help text colorization

## Verification

End-to-end tested:

    1. Empty products list renders              OK
    2. Create simple product                    OK
    3. Product row written to DB                OK
    4. Inventory row + opening movement logged  OK
    5. Editor renders all five tabs             OK
    6. Tab switching works                      OK
    7. Edit loads existing values               OK
    8. Update saves changes                     OK
    9. Inventory adjustment logged on change    OK
    10. Publish / unpublish                     OK
    11. Archive / restore                       OK
    12. Soft delete + DB soft-delete flag       OK
    13. Live search filter                      OK
    14. Status dropdown filter                  OK
    15. Category filter                          OK
    16. Clear filters                            OK
    17. Pagination preserves filters             OK
    18. Dark mode readable                       OK
    19. Light mode readable                      OK
    20. Validation rejects negative price       OK
    21. Validation rejects compare-at < price   OK

## Design Notes

- **Simple products only in 006E.** The `product_type` column is
  forced to 'simple'. Variable products and their variants arrive
  in 006F.
- **No image upload yet.** The image management UI is deferred to
  a future sub-stage (likely 006F or a dedicated media stage).
- **Tags UI disabled** — repository support exists, but the tag
  management screen isn't built. Field shows as disabled placeholder.
- **Rich text editor deferred** — the description field is a plain
  textarea. A WYSIWYG editor is out of scope for 006E.

## What's Next

Stage 006F - Product variants.
The last piece of Stage 006. Enables variable products (size/color
matrix), per-variant pricing, per-variant inventory, and the
variant editor UI.

After 006F:
- 006G - Inventory overview (dashboard, movements, adjustments)
- Stage 006 top-level doc
- Stage 007 - B2B & customer groups
