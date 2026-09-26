# Stage 006B - Catalog Models & Repositories

**Date:** 2026-09-25
**Status:** Complete

## Summary

Built the full data access layer for the catalog: 6 models and 5
repositories (4 new + StoreRepository from Stage 005), plus a
CatalogService orchestrator. All 8 classes resolve from the
container. The catalog is now queryable and writable from
application code.

## Files Created

### Models

    app/Commerce/Models/Category.php           (2.7 KB)
    app/Commerce/Models/Brand.php              (2.0 KB)
    app/Commerce/Models/Product.php            (6.2 KB)
    app/Commerce/Models/ProductVariant.php     (3.7 KB)
    app/Commerce/Models/Inventory.php          (2.4 KB)
    app/Commerce/Models/InventoryMovement.php  (2.1 KB)

### Repositories

    app/Commerce/Repositories/CategoryRepository.php   (7.0 KB)
    app/Commerce/Repositories/BrandRepository.php      (5.6 KB)
    app/Commerce/Repositories/ProductRepository.php    (16.6 KB, 487 lines)
    app/Commerce/Repositories/InventoryRepository.php  (12.0 KB, 330 lines)

### Services

    app/Commerce/Services/CatalogService.php           (3.5 KB)

### Provider

    app/Commerce/CommerceServiceProvider.php           (updated)

## Design Patterns Established

### Model pattern

- `final class` with public typed properties
- Static `fromRow(array $row): self` is the only hydration path
- Business-rule helpers (`isOnSale()`, `discountPercent()`,
  `isLowStock()`, `canSell()`) live on the model
- Validation helpers (`assertNotOwnParent()`) that enforce rules
  MySQL can't (because of AUTO_INCREMENT CHECK constraints)
- No active record pattern. No magic. Models are pure DTOs.

### Repository pattern

- Every repository extends `CommerceRepository`
- `$this->storeId()` on every query - no cross-store leaks possible
- `findBy*` returns `?Model`; list methods return `array<int, Model>`
- `create()` / `update()` / lifecycle methods (`archive`, `restore`,
  `softDelete`)
- Junction operations use replace-set semantics (`syncCategories`,
  `syncTags`)
- Small `count*()` methods for dashboards

### Service pattern

- `CatalogService` orchestrates multiple repositories
- Single-repository reads may go directly to the repository
- Multi-entity operations go through the service
- Service exposes getters for direct repository access when needed

## Key Implementation Details

### ProductRepository

- Paginated list with filters: status, brand_id, category_id,
  featured, type, text search
- `EXISTS` subquery for category filter (uses junction index)
- `LIKE`-based text search for now; FULLTEXT activation deferred to
  Stage 008 (storefront search)
- `slugExists` / `skuExists` with `excludeId` for edit forms
- `publish` / `unpublish` / `archive` / `restore` / `softDelete`
- `syncCategories` / `syncTags` - delete all then insert, within a
  transaction
- Dashboard counts: total, published, draft, low stock

### InventoryRepository

- The ONLY way to change stock is `adjust()`
- `adjust()` wraps everything in a transaction with
  `SELECT ... FOR UPDATE` locking the stock row
- Every `adjust()` appends to the movement ledger
- If either the quantity update or the movement insert fails, both
  roll back - the ledger never drifts from current stock
- `reserve()` / `release()` for cart reservations (doesn't move stock,
  only increments reserved)
- `forUnits(array)` for bulk fetch on cart page (avoid N+1)
- `history()` for the movement log
- `listLowStock()` / `listOutOfStock()` for dashboard feeds

### CatalogService

- `dashboardCounts()` - aggregated metrics across repositories
- `productForEdit()` - loads product + categories + tags + inventory
  in one call
- `categoriesForSelect()` / `brandsForSelect()` - dropdown data
- Getters for direct access: `products()`, `categories()`,
  `brands()`, `inventory()`

## Verification

Smoke test resolved all 8 classes from the container:

    StoreContext                 OK
    StoreRepository              OK
    StoreService                 OK
    CategoryRepository           OK
    BrandRepository              OK
    ProductRepository            OK
    InventoryRepository          OK
    CatalogService               OK

    All 8 classes resolved.

## Type Contracts

- Models to DB: models never call the DB directly. Only repositories
  do.
- Controllers to repositories: may go directly for single-entity reads
  (`ProductRepository::findById`).
- Controllers to service: for multi-entity operations
  (`CatalogService::productForEdit`).
- Cross-store access: impossible. Every query scoped by `storeId()`
  which throws if no store is resolved.

## What's Next

Stage 006C - Categories admin CRUD.
Controllers, views, routes, sidebar unlock.

After 006C:
- 006D - Brands admin CRUD
- 006E - Products list & editor
- 006F - Product variants
- 006G - Inventory overview
- 006H - Stage 006 doc
