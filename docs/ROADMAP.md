# Anaya Global Commerce Platform - Roadmap

**Status:** Locked 2026-09-27
**Supersedes:** prior roadmap, updated for storefront split

---

## Purpose

This document is the canonical build sequence for the commerce
platform. Stages are ordered so that each one depends only on what
already exists. Every stage delivers a working, tested module.

Adding a stage or reordering requires updating this document first.

---

## Completed Stages

| Stage | Title                                  | Completed  |
|-------|----------------------------------------|------------|
| 001   | Project initialization & foundation    | 2026-09-25 |
| 002   | Framework import & Recruit cleanup     | 2026-09-25 |
| 003   | Database schema (core + commerce)      | 2026-09-25 |
| 004   | Foundation rewrite & first login       | 2026-09-25 |
| 005   | Admin shell & setup hub                | 2026-09-25 |
| 006A  | Catalog migrations                     | 2026-09-25 |
| 006B  | Catalog models & repositories          | 2026-09-25 |
| 006C  | Categories admin CRUD                  | 2026-09-25 |
| 006D  | Brands admin CRUD                      | 2026-09-26 |
| 006E  | Products list & editor                 | 2026-09-26 |
| 006F  | Product variations                     | 2026-09-26 |
| 006G  | Inventory overview                     | 2026-09-26 |
| 007A  | Customer groups & price lists          | 2026-09-26 |
| 007B  | Purchase orders & approval             | 2026-09-26 |
| 008A  | Storefront shell & Store model         | 2026-09-27 |
| 008B  | Storefront visual identity & catalog   | 2026-09-27 |
| 008B.2| Extended visual pass                   | 2026-09-27 |
| 008C  | Customer registration, login, account  | 2026-09-27 |
| 008D  | Cart                                   | 2026-09-28 |
| 008D.1| Variant-level inventory                | 2026-09-28 |
| 008D.2| Per-product inventory page          | 2026-09-28 |

Also completed outside the staged sequence:

  - Git initialization (2026-09-26)
  - Backup remote + Google Drive Desktop sync (2026-09-26)
  - Static analysis tooling: syntax linter, pre-commit hook
    (2026-09-27)
  - PHPStan level 8 pass: 266 errors resolved across three
    passes (levels 6, 7, 8), six real runtime bugs caught,
    zero errors remaining, no baseline (2026-09-27)
  - docs/BASELINE.md + 16 section files documenting verified
    codebase conventions

---

## In Progress

### Stage 008E - Checkout

**Goal:** Full checkout flow with B2B purchase order support.

**Deliverables:**

- `Storefront/CheckoutController` - multi-step checkout
- Order creation (transactional, with inventory reservation)
- Order confirmation page + email queue
- B2B path: purchase order / net terms for approved customers
- Stub payment providers: COD, bank transfer, purchase order
- Shipping method stub (real engine in 010)

**Depends on:** Stage 008D (cart), Stage 008D.1 (variant
inventory), Stage 008D.2 (per-product inventory page)

## Planned Stages

### Stage 007C - Procurement (Suppliers, LPOs, GRN, Expenses)

**Goal:** Buy-side inventory management. Track what was
ordered from whom, at what cost, and when it arrived. Feed
the existing inventory ledger with `transfer_in` movements
that reference an LPO. Give the merchant an expense view
(cost of goods received plus supplier invoices) that answers
"what did I spend this month and on what."

**Why this stage exists now:** the inventory ledger already
records every `adjustment` and `sale` with a signed delta
and a `reference_type`/`reference_id` back-pointer. The
`transfer_in` movement type is already defined but has no
producer. Procurement is that producer. The product model
already carries `cost_price`, so margin is one join away.
The missing piece is the supplier-facing workflow.

