# Catalog & B2B Overview

**Purpose:** One-page reference for everything built in Stages
006 (Catalog) and 007 (B2B). Every feature, table, route, and
concept in one place.

**Audience:** Developers joining the project. Read this after
ARCHITECTURE.md and NAMING-CONVENTION.md to understand what the
current codebase actually does.

---

## What the Catalog Does

An admin can:

1. Create attributes (Color, Size, Material) with values
2. Create hierarchical categories
3. Map attributes to categories (variant axes vs informational)
4. Create brands
5. Create simple or variable products
6. Assign products to categories, brands, tags
7. Auto-generate variations from category attributes
8. Edit variations with search, filters, bulk actions, pagination
9. Track stock across products and variants
10. Adjust stock through a modal with logged movements
11. Review the immutable movement ledger
12. Export inventory and movements to CSV
13. See inventory health at a glance on the dashboard
14. Create customer groups (Retail, Wholesale, VIP, Staff)
15. Set per-group price lists with tier pricing
16. Set minimum order quantities per product

---

## The Data Model

### Store anchor

    commerce_stores
      ├── commerce_store_domains
      ├── commerce_store_users
      └── commerce_store_currencies

Every tenant-scoped table carries `store_id NOT NULL` FK back here.

### Catalog structure

    commerce_categories          (self-referential tree)
    commerce_brands
    commerce_products            (simple or variable)
      ├── commerce_product_variants
      │     └── commerce_variant_attribute_values
      ├── commerce_product_images
      ├── commerce_product_videos
      └── commerce_product_attribute_values

    commerce_attribute_definitions   (global vocabulary)
      └── commerce_attribute_values

    commerce_product_categories      (product ↔ category junction)
    commerce_product_tags
    commerce_product_tag_links
    commerce_category_attributes     (category → attribute mapping)

### Inventory

    commerce_inventory               (one row per sellable unit)
      quantity, reserved, available (GENERATED), low_stock_threshold

    commerce_inventory_movements     (append-only ledger)
      movement_type, quantity_delta, quantity_after,
      reference_type, reference_id, reason, created_by

### Customers (Stage 007B)

    commerce_customers
      ├── commerce_customer_addresses
      ├── commerce_customer_password_resets
      └── commerce_customer_sessions

    commerce_customer_groups
      └── commerce_customer_group_prices   (with tier support)

### Orders (Stage 007B)

    commerce_orders
      ├── commerce_order_items
      ├── commerce_order_status_history
      ├── commerce_order_notes
      ├── commerce_order_taxes
      └── commerce_order_shipments

---

## Key Concepts

### Attributes are global vocabulary

`commerce_attribute_definitions` has **no store_id**. Color is
Color across every store. What differs per store is which
attributes are mapped to which categories, and which values each
attribute has.

### Categories declare which attributes are relevant

`commerce_category_attributes` is a junction with direction
Category → Attribute. A category declares which attributes are
relevant to products in it. Multiple categories can reference the
same attribute. Deleting a category cascades only its mappings;
the attribute survives.

### Products inherit attributes from categories

A variable product's variant axes are the union of variant-axis
attributes (is_variant_axis = 1) across every category the
product belongs to. No per-product attribute selection.

### Variations auto-generate

When a variable product's variations page loads and the product
has no variations yet, and all mapped variant axes have at least
one value, the Cartesian product is inserted as new variants with
the product's base price and zero stock.

### Drift detection

When the current expected combinations differ from existing
variations, the page shows a warning listing missing and obsolete
counts, with a Regenerate button (type-to-confirm).

### Variations are archived, not deleted

Variations auto-generate; deleting one means it regenerates.
Archive hides the variation from the storefront but keeps its
SKU, price, and stock intact.

### Inventory ledger is immutable

`commerce_inventory_movements` is append-only. Every stock change
is a new row. Corrections use compensating movements.

### One customer group per customer

A customer belongs to exactly one group. That group's price list
applies to them. Falls back to base price if no override exists.

### Price resolution fallback chain

1. Variant + group + tier matching quantity
2. Variant + group (any tier, base override)
3. Product + group + tier
4. Product + group (base override)
5. Base product/variant price

Anonymous visitors (no group) always see base price.

---

## Admin Routes

