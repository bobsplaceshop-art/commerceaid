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

## Coupling Principle

Features that share a schema, a calculation, or a workflow are
shipped as one stage. The test is: would shipping A first
require rewriting code when B ships? If yes, they are one
stage.

The principle exists because shipping half a computation
means the second half rewrites the first. Two examples:

  Coupons, tax, and shipping all compute into the same
  totals formula in CheckoutService::placeOrder(). Shipping
  coupons alone puts discount before tax in the code. Then
  the tax engine ships and the formula has to change to
  compute tax on the discounted base. The coupon work is
  rewritten. The three subsystems ship together as the
  Order Pricing Engine (008K.5).

  Store builder and theme engine both define the
  storefront's visual layer. The store builder edits the
  sections a theme renders. Shipping the builder without
  the theme engine means the section registry is written
  twice. They ship together.

The principle is a guideline, not a law. Three cases where
separate shipping is correct:

  - When the shared surface is trivially small. A two-line
    helper shared by two features does not justify merging
    the stages.
  - When one feature is a dependency of the other but the
    coupling is one-directional. Newsletter (008I) depends
    on Message Center (008G.3) but Message Center ships
    standalone and uses nothing from Newsletter.
  - When the feature sets are large enough that shipping
    them together would produce an unreviewable stage.
    Splitting is correct if the split is clean.

Applying the principle to the current roadmap:

  - 008K.5 becomes Order Pricing Engine: Coupons, Tax,
    Shipping. The three subsystems compute the same totals
    formula.

  - Stage 010 loses tax and shipping. It becomes Payments
    (Deep) only. It gains the responsibility of wiring
    refunds to the payment provider (consuming
    ReturnRequestService::processRefund() from 008H).

  - Stage 011 splits into 011 Advanced Commerce and
    012 Store Builder. Subscriptions, gift cards, and
    loyalty share nothing with the store builder UI. They
    were bundled under a single name because they were
    scheduled at the same time, not because they are
    coupled.

  - Stage 012 renumbers to 013. Theme Engine and Page
    Builder merge with Store Builder's section registry,
    which is what the original 012 plan already required.

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
| 008G.4a| Upload Core + Store Branding          | 2026-09-30 |
| 008G.4a.1| Media Library Page                   | 2026-09-30 |
| 008G.4a.2| Product Media                        | 2026-10-03 |
| 008G.4a.3| Responsive Image Templates           | 2026-10-04 |
| 008G.4c  | Self-Hosted Font Manager             | 2026-10-04 |
| 008G.4b  | PWA                                  | 2026-10-05 |
| 008G.5   | Product Reviews                      | 2026-10-05 |
| 008G.4d  | Category Filters                     | 2026-10-05 |
| 008J     | Container Auto-Construction          | 2026-10-05 |
| 008H     | Returns & Refunds                    | 2026-10-11 |
| 008I     | Newsletter & Email Campaigns         | 2026-10-11 |
| 008I.1   | Newsletter hygiene                   | 2026-10-12 |
| 008K.1   | Admin Orders                         | 2026-10-06 |
| 008K.2   | Admin Customers                      | 2026-10-06 |
| 008K.3   | Admin Users                          | 2026-10-10 |
| 008K.4   | Admin Roles                          | 2026-10-10 |
| 008K.5   | Order Pricing Engine                 | 2026-10-11 |
| 009      | Forms & Submissions                  | 2026-10-13 |
| 014      | Responsive Layout System             | 2026-10-14 |
| 010      | Payments (Deep)                      | 2026-10-15 |

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
  - Prevenger: three structural checkers (theme contract,
    CSS structure, HTML structure) + the aggregator wired
    into composer check (2026-10-12)
  - Error pipeline: three-tier logger, request IDs,
    structured exception context, debug endpoint, dev
    overlay opt-in (2026-10-13)
  - Logs hub: Activity Log, System Logs, Audit Trail, and
    Overview in one screen with store-aware rows
    (2026-10-13)

---

## In Progress

No stage in progress.

Stage 010 (Payments Deep) closed 2026-10-15 (tag
`stage-010-complete`). Both payment providers
verified end to end. See the Completed Stages
table below.

The storefront audit tail from Stage 014 remains
open: product detail at 900, checkout at 900,
error pages 403/404/500 at four widths, PWA
offline page at four widths, dark theme across
every storefront surface. When the tail is
walked, Stage 014 is formally closed.

## Planned Stages

### Stage 008K - Admin Surface Completion

**Goal:** Complete the merchant-facing admin surfaces. The
platform has a full customer-facing storefront (008C through
008H) but the merchant cannot see orders, customers, coupons,
or their own team. Five sidebar entries have been placeholders
since Stage 005.