**Deliverables:**

  Schema:
    commerce_suppliers              (vendor master)
    commerce_suppliers_contacts     (optional, multiple per vendor)
    commerce_purchase_orders        (LPO header: supplier, status,
                                     expected_at, currency, terms)
    commerce_purchase_order_items   (line items: product, variant,
                                     qty, unit_cost, received_qty)
    commerce_goods_receipts         (GRN header: PO link, received_at,
                                     received_by)
    commerce_goods_receipt_items    (per-line received qty, batch,
                                     expiry where relevant)
    commerce_supplier_invoices      (invoice header: supplier,
                                     number, amount, due_at, status)
    commerce_supplier_invoice_lines (optional, line-level match to GRN)

  Admin:
    /commerce/ca-admin/suppliers                 list + CRUD
    /commerce/ca-admin/suppliers/{id}            detail (activity)
    /commerce/ca-admin/purchase-orders           list + filters
    /commerce/ca-admin/purchase-orders/create    new LPO
    /commerce/ca-admin/purchase-orders/{id}      edit / issue / receive
    /commerce/ca-admin/goods-receipts            list
    /commerce/ca-admin/goods-receipts/{id}       view + post to inventory
    /commerce/ca-admin/supplier-invoices         list + match
    /commerce/ca-admin/supplier-invoices/{id}    detail + payment status
    /commerce/ca-admin/expenses                  aggregate view

  Services:
    PurchaseOrderService   - create, issue, receive line qty
    GoodsReceiptService    - post GRN to inventory via
                             InventoryRepository::adjust() with
                             movement_type='transfer_in' and
                             reference to the PO/GRN
    SupplierInvoiceService - match invoice lines to GRNs, mark paid

  Reports:
    Expense summary by month / supplier / category
    COGS per order (uses commerce_products.cost_price at time of
    sale, snapshot preserved on commerce_order_items)
    Stock on order (qty ordered minus qty received, per product)
    Supplier performance (on-time delivery, fill rate)

**Depends on:** Stage 006 (catalog + inventory ledger). Can run
in parallel with 008D-008G, but the ledger will not have `sale`
movements until 008E ships, so COGS reports will under-report
until then.

**Blocked by nothing, but sequenced after 008D.2.** The
detailed test pass for 008D.2 must finish before this stage
opens.

**Open questions:**
  - Multi-currency POs (supplier invoices in USD, books in KES)?
  - Batch/lot tracking (for expiry-sensitive categories)?
  - Landed cost (freight, duty, other charges allocated per line)?
  - Partial receipts (order 100, receive 60 now, 40 later)?
  - Returns to supplier?

These are captured as Open Questions, to be resolved in the
007C plan document before code is written.

### Stage 008F - Order Lifecycle (Customer-Facing)

**Goal:** Customer can view their orders, order detail, reorder.

**Deliverables:**

- `Storefront/OrdersController` - list, view detail
- Order status display, shipment tracking stub

**Depends on:** Stage 008E (checkout)

### Stage 008G - PWA, Search, Storefront Polish

**Goal:** Close the storefront loop with PWA, search, and the
deferred polish items.

**Deliverables:**

- Service worker + offline shell
- PWA manifest per store
- Full-text search across products
- **Out-of-stock product hiding** — admin setting, default
  off. When on, out-of-stock products are hidden from listing
  pages (catalog, category, home sections). Direct product
  URLs still work and show a "Sold out" state so bookmarks
  and SEO survive. Storewide toggle. Variable products count
  as in stock if ANY variant is sellable. Implementation:
  new `commerce.catalog.hide_out_of_stock` setting, conditional
  inventory join in ProductRepository::paginatePublished().
- **Categories with no visible products hidden from the
  storefront** — home grid, category index, header nav.
  Categories with zero published in-stock products (and no
  children with any) disappear from the storefront but remain
  in the admin. Direct category URL still works but shows
  an empty state.
- **Product image gallery** — clicking a thumbnail swaps the
  main image on the product detail page. Small JS enhancement.
- **Header search bar** — a full-width search input in the
  middle of the main header row (between logo and actions).
  Currently the middle of the row is empty space.
- `/offline` page visual redesign
- Attribute-based faceted search (color, size, etc.)
- Responsive audit at 375 / 768 / 1024

