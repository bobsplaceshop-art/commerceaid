# Project Status

**As of:** 2026-10-12
**Version:** 0.31.0-alpha
**Stage:** 017 (Online Deployment) complete. Sub-stages shipping: 010.1 (M-Pesa) closed 2026-10-17. 010.2 (Pesapal) and 010.3 (Flutterwave) code-complete 2026-10-17, browser tests pending their sandbox accounts. 010.4 (Manual), 010.5 (Cash on Delivery), and 010.6 (Bank Transfer) closed 2026-10-16. Three providers production-proven: Stripe, PayPal, and M-Pesa.

One-page snapshot of where the project stands. For deeper context
read `docs/REMEMBER.md` and `docs/ROADMAP.md`.

---

## What Works Right Now

### Core platform

- Custom PHP MVC framework boots
- Router dispatches with middleware onion pipeline
- DI container resolves services
- Session, CSRF, rate limiting active
- Multi-tenant StoreContext enforces per-store isolation
- Audit logs every state-changing HTTP request
- Error pages with ERR-xxxxxx reference IDs

### Admin

- Login / logout / password change
- Setup wizard with 8 tabs
- Grouped collapsible sidebar with localStorage persistence
- Sidebar active item scrolls into view on load
- Dark / light / system theme with per-user preference
- Dashboard with product / order / customer tiles wired
  to real counts, plus an inventory health card
- Responsive across all viewports down to 375px
- Sticky action bars on all edit pages

#### Admin - Orders (008K.1)

- Order list with status tabs, payment-state filter,
  search
- Order detail with line items, totals, addresses,
  internal notes, and status history
- Status transitions with a reason note, written to the
  history ledger
- Internal notes on any order
- Permission-gated by `orders.view` (reads) and
  `orders.manage` (writes)

#### Admin - Customers (008K.2)

- Customer list with approval-status tabs, search, group
  filter, account-state filter, pagination
- Customer detail with contact, group, addresses,
  recent orders, and approval history
- Profile editing (name, phone, company, tax ID, date of
  birth; email is read-only by design)
- Group change with modal
- Account state change: enable, disable, archive
- Address management: create, edit, delete, set-default,
  with automatic default handling
- Approval actions (approve, reject, reset to pending)
  reuse the existing CustomerApprovals controller
- Permission-gated by `customers.view` (reads) and
  `customers.manage` (writes)

#### Admin - Pricing Engine (008K.5)

- **Coupons** at /commerce/ca-admin/coupons. CRUD for
  code-based discounts (percentage, fixed amount, free
  shipping). Usage limits, per-customer limits, minimum
  order, category scope.
- **Tax** at /commerce/ca-admin/tax. Tax classes and
  per-country/state rates. Inclusive or exclusive flag
  per rate. Shipping-taxable flag. Seed: Standard,
  Reduced, Zero.
- **Shipping** at /commerce/ca-admin/shipping. Shipping
  methods (flat, free over, weight-based, price-based,
  local pickup) and zones (per country/region).
- **Per-product tax override** on the product editor's
  Pricing tab. Rate percent override plus a three-state
  inclusive flag (inherit / inclusive / exclusive).
- **Storefront coupon apply** on the cart page. Enter a
  code, see the discount line, proceed to checkout.
- **Storefront shipping method picker** on the payment
  step. Radio list from available methods for the
  destination.
- **Full totals breakdown** on cart, payment, confirm,
  and confirmation pages. Subtotal, discount, shipping,
  VAT note ("inkl." or "zzgl."), grand total.
- Permission-gated by `coupons.*`, `tax.*`, `shipping.*`.

#### Admin - Returns (008H)

- **Returns queue** at /commerce/ca-admin/returns. Four
  status tabs (open, received, closed, all), a
  request-number search, and a date range.
- **Detail page** with state-aware action buttons:
  Requested -> Approve, Reject. Approved -> Reject, Mark
  received. Received -> Process refund. Terminal -> no
  actions.
- **Mark received** with per-line received quantities. A
  customer who requested three and the warehouse counted
  two is refunded for two. Lines not adjusted default to
  the requested quantity.
- **Process refund** updates the order's `refunded_total`,
  `payment_status`, and each line's `returned_quantity`.
  The customer's order detail and the admin order detail
  show the change immediately.
- Customer-initiated from `/account/returns`. The
  14-day Widerrufsrecht window is enforced by
  `ReturnRequestService::isWithinWindow()` with a hard
  14-day floor for German deployments.
- Permission-gated by `returns.view` (reads) and
  `returns.moderate` (writes).