**Why this stage exists now:** Stage 008H (Returns & Refunds)
was paused because its admin queue references an orders page
that does not exist. The admin surface gap is bigger than
returns - it is the merchant-facing half of the platform that
was never built. Completing the admin surface first gives
008H and every subsequent stage a coherent neighborhood to
land in.

**Sub-stages, in delivery order:**

| # | Sub-stage | Scope | Status |
|---|---|---|---|
| 008K.1 | Admin Orders | List with filters, detail with line items and status history, status transition actions. Feeds 008H and Stage 010. | Complete 2026-10-06 |
| 008K.2 | Admin Customers | List, detail, address book management, order history, group assignment, account state management. | Complete 2026-10-06 |
| 008K.3 | Admin Users | List, invite, role assignment, deactivate/reactivate, password reset trigger. | Complete 2026-10-10 |
| 008K.4 | Admin Roles | List, create custom roles, permission matrix editor, multi-store scoping, full permission vocabulary. | Complete 2026-10-10 |
| 008K.5 | Order Pricing Engine | Coupons (schema, admin CRUD, storefront apply), tax classes and rates, shipping methods and zones. Rebuilds the totals computation in CheckoutService::placeOrder() in the correct sequence: subtotal, then discount, then tax on the discounted base, then shipping. Per-product tax rate override with explicit inclusive/exclusive flag. | Complete 2026-10-11 |

008K.1 through 008K.4 were each a session or two. 008K.5
is a larger stage - roughly three sessions - because the
coupling principle merges three subsystems that share the
same totals computation. The whole 008K stage is roughly
eight sessions.

**Commits:**

  008K.1  dd6bf4c  feat(admin): orders + customer detail
  008K.2  8263f1f  feat(admin): customers list, profile edit, group + state
  008K.2  06ab56b  feat(admin): customer address management
  008K.3  f71cac1  feat(admin): user management surface
  008K.3  5c156cb  feat(auth): admin password reset, sign-out page,
                   correct redirects
  008K.4  f637871  fix(rbac): standardize super-admin slug checks
  008K.4  ff6bd9c  feat(rbac): roles editor and full permission vocabulary

**Depends on:** Stage 007B (order schema), Stage 007A
(customer groups), Stage 005 (admin shell and RBAC schema).
Does not depend on 008H.

**Blocks:** Stage 008H resumption. Stage 010 (payments deep)
needs an order detail surface to display payment state.
Stage 011 (store builder) assumes customers and orders exist
as first-class admin surfaces.

**Sequenced:** opens 2026-10-05, immediately after the 008H
pause.

**See also:** docs/stages/008k.1_admin-orders-plan.md (to be
written at 008K.1 open). Per-sub-stage plans are written at
each sub-stage's open.

**Appendix - surfaces a complete admin needs but are not in
008K:**

  - Dashboard reporting (sales over time, top products,
    low stock velocity). Currently the dashboard shows
    counters only.
  - Audit log viewer. core_audit_logs is written on every
    state-changing request but no admin page reads it.
  - Notification rules editor. core_notification_rules
    exists; the admin UI does not.
  - Settings groups beyond compliance and general.
  - A search-everything bar across admin.
  - A global admin action log (who did what, when).

  These are the fuller picture. They are not in 008K
  because the sidebar does not promise them and the
  current stages do not need them. They are logged here so
  the roadmap reflects what a merchant would eventually
  expect.

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


### Stage 008I - Newsletter & Email Campaigns

**Goal:** An inbuilt newsletter and campaign system. No
Mailchimp, no Klaviyo, no third-party marketing processor.
The subscriber list, the campaigns, and the sending
infrastructure all live inside the store's own deployment.

**Status:** Complete. Sessions 1, 2, 3a, 3b, and 4
shipped 2026-10-11.

**Shipped (2026-10-11):**

  78f926b  schema for subscribers, lists, campaigns, queue
  203d720  models and repositories (008I session 1b)
  f740f69  subscriber service and signing infrastructure
  a9c5d85  campaign and queue services, CLI worker
  594a4f2  subscriber and list admin surfaces
  da0aced  subscriber and list admin views
  ff96583  campaign admin surface (008I session 2)
  39995af  campaign detail + preview iframe
  ea0812b  docs(admin-guide): cover Returns, Coupons, Tax,
           Shipping, Newsletter
  cfe6a44  docs(session): 2026-10-11-4
  b17f7bf  storefront subscribe, confirm, unsubscribe
           (008I session 3a)
  50b9eaf  docs(session): 2026-10-11-5
  97cb3bf  docs: update STATUS and ROADMAP for 008I s3a
  3e840db  account preferences newsletter toggle
           (008I session 3b)
  648d962  resubscribeUrl points at /#newsletter
  1264676  docs(session): 2026-10-11-6

