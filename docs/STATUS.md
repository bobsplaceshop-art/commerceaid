# Project Status

**As of:** 2026-10-12
**Version:** 0.31.0-alpha
**Stage:** 009 (Forms & Submissions) complete. All four code sessions shipped: schema and models, admin surface (form CRUD and field editor), public surface (rendering at /forms/{slug}, submission processing with honeypot and conditional logic, multi-step navigation), and submissions admin (list with filters, bulk actions, CSV export, status change with notes, notification dispatch on new submissions). The webhook and event dispatcher are deferred to a future sub-stage 009.7; the codebase has no event bus and the notification service is the working dispatch path.

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

## Current Focus - Stage 010 Sessions 1-3 complete

**Stage 010 Sessions 1, 2, and 3 are complete.**

Sessions 1 and 2 built the payment infrastructure
and the refund wrapper. Session 3 built Stripe end
to end.

Session 3 (`4571f82`, `052ca4e`, `e8ea8f8`) shipped
the first real payment provider:

  - `StripeProvider` implements `PaymentProvider`.
    Charge uses Stripe Checkout (hosted redirect).
    Refund uses `POST /v1/refunds`. Webhook
    verification uses HMAC-SHA256 over
    "{timestamp}.{raw_body}".
  - `ChargeResult` value object (redirected,
    charged, offline, failed).
  - `PaymentChargeService` - the charge wrapper.
  - `WebhookController` - the codebase's first
    webhook receiver.
  - `WebhookEventRepository` - `commerce_webhook_events`
    reads and writes. Idempotency via unique key
    on (provider_key, event_id).
  - `StripeReturnController` - the customer return
    handler.
  - Two webhook routes and one return route.
  - `CheckoutService` payment-key validation is
    registry-driven.
  - `CheckoutController::place()` dispatches the
    charge.
  - `App::loadRoutes()` skips `Session->start()`
    on CLI.
  - Two smoke cases pass: `refund` and
    `stripe-webhook`.

**Stage 014 closed on 2026-10-14** (tag
`stage-014-complete`).

**Stage 010 Session 4 shipped** (11e00fb): PayPal
end to end. The provider, the capture flow, the
webhook handler, the return handler, and the
checkout integration. Both Stripe and PayPal are
production-ready.

The Stage 014 Session 5 and Session 3 tail paragraphs
below are historical and remain for the record.

**Session 5 shipped** (2bec764): the enforcement
script.

  - `bin/check-responsive.php` is a new prevenger
    checker. Three rules:
      1. Inline `<style>` blocks in views must not
         contain `@media` queries. Moved from
         `check-css.php` Pass 3.
      2. Views must not hardcode pixel widths above
         375px on inline style attributes. New.
      3. `<table>` elements in admin views must sit
         inside a wrapper with a `data-responsive`
         attribute. Moved from `check-html.php` W1,
         flipped from warning to hard error.
  - `check-css.php` and `check-html.php` lose the
    rules that moved. `check.php` gains a fourth
    entry in `$checkers`.
  - `composer check` now runs lint, PHPStan level 8,
    and four prevenger checkers. All pass.

**Session 3 tail shipped** (e2ed540, 923aef3, 0454eec,
f82aeaf, 235be2f): the admin tab strips, the view-local
CSS moves, sixteen list tables, and eleven detail-page
tables on the card treatment.

  - Tab strips scroll instead of wrap. Four class
    families: .ra-admin-tabs, .ra-content .nav-tabs,
    .ra-setup-nav-list, .ra-settings-tabs. All scroll
    horizontally with a hidden scrollbar below 899.
  - Four view-local <style> blocks move to
    main-v2.css. Dashboard, inventory, products Basic
    tab, setup payments. One shared mobile fallback
    for the four tile grids.
  - Sixteen list tables convert from
    data-responsive="scroll" to "cards":
      Batch 1  categories, brands, users, roles, coupons
      Batch 2  attributes, customer_groups,
               customer_approvals, returns, forms
      Batch 3  legal_pages, shipping, tax,
               subscribers, subscriber_lists, campaigns
  - Eleven detail-page tables convert:
      customers/show, orders/show, returns/show,
      inventory/item (via _variant-row partial),
      inventory/movements, inventory/_movement-block,
      coupons/edit, forms/edit, customer_groups/prices,
      subscribers/show
      The key-value settings table on settings/index
      stays on scroll by design.
      The variant quantity input moved from the
      inert ra-form-control class to Bootstrap's
      form-control.

**Session 4 shipped** (dc4320c, 21ee520, d0c26ab,
3aab0eb, aed259e): the storefront audit. Five
findings, five fixes.

  - Cart line media renders the product image.
    The partial had carried a Bootstrap icon
    placeholder since the cart shipped. The
    media library landed in 008G.4a; the
    placeholder was never replaced.
  - Cart line 5-column layout activates at
    1200 instead of 768. At 900-1199 the info
    column was too narrow for product names.
  - Account shell and chip strip both fire at
    900. The shell collapsed at 900 and the
    chip strip fired at 768; 769-899 was a
    gap where neither applied.
  - Product grid is 1 column below 481. The
    base was 2 columns at any width.
  - Category grid auto-fills at a 165px floor.
    The 7-column rule at 1024 produced 120px
    cards.

**Session 1 shipped** (3d200e4): the four breakpoints,
the component treatment matrix, the card grid rules
in main-v2.css. No view changes.

**Session 2 shipped** (937a78d, de967eb, 9ff8575,
f927cce): every admin list table carries a declared
treatment. `data-responsive="scroll"` or
`data-responsive="cards"` on the wrapper. Products
and five long-content tables on the card treatment.
The general `ra-hide-sm` rule at 899px.

