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
| 008E  | Checkout                               | 2026-09-28 |
| 008E.1| German compliance                      | 2026-09-28 |

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

### Stage 008E.2 - Guest Order Claim

**Goal:** Reconcile guest orders with existing customer accounts
after the fact, without adding friction at checkout.

**Deliverables:**

- Migration: claimable_by_customer_id, claimed_at on
  commerce_orders.
- CheckoutService populates claimable_by_customer_id on guest
  orders whose email matches an existing customer.
- CustomerClaimController - list claimable orders, claim action.
- Claim notification on the customer dashboard.
- Admin toggle: off by default, disclaimer when enabling.

**Depends on:** Stage 008E (checkout, complete),
Stage 008C (customer auth, complete).

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

**Goal:** The customer can see every order, understand its
current state, and track it. The post-purchase half of the
relationship.

The AliExpress-style account portal is the reference for the
shape (order list with status tabs, order detail with line
items and shipping info), but we build better: purpose-built
empty states, honest status labels, and a Widerrufsrecht
link on every order detail that opens the withdrawal policy.

**Design principles locked:**

  1. One account, one coherent surface. Every /account/* page
     shares the same shell (sidebar + header + breadcrumbs).
     Moving between orders, wishlist, returns, and messages
     feels like moving between rooms in the same house.

  2. Tabs where they clarify, sections where they don't.
     Tabs are for genuinely distinct states (five order
     statuses). A single page is fine for addresses.

  3. Every empty state has a purpose. "No order" is a
     placeholder. Ours says what the empty state means and
     offers the next action.

  4. The customer account mirrors the merchant's admin.
     Not identical, but visually and structurally related.

**Deliverables:**

- `Storefront/OrdersController` - list with status tabs,
  detail page.
- Status tabs: Unpaid / To Be Shipped / Shipped / Completed /
  Cancelled. Each tab filters the customer's own orders.
- Order detail page shows: line items with snapshots (name,
  variant, sku, qty, unit price, line total), shipping
  address, billing address, payment method, current statuses
  (main, payment, fulfilment), totals.
- **Tracking number display.** New columns on commerce_orders:
  `carrier_key`, `tracking_number`. A carrier key maps to a
  URL template (DHL, FedEx, UPS, DPD, Hermes, etc.) via a
  small config map. The link opens the carrier's public
  tracking page. No API integration.
- **Widerrufsrecht link on every order detail.** German
  consumer protection requires the customer to have access
  to the withdrawal policy from their order. Links to
  /legal/widerruf.
- Rewire `/account/orders` from the current stub to the real
  list. Order numbers link to the detail page, not the
  checkout confirmation URL.
- 14-day withdrawal-window indicator (informational badge:
  "Withdrawal available until Oct 12"). Ties into 008H.

**Out of scope (later stages):**

- Carrier API polling and auto-updated tracking (010).
- Split shipments (010).
- Reorder button (deferred until the cart supports
  one-click re-add, 008G polish or later).
- Customer-facing order notes / attachments (012+).

**Depends on:** Stage 008E (checkout, complete).

### Stage 008F.1 - Account Shell & Navigation

**Goal:** Every /account/* page shares one shell. Left sidebar
navigation, breadcrumbs, consistent header. The pages that
exist (dashboard, orders, addresses, password) gain a
shared visual frame. New pages added by later sub-stages
slot into the same frame automatically.

**Deliverables:**

- `resources/views/storefront/account/_shell.php` partial.
  Renders the sidebar + breadcrumbs + header.
- `resources/views/storefront/account/_sidebar.php` partial.
  Grouped, collapsible sections:
    My Account (dashboard, addresses, password)
    My Orders (list, filters)
    Returns (008H - link until then shows "coming soon")
    Wishlist (008G.1)
    Recently Viewed (008G.2)
    Messages (008G.3, with unread badge)
- Breadcrumb trail on every /account/* page.
- Refactor existing account views to render inside the shell.
  Existing pages: dashboard, orders, addresses, address-form,
  password.
- Mobile: sidebar collapses to a horizontal tab strip on
  narrow viewports (or a drawer; decide at build time).
- Responsive at 375 / 768 / 1024.

**Not in scope:**

- New account pages themselves. Those land in their own
  sub-stages. F.1 is only the shell.
- Notification badge wiring. F.1 places the placeholder;
  008G.3 fills it.

**Depends on:** Stage 008F (order detail page).

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

**Depends on:** Stages 008A-008H. Runs after the customer
account work (F, F.1, G.1, G.2, G.3, H) so the storefront
polish lands on a stable foundation.

### Stage 008G.1 - Wishlist

**Goal:** Customers can save products for later. Per-customer
for logged-in customers, per-session for guests. Merge on
login, same pattern as the cart.

**Deliverables:**

- `commerce_wishlists` table (store_id, customer_id, session_id,
  created_at, updated_at) or a simpler per-customer table.
- `WishlistRepository` + `WishlistService`.
- Add/remove from the product card (heart icon always visible)
  and the product detail page.
- `/account/wishlist` page.
- Optional: "Add all to cart" button on the wishlist page.
- Merge guest session wishlist into the customer's DB wishlist
  on login (same pattern as CartService::mergeGuestCartOnLogin).

**Depends on:** Stage 008F.1 (account shell).

### Stage 008G.2 - Recently Viewed

**Goal:** Track products the customer viewed. Show them on
/account/recent. A returning customer can pick up where they
left off.

**Deliverables:**

- `commerce_recently_viewed` table. Per-session for guests,
  per-customer for logged-in. Bounded to N most recent
  (e.g. 50), oldest rotates out.
- Record on each product detail page load (deduplicated by
  product_id within a 24h window).
- `/account/recent` page with clear history option.
- Merge guest session history into the customer history on
  login (same pattern as wishlist).

**Depends on:** Stage 008F.1 (account shell).

### Stage 008G.3 - Message Center

**Goal:** A unified notification surface for the customer.
System messages, order updates, promotions. Reads from
`core_notifications` (already exists from Platform).

**Deliverables:**

- `/account/messages` page with tabs:
    System Message    - platform announcements, policy changes
    Order Updates     - order status transitions
    Promotions        - marketing, opt-in only
- Unread count badge in the account sidebar (F.1 placeholder
  filled here).
- Mark as read, bulk mark-all-read.
- Preferences page: which categories the customer wants by
  email vs in-app. Ties into the Platform's
  NotificationRuleService.
- Email digest stub (real sending is Stage 010).

**Depends on:** Stage 008F (order status events feed the
Order Updates tab), Stage 008F.1 (shell).

### Stage 008H - Returns & Refunds

**Goal:** Customer-initiated return requests. Integrates the
German Widerrufsrecht (14-day right of withdrawal) with a
real workflow.

**Deliverables:**

- `commerce_return_requests` table: order_id, customer_id,
  line items, reason, status, requested_at, resolved_at.
- `commerce_return_request_items` table for per-line returns.
- `/account/returns` page with tabs:
    Apply           - start a return
    In Processing   - returns awaiting merchant action
    Closed          - resolved returns
- 14-day window enforcement. Requests beyond the window are
  rejected with a clear message (unless the merchant has
  configured a longer policy).
- Reason picker: "Damaged", "Wrong item", "Not as described",
  "Changed my mind" (the last one is the Widerrufsrecht
  path, the others are warranty).
- Admin approval workflow: view requests, approve, generate
  refund, mark received.
- Integration with Widerrufsrecht legal text: the Apply form
  links to /legal/widerruf.
- Refund tracking: refunded_total on the order updates when
  a return is resolved. Feeds the payment_status track.

**Out of scope (later):**

- Actual payment provider refunds (Stage 010 - the payment
  providers land there).
- Return shipment labels (Stage 010 or 011).

**Depends on:** Stage 008F (order detail page), Stage 008F.1
(account shell), Stage 008E.1 (Widerrufsrecht content).

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

### Coming Soon / Maintenance Mode

A per-store toggle that replaces the public storefront with
a branded holding page. Intended for:

  - Pre-launch: the store exists but is not ready for
    customers. Visitors see a coming-soon page; admins
    browse the full storefront normally.
  - Maintenance: the store is temporarily offline (payment
    issues, catalog overhaul, migrations).
  - Private beta: the store is live but only accessible to
    invited customers, with a password gate or email
    capture for waitlist.

Shape:

  - Admin toggle in Store Settings: enabled / disabled,
    with an optional scheduled window (starts_at, ends_at).
  - The holding page is edited in the admin: headline,
    subtext, hero image, optional countdown, optional
    newsletter / waitlist signup form.
  - The page renders from a theme or template that can be
    swapped (per the future theme engine, Stage 012).
  - Logged-in admins bypass the gate and see the full site.
  - The gate runs as middleware: if enabled and the visitor
    is not admin, render the coming-soon view instead of
    the requested page. Return 503 with a Retry-After header
    for crawlers, so search engines do not deindex the site.

Why this is its own entry: it touches the storefront
middleware stack, the admin Store Settings page, and (in
the themed version) the theme engine. Small feature, but it
spans three layers.

Likely stage: 008G (PWA, Search, Storefront Polish) for the
basic version, or 011 (Advanced Commerce + Store Builder)
for the themed version with per-store templates.

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

### Unit of Measure Configuration

Fashion shopping mostly sells by piece, pair, set, or pack,
so Grundpreis is not currently required. But German law
(PAngV § 4) requires a base price (per kg, liter, meter,
square meter) for products sold by weight, volume, or length.
To support that - and to support future categories that need
it - the platform needs a Unit of Measure subsystem.

Shape:

  - Store Settings: unit configuration panel. Pre-populated
    with common units (piece, pair, set, pack, meter, cm,
    square meter, gram, kg, ml, liter). Editable. Addable.
  - Each unit has: code, label (singular / plural),
    symbol (kg, l, m), dimension (count / length / area /
    volume / mass).
  - Products carry an optional base_unit and
    base_unit_quantity (e.g. 500 g). Set on the product
    pricing tab.
  - Grundpreis computed as unit_price / (base_unit_quantity
    in base units). Displayed on product page and cart when
    set.

Why deferred: no current product needs it. Deferring keeps
008E focused on the order flow. Building it before it is
needed risks designing for imagined requirements.

Home stage: not yet scheduled. Could be a sub-stage of
008E.1 (compliance), a sub-stage of 010 (payments and
shipping deep), or its own short stage. The compliance
sub-stage is the natural home because Grundpreis is the
reason it exists.

### Storefront Flash Session Mismatch

`AccountController` resolves `Session::class` for every flash
write. `Session::class` is bound to the *admin* session
(`ca_session`) in `App.php`. The customer session is bound
separately as `'customer_session'` (`ca_customer_session`) in
`CommerceServiceProvider`.

So when a customer adds an address, the flash is written to
the admin session, not the customer session. In a normal
single-user browser this accidentally works: the server sets
a fresh admin cookie if none exists, and the storefront
layout reads flash from `Session::class` too. But:

  - A customer who is also an admin sees cross-contamination
    between their admin flashes and customer flashes.
  - Any future code that reads flash specifically from
    `'customer_session'` will not find address flashes.
  - The account page's flash behavior depends on an implicit
    binding, not on the correct one.

Fix: `AccountController` (and any other storefront controller
that writes flash) should resolve `'customer_session'`
explicitly. Same pattern the customer auth flow and the cart
already use.

`CheckoutController` (008E file 3) does this correctly from
the start. The `AccountController` fix is a small standalone
commit.

Likely stage: 008E or 008G. Small.

### Product Card Title Truncation

Product cards in the catalog grid render the full product
name. Long names wrap to five or six lines, producing ragged
card heights and an uneven grid. Reference marketplaces
(Kilimall, Jumia) clamp the title to two lines with an
ellipsis.

Fix: CSS-only. `-webkit-line-clamp: 2` on the product card
title with `overflow: hidden`. Modern browsers all support
this. The product detail page keeps the full title.

Where: `public/assets/css/storefront-catalog.css`, one rule.

Likely stage: 008G (storefront polish). Small.

### Variant Image Preview

Colour swatches on the product page show a small preview
image per colour. Clicking a swatch swaps the main product
image. Reference: Kilimall product detail page, eight
colour swatches with mini thumbnails, main image swaps on
selection.

Requires:

  - `commerce_product_images` already has a nullable
    `variant_id` column. Assigning images to specific
    variants is what this feature needs. No schema change.
  - Admin product editor needs UI to upload images and
    assign each to a variant (or to an attribute value
    like "Color: Red").
  - Storefront product detail page needs the swatch UI
    and the JS to swap the hero image on click.
  - The variant selector on the product page is currently
    disabled ("Variant selection coming soon" from 008D).
    This feature would activate it.

Scope note: this is not a small change. It touches the
admin product editor, the storefront product page, and the
variant selector logic.

Likely stage: 008G (storefront polish) or a dedicated
008G.1 sub-stage if it grows beyond a couple of files.

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