**Shipped (008I.1), 2026-10-12:**

  d99021b  GET/POST symmetry in
           NewsletterController::showUnsubscribe() -
           the GET now looks up the subscriber and
           renders the neutral error page directly
           when the row is gone.

  d99021b  "Did not ask to subscribe" link in
           resources/emails/newsletter/opt-in-confirmation.php.
           Every confirmation email now carries a
           one-click unsubscribe path, not just
           campaigns.

  With these two fixes, 008I has no outstanding
  follow-ups.

**What 008I delivered:**

  An inbuilt newsletter and campaign system with no
  third-party marketing processor. Subscriber list,
  campaigns, and sending all live inside the store's
  own infrastructure.

    - Subscriber list, subscriber lists, campaigns
      admin surfaces.
    - Campaign composer, preview iframe, send action.
    - Campaign detail with stats tiles.
    - Storefront subscribe form on the footer and home
      page.
    - Double opt-in confirm flow.
    - Unsubscribe from a token link or from the account
      preferences toggle.
    - Per-email rate limit on confirmation sends
      (subscription-bomb protection).
    - Honeypot on the public subscribe form.
    - CLI queue worker at bin/send-emails.php.
    - Consent evidence captured per subscriber
      (source, IP, user agent, timestamp).
    - Unsubscribe acknowledgement email on every
      unsubscribe.

  German compliance satisfied: double opt-in by
  default, consent evidence retained, unsubscribe link
  in every campaign and (after 008I.1) every
  confirmation email, no data leaves the store.

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

**Status:** Complete 2026-10-11.

**Goal:** Customer-initiated return requests. Integrates the
German Widerrufsrecht (14-day right of withdrawal) with a
real workflow.

**What shipped:**

Sessions 1 and 2:

  - `commerce_return_requests` and
    `commerce_return_request_items` tables.
  - Models, repositories, and `ReturnRequestService`.
  - Three store settings for the return window,
    pre-delivery requests, and auto-approval.
  - Customer surface: `/account/returns` three-tab landing,
    submission form, detail page, cancel action.
  - Returns nav item and breadcrumb.
  - "Return in progress" badges on the order list and order
    detail header.
  - Per-line "N being returned" annotation.
  - Refund / net-paid rows in the order totals.

Session 3 (2026-10-11):

  - `AdminReturnController` at `/commerce/ca-admin/returns`
    with six actions: index, show, approve, reject,
    markReceived, refund.
  - List with four status tabs (open, received, closed,
    all), a request-number search, and a date range.
  - Detail with line items, customer, original order,
    audit timeline, and state-aware action buttons.
  - Three modals: reject (with reason), receive (with
    per-line received quantities), refund (confirmation).
  - `markReceived` accepts received quantities per line.
    The refund adjusts from what the warehouse actually
    counted, not what the customer requested.
  - `ReturnRequestRepository::buildFilters()` gained
    `date_from` and `date_to`.
  - Permissions: `returns.view` (list, detail) and
    `returns.moderate` (approve, reject, receive, refund).
  - Sidebar item under Sales.

**Commits:**

  50f77b6  feat(returns): schema, models, repositories, service
  8a3e56b  feat(returns): customer-facing surface + order list
  3a6cbcd  feat(returns): admin queue permission slugs +
           Widerrufsbutton log
  b2a3dae  feat(returns): markReceived accepts received
           quantities per line
  d10b39d  feat(returns): admin returns queue (008H session 3)

**Refund API wiring is deferred to Stage 010.** Session 3
calls `ReturnRequestService::processRefund()`, which updates
`refunded_total`, `payment_status`, and the order item's
`returned_quantity`. The actual money movement - calling the
payment provider's refund API - lands with the Payments
(Deep) stage. The `refunded_total` column is the
authoritative record of what is owed; Stage 010 reconciles
it against the provider.

**Depends on:** Stage 008K.1 (admin orders detail page),
Stage 008K.5 (pricing engine). Both shipped before this
stage resumed.

**Compliance:** closes COMPLIANCE-DE.md entry 8 (the 14-day
Widerrufsrecht window). The entry moves from Deferred to
Done at this close.

**Deferred:** condition grading and resellable flags per
line (restocking concerns, not compliance). A `statuses`
array param on the repository to replace the multi-status
tab merge workaround. The Widerrufsbutton (COMPLIANCE-DE
entry 22) is a separate compliance stage; deadline
19 June 2026.

### Stage 009 - Forms & Submissions

**Status:** In progress. Sessions 1, 2a, and 2b shipped
2026-10-12.

**Goal:** Native form builder. The merchant creates
forms without code, embeds them in the storefront, and
manages the submissions they receive. Targets the
paid-tier feature set of the Shopify form-builder
marketplace at no additional cost: no submission caps,
no storage limits.