**Session 3 shipped** (5265536, 94bee03):

  - Filter bars on 17 admin list views. New JS module
    public/assets/js/admin-filters.js. One script tag
    in the admin layout. Toggle styling in the theme
    contract.
  - Modals verified at 390, 768, 1440, both themes.
    Bottom sheet at mobile, centered at desktop. No
    code changes needed.
  - Sticky action bars verified at 390. Buttons stack
    vertical, full width. No changes needed.
  - Variants table moved from scroll to cards. The
    leftmost column (the combination) was cut off at
    mobile; the card treatment puts it full-width at
    the top of each row.

**What Session 3 did not do:** card grids were named
in the plan's Session 3 scope and were not fixed.
Four admin views carry `grid-template-columns` with
`auto-fit`/`auto-fill` and a `minmax()` value without
a media-query fallback: dashboard.php, inventory/index.php,
products/_tab_basic.php, setup/payments.php. Deferred
to Session 5 alongside the enforcement script that
will flag the same pattern.

**Session 4 shipped.** The storefront audit. Home,
catalog, category, product detail, cart, checkout,
account, plus the standalone error pages and the PWA
offline page. Test at 375, 768, 1024, 1440 in dark
and light. Five storefront fixes.

**Session 5 shipped.** `bin/check-responsive.php`
is a fourth prevenger checker. Three rules:
inline `@media` in views, hardcoded widths above
375px, and admin tables without a `data-responsive`
wrapper. The two rules it absorbed were removed
from `check-css.php` and `check-html.php`.

**Stage 014 is complete.** The responsive layout
system shipped across six sessions: foundations,
admin list tables, admin forms and modals, the
storefront audit, the enforcement script, and the
tail walk.

**The tail was walked in the same day's second
session** (`6515e85`, session file
`2026-10-14-2.md`):

  - Checkout at 900 verified passing. The
    speculative `min-width: 0` guard from the
    previous session reverted.
  - Error pages 403/404/500 verified theme-aware.
    All three use the same pattern the offline
    page now does.
  - PWA offline page rewritten to match the
    sibling error pages. `main-v2.css` precached
    by the service worker. `CACHE_VERSION` bumped
    from `v3` to `v4`.
  - Storefront dark theme: not applicable. The
    storefront is light-only by design (see the
    layout docblock in
    `resources/views/storefront/layouts/main.php`,
    line 34).

The prevenger's CSS structural checker caught a
multi-line declaration in the first draft of the
offline page rewrite. Fixed in the same session
before commit. `composer check` passes (lint,
PHPStan level 8, four prevenger checkers).

**008K.5 status:** Complete. Code on disk, admin
surfaces live (Coupons, Tax, Shipping), storefront
integration live, browser test passed end to end.
Tagged stage-008k.5-complete on 2026-10-11.

**008I status:** Complete. Sessions 1-4 plus 008I.1
hygiene shipped. No outstanding follow-ups.

**008H status:** Complete. The admin returns queue
shipped at d10b39d. COMPLIANCE-DE entry 8 (the 14-day
Widerrufsrecht window) moved from Deferred to Done at
the stage close. The Widerrufsbutton (entry 22) remains
open with a 19 June 2026 deadline.

**Next 3 stages:**

  - **010 Session 5** (next): the admin
    configuration tab (Settings > Payments with a
    row per provider), the compliance entries for
    Stripe and PayPal as DSGVO Art. 28 processors,
    and any sandbox verification still owed from
    Sessions 3 and 4.
  - **010 Sessions 6-7**: the stage close.
  - **Responsive render harness** (backlog):
    Chrome headless via CDP. Iterates every route
    at four viewports and checks for horizontal
    overflow. The static checker cannot catch
    rendered layout problems. This can. Its own
    stage, not part of Stage 014.

**Permission vocabulary:** 67 across 17 modules. The
seeders build it in layers: 0012 (the original 49),
0013 (pricing: coupons, tax, shipping - 8 new),
0014 (returns - 2), 0016 (newsletter - 4), and
0017 (forms - 4). Counted in the database:
`SELECT COUNT(*) FROM core_permissions` returns 67.
The coupons.*, tax.*, and shipping.* families were
added by the pricing engine; the newsletter.* family
by 008I; the forms.* family by 009 sessions 1-2b.

**Tooling:** composer check now runs four layers -
syntax linter, PHPStan level 8, and the prevenger
(theme contract, CSS structure, HTML structure).

**Error pipeline:** five commits shipped the
structured error logging pipeline:
  - Logger::log() falls through three tiers (file,
    error_log, STDERR) with a candidate-directory
    walk. No path returns without writing.
  - Every HTTP request gets a 12-hex request ID
    exposed as the X-Request-ID response header.
  - Logger::exception() carries file, line, trace,
    request_method, request_uri, user_agent,
    session_id, and user_id alongside the reference.
  - The admin dev overlay fetches the last 60
    seconds of exceptions from
    /commerce/ca-admin/_debug/recent-errors and
    displays them in the browser.

The pipeline is the foundation every future
debugging session builds on. A 500 that used to take
two hours to trace now lands in the browser overlay
with the file and line within one second.

---

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

- **009** (now): Forms & Submissions. Sessions 3 and 4
  remain. The public form surface and the submissions
  admin with filters and CSV export.
- **014**: Responsive Layout System. The four
  breakpoints, the component treatment matrix, the
  admin list table audit, the storefront audit, and
  the enforcement script. Prerequisite for every stage
  that ships a view.
- **010**: Payments (Deep). Wires refunds from the
  returns workflow to the payment provider.

The platform has completed the commerce loop end to end:
browse, cart, checkout, order, return; and on the
merchant side: products, orders, customers, returns,
users, roles, coupons, tax, shipping, newsletter,
forms. Every surface a merchant needs to operate the
store is live.

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
