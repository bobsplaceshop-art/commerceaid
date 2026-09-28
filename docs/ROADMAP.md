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

### Stage 008D - Cart

**Goal:** Session-based cart for guests, database-persisted cart
for logged-in customers. Cart merge on login.

**Deliverables:**

- `Storefront/CartController` - add, update, remove, view
- `CartService` - cart state management
- Migrations: `commerce_carts`, `commerce_cart_items`
- Mini-cart in header
- AJAX add-to-cart endpoint
- Cart page with quantity controls

**Depends on:** Stage 008C (customer auth for persistent carts)

---

## Planned Stages

### Stage 008E - Checkout

**Goal:** Full checkout flow with B2B purchase order support.

**Deliverables:**

- `Storefront/CheckoutController` - multi-step checkout
- Order creation (transactional, with inventory reservation)
- Order confirmation page + email queue
- B2B path: purchase order / net terms for approved customers
- Stub payment providers: COD, bank transfer, purchase order
- Shipping method stub (real engine in 010)

**Depends on:** Stage 008D (cart)

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