**Shipped (2026-10-12):**

  913ec60  docs(009): stage plan
  8478468  feat(forms): schema, models, repositories
  90073f2  docs(session): 009 session 1
  fc8a992  docs: mark Stage 009 Session 1 shipped
  e7263b6  feat(forms): FormService, admin controller,
           list + editor
  d4e157c  docs(session): 009 session 2a

  Session 1 delivered:

    - Three tables: commerce_forms,
      commerce_form_fields, commerce_form_submissions.
    - Three models: Form, FormField, FormSubmission.
    - Three repositories: FormRepository,
      FormFieldRepository, FormSubmissionRepository.
    - Permissions seeded (seeder 0017): forms.view,
      forms.manage, forms.submissions.view,
      forms.submissions.manage.

  Session 2a delivered:

    - FormService with form CRUD, duplicate, and
      status transitions (publish, unpublish,
      archive, soft delete).
    - AdminFormController with eleven actions.
    - Three admin views: index (list with status
      tabs and counts), create, edit (metadata +
      status card + fields/submissions placeholders
      + danger zone).
    - Eleven admin routes, gated by forms.view /
      forms.manage.
    - Sidebar item under Marketing.

  Session 2b delivered:

    - Four new methods on FormService: addField,
      updateField, deleteField, moveField.
    - Four new actions on AdminFormController plus
      four field routes.
    - The field editor modal, using the shared
      .ra-modal* system. Type-conditional config
      sections generated from InputType::configFields().
      Options editor for option-bearing types.
      Conditional visibility select populated from
      the form's own fields.
    - Rebuilt field list with position, label,
      key, type, step, required badge, and per-row
      actions (move up, move down, edit, delete).
    - Fixed SelectType::render() (was producing a
      file input) and the InputType::render()
      docblock (narrowed \$options to string[]).

**Remaining:**

  Session 3 - the public surface and submission
  processing. FormSubmissionService, FormRenderer,
  public FormController, two public routes, conditional
  logic JS, multi-step rendering.

  Session 4 - submissions admin with filters and CSV
  export, domain event firing, notification and webhook
  integration, stage close.

  Session 3 - public surface (GET /forms/{slug}),
  submission processing (POST /forms/{slug}),
  conditional logic JS, multi-step rendering.

  Session 4 - submissions admin with filters and CSV
  export, domain event firing, notification and webhook
  integration, stage close.

**Deferred sub-stages:**

  009.1  Drag-and-drop field editor.
  009.2  Field calculations and scoring.
  009.3  Payment fields (belongs in Stage 010).
  009.4  Form templates gallery.
  009.5  Analytics (views, conversion, abandon rate).
  009.6  PDF export of submissions.

**Industry reference:** the paid-tier feature set of
the Shopify form-builder marketplace (Form Builder,
Typeform, Jotform, Powr). Forminator is the conceptual
model for the field type registry and the conditional
logic operators. Shopify native Forms is not the
benchmark (email/SMS capture only).

**Depends on:** Stage 008 (storefront), Stage 008I
(honeypot + rate-limit patterns).

**See also:** docs/stages/009_forms-plan.md

### Stage 010 - Payments (Deep)

**Goal:** Full payment provider integrations. Real money
movement: charging cards, moving M-Pesa, calling Stripe,
handling webhooks, issuing refunds through the provider.

**Why shipping and tax are not here:** both move to
008K.5 (Order Pricing Engine). They compute into the same
totals formula that coupons compute into. Shipping them
in a later stage would force a rewrite of the coupon code
from 008K.5. The coupling principle places them where they
belong.

**Deliverables:**

- Payment provider registry
- Reference implementations: M-Pesa, Stripe, PayPal, Pesapal,
  Flutterwave, Manual, COD, Bank Transfer
- Webhook handling per provider
- Refund processing: consume `ReturnRequestService::processRefund()`
  from 008H Session 3 and call the provider's refund API.
  The `refunded_total` column is the authoritative record of
  what is owed; the provider's response is the confirmation
  that it moved.
- Payment state on the order: `paid_total` incremented as
  payments clear. `payment_status` transitions from
  `unpaid` to `paid` (or `partial_refunded` / `refunded`
  when applicable).

**Depends on:** Stage 008E (checkout), Stage 008K.5 (pricing
engine - the order totals are now the source of truth),
Stage 008H (returns - the refund intent is written).

**Sub-stages:**

| # | Provider | Status |
|---|---|---|
| 010  | Stripe, PayPal | Complete 2026-10-15 |
| 010.1 | M-Pesa | Complete 2026-10-17 |
| 010.2 | Pesapal | Code complete 2026-10-17, browser test pending |
| 010.3 | Flutterwave | Code complete 2026-10-17, browser test pending |
| 010.4 | Manual | Complete 2026-10-16 |
| 010.5 | Cash on Delivery | Complete 2026-10-16 |
| 010.6 | Bank Transfer | Complete 2026-10-16 |

