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
| 008E.2| Guest order claim                     | 2026-09-28 |
| 008F  | Order lifecycle (customer-facing)      | 2026-09-28 |
| 008F.1| Account shell & navigation             | 2026-09-28 |
| 008G  | Search, out-of-stock hiding, gallery,  | 2026-09-28 |
|       | responsive audit                       |            |
| 008G.1| Wishlist                               | 2026-09-29 |
| 008G.2| Recently Viewed                        | 2026-09-30 |
| 008G.3| Message Center                         | 2026-10-01 |

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

### Stage 008G.4 - PWA (deferred from 008G)

**Goal:** Progressive Web App support for the storefront.
Manifest, service worker, offline shell, install prompt.

**Depends on:** Stage 008G (storefront polish complete).

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

### Stage 008I - Newsletter & Email Campaigns

**Goal:** An inbuilt newsletter and campaign system. No
Mailchimp, no Klaviyo, no third-party marketing processor.
The subscriber list, the campaigns, and the sending
infrastructure all live inside the store's own deployment.

**Why inbuilt:**

  1. DSGVO data transfer. Sending email addresses to a
     US-owned marketing service makes that service a
     processor and requires Standard Contractual Clauses,
     a Transfer Impact Assessment, and specific
     disclosures under Schrems II. Every customer who
     used the platform would have to do that work
     themselves if the platform relied on an external
     service.

  2. Cost. Mailchimp's free tier caps at 500 contacts and
     1,000 sends/month. Beyond that it is $20-100+/month
     depending on list size. The store's own SMTP provider
     is the only ongoing cost.

  3. Vendor lock-in. Every integration with an external
     marketing service is a maintenance burden the
     platform's customers inherit. No API changes, no
     deprecations, no billing surprises.

  4. Consent records. German law requires provable
     double opt-in: consent timestamp, source, subscriber
     IP. Mailchimp provides this. So can the platform,
     and the customer owns the record.

**Deliverables:**

  Schema:
    commerce_subscribers                 email, status,
                                         consent timestamp,
                                         consent IP, source,
                                         confirmation token,
                                         confirmed_at,
                                         unsubscribed_at,
                                         unsubscribe_reason
    commerce_subscriber_lists            named lists
                                         (newsletter, product
                                         updates, promotions)
    commerce_subscriber_list_members     subscriber <-> list
    commerce_email_campaigns             subject, body,
                                         list, status,
                                         sent_at, stats
    commerce_email_queue                 pending sends
    commerce_email_events                opens, clicks,
                                         bounces, unsubscribes

  Admin:
    /commerce/ca-admin/subscribers       list + filter + export
    /commerce/ca-admin/subscribers/lists named lists
    /commerce/ca-admin/campaigns         compose + send
    /commerce/ca-admin/campaigns/{id}    stats

  Storefront:
    - The existing newsletter subscribe form (footer) writes
      to commerce_subscribers.
    - Double opt-in confirmation email with a signed link.
    - Unsubscribe link in every campaign email:
      /newsletter/unsubscribe/{token}
    - The account preferences page
      (/account/messages/preferences, from 008G.3) gains a
      "Newsletter and promotions" toggle that opts back in.

  Sending:
    - Uses the existing EmailService (Platform).
    - Queue-based: campaigns enqueue N rows in
      commerce_email_queue. A cron or CLI worker drains
      them.
    - Rate-limited to avoid blacklisting.
    - No third-party dependency.

**Compliance:**

  - Double opt-in by default for German deployments.
    Single opt-in configurable for other countries.
  - Consent timestamp, source, and IP recorded per
    subscriber. Audit trail for DSGVO Art. 7(1).
  - Unsubscribe link mandatory in every email. UWG § 7,
    DSGVO Art. 21.
  - No external marketing processor. The subscriber list
    stays inside the store's own infrastructure.
  - The operator's SMTP provider is a separate processor
    under DSGVO Art. 28. A Data Processing Agreement is
    required. The platform does not pick the SMTP
    provider; the operator does, and the operator signs
    the DPA.
  - See docs/COMPLIANCE-DE.md entry 20 (hosting provider
    processor agreement) for the general framework. The
    SMTP provider fits the same pattern.

**Depends on:** 008E.1 (German compliance), 008G.3 (account
preferences page).

**Sequenced:** After 008G.4c (self-hosted fonts) and
before 008H (returns). Newsletter is independent of the
order lifecycle; returns are not.

**Open questions:**

  - Should campaigns support A/B subject testing? Nice to
    have, not essential.
  - Should subscriber segments be rule-based (customers
    who bought X) or only list-based? List-based is
    simpler; segments are a Stage 011+ concern.
  - Bounce handling: hard bounces auto-suppress the
    subscriber. Soft bounces retry N times, then
    suppress. Standard practice; low effort.
  - Open and click tracking: both require a tracking
    pixel and a redirect endpoint. Both have DSGVO
    implications (the pixel is stored on the user's
    device). Under TTDSG § 25 they need consent. This
    likely means tracking is opt-in for the operator,
    default off for German deployments.

