# Stage 006C - Categories Admin CRUD

**Date:** 2026-09-25
**Status:** Complete

## Summary

First admin module shipped on top of the catalog data layer. Full
CRUD for categories with hierarchical tree support, archive/
restore, soft-delete with dependent-count confirmation, and a
reusable navigation pattern.

Also introduced collapsible sidebar groups with localStorage
persistence - the sidebar now remembers which groups the user has
open across page loads.

## Files Created

    app/Commerce/Controllers/Admin/CategoryController.php
    resources/views/admin/categories/index.php
    resources/views/admin/categories/edit.php
    resources/views/admin/categories/_delete-modal.php

## Files Modified

    routes/commerce.php                     (added category routes)
    resources/views/partials/sidebar.php    (rewritten with grouped nav)
    public/assets/css/setup.css             (added nav group styles)

## Category CRUD

### Routes

    GET    /commerce/ca-admin/categories                    index (tree)
    GET    /commerce/ca-admin/categories/create             create form
    POST   /commerce/ca-admin/categories                    store
    GET    /commerce/ca-admin/categories/{id}/edit          edit form
    POST   /commerce/ca-admin/categories/{id}               update
    POST   /commerce/ca-admin/categories/{id}/archive       archive
    POST   /commerce/ca-admin/categories/{id}/restore       restore
    POST   /commerce/ca-admin/categories/{id}/delete        soft-delete
    GET    /commerce/ca-admin/categories/{id}/delete-info   JSON for modal

### CategoryController

Reads:
- `index` - tree view with `?archived=1` toggle
- `showCreate` - blank form with parent dropdown
- `showEdit` - populated form, parent dropdown excludes self and
  descendants (prevents hierarchy cycles)
- `deleteInfo` - JSON with dependent counts for confirm modal

Writes:
- `store` - create, auto-slugifies if slug blank
- `update` - with self-parent and cycle validation
- `archive` / `restore` / `destroy`

Helpers:
- `collectInput` - form normalization
- `validate` - name, slug format/uniqueness, self-parent
- `parentOptions` / `flatten` - hierarchical dropdown
- `slugify`

### Validation enforced

- Name required
- Slug required (auto-generated from name if blank)
- Slug format: lowercase letters, digits, hyphens
- Slug unique per store (checked via `findBySlug` + exclude self)
- Parent cannot be self (also enforced by model)
- Parent cannot be a descendant of self (prevents cycles)

The "cannot be own parent" rule is enforced in the model layer
because MySQL 8 rejects CHECK constraints referencing
AUTO_INCREMENT columns - a limitation we hit in Stage 006A.

### Delete semantics

- **Archive** hides the category from active lists; still visible
  on historical orders. Restorable.
- **Soft-delete** sets `deleted_at`; category disappears entirely.
  Recoverable via DB (no restore UI yet).
- **Delete confirmation** shows dependent counts:
  - Number of products in this category
  - Number of descendant categories
  - Products keep other category assignments
  - Subcategories become top-level

## Sidebar Rework

Grouped navigation with collapsible sections:

    Dashboard (flat)
    BUSINESS
      - Setup
    CATALOG
      - Products (locked until 006E)
      - Categories
      - Brands (locked until 006D)
      - Attributes (locked until later)
    SALES
      - Orders (locked)
      - Customers (locked)
      - Coupons (locked)
    SETTINGS
      - Store Settings
      - Users
      - Roles

### Features

- Groups with labels render as collapsible sections
- Groups without labels (Dashboard) render as flat links
- Active group auto-expands on first visit
- User's open/closed state persists to `localStorage['ra-nav-groups']`
- Server default applied when no localStorage state exists
- Chevron rotates to indicate state
- Items with `locked: true` show greyed with lock icon when
  `setupComplete` is false

### Design decision

Sidebar state is stored in browser localStorage, not the database.
Rationale:
- It is a per-browser preference, not an account-wide setting
- No schema change needed
- No settings page needed
- Persists across page loads without any AJAX round trip

If we later build user preferences (Stage 007+), this can be
promoted to server-side storage.

## Verification

End-to-end tested:

    1. Create root category "Clothing"         OK
    2. Create child "Shirts" under Clothing    OK
    3. Tree renders with indentation           OK
    4. Edit category                            OK
    5. Archive category                         OK
    6. Show/hide archived toggle               OK
    7. Restore archived category               OK
    8. Delete confirmation modal (0 dependents) OK
    9. Delete with dependent products          OK (warning shown)
    10. Slug auto-generation                    OK
    11. Slug uniqueness validation              OK
    12. Sidebar group expand/collapse           OK
    13. Sidebar state persists across nav       OK

## Known Issues / Deferred

- Em dash sweep ran on views, no remaining em dashes in new files
- Rows use Bootstrap table styling (`ra-table`, `ra-badge`,
  `ra-icon-btn`). Some may fall back to raw styling until we add
  the missing CSS. Cosmetic, not functional.
- Categories tree renders flat with indentation rather than as an
  expandable tree. Fine for now; may add collapsible tree in a
  future stage.
- Attribute management (with variants support) is deferred to
  006F.

## What's Next

Stage 006D - Brands admin CRUD.
Same pattern as Categories but simpler (no tree, no parent).
Estimated 4 files + routes + sidebar unlock.