Each sub-stage ships one provider. The registry, the
interface, and the HTTP client (Stage 010 Session 1)
support every provider that follows.

**Commits:**

  010    863c950  PayPal webhook delivery and capture
         08c0638  Stage 010 close (tag: stage-010-complete)
  010.1  75b8e62  M-Pesa schema, settings, repository
         581e802  M-Pesa phone field, failure cleanup,
                  token trim
  010.2  f4140ee  Pesapal schema, settings, repository
         6bbc911  Pesapal provider class and webhook
         ea4096c  Pesapal return controller and cleanup
                  cron
  010.3  eec5e75  Flutterwave schema, settings, repository
         38c995d  Flutterwave provider class and webhook
         f7b9fd8  Flutterwave return controller and
                  cleanup cron
  010.4  5bb5e76  Offline providers: cod, bank_transfer,
  010.5           manual
  010.6

### Stage 011 - Advanced Commerce

**Goal:** Consumer features that extend the basic purchase
flow. Subscriptions, gift cards, multi-location inventory,
and loyalty programs.

**Deliverables:**

- Subscriptions: recurring products, billing cycles
- Gift cards: purchase, redeem, balance tracking
- Multi-location inventory: warehouses, transfers
- BOPIS: buy online pick up in store
- Store credit, loyalty points

**Depends on:** Stages 006-010. The store builder (012) and
the theme engine (013) are separate concerns.

**Why separate from the store builder:** subscriptions,
gift cards, and loyalty change what a customer can buy and
how. The store builder changes how the storefront looks.
They share no schema, no calculation, and no workflow. The
coupling principle does not apply.

### Stage 012 - Store Builder

**Goal:** The admin UI for editing the storefront's
structure and content without touching code. The homepage
sections, navigation menus, trust badges, and social links.

**Deliverables:**

- `commerce_homepage_sections` table (section type, order,
  config JSON)
- `commerce_navigation_menu_items` table (header + footer nav)
- `commerce_store_trust_badges`, `commerce_store_social_links`
- Drag-drop section editor
- Live preview using the storefront views
- Section type registry (the block types a merchant can
  place on the homepage)
- Per-section configuration forms (which category, how many
  products, sort order, etc.)

**Companion to:** Stage 013 (Theme Engine & Page Builder).
The store builder edits the sections; the theme engine
renders them in different visual styles. The data model
(the section registry) is shared between the two. They are
coupled and should be built with the second in mind. The
Store Builder ships the editing surface; the Theme Engine
ships the visual templates and the page builder that
renders the same sections on non-homepage pages.

**Depends on:** Stages 006-011.

### Stage 013 - Theme Engine & Page Builder

**Goal:** Full storefront theming system with 20+ industry
themes. This is the layer that consumes the section registry
defined by the Store Builder.

**Deliverables:**

- Theme contract (directory structure, theme.json manifest)
- Theme registry: install, activate, list, preview
- Theme customizer: colours, fonts, layout, live preview
- Page builder: renders the same section types as the
  Store Builder, but on arbitrary pages (not just the
  homepage)
- Section types: hero, product grid, category grid,
  testimonials, rich text, video, banner, newsletter,
  FAQ, form, spacer
- Navigation management (reads the menu data written by
  the Store Builder)
- At least 20 themes: Modern, Minimal, Fashion, Luxury,
  Electronics, Furniture, Grocery, Restaurant, Services,
  Beauty, Home & Living, Gifts, Pharmacy, Wholesale/B2B,
  Kids, Sports, Auto Parts, Real Estate, Digital Products,
  Marketplace

**Depends on:** All prior stages. In particular the Store
Builder (012) defines the section registry that the theme
engine renders.

**Why paired with the Store Builder:** the two stages
define one layer - how the storefront is composed and
displayed. The Store Builder is the editing UI. The Theme
Engine is the rendering layer plus the style catalogue.
Shipping the Store Builder alone produces a data model
nothing renders. Shipping the Theme Engine alone produces
templates with no editable source. They ship as adjacent
stages with a shared data model.

### Stage 023 - Page Builder (Tier 0.8 complete)

**Status:** Tier 0.8 complete 2026-10-25. Tier 3 next.

**Goal:** A merchant-editable page builder. The
merchant composes storefront pages by dragging block
types into a tree. The tree persists across reloads
and renders identically in the admin preview and on
the storefront.

**Shipped (2026-10-24, 2026-10-25):**

Tier 0 foundation — the registries, the dispatcher,
the tree model:

- `58df9c0` ConfigFieldRegistry — shared field types.
- `dc9f74d` parent_id on `commerce_page_blocks` and
  `commerce_page_drafts`.
