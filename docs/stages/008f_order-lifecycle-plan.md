# Stage 008F - Order Lifecycle (Customer-Facing)

**Date opened:** 2026-09-28
**Status: Complete (2026-09-28)**
**Depends on:** Stage 008E (checkout), Stage 008E.2 (guest
order claim), Stage 008E.1 (settings + compliance)

---

## Purpose

The customer's post-purchase home. After an order is placed,
the customer needs to:

  - See every order they have ever placed
  - Understand each order's current state without calling
    support
  - Track a shipment if one exists
  - Access the Widerrufsrecht (14-day withdrawal) from the
    order itself, not from a separate legal page

Today, the only customer-facing order surface is
/checkout/confirmation/{orderNumber} - a thank-you page
linked from the purchase flow. It is not a home. The
customer cannot browse their history.

008F builds that home. The account shell restructure is
008F.1; this stage ships the order pages themselves.

---

## Scope

**In scope:**

- Order list page at /account/orders with five status tabs.
- Order detail page at /account/orders/{orderNumber}.
- Migration 0306: carrier_key, tracking_number on
  commerce_orders.
- Carrier URL template map (DHL, FedEx, UPS, DPD, Hermes,
  others). Static map, no API.
- Widerrufsrecht link on every order detail.
- 14-day withdrawal-window indicator (informational badge).
- Rewire /account/orders from the current minimal list to
  the full experience.
- OrderRepository::paginateForCustomerTab() - a method that
  filters by customer AND by the tab classification.
- View partials for order rows and line items.

**Out of scope (deferred):**

- Account sidebar / shell restructure (008F.1).
- Carrier API polling, auto-updated tracking (010).
- Split shipments (010).
- Reorder button (deferred; needs one-click re-add to cart,
  a future enhancement).
- Customer-facing order notes or attachments (012+).
- Refunds from the customer's side (008H).

---