**Depends on:** Stages 008A-008F

### Stage 009 - Forms & Submissions

**Goal:** Native form builder as a differentiator.

**Deliverables:**

- commerce_forms, commerce_form_fields, commerce_form_submissions
- Field type registry with 12+ input types
- Conditional logic engine
- Form builder admin with drag-drop and live preview
- Submission management with filters and CSV export
- Form rendering in themes
- Webhook + notification on submission

**Depends on:** Stages 008 (storefront), 007 (customers)

### Stage 010 - Payments & Shipping (Deep)

**Goal:** Full payment provider integrations and shipping engine.

**Deliverables:**

- Payment provider registry
- Reference implementations: M-Pesa, Stripe, PayPal, Pesapal,
  Flutterwave, Manual, COD, Bank Transfer
- Webhook handling per provider, refund processing
- Shipping zones, methods, rates (flat, free-over, weight-based,
  price-based, local pickup)
- Shipping carrier integrations (DHL, FedEx optional)
- Tax engine: tax classes, rates per region, inclusive/exclusive

**Depends on:** Stage 008E (checkout)

### Stage 011 - Advanced Commerce + Store Builder

**Goal:** Subscriptions, gift cards, multi-location inventory,
and the store builder admin UI.

**Deliverables:**

- Subscriptions: recurring products, billing cycles
- Gift cards: purchase, redeem, balance tracking
- Multi-location inventory: warehouses, transfers
- BOPIS: buy online pick up in store
- Store credit, loyalty points
- **Store builder admin UI:**
  - `commerce_homepage_sections` table (section type, order,
    config JSON)
  - `commerce_navigation_menu_items` table (header + footer nav)
  - `commerce_store_trust_badges`, `commerce_store_social_links`
  - Drag-drop section editor
  - Live preview using the storefront views
  - Section type registry

**Depends on:** Stages 006-010

### Stage 012 - Theme Engine & Page Builder

**Goal:** Full storefront theming system with 20+ industry themes.

**Deliverables:**

- Theme contract (directory structure, theme.json manifest)
- Theme registry: install, activate, list, preview
- Theme customizer: colours, fonts, layout, live preview
- Page builder: section registry, drag-drop ordering
- Section types: hero, product grid, category grid, testimonials,
  rich text, video, banner, newsletter, FAQ, form, spacer
- Navigation management
- At least 20 themes: Modern, Minimal, Fashion, Luxury,
  Electronics, Furniture, Grocery, Restaurant, Services, Beauty,
  Home & Living, Gifts, Pharmacy, Wholesale/B2B, Kids, Sports,
  Auto Parts, Real Estate, Digital Products, Marketplace

**Depends on:** All prior stages

---

## Backlog (Not Yet Scheduled)

Items that have been deferred during development but not yet
assigned to a stage. Each entry should note why it exists and
where it will likely land.

### Configurable Search Filters

Admin should be able to add or remove filterable fields (brands,
categories, price ranges, attribute facets) from the storefront
search without code changes. Defaults remain the current set.

**Likely stage:** 010 or 011.

### Store Builder Section Reorder

The `data-sf-section` attributes are already in place on every
homepage section. The UI to reorder them from the admin panel
lands with Stage 011 (Advanced Commerce + Store Builder).

### Media Library

Real product, category, and hero photography uploads require a
media library that doesn't exist yet. Currently the storefront
uses picsum.photos placeholders throughout.

**Likely stage:** 009 (Forms & Submissions infrastructure) or
a dedicated sub-stage.

### Conversion-Focused Product Recommendations

Product detail pages and the cart page become conversion
surfaces: each renders an advertisement-style product grid
showing related / complementary products from the same
category. The grid is styled as a merchandising block (not
just a list of links) with price, discount badge, star rating,
review count, and fulfillment badge, in the pattern used by
Kilimall, Jumia, and similar marketplaces.