These are captured as Open Questions, to be resolved in
the 008I plan document before code is written.

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

### Responsive Image Templates (008G.4a.3)

The variant pipeline (008G.4a) generates WebP and JPEG
variants at multiple sizes per media context. The
storefront templates do not yet request them. They still
render the source image path directly.

This sub-stage wires the templates to the variants:

  - Product cards request /media/{uuid}/card.webp and
    /media/{uuid}/card.jpg with <picture> fallback.
  - Product detail requests /media/{uuid}/lg.webp with
    <picture> fallback and a srcset for 1200w / 800w /
    400w.
  - Category banners request /media/{uuid}/md.webp and
    /media/{uuid}/lg.webp with srcset.
  - Store logos request /media/{uuid}/md.webp with the
    PNG fallback already served by the variant pipeline.
  - Lazy loading on all product and category images
    (loading="lazy" attribute plus a native fallback for
    browsers without it).

Deliberately deferred from 008G.4a so the upload pipeline
ships first and the template rewrite is its own reviewable
change.

**Why:** a 400x500 WebP card variant is 20-40 KB. Twelve
cards is ~360 KB. The same grid from 5 MB source images
is 60 MB and unshippable on mobile.

**Likely stage:** 008G.4a.3, after the upload pipeline and
store settings integration are complete.

### Self-Hosted Font Manager (008G.4c)

Google Fonts and other external font services transmit
the visitor's IP address to a third-party server. Under
DSGVO, this requires explicit consent. The Bavarian data
protection authority has stated this position, and German
courts have issued rulings ordering damages for embedding
Google Fonts without consent (LG München, ~€100 per case).
The IHK warns of Abmahnungen (cease-and-desist letters).

The fix is self-hosting: download the font files, serve
them from the store's own domain, no external request.

Scope:

  - A Font Manager page in the admin under Settings.
  - A curated catalog of open-source fonts, all OFL or
    Apache 2.0 licensed. Full Google Fonts catalog is
    available; Fontsource publishes the same fonts
    specifically for self-hosting; Bunny Fonts and
    Fontshare provide alternatives without tracking.
  - The merchant picks display and body fonts.
  - On save, the platform downloads the WOFF2 files
    locally to storage/uploads/fonts/.
  - The storefront serves them via /assets/fonts/ with
    font-display: swap.
  - No external font requests anywhere in the rendered
    page. CSP style-src and font-src drop the Google
    Fonts origins.
  - A font stack fallback in case the WOFF2 fails to
    load.

Font Awesome Free is bundled alongside this stage. MIT
for non-font files, SIL OFL 1.1 for the icon fonts,
CC BY 3.0 for documentation. Commercially usable with
attribution already embedded in the files. Self-hosted,
no external CDN.

**Why:** the current storefront loads Google Fonts from
fonts.googleapis.com. That is a DSGVO liability for any
German customer. The fix is not optional for a German
deployment.

**Likely stage:** 008G.4c, after the upload pipeline and
PWA are done.

### Page Builder: Animations, Mosaic, Carousel (Stage 012)

The eventual Store Builder (Stage 012) grows a block
registry with these block types:

  Existing plan (from Stage 012):
    hero, product grid, category grid, testimonials,
    rich text, video, banner, newsletter, FAQ, form,
    spacer

  Additions requested 2026-10-02:
    - Mosaic grid: a grid block with configurable column
      spans. A 3-column layout can render one tall image
      next to two stacked short ones. Config per block:
      column count, image aspect per cell.
    - Carousel: a product-grid block with
      mode: carousel. Config: items per slide, autoplay
      interval, dots/arrows, loop. Built as a native
      scroll-snap carousel, no JS library.
    - Animated text: a rich-text block with per-element
      animation. Headline fades in on scroll, subtitle
      slides up, accent script enters from the side.
      CSS-only via scroll-driven animations or
      IntersectionObserver. Config: animation type per
      element, delay, duration.
    - Fancy typography: the block registry exposes the
      font pairing (display + body from 008G.4c) plus
      per-block overrides. A hero block can use Playfair
      Display for its headline while the rest of the
      page uses Inter. The merchant picks from a curated
      list, not a free-form font input.
    - Fancy headers: preset header layouts (centered,
      left-aligned with a rule, script above serif,
      underline accent) that a merchant picks per block.
      Each preset is a CSS class, not a template.

Foundation already in place:

  - Every storefront element carries data-sf-* attributes
    (the store builder contract from 008A).
  - Homepage sections are block-structured with
    data-sf-section attributes.
  - Block-based templates avoid per-page PHP edits.

The editor UI (drag-and-drop, live preview) is the big
piece. The blocks themselves are incremental.

**Why it belongs here:** every one of these features
depends on the block registry, the font manager, and the
image variant pipeline. None of them is standalone.

**Likely stage:** Stage 012, extending the existing
"Page builder: section registry, drag-drop ordering"
scope.

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