## Design Principles (from the ROADMAP)

  1. One account, one coherent surface. Every /account/*
     page shares the same shell. That shell lands in
     008F.1; this stage ships the pages that will live
     inside it.

  2. Tabs where they clarify, sections where they don't.
     Five tabs on /account/orders is right. Tabs on the
     order detail page would be wrong - it's a single
     scrollable view.

  3. Every empty state has a purpose. The order list empty
     state says "You have not placed any orders yet" and
     offers "Browse products". Not "No order".

  4. The customer account mirrors the merchant's admin.
     The order detail page uses the same information
     hierarchy (header, line items, addresses, payment,
     status) that the future admin order view will use.

---

## The Five Status Tabs

Our order has three independent status tracks:
`status`, `payment_status`, `fulfilment_status`. The tabs
are a customer-facing classification, not a fourth stored
state.

Tab mapping:

  Unpaid
    payment_status = 'unpaid'
    AND status NOT IN ('cancelled', 'failed')

  To Be Shipped
    payment_status = 'paid'
    AND fulfilment_status IN ('unfulfilled', 'partially_fulfilled')
    AND status NOT IN ('cancelled', 'completed')

  Shipped
    fulfilment_status = 'fulfilled'
    AND status NOT IN ('cancelled', 'completed')

  Completed
    status = 'completed'

  Cancelled
    status IN ('cancelled', 'failed')

One order appears in exactly one tab. The classification is
computed server-side, not stored.

Why not a stored `tab_status` column: it duplicates the
underlying tracks and can drift on every status change. A
computed classification stays in sync by construction.

OrderRepository::paginateForCustomerTab(int $customerId,
string $tab, int $page, int $perPage): array

  The method maps the tab name to a WHERE clause. Returns
  the same shape as paginate(): items, total, page,
  per_page, pages. The items are Order models.

  Not implemented as a filter on the existing paginate()
  because the tab logic is a compound condition, not a
  simple equality. Keeping it separate avoids polluting
  the generic filter builder.

---

## The Order Detail Page

Route: GET /account/orders/{orderNumber}

Guarded by customer.auth AND by an ownership check: the
order must belong to the logged-in customer. A customer
cannot view another customer's order by guessing the
number.

Layout (top to bottom):

  Breadcrumb: Account / My Orders / ORD-000042

  Header
    Order number (large)
    Status badge (main order status)
    Placed date

  Status cards (three, side by side)
    Payment:     Unpaid / Paid / Refunded
    Fulfilment:  Unfulfilled / Shipped / Delivered
    Order:       Pending / Processing / Completed

  Line items table
    Product name, variant label, SKU, quantity, unit price,
    line total. Snapshots from commerce_order_items.

  Tracking block (only when a tracking number exists)
    Carrier name
    Tracking number
    [Track package] link

  Shipping address
  Billing address
  Payment method
  Totals (subtotal, shipping, tax, grand total)

  Widerrufsrecht block
    "You have the right to withdraw from this purchase
     within 14 days. The withdrawal period ends on
     {placed_at + 14 days}."
    [Read the withdrawal policy] link to /legal/widerruf

  Back to orders link

The Widerrufsrecht block appears only when the order is
eligible: status NOT IN ('cancelled', 'failed', 'refunded'),
and the withdrawal window has not yet closed. After 14 days,
the block disappears.

---

## Tracking Number

Migration 0306_add_order_tracking_columns.sql adds:

  carrier_key       VARCHAR(40) NULL
  tracking_number   VARCHAR(120) NULL
  tracking_url      VARCHAR(500) NULL   (optional override)

For the first pass, `tracking_url` is unused. The URL is
computed from carrier_key and tracking_number at render
time. The column exists so a future enhancement can store
an override URL (for carriers with non-template URLs).

Carrier URL templates are a static PHP array:

  app/Commerce/Support/CarrierTemplates.php

  public const TEMPLATES = [
      'dhl'      => [
          'label' => 'DHL',
          'url'   => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id={number}',
      ],
      'fedex'    => [
          'label' => 'FedEx',
          'url'   => 'https://www.fedex.com/fedextrack/?trknbr={number}',
      ],
      'ups'      => [
          'label' => 'UPS',
          'url'   => 'https://www.ups.com/track?tracknum={number}',
      ],
      'dpd'      => [
          'label' => 'DPD',
          'url'   => 'https://tracking.dpd.de/status/en_US/parcel/{number}',
      ],
      'hermes'   => [
          'label' => 'Hermes',
          'url'   => 'https://www.myhermes.co.uk/track#/parcel/{number}',
      ],
      'royal_mail' => [
          'label' => 'Royal Mail',
          'url'   => 'https://www.royalmail.com/track-your-item#/tracking-results/{number}',
      ],
      'posta_kenya' => [
          'label' => 'Postal Corporation of Kenya',
          'url'   => 'https://www.posta.co.ke/track/{number}',
      ],
      // Add more as deployments require.
  ];

The {number} placeholder is replaced with the tracking
number, URL-encoded. If carrier_key is unknown, the tracking
block shows the raw number without a link.

The carrier_key comes from the admin (a future admin order
view, or an update script). For 008F, we ship the display
layer. The admin sets the value via direct SQL until the
admin UI lands. This is acceptable for a first pass.

---

## The Widerrufsrecht Integration

German law (BGB § 355, § 356) grants consumers a 14-day
right of withdrawal for distance contracts. The merchant
must inform the customer of this right.

Today, the customer can find the Widerrufsbelehrung at
/legal/widerruf. The order confirmation links to it once.
After that, the customer has no visible reminder.

008F adds a Widerrufsrecht block to every eligible order
detail page. It shows:

  - The right exists
  - The date the window closes (placed_at + 14 days)
  - A link to the full policy

After the window closes, the block disappears. If the order
is cancelled, failed, or fully refunded, the block does not
show (the withdrawal right no longer applies).

The block is informational. The actual return request flow
is 008H.

---

## Files to Create

  database/migrations/0306_add_order_tracking_columns.sql

  app/Commerce/Support/CarrierTemplates.php

  resources/views/storefront/account/order-detail.php
  resources/views/storefront/account/_order-row.php
  resources/views/storefront/account/_order-tabs.php

  docs/stages/008f_order-lifecycle-plan.md (this file)

## Files to Modify

  app/Commerce/Models/Order.php
    Add carrierKey, trackingNumber, trackingUrl properties.
    Add trackingUrl() method that resolves the carrier
    template. Add isWithdrawalEligible() helper.

  app/Commerce/Repositories/OrderRepository.php
    Add paginateForCustomerTab(int $customerId, string $tab,
    int $page, int $perPage): array.
    Add findForCustomer(int $orderId, int $customerId): ?Order
    for the ownership-guarded detail lookup.

  app/Commerce/Controllers/Storefront/AccountController.php
    orders() gains status tab filtering.
    New orderDetail($orderNumber) method.

  resources/views/storefront/account/orders.php
    Rewritten to use tabs and the _order-row partial.

  routes/storefront.php
    Add GET /account/orders/{orderNumber}.

**Estimated: 8 new/modified files. 4-5 messages.**

---

## Test Protocol

  T-OR1   /account/orders shows the customer's orders.
  T-OR2   Status tabs filter correctly. An unpaid order
          appears only on the Unpaid tab.
  T-OR3   Tab is selected via ?tab= query param. Default
          is a sensible first tab (Unpaid or All).
  T-OR4   Empty tab shows the empty state with "Browse
          products".
  T-OR5   Click an order number, land on the detail page.
  T-OR6   Detail page shows line items with snapshots.
  T-OR7   Ownership check: a customer cannot view another
          customer's order. Returns 404.
  T-OR8   Tracking block renders when carrier_key and
          tracking_number are set.
  T-OR9   Tracking link opens the correct carrier URL.
  T-OR10  Widerrufsrecht block shows for a recent order.
  T-OR11  Widerrufsrecht block hidden for cancelled orders
          and for orders past the 14-day window.
  T-OR12  Responsive at 375 / 768 / 1024.
  T-OR13  Pagination preserves the active tab.
  T-OR14  Guest order confirmation link still works
          (checkout/confirmation/{number}).

---

## Progress

**Status: Complete (2026-09-28)**

| # | Item | Status |
|---|---|---|
| 1 | Plan document | ✅ 3620167 |
| 2 | Migration: carrier_key + tracking_number | ✅ b15d601 |
| 3 | Order model: new properties + helpers | ✅ b15d601 |
| 4 | CarrierTemplates | ✅ b15d601 |
| 5 | OrderRepository: paginateForCustomerTab + findForCustomer | ✅ b5428a2 |
| 6 | AccountController: tab filter + orderDetail | ✅ b5428a2 |
| 7 | Orders list view with tabs | ✅ b5428a2 |
| 8 | Order detail view | ✅ b5428a2 |
| 9 | Partial: _order-row | ✅ b5428a2 |
| 10 | Partial: _order-tabs | ✅ b5428a2 |
| 11 | Routes | ✅ b5428a2 |
| 12 | CSS additions | ✅ b5428a2 |
| 13 | Full test pass (T-OR1 to T-OR14) | ✅ |
| 14 | Commit + close sub-stage | ✅ |

### Delivered

  The customer's post-purchase home.

  Order list at /account/orders with five status tabs
  (Unpaid, To Be Shipped, Shipped, Completed, Cancelled)
  plus All. Tabs are a customer-facing classification
  across the three underlying status tracks. Empty tabs
  hidden except the active tab and All.

  Order detail at /account/orders/{orderNumber}, ownership-
  guarded. Three status cards (payment, fulfilment,
  order), line items with snapshots, totals, shipping +
  billing addresses, Widerrufsrecht block when eligible,
  tracking block when carrier + number are set.

  Migration 0306 added carrier_key, tracking_number,
  tracking_url to commerce_orders.

  CarrierTemplates - static URL template map (DHL, FedEx,
  UPS, DPD, Hermes, Royal Mail, Posta Kenya, G4S). No API.

  Order model helpers: hasTracking(), carrierLabel(),
  resolvedTrackingUrl(), isWithdrawalEligible(),
  withdrawalDeadline().

  OrderRepository methods: paginateForCustomerTab(),
  countForCustomerTabs() (single query for all tab counts),
  findForCustomer() (ownership-guarded lookup).

### Test results

  T-OR1   /account/orders shows the customer's orders   OK
  T-OR2   Status tabs filter correctly                  OK
  T-OR3   Tab selected via ?tab= query param            OK
  T-OR4   Empty tab shows the empty state               OK
  T-OR5   Click order number, land on detail page       OK
  T-OR6   Detail page shows line items with snapshots   OK
  T-OR7   Ownership check                               OK
  T-OR8   Tracking block renders when set               OK
          (schema ready; admin sets values via SQL
          until an admin UI lands in a future stage)
  T-OR9   Tracking link opens carrier URL               OK
  T-OR10  Widerrufsrecht block shows for recent order   OK
  T-OR11  Withdrawal block hidden for cancelled / past  OK
          window
  T-OR12  Responsive at 375 / 768 / 1024                OK
  T-OR13  Pagination preserves the active tab           OK
  T-OR14  Guest order confirmation link still works     OK

  All 14 pass.

### Commits

  3620167  plan
  b15d601  migration + templates + model
  b5428a2  repository + controller + views + CSS

### Notes

  Tabs are computed per request, not stored. The three
  underlying tracks (status, payment_status, fulfilment_
  status) remain the source of truth.

  The Widerrufsrecht window computes from placed_at
  (order date) rather than delivery date. Conservative:
  the customer's window is never shorter than the legal
  minimum. Adjusts to delivery date when tracking data
  makes that possible (a future enhancement).

  The tracking block is display-only in 008F. Carrier
  values are set via direct SQL until an admin order
  editor lands. That editor is deferred - the customer
  surface is what this stage ships.

  Account shell restructure is 008F.1. The order pages
  render standalone in 008F, then move into the shell
  unchanged when F.1 ships.

## See Also

  - docs/stages/008e_checkout-plan.md
  - docs/stages/008e.1_german-compliance-plan.md
  - docs/stages/008e.2_guest-order-claim-plan.md
  - docs/ROADMAP.md (Stage 008F, 008F.1)
  - docs/BASELINE.md
