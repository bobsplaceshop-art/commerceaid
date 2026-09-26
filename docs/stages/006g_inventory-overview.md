# Stage 006G - Inventory Overview

**Date:** 2026-09-26
**Status:** Complete

## Summary

Inventory management UI on top of the InventoryRepository built in
Stage 006B. Admin can view stock across all sellable units, filter by
status/category/brand, adjust quantities through a modal, and review
the immutable movement ledger. CSV exports on both views. Dashboard
gains an Inventory health card.

This closes Stage 006. The entire catalog and inventory back-office
is now shipped.

## Files Created

    app/Commerce/Controllers/Admin/InventoryController.php
    resources/views/admin/inventory/index.php
    resources/views/admin/inventory/movements.php
    resources/views/admin/inventory/_adjust-modal.php
    docs/RESPONSIVENESS.md

## Files Modified

    app/Commerce/Repositories/InventoryRepository.php   (paginate + movementsPaginate)
    app/Commerce/Controllers/Admin/DashboardController.php  (inventory health card)
    resources/views/admin/dashboard.php                 (health card markup)
    resources/views/partials/sidebar.php                (Inventory nav item)
    routes/commerce.php                                 (inventory routes)
    public/assets/css/setup.css                         (modal + responsive table styles)

## Inventory List (`/commerce/ca-admin/inventory`)

- Summary tiles: Low Stock, Out of Stock, Units on Hand
- Click Low Stock / Out of Stock tile to filter
- Filter bar: search, status, category, brand (live update on change)
- Table columns: Product, Variant, SKU, On Hand, Reserved, Available, Status, Actions
- Status badge: In Stock / Low Stock / Out of Stock
- Actions: Adjust stock (modal), View movements (filtered)
- Pagination (25 rows per page)
- Responsive: secondary columns hidden below 768px, action buttons stack vertically
- Export CSV (up to 10,000 rows)

## Movement History (`/commerce/ca-admin/inventory/movements`)

- Table columns: When, Product, Variant, Type, Change, After, Reason, Actor
- Delta color-coded: green for increases, red for decreases
- Movement type badges: DAMAGE (red), ADJUSTMENT (muted), etc.
- Filter bar: search (product name, SKU, reason), type, date range
- Pagination (50 rows per page)
- Responsive: same rules as inventory list
- Export CSV

## Stock Adjustment Modal

Opened from the pencil icon on any inventory row.

- Product name shown immediately (before fetch)
- Variant label if applicable
- Current stock fetched from `/inventory/{id}/adjust-info`
- New quantity prefilled with current; delta indicator updates live
- Movement type dropdown: Adjustment, Stocktake, Damage, Transfer In/Out, Return
- Notes textarea
- Save posts to `/inventory/adjust`; controller computes delta, logs movement

Close mechanisms: X button, Cancel button, backdrop click, Escape key.
All wired via addEventListener with named IDs (not inline onclick).

## Dashboard

New "Inventory health" card with three tiles:

- Low stock count (amber) → links to filtered inventory list
- Out of stock count (red) → links to filtered inventory list
- Units on hand (total across store)

## Repository Methods Added

    InventoryRepository::paginate($filters, $page, $perPage)
    InventoryRepository::movementsPaginate($filters, $page, $perPage)
    private InventoryRepository::buildListFilters($filters)
    private InventoryRepository::buildMovementFilters($filters)

## Controller Methods

    InventoryController::index           GET  /inventory
    InventoryController::movements       GET  /inventory/movements
    InventoryController::adjustInfo      GET  /inventory/{productId}/adjust-info
    InventoryController::adjust          POST /inventory/adjust
    InventoryController::exportCsv       GET  /inventory/export.csv
    InventoryController::exportMovementsCsv  GET /inventory/movements/export.csv

## Responsiveness Directive

Published docs/RESPONSIVENESS.md as a mandatory directive for all
future views. Every table wraps in `.ra-table-responsive-wrap`.
Secondary columns hide below 768px via `.ra-hide-sm`. Action buttons
stack vertically on narrow screens.

The directive applies platform-wide, not just to inventory.

## Bugs Hit and Fixed

### Bug 1 - Truncated heredoc writes

The `_adjust-modal.php` file was silently truncated by the shell at
~3334 bytes. This caused "Unexpected end of input" errors. Fixed by
splitting the write into two parts (HTML first, JS appended second).
Rule added to REMEMBER.md.

### Bug 2 - Inline onclick quote collision

The pencil icon's inline `onclick='RA_AdjustStock.open(...)'` broke
when the product name contained an apostrophe, truncating the HTML
attribute. Fixed by switching to `data-*` attributes + `addEventListener`.
This is Pattern 13 in REMEMBER.md.

### Bug 3 - Modal had no styles when rendered

The `_adjust-modal.php` relied on `.ra-modal` class definitions that
only existed inside other modal partials. The modal rendered inline
without overlay. Fixed by adding `.ra-modal` styles to `setup.css`
(global) plus inline critical styles on the panel.

### Bug 4 - Toggle display used wrong property names

`_tab_inventory.php` read `$valBool('manage_stock')` but the Product
model property is `manageStock`. The toggle always showed OFF even
when the DB had manage_stock = 1. Fixed by using camelCase property
names in the view (`manageStock`, `backordersAllowed`).

### Bug 5 - Save lost the current tab

`ProductController::update()` redirected to `/products/{id}/edit`
without a tab param, resetting to Basic. Fixed by adding a hidden
`current_tab` field to the form, JS that updates it on tab switch,
and the controller appending `?tab=X` to both success and error
redirects.

## Verification

End-to-end tested:

- Inventory list renders, filters work, status tiles link to filtered list
- Adjust modal opens with correct product name
- Current stock fetched correctly
- Delta indicator updates live
- Save adjustment persists and logs a movement
- Movement history shows all movement types with correct colors
- Movement filters (search, type, date range) work
- CSV exports download with correct columns
- Modal closes via X, Cancel, backdrop, Escape
- Dashboard inventory health card renders and links correctly
- Toggle state on product edit page matches DB
- Tab persists across save (success and validation error)
- Responsive table at 375px shows primary columns only

## What's Next

**Stage 006 is complete.** The full catalog and inventory back-office
ships: categories, brands, attributes, products, variations, inventory,
and movements.

Next options:

- **Stage 007** - B2B and customer groups. Customer groups, price
  lists, purchase orders, quote management, approval workflows.
  The differentiator vs consumer-only platforms.
- **Stage 008** - Storefront and search. The public-facing site.
  Product pages, cart, checkout, search, filters, reviews, wishlist.

The roadmap in docs/ROADMAP.md orders them 007 then 008.