#### Admin - Newsletter & Campaigns (008I Sessions 1+2)

- **Subscriber list** at /commerce/ca-admin/subscribers.
  Filters by status, source, list, and date range. A
  search box matches the email. CSV export includes
  every field relevant to a DSGVO audit (email, name,
  status, consent timestamp, consent IP, consent source).
- **Subscriber detail** shows the consent record, list
  memberships, and event history. A manual unsubscribe
  action records a reason.
- **Subscriber lists** at /commerce/ca-admin/subscriber-lists.
  Named lists with membership. The default list
  ("Newsletter") is seeded per store and joined by the
  footer signup form.
- **Campaign list** at /commerce/ca-admin/campaigns.
  Drafts, sent, cancelled.
- **Campaign composer** uses the shared rich-text
  editor (the same one used by Legal Pages).
- **Campaign detail** shows stats tiles (sent, pending,
  opens, clicks) and a body preview rendered in a
  sandboxed same-origin iframe. The iframe loads a bare
  email document from
  /commerce/ca-admin/campaigns/{id}/preview?raw=1 and
  autogrows via postMessage.
- **CLI queue worker** at bin/send-emails.php. Drains
  the queue, applies the retry, bounce, and suppression
  rules.
- Permission-gated by `newsletter.subscribers.view` /
  `.manage` and `newsletter.campaigns.view` / `.manage`.

**Shipped (008I Session 3a):** the storefront subscribe
endpoint (POST /newsletter/subscribe), double opt-in
confirm (GET /newsletter/confirm/{token}), unsubscribe
(GET and POST /newsletter/unsubscribe/{token}), the
three newsletter email templates, and both footer and
home subscribe forms wired to the live endpoint.

**Shipped (008I Sessions 3b and 4):** the account
preferences newsletter toggle (three-state copy, lock on
suppressed addresses, authenticated resubscribe), and
the campaign send test through the CLI queue worker.
Campaign-email unsubscribe links verified end to end.

**Shipped (008I.1):** GET/POST symmetry in the
unsubscribe GET handler, and a "did not ask to
subscribe" link in the confirmation email template.
Both shipped 2026-10-12 (commit d99021b). Stage 008I
has no outstanding follow-ups.

#### Forms (009 Sessions 1, 2a, 2b, and 3)

**Sessions 1 and 2a shipped.** Schema, models,
repositories, plus the first user-visible surface:
list, create, edit, and status transitions. The field
editor ships in Session 2b. The submissions viewer
ships in Session 4.

Backend (Session 1):

- `commerce_forms`, `commerce_form_fields`,
  `commerce_form_submissions` tables (migrations
  0334-0336).
- `Form`, `FormField`, `FormSubmission` models.
- `FormRepository`, `FormFieldRepository`,
  `FormSubmissionRepository` (all resolve via the
  container through ADR-018 auto-construction).
- Permissions seeded (seeder 0017): `forms.view`,
  `forms.manage`, `forms.submissions.view`,
  `forms.submissions.manage`. Granted to super-admin,
  store-admin, marketing (all four) and support
  (submissions.view only).

Admin surface (Session 2a):

- Forms list at /commerce/ca-admin/forms with status
  tabs (All / Draft / Published / Archived), numeric
  counts on each tab, search by name or slug, and
  pagination.
- Create form page.
- Edit page: status card with contextual publish /
  unpublish / archive / duplicate actions, metadata
  editor (name, slug, description, success message,
  notify email), honeypot and auth-required toggles,
  danger zone for delete.
- `FormService` handles all writes. Slug is normalised
  and unique per store; the slug is immutable after
  publish; publish() refuses a form with zero fields;
  duplicate() clones every field and suffixes the slug
  with `-copy`; delete() is a soft delete.
- Sidebar item under Marketing, gated by `forms.view`.

Field editor (Session 2b):

- Add, edit, reorder, and delete fields on any form.
- Twelve input types, reused from the CustomFields
  registry without modification.
- Type-conditional config sections rendered from each
  input type's `configFields()` method.
- Options editor for Select, MultiSelect, and
  Checkbox-group types.
- Conditional visibility rules: one rule per field,
  six operators, target field select populated from
  the form's existing fields.
- Multi-step support via the `step` column on each
  field. Fields at the same step render together on
  the public form (Session 3).
- `FormService` gained `addField`, `updateField`,
  `deleteField`, and `moveField`.
- `AdminFormController` gained `fieldStore`,
  `fieldUpdate`, `fieldDelete`, and `fieldMove`,
  plus four field routes.