Surfaces:

  - Product detail page: "You May Also Like" style grid below
    the related-products block. Auto-derived from the product's
    categories.
  - Cart page: "You May Also Like" grid below the cart summary.
    Derived from the union of the categories of the products
    currently in the cart. This is where a customer is closest
    to checkout, so the surface has the highest conversion
    potential.

Auto-derivation first (no admin curation). The customer's
existing category structure and published products drive the
grid. Fallback chain: same category > sibling category > best
sellers.

Admin curation (which products to promote, override order,
pin specific items) lands later in Stage 011 (Store Builder).
The data-sf-* attributes on the grid let the store builder
toggle the block, set the column count, and choose the source
(related / cross-sell / best sellers).

Likely stage: 008G (PWA, Search, Storefront Polish) for the
auto-derived version, 011 for the admin-curated version.

Reference: Kilimall product detail + cart page cross-sell
pattern, supplied by the customer as a design concept.

### Data Import (CSV / Excel)

A merchant setting up a store needs to bulk-load products,
categories, brands, attributes, variants, inventory, customers,
customer groups, and (once 007C ships) suppliers. Today every
record is entered by hand through the admin UI. For a store
with 200+ SKUs that is days of manual work; for a store
migrating from another platform it is not feasible at all.

The export side already exists (inventory CSV, movements CSV).
Import is the symmetric missing operation.

Shape:

  - Upload: CSV first (universally supported). XLSX later.
  - Column mapping UI: match uploaded columns to entity fields.
  - Validation pass: per-row errors, nothing written.
  - Dry-run preview: show what will be created / updated.
  - Commit: transactional per chunk, with a batch id.
  - Idempotency: match on natural key (SKU, slug, email).
    Re-uploading the same file updates existing rows.
  - Failed-row export: download a CSV with only the rows that
    failed and a reason column, fix, re-upload.
  - Rollback: a batch can be reversed.

Supported entities (dependency order - later entities
reference earlier ones):

  brands -> categories -> attributes -> attribute values ->
  products -> variants -> inventory -> customers ->
  customer groups -> price lists -> suppliers (after 007C)

Home stage: not yet scheduled. Likely 009 (Forms &
Submissions) as a sub-stage, or a dedicated stage between 010
and 011 once the catalog and B2B schemas are frozen. Do NOT
build before 007C and 008E are complete - the schema must be
stable before an importer writes to it, or the importer gets
written twice.

Reference: the inventory CSV export (Stage 006G) is the
pattern the importer should round-trip against.

### Storefront-Hosted Hero Asset

The hero section is a CSS-composed three-column layout. Real
editorial photography (Eiffel Tower, airplane, globe, phone
mockup, woman with bags) will be supplied by the customer.
The swap is a one-file change when the asset is ready.

**Likely stage:** when the customer supplies the asset.

### Session Table vs File Sessions

Currently sessions are file-based (satisfies shared cPanel
hosting). If we ever move to a host with better DB access,
session storage could move to a table. Not a priority.

**Likely stage:** not scheduled.

---

## Feature Toggle System

All new features ship behind a toggle in `core_feature_flags`.

Scope: global | store | customer_group

States: disabled | admin_only | beta | enabled

Naming: `{module}.{feature}.{version}`

Resolution order (most specific wins):

1. Customer group override
2. Store override
3. Global setting
4. Hardcoded default (disabled)

Fail-closed: a toggle lookup that fails returns disabled.

---

## Not in Scope

- WordPress / WooCommerce / Shopify integration
- Node.js, npm, build steps
- Docker, Kubernetes, Redis, Memcached
- PostgreSQL, MongoDB
- SaaS mode (customer owns the install)
- Marketplace (multi-vendor) in the core - future module
- Mobile native apps - PWA covers this
- AI features - future module

---

## Reordering Rule

A stage may be pulled forward only if:

1. All its dependencies are complete
2. It does not break a later stage's dependency
3. This document is updated in the same commit

No new stage may be added without updating the Completed Stages
table and the "What's Next" pointer.