- `850817c` PageBlock `$parentId`, `$children`.
- `32677a7` PageBlockRepository parent-aware.
- `84bb976` DraftManager::getMergedTree(); DraftPublisher
  two-pass publish.
- `0be9240` BlockRenderer recursion + client tree Phase 1.

Drag rewrite, Phases 1-5 — the client tree becomes
the source of truth for drag:

- `0be9240` Phase 1: client tree on load.
- `92031d0` Phase 2: `moveNode()` updates the tree.
- `0b5fe1f` Phase 3: `saveTree()` persists
  `parent_id` + `sort_order`.
- `229dc89` Phase 4: client sends the tree.
- `645e596` Phase 5: sidebar re-renders from
  `<template id="tpl-block-row">`.

Companion fixes:

- `28ff776` Product grid 3-8 columns + 4 alignment
  options.
- `127a1ea` Product grid respects category_slug and
  source; legacy home sections removed.
- `ac274e3` `best_sellers` source uses
  `ReportRepository`.
- `c39922f` Config save double-wrap, Hero bg UUID,
  print CSS contract.
- `264165d` Empty product grid blocks collapse on the
  live storefront.
- `e4d4377` Media URL resolution across 7 files +
  `bin/check-media-urls.php` prevenger.
- `2a361af` Event delegation for settings and
  mobile-close.
- `1038be6` Product `og:image` is absolute.
- `574f559` Pattern 46 in `docs/REMEMBER.md`.

Navigation builders, trust bar as a block, block config
modernization (2026-10-26):

- `cd1fc13` Navigation data layer (Tier 1).
- `4293261` Trust bar moved into the database.
- `3bd302a` Trust bar items edited from the header builder.
- `f31e357` Navigation, trust bar, and modal theming.
- `919a6a5` Storefront header, footer, preview flip to navigation db.
- `57aad20` Visual icon picker for navigation items.
- `b80e81c` Footer top, column management, preview isolation.
- `33bef2f` Theme-aware builder shell.
- `2d6f056` Menu builder with custom menu edit page.
- `104bb78` Pattern 49 - Bootstrap modals do not inherit theme.
- `ab997c8` Section/container fields, direct-create dropdown,
  depth tree.
- `85d39ce` Typography fields on text blocks.
- `6d22113` Composite typography panels per text role.
- `32fad95` Live preview reloads structural blocks.
- `5fc72a3` Live preview and container layout fix.
- `ada72ca` Media variant generation loop and directory fix.
- `34c5a4d` Full-text search + premium autocomplete.
- `da7ffc5` Abandoned-cart cleanup + admin stock release.
- `fc8a161` Block config panel modernization (sections, pairs,
  BlockBackground, 420px sidebar).
- `c637fb1` deleteDraftTree removes children of every status.
- `9b5a6bf` Drop guarded var() references to undefined tokens.
- `a9917f0` Move band media queries to shared stylesheet.
- `d40e5b5` Correct four docblocks fc8a161 shipped with errors.
- `b890638` Shared scripts partial + product card redesign.
- `106dd53` Complete trust bar block migration.

**What is complete:**

- Product grid: 3-8 columns, 4 alignment options,
  category and source filters, `best_sellers` wired.
- Drag-and-drop: reorder within parent, drag child
  to root, drag root into Section/Container.
- Sidebar re-renders after drop. Settings pencil
  works after any drag (delegation).
- Row markup has one source (`tpl-block-row`).
  Tree has one source (`window.__builderTree`).
  Click handling has one source (`document`
  delegation).
- Media URL bug class closed. No numeric id in any
  `/media/` URL in `app/` or `resources/`.

**Tier 3 next (three items):**

1. **Section and Container configuration fields** —
   padding, background, column count. The layout
   primitives exist as empty wrappers; the config
   fields make them useful.
2. **Drag-into-canvas** — dropping a block onto the
   preview iframe. Resolves drop coordinates to a
   target block and insertion side. Needs a plan
   document.
3. **Menu and footer builder** — header and footer
   nav read from `commerce_navigation_menus` and
   `commerce_navigation_items` instead of the
   hardcoded arrays in `layouts/main.php`.

**Depends on:** Stage 008 (storefront shell),
Stage 012 (section registry — deferred until the
page builder needs to read its data).

**See also:** `docs/stages/023_page-builder-architecture.md`
for the tier plan and the dependency graph.

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
lands with Stage 012 (Store Builder) and Stage 013
(Theme Engine & Page Builder).

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
basic version, or 012 (Store Builder)
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

### AVIF Image Variants (008G.4a.4)

Add AVIF encoding to the media pipeline as a third
format alongside WebP and JPEG. AVIF produces 30-50%
smaller files than WebP at the same visual quality.