- The field editor modal uses the shared `.ra-modal*`
  system, matching every other admin modal.

Also fixed in Session 2b:

- `SelectType::render()` was producing a file input
  instead of a dropdown. A stale body had been copied
  from `FileType`. Corrected.
- `InputType::render()`'s docblock narrowed the
  `$options` parameter to `string[]` while every
  consumer passed `array{value,label}[]`. Widened to
  accept both shapes.

The public surface and submission processing ship in
Session 3. The
submissions admin and webhook integration ship in
Session 4.

### Admin - Logs hub (Stage 014)

One sidebar entry, four tabs, three audiences.

  Overview       Counts from every stream. The merchant's
                 landing page. No data table.

  Activity Log   core_activities. One human sentence per
                 row: "Esther published Product #42." The
                 merchant timeline. Empty until services
                 start calling ActivityLogger::log().

  System Logs    storage/logs/app.log. The developer log.
                 Exception, error, warning, and info
                 levels. Filter by level, date, search.

  Audit Trail    core_audit_logs. Raw HTTP audit rows.
                 Method, path, IP, user agent, payload
                 hash, response status. Every state-
                 changing request since Stage 005.

Every log row carries its store. `Platform\Context\StoreContextInterface`
is the boundary that lets Platform read the current store
without importing Commerce. Both writers (ActivityLogger and
AuditLoggerMiddleware) populate store_id. Migration 0338
backfilled the 868 existing audit rows.

The sidebar entry under Settings is a single "Logs" item;
the four tabs live inside via a sub-nav rail that mirrors
the Setup hub.

### Catalog

- Categories: tree with attribute mapping
- Brands: flat list
- Attributes: full CRUD + 12 input types
- Products: 5-tab editor
- Product variations: auto-generate from category attributes
- Variation manager: search, filters, bulk actions, pagination
- Inventory: list, filters, adjust modal, movement ledger
- CSV exports

### B2B

- Customer groups with default / B2B / approval flags
- Price lists with tier pricing per group
- Net terms field on groups
- Customer approval queue with per-transaction history
- Purchase order fields on orders (schema ready)

### Storefront (public)

- Home page with hero, trust row, category grid, promo panels,
  product sections, trust strip, newsletter
- Catalog page with filters, active chips, sort, pagination
- Product detail page with media column, sticky info,
  thumbnails, related products
- Category index (7-card grid)
- Category detail (hero banner + subcategory chips)
- Full visual identity: Playfair Display + Inter +
  Dancing Script, cream/beige/champagne-gold/charcoal palette
- Section collapse rule: data-driven sections vanish when empty
- Store builder contract: every element carries a data-sf-*
  attribute for future admin editability

### Settings & Compliance (008E.1)

- Store Settings page with dynamic tabbed form
- Adding a new setting is one line in a definitions array
- Advanced tab for arbitrary custom key-value settings
- Legal Pages admin CRUD for Impressum, AGB, Widerrufs-
  belehrung, Datenschutzerklaerung
- Rich text editor for legal page content (zero deps)
- Cookie consent banner (TTDSG-compliant)
- Public consent API: window.CAConsent.has(category)
- Country-driven compliance via ComplianceMap (DE wired)
- All settings stored in commerce_store_settings key-value
  table

### Shopping cart (008D)

- Session-based cart for guests ($_SESSION['cart'])
- DB-persisted cart for logged-in customers
  (commerce_carts + commerce_cart_items)
- Merge on login: guest session cart flows into DB
- Mini-cart badge in header with live AJAX updates
- Cart page with quantity controls, remove, subtotal
- Toast notification on add-to-cart
- Per-variant stock checks (variant is the sellable unit)
- Stock rejection: cannot add more than available
- Variant selector on product detail page
- Quantity input on product detail page

### Customer accounts (008C)

- Registration with optional group selection
- Login, logout, forgot password, reset password
- Account dashboard with group / net-terms display
- Address book CRUD with set-default
- Password change with current-password verification
- Approval workflow integration: pending customers can log
  in and browse; checkout gated by Customer::canCheckout()
- Separate customer session cookie (ca_customer_session)
  distinct from admin ca_session

---

## What Doesn't Work Yet

- Order lifecycle customer view (008F)
- Search, PWA, attribute facets, category-hiding polish (008G)
- Payment capture (010)
- Shipping rate engine (010)
- Tax engine (010)
- Forms builder (009)
- Store builder admin UI (011)
- Theme engine + 20 themes (012)
- Product reviews
- Wishlist
- Real product / category / hero photography
- Real rating aggregates

---

## Current Focus - Page Builder Tier 0.8 complete