### Catalog

    /commerce/ca-admin/products                  list + filters
    /commerce/ca-admin/products/create           new
    /commerce/ca-admin/products/{id}/edit        edit
    /commerce/ca-admin/products/{id}/variants    variation manager
    /commerce/ca-admin/categories                tree + filter
    /commerce/ca-admin/brands                    list
    /commerce/ca-admin/attributes                attribute CRUD
    /commerce/ca-admin/inventory                 stock list
    /commerce/ca-admin/inventory/movements       ledger

### B2B

    /commerce/ca-admin/customer-groups           group list
    /commerce/ca-admin/customer-groups/{id}/prices    price list

---

## Public Routes (Stage 008+)

    /                              storefront home
    /products/{slug}               product detail
    /categories/{slug}             category browse
    /collections/{slug}            curated collections
    /cart                          shopping cart
    /checkout                      checkout flow
    /account/*                     customer account
    /blog/{slug}                   blog posts
    /pages/{slug}                  CMS pages

---

## Key Services

### CatalogService

Orchestrates catalog repositories for cross-entity operations.
Provides `dashboardCounts()`, `productForEdit()`,
`categoriesForSelect()`, `brandsForSelect()`.

### PricingService

Resolves effective price for (product, variant, group, quantity).
Used by both admin preview and storefront checkout.

Method: `resolvePrice($productId, $variantId, $customerGroupId,
$quantity)` returning unit_price, compare_at_price, base_price,
source, tier_label, discount_percent.

### InventoryService (via InventoryRepository)

The ONLY way to change stock is `adjust()`. It wraps everything
in a transaction with `SELECT ... FOR UPDATE` locking the row.
Every adjustment appends to the movement ledger.

### ValidationService (via Support/Validator)

Every controller validates through the shared helper. Rules
supported: required, maxLength, minLength, url, email, inArray,
timezone, positiveInt, nonNegativeNumber, hexColor, countryCode,
slug.

---

## Design System

All admin pages use the same components:

- `ra-card` — container for content sections
- `ra-table` — responsive data table
- `ra-btn` variants: primary, outline, danger
- `ra-icon-btn` — icon-only button
- `ra-badge` variants: success, muted, warning, danger
- `ra-modal` — centered overlay with backdrop
- `ra-sticky-actions` — fixed action bar at viewport bottom
- `ra-alert` variants: success, error, warning, info
- `ra-form-control`, `ra-form-select` — themed inputs

Responsive rules from `docs/RESPONSIVENESS.md`:
- `.ra-table-responsive-wrap` wrapper for tables
- `.ra-hide-sm` class hides columns below 768px
- Action buttons stack vertically on narrow screens

---

## Terminology

| Term | Meaning |
|---|---|
| Product | A catalog item (simple or variable) |
| Variant | A concrete sellable combination (Red / M) |
| Variation | Same as variant; user-facing term |
| Attribute | A product option (Color, Size, Material) |
| Attribute value | One option value (Red, M, Cotton) |
| Variant axis | An attribute that generates variants (is_variant_axis = 1) |
| Simple product | A product with no variants |
| Variable product | A product with one or more variants |
| Category | A grouping of products (tree structure) |
| Brand | A manufacturer or label |
| Group | A customer segment (Retail, Wholesale, VIP, Staff) |
| Price list | Per-group price overrides with optional tiers |
| MOQ | Minimum order quantity per product |
| PO | Purchase order (net terms checkout) |
| Net terms | Pay within N days of order (30, 60, 90) |
| Drift | Category attributes changed after variations were generated |

---

## What's Not Built Yet

- Checkout (Stage 008E)
- Forms builder (Stage 009)
- Full payment provider integrations (Stage 010)
- Shipping zone editor (Stage 010)
- Tax class editor (Stage 010)
- Subscriptions, gift cards, BOPIS (Stage 011)
- Theme engine + customizer (Stage 012)
- 20+ themes (Stage 012)

---

## Status

Stage 006 (Catalog): complete.
Stage 007A (B2B foundation): complete.
Stage 007B (Approval + PO): complete.
Stage 008A (Storefront shell): complete.
Stage 008B (Storefront visual identity): complete.
Stage 008B.2 (Extended visual pass): complete.
Stage 008C (Customer auth + account): complete.
Stage 008D (Cart): complete.
Stage 008D.1 (Variant-level inventory): complete.
Stage 008D.2 (Per-product inventory page): complete.

Next: Stage 008E (Checkout).