The `<picture>` markup shipped in 008G.4a.3 is already
forward-compatible: the helpers emit a
`<source type="image/avif">` only if an AVIF variant
exists for the file. When this sub-stage lands, every
call site automatically starts including AVIF — no
markup change needed.

Scope:

  - Extend MediaProcessor::generateVariants() to
    produce `.avif` alongside `.webp` and `.jpeg`.
  - Extend each MediaVariantSet context's format
    list with 'avif'.
  - Backfill existing variants: re-run the pipeline
    on demand for files with no AVIF variant, or
    schedule a batch job.

Prerequisite check: whether the installed GD build
has AVIF encoding enabled. PHP 8.1+ supports it, but
GD builds vary. Test with:

    php -r 'var_dump(function_exists("imageavif"));'

If false, either rebuild GD or add an Imagick
fallback path.

Sequenced: after 008G.4a.3. Before 008G.4b (PWA),
so the service worker caches every format from day
one.

### Blur-Up Image Placeholders

Render a tiny inline base64 placeholder as the `<img>`
src, then swap to the real image once it loads. No
visible pop-in, no layout shift after first paint.

Scope:

  - Generate a very small variant (32x40 or similar)
    per image.
  - Encode it inline as base64 in the `<img src>`.
  - A small JS handler swaps the src once the real
    image has loaded.
  - Decide which images get blur-up: product cards
    only, or hero too.

Trade-off: adds ~200-400 bytes per image to the HTML
for a store with many product cards. Worth doing only
if a performance audit shows the LCP or CLS metric
needs it.

The `width` and `height` attributes shipped in
008G.4a.3 already prevent layout shift. Blur-up is a
further polish, not a fix.

Likely stage: not yet scheduled. Evaluate after
008G.4a.3 ships and a Lighthouse run gives a
baseline.

### Category Hero Toggle

A per-category setting to hide the hero banner.
Useful for categories where the merchant does not
have a 3:1 image and prefers to show only the title
and description.

Two complementary features:

  - A "Show hero image" checkbox on the category
    editor (default on).
  - When off, `category/show.php` renders the hero
    section without the image div, falling back to a
    plain text header.

The current default (always show, crop to fill) is
the industry standard. The toggle is a merchant
preference, not a bug fix.

Likely stage: 008G.4c or a future storefront polish
pass.

### Self-Host Bootstrap CSS + JS

Bootstrap 5.3.3 ships from cdn.jsdelivr.net in both the
admin layout and the storefront layout. The CSP allows
that origin for script-src and style-src because of it.
Self-hosting Bootstrap is a separate concern from the
self-hosted fonts and icons (008G.4c).

Scope:

  - Download bootstrap.min.css and
    bootstrap.bundle.min.js into public/assets/bootstrap/.
  - Replace the two CDN references in the admin and
    storefront layouts.
  - Drop cdn.jsdelivr.net from the CSP entirely.
  - Add a version-pinning note: the local copy is
    upgraded manually when a new Bootstrap release is
    adopted.

Why deferred: Bootstrap is a versioned dependency, not a
brand asset. Bumping it means downloading a new file and
verifying no breakage. That is a mechanical but
deliberate step, not a one-time self-host like the fonts
and icons were. Best done as its own small sub-stage when
the CSP tightening is actually needed.

**Likely stage:** 008G.4c.2 or a future storefront polish
pass.

---

### Font Preview Panel (008G.4c.1)

The Fonts tab on the Store Settings page offers three
dropdowns (body, display, script), each populated from
the FontCatalog's 50 curated fonts. The merchant picks
a font and saves, but there is no way to see what the
font actually looks like until they reload the
storefront.

Scope:

  - A live preview line under each select showing
    sample text rendered in the currently selected font.
  - A grid preview of the whole catalog filtered by
    slot, with each font's name and a sample line
    rendered in that font.

Mechanism (recommended):

  - On selection, a small AJAX call triggers
    FontDownloader::download() for the chosen slug.
    Once the WOFF2 is on disk, the browser re-renders
    the preview using the now-available @font-face.
  - One-time download per font per store install.
    Files land in public/assets/fonts/{slug}/ and are
    served by Apache directly.

Deliberately not included in the 008G.4c stage:

  - The stage had a specific compliance goal (drop
    Google Fonts from the CSP). Preview is merchant UX,
    not compliance.
  - Preview needs a new route, a new JS module, and a
    per-selection download trigger. Real scope of its
    own.

**Likely stage:** 008G.4c.1 or a later storefront
polish pass.

---

### Services Extension (Flights, Hotels, Cargo)

The client has asked for three new services alongside the
existing Online Store: Travel and Flight Booking, Hotel
Booking, and Cargo Services.

The four sections share one brand, one website, one
customer account, one payment system, and one admin
panel. Every existing capability of the platform is
inherited for free: accounts, orders, payments, email,
returns, tax, compliance. Only the service-specific
logic is new work.