**Page Builder Tier 0.8 is functionally complete for the
sidebar workflow.** Sessions 2026-10-24 and 2026-10-25
shipped the final pieces of the builder's drag-and-drop
flow, and closed the media URL bug class across the
codebase.

### Drag rewrite, Phases 1-5

The page builder's drag-and-drop cannot move a block
between parents without the client keeping a tree. The
DOM is a flat list of rows; parent-child nesting is
implied only by the `.ra-customizer-block--child` class,
which is the same for a child and a grandchild. Two
levels cannot be distinguished from the class alone.

Phases 1-5 make the client tree the source of truth:

- **Phase 1 (`0be9240`)** — client tree built once on
  load from `data-block-id` and `data-parent-id`.
  Exposed as `window.__builderTree`.
- **Phase 2 (`92031d0`)** — `moveNode()` updates the
  tree during dragover. No DOM mutation during drag.
- **Phase 3 (`0b5fe1f`)** — `PageDraftRepository::saveTree()`
  writes `parent_id` and `sort_order` in one pass.
  `reorderBlocks` accepts a `tree` payload.
- **Phase 4 (`229dc89`)** — the drop handler sends the
  tree. The legacy `ids` payload is preserved for
  backward compatibility.
- **Phase 5 (`645e596`)** — the sidebar re-renders from
  a shared `<template id="tpl-block-row">`.
  `window.__builderFillRow` is the one cloner, used by
  both the PHP initial render and the JS re-render.
  Row markup has one source, not two.

### Companion fixes in the same arc

- **`c39922f`** — config field save
  (`config[config[headline]]` double-wrap fixed),
  Hero block background UUID resolved via
  `BlockRenderer::$bg_url`, print CSS moved to
  `public/assets/css/print.css`.
- **`264165d`** — empty product grid blocks collapse on
  the live storefront. Admin preview still shows the
  empty state.
- **`e4d4377`** — media URL resolution across seven
  files (blocks, feed, social meta, structured data).
  All fixed. `bin/check-media-urls.php` prevenger
  checker added.
- **`2a361af`** — event delegation for settings and
  mobile-close handlers. Survives every `rerenderSidebar()`.
- **`1038be6`** — product `og:image` is now absolute.
- **`574f559`** — Pattern 46 in `docs/REMEMBER.md`.

### What is complete

- Product grid: 3-8 columns, four alignment options,
  category and source filters, `best_sellers` wired to
  `ReportRepository::listBestSellers()`.
- Drag-and-drop: reorder within parent, drag child to
  root, drag root into Section/Container.
- Sidebar re-renders after drop; settings pencil works
  after any drag; empty-state paragraphs preserved.
- Media URL bug class closed by the prevenger. No
  numeric id in any `/media/` URL anywhere in
  `app/` or `resources/`.
- Seven commit arc from `28ff776` to `645e596`, then
  three docs commits (`cb35e37` and this one).

### What remains

Three items, all in
`docs/stages/023_page-builder-architecture.md`:

1. **Section and Container config fields** — padding,
   background, column count. Tier 3. This is the next
   session's focus.
2. **Drag-into-canvas** — dropping a block onto the
   preview iframe. Resolves the drop coordinates to a
   target block and insertion side. Requires a plan
   document. Tier 3.4.
3. **Menu and footer builder** — the header nav and
   footer nav read from `commerce_navigation_menus`
   instead of hardcoded lists. Section 10 of the
   stage doc.

Tier 0.8 is closed. Tier 3 is open.


## Database State

- 47 `core_*` tables
- 28 `commerce_*` tables
- 29 migrations applied (`0001` through `0208`)
- 7 seeders applied:
  - currencies, roles + permissions, admin user,
    default store, customer groups, test B2B customer,
    order + review + customer permissions

Test data in dev:

- 1 admin user: `admin@anayaglobal.test` / `ChangeMe!2026`
- 1 store: `Anaya Global` (slug: anaya)
- 3 products: Comb, Wool Crew Neck Sweater, T-Shirt
- 1 variable product with 12 variations (T-Shirt)
- 3 customer groups (Retail, Wholesale, VIP)
- 1 category
- 3 attributes: Color, Size, Care Instructions
- 1 B2B test customer (pending from 007B)
- 1 registered storefront customer (rmbuthia49@gmail.com)
- 1 pending Wholesale registration (test-wholesale@example.com)

---

## Environment

