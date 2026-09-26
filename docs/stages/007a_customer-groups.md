# Stage 007A - Customer Groups and Price Lists

**Date:** 2026-09-26
**Status:** Complete

## Summary

First half of Stage 007 (B2B). Customer groups, price lists, tier
pricing, and MOQ on products. The PricingService resolves the
effective price for any customer/group/quantity combination.

Second half (007B) will add purchase orders and the B2B approval
workflow.

## Files Created

    database/migrations/0204_create_customer_groups.sql
    database/seeders/0005_seed_customer_groups.sql
    app/Commerce/Models/CustomerGroup.php
    app/Commerce/Models/CustomerGroupPrice.php
    app/Commerce/Repositories/CustomerGroupRepository.php
    app/Commerce/Repositories/CustomerGroupPriceRepository.php
    app/Commerce/Services/PricingService.php
    app/Commerce/Controllers/Admin/CustomerGroupController.php
    resources/views/admin/customer_groups/index.php
    resources/views/admin/customer_groups/edit.php
    resources/views/admin/customer_groups/prices.php

## Files Modified

    app/Commerce/Models/Product.php                     (minOrderQuantity)
    app/Commerce/Controllers/Admin/ProductController.php  (MOQ in collectInput + hydrate)
    app/Commerce/CommerceServiceProvider.php            (3 new bindings)
    resources/views/admin/products/_tab_pricing.php     (MOQ input)
    resources/views/partials/sidebar.php                (Customer Groups nav)
    routes/commerce.php                                 (customer group routes)

## Schema

### commerce_customer_groups

- id, store_id, slug, name, description
- is_default (only one per store, enforced in app)
- is_b2b (differentiates retail from wholesale UX)
- requires_approval (blocks ordering until admin approves)
- sort_order, status, timestamps

### commerce_customer_group_prices

- id, store_id, customer_group_id, product_id, variant_id
- price, compare_at_price (both nullable)
- min_quantity, max_quantity (max nullable = unbounded tier)
- variant_id = 0 sentinel for simple products
- Unique key: (customer_group_id, product_id, variant_id, min_quantity)

### commerce_products

- Added min_order_quantity INT UNSIGNED NOT NULL DEFAULT 1

## Seeded Groups

- Retail (default, non-B2B, no approval)
- Wholesale (B2B, requires approval)
- VIP (non-B2B, no approval)

Seeded via database/seeders/0005_seed_customer_groups.sql.

## PricingService

Method: resolvePrice(productId, variantId, customerGroupId, quantity)

Fallback chain (most specific wins):

1. Variant + group + tier matching quantity
2. Variant + group (any tier, base override)
3. Product + group + tier
4. Product + group (base override)
5. Base product/variant price

Anonymous visitors (no group) always get base price.

Returns:

    [
        'unit_price'        => float,
        'compare_at_price'  => ?float,
        'base_price'        => float,
        'source'            => 'base'|'group',
        'tier_label'        => ?string,
        'discount_percent'  => int,
    ]

## Admin UI

### Customer Groups list (/commerce/ca-admin/customer-groups)

- Table: Group (with Default/B2B/Approval badges), Slug, Type, Customers, Overrides, Status, Actions
- Show archived toggle
- Actions: view prices, edit, archive (guarded for default group)
- Responsive: hides Slug and Type on narrow screens

### Customer Group edit

- Basic: name, slug, description, sort_order
- Behavior: is_default, is_b2b, requires_approval (toggle switches)
- Link to price list (edit mode only)
- Sticky action bar with Cancel + Save

### Price List (/commerce/ca-admin/customer-groups/{id}/prices)

- Add override form: product, min_qty, max_qty, price, compare-at
- Table: Product, Variant, Base price, Group price (with % saved in green), Compare-at, Tier, Actions
- Delete override with confirmation
- Tiers display as "All qty", "10 - 49", "50+"

### Product editor Pricing tab

- New field: "Minimum order quantity" (int, default 1)
- Hint: "Customers must add at least this many units. Useful for B2B/wholesale."

## Design Decisions (Industry Standard)

1. One group per customer (Shopify B2B model)
2. Price lists per group (BigCommerce model)
3. Tier pricing lives in the price list, not the product
4. MOQ is per product
5. Default group cannot be archived
6. Setting a group as default clears the flag from other groups

## Verification

End-to-end tested:

- Three seed groups appear in list
- Create Staff group
- Toggle Default: only one group is default at a time
- Archive guard blocks archiving the default
- Add price overrides with tier pricing
- Delete override
- Product MOQ saves and persists
- Tab persistence works from Pricing tab

## What's Next

Stage 007B - Purchase Orders and Approval.

- commerce_purchase_orders table
- Approval workflow (customer signup queue)
- PO checkout flow
- Admin approval queue
- Customer portal PO view