A questionnaire is live at
`/forms/services-questionnaire`. The client fills it in
once. The answers determine which tier fits each
service. Pricing follows from the tier. The form is
deleted after the answers are read.

Four tiers per service, from a landing page and enquiry
form to a live supplier integration.

  Flights
    Tier 1 - Landing and enquiry form.       $150
    Tier 2 - Form and manual booking.        $250
    Tier 3 - Live booking via supplier API.  $5,000
    Tier 4 - Agency platform.                $11,000 to $18,000

  Hotels
    Tier 1 - Landing and enquiry form.       $150
    Tier 2 - Static catalogue.               $250
    Tier 3 - Date-range catalogue.           $550
    Tier 4 - Live supplier API.              $3,000 and up

  Cargo
    Tier 1 - Landing and form.               $150
    Tier 2 - Detailed quote request.         $250
    Tier 3 - Quote, payment, tracking.       $550
    Tier 4 - Live carrier integration.       $1,800 and up per carrier

Tier 3 and Tier 4 for Flights, Tier 4 for Hotels, and
Tier 4 for Cargo require a supplier contract and a
documented API. The development fee depends on the
completeness of that documentation. The client chooses
the supplier. The effort follows from that choice.

Nothing is built. The questionnaire is the first step.
The tier decisions come after. Each service is its own
sub-stage once the tier is chosen.

**Status:** awaiting the client's answers.

**See also:** `docs/stages/services-extension-questionnaire.md`
(to be written) for the full questionnaire text.

---

### Country Format Extensions

The country dropdown on the checkout shipping step
shipped with 249 ISO 3166-1 entries, self-hosted SVG
flags from Twemoji, and a searchable filter. The phone
field validates against the selected country for twenty
markets.

Shipped today:

  - The full ISO 3166-1 country list.
  - Self-hosted SVG flags, one file per country.
  - A searchable dropdown with keyboard navigation.
  - Phone validation with length bands and dial-code
    matching for twenty markets.

Deferred:

  - Dial codes for every country. The current list
    covers the twenty markets the platform serves most
    (Germany, Kenya, United States, United Kingdom,
    France, Italy, Spain, Netherlands, Austria,
    Switzerland, Belgium, Ireland, Portugal, Sweden,
    Norway, Denmark, Finland, Poland, Australia, and
    Canada). Every other country falls through to a
    loose phone check of six to fifteen digits.
  - Per-country phone prefix validation. The current
    validator checks length and dial-code match. A full
    check requires every country's numbering plan.
  - Full `libphonenumber` integration. The correct
    library for numbering-plan-level accuracy. A
    composer dependency of about five megabytes. The
    right call when the platform serves merchants with
    a customer base spanning many countries.

The current approach catches the two most common
mistakes: an empty phone number and a number whose
country prefix does not match the selected country.
Full format parsing is a future pass.

Priority: low. The platform accepts any reasonable
number. Format polish can wait until a merchant asks
for it.

**Likely stage:** a sub-stage of Stage 011 or a
standalone localisation stage.

---

### Abandoned Checkout Recovery

The cart and the checkout wizard state now survive
an abandoned payment. A customer who leaves the
payment provider's page without completing the
payment returns to a full cart. This shipped
2026-10-15 (commit e4525cb).

What is not yet built:

  - **Auto-expire unpaid orders.** An order placed
    with a payment provider that never completes
    sits `unpaid` in the database forever. A
    scheduled task should cancel unpaid orders older
    than N hours. The `core_scheduled_tasks` table
    exists for this. The window is configurable per
    store, default around sixty minutes.

  - **Restore reserved stock.** When an order is
    auto-cancelled, the inventory reservation must
    be released. That is a compensating movement in
    the ledger, not a silent update.

  - **Recovery email (optional).** A customer who
    abandons mid-payment receives a follow-up email
    with a link to resume the checkout. Consent for
    this email must be collected at the checkout
    step for German deployments. The consent banner
    and the notification preferences page already
    exist. Wiring them is a small feature.

The industry reference for the recovery flow:

  - WooCommerce recommends clearing the cart on
    confirmed, not created. It is the setting the
    platform now matches.

  - Stripe's `checkout.session.expired` webhook
    fires when a Checkout Session hits its
    `expires_at`. The default is 24 hours.
    Enabling `after_expiration.recovery` on the
    session returns a recovery URL in the webhook
    payload that recreates a fresh session.

  - Recovery emails are the standard follow-up.
    One at ~1 hour after expiry, one at 24 hours,
    one at 72 hours.

**Priority:** medium. The cart-survival piece is
done. The cleanup and recovery pieces are quality-
of-life improvements that matter more once the
store has real order volume.

**Likely stage:** a sub-stage of Stage 010 or a
standalone operational stage.

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