- OS: Windows 11
- Dev shell: WSL Ubuntu (authoring)
- Runtime: Laragon Terminal (PHP 8.3.33, MySQL 8.4.3)
- Apache: Port 8081, vhost `00-commerce-aid.conf`
- Database GUI: HeidiSQL
- URL: `http://localhost:8081`
- Admin: `http://localhost:8081/commerce/ca-admin`
- DB name: `anaya_commerce`

### Tooling

- `composer lint` — PHP syntax check across app/ + routes/
- `composer analyse` — PHPStan level 8 (zero errors, no baseline)
- `composer prevenger` — three structural checkers in one
  entry point (`bin/check.php`):
    - `bin/check-theme.php` — theme variable contract.
      Every `--ra-X:` definition has a `var(--ra-X)`
      reference and vice versa.
    - `bin/check-css.php` — inline `<style>` structural
      integrity. Brace balance, declaration shape, no
      `@media` blocks in views.
    - `bin/check-html.php` — nested forms, modal
      includes inside forms, unescaped JSON in `data-*`
      attributes, unbalanced attribute quotes. Warns on
      `<table>` missing `data-responsive` (flips to a
      hard error when Stage 014 Session 1 ships the
      breakpoint system).
- `composer check` — all four: lint, analyse, prevenger
- Pre-commit hook runs `bin/lint.php` before every commit
- `bin/verify-file.php` — per-file post-write verification

The prevenger is the structural counterpart to PHPStan.
PHPStan catches type errors; the prevenger catches the
structural bug classes PHPStan cannot see. Every commit
that passes `composer check` runs both.

**Commits happen from WSL**, not Laragon Terminal. See
`docs/git-workflow.md` for the reasoning.

### Backup

- Local: `/mnt/c/Users/Admin/Documents/RepoBackup/commerceaid.git`
- Remote: Google Drive Desktop syncs that folder
- Push after every meaningful commit: `git push backup main`

---

## Known Issues

None blocking.

Deferred / cosmetic:

- Topbar search input is disabled (search lands in 008G)
- Settings gear in topbar links to a route that 404s
- `/offline` page uses plain styling (redesign in 008G)
- Product / category / hero images are picsum placeholders
  (real media in a later stage)
- Category cards with no products still appear (hide rule in
  008G)

---

## Patterns Locked

See `docs/REMEMBER.md` for the full list (22 patterns). Recent:

- Pattern 17: FKs to core_* are INT UNSIGNED
- Pattern 18: Partial migration application
- Pattern 19: Pre-flight inventory before touching closed code
- Pattern 20: One file per message during critical work
- Pattern 21: StoreContext required for repository DB access
- Pattern 22: Prefix-match active state breaks on strict-prefix
  routes

---

## Verified Baseline

`docs/BASELINE.md` + `docs/baseline/*.md` (16 sections) documents
every verified codebase convention. Read this before writing any
new code.

---

## Next 3 Stages

- **Page Builder Tier 3** (now): Section and Container
  config fields (padding, background, column count).
  The layout primitives exist as empty wrappers today;
  the config fields make them useful.
- **Drag-into-canvas**: the plan document for dropping
  a block onto the storefront preview iframe.
  Coordinates resolve to a target block and insertion
  side. The sidebar drag flow is the foundation.
- **Menu and footer builder**: the header and footer
  nav read from `commerce_navigation_menus` and
  `commerce_navigation_items` instead of the hardcoded
  arrays in `layouts/main.php`.

The commerce loop is complete end to end: browse,
cart, checkout, order, return; and on the merchant
side: products, orders, customers, returns, users,
roles, coupons, tax, shipping, newsletter, forms,
page builder. Every surface a merchant needs to
operate the store is live, and the storefront layout
is merchant-editable.

---

## How to Run

    cd C:\projects\commerceaid
    composer install
    cp config/local.php.example config/local.php
    cp config/cli.php.example config/cli.php
    (edit both with DB credentials)
    php bin/migrate.php
    php bin/seed.php
    http://localhost:8081

Login: `admin@anayaglobal.test` / `ChangeMe!2026`

---

## How to Resume Work

If this is a new session:

1. Read `docs/STATUS.md` (this file)
2. Read `docs/REMEMBER.md` (full context + patterns)
3. Check `docs/stages/README.md` for stage index
4. Skim `docs/BASELINE.md` for the verified conventions
5. Verify environment: `php -v` in Laragon Terminal
6. Check admin URL loads
7. Continue from the "Current Focus" section above

---

## Ownership

Built by Anaya Global, operating as the first customer of the
Corporate Aid Solutions suite. The platform is designed to be
resold to other customers with only configuration changes. No
runtime dependency on Corporate Aid Solutions after delivery.
