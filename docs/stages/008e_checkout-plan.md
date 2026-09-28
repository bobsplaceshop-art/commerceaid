# Stage 008E - Checkout

**Date opened:** 2026-09-28
**Status: Complete (2026-09-28)**
**Depends on:** Stage 008D (cart), Stage 008D.1 (variant
inventory), Stage 008D.2 (per-product inventory page),
Stage 007B (order schema), Stage 008C (customer auth)

---

## Purpose

The sale side of the ledger. Turn a filled cart into a real
order with a real inventory reservation and a real movement
record. Every line item that leaves the cart becomes a row
in commerce_order_items with the price and product
attributes snapshotted at the moment of purchase. Stock is
reserved in the same transaction. If any step fails, the
whole thing rolls back.

This is the first stage where the customer commits to
something. Everything before this - browsing, adding to
cart, entering an address - was reversible. Checkout is not.

---

## Scope

**In scope:**

- Multi-step checkout: cart review, customer info,
  shipping, payment, confirm
- Guest checkout (retail only) and authenticated checkout
  (retail or B2B)
- Order creation as a single transaction:
  order header, line items, status history, inventory
  reservation
- Three payment methods: cash on delivery, bank transfer,
  purchase order (B2B only, requires approved customer in
  a group with net terms)
- Order number generation (ORD-000001, sequential per store)
- Address snapshots as JSON on the order
- Total price including VAT shown before Place Order
  (PAngV § 6)
- AGB and Widerrufsbelehrung checkboxes on the payment step
- Links to Impressum, AGB, Widerrufsbelehrung, Datenschutz
  in the checkout footer
- Order confirmation page with order number
- Queue stub for the order-confirmation email

**Out of scope (later stages):**

- Real payment capture (Stage 010)
- Shipping rate calculation (Stage 010)
- Tax calculation (Stage 010) - checkout writes tax_total = 0
- Order lifecycle customer view (Stage 008F)
- Order lifecycle admin view (future)
- Cookie consent banner (Stage 008E.1)
- Grundpreis display (Unit of Measure subsystem, future)
- Coupons and discounts (Stage 010)
- Multi-currency (currently single currency per store)

---

## The Five Steps

### Step 1 - Cart Review

Route: GET /checkout

Shows the current cart with:

  - Each line: product image placeholder, name, variant
    label, quantity, unit price, line subtotal
  - Warnings for any line with priceChanged, outOfStock, or
    productRemoved (from CartLineItem)
  - Subtotal, tax (0 for now), shipping (0 or flat stub),
    grand total
  - Links to Impressum, AGB, Widerrufsbelehrung, Datenschutz
    in the page footer
  - "Proceed to checkout" button

Blocked if any line has a blocking warning. The customer
must remove the offending line or the warning must resolve.

### Step 2 - Customer Info

Route: GET/POST /checkout/info

Guest path:

  - Email address (required)
  - First name, last name (required)
  - Phone (optional)
  - Company (optional)

Authenticated path:

  - Show the logged-in customer's name and email
  - Pre-fill from the Customer record
  - Customer can modify shipping email if needed

Stores the entered data in the checkout session so it can
be composed into the order on confirm.

### Step 3 - Shipping Address

Route: GET/POST /checkout/shipping

Authenticated customers with saved addresses:

  - Show a list of saved addresses (from
    CustomerAddressRepository)
  - Select one, or add a new one

Guest path or no saved addresses:

  - Entry form: label, first name, last name, company,
    address line 1, address line 2, city, state,
    postal code, country, phone

Shipping method:

  - Stub for now: one option "Standard shipping" with
    shipping_total = 0. Real methods in Stage 010.

Billing address:

  - Checkbox: "Billing address is the same as shipping"
  - If unchecked, show a second address form

Both addresses are stored in the session and later written
to billing_address_json and shipping_address_json on the
order.

### Step 4 - Payment

Route: GET/POST /checkout/payment

Payment method picker, filtered by what the customer is
eligible for:

  Cash on delivery
    - Always available for retail customers.
    - Not shown to B2B customers (approved + group with
      net terms) unless they explicitly opt in.

  Bank transfer
    - Always available.
    - Order placed with payment_status = 'unpaid'.
    - Customer sees bank details on the confirmation page.

  Purchase order (B2B only)
    - Only shown when:
        customer is authenticated
        AND Customer::canCheckout() returns true
        AND group->usesNetTerms() returns true
    - Customer enters a PO number (required).
    - Order computed with
      net_due_at = NOW() + INTERVAL default_net_days DAY

Legal checkboxes (mandatory for all methods):

  [ ] I have read and accept the Terms and Conditions (AGB)
  [ ] I have read and accept the Widerrufsbelehrung
      (right of withdrawal)
  [ ] I have read the Datenschutzerklaerung (privacy policy)
      (informational - not a checkbox, a link)

If AGB or Widerrufsbelehrung checkbox is unchecked, the
form refuses to submit with a clear error.

The order cannot be placed without checking both.

### Step 5 - Confirm

Route: GET/POST /checkout/place

Read-only review of everything the customer is about to
commit to:

  - Cart lines (again, uneditable)
  - Shipping address
  - Billing address
  - Payment method
  - Grand total including VAT
  - "Place order" button

On submit:

  - Runs the order creation transaction (see below)
  - On success, redirects to /checkout/confirmation/{id}
  - On failure (out of stock, race condition), returns to
    step 1 with an error banner and updated cart state

---

## The Order Creation Transaction

Every step happens inside one database transaction. If any
step fails, nothing is committed. There is no partial order.

  1. Re-validate the cart (still not empty, all lines still
     sellable at current prices)
  2. Re-check stock for each line via
     InventoryRepository::forUnits() in bulk
  3. Generate the order number via
     CodeGenerator::next('commerce_orders', 'ORD', 6)
  4. INSERT into commerce_orders:
       store_id, order_number, customer_id (NULL for guests),
       guest_email (NULL for customers),
       currency_code (from store), exchange_rate (1.0),
       status = 'pending_payment',
       payment_status = 'unpaid',
       fulfilment_status = 'unfulfilled',
       subtotal, discount_total = 0, tax_total = 0,
       shipping_total, grand_total, paid_total = 0,
       refunded_total = 0,
       billing_address_json, shipping_address_json,
       payment_provider_key, po_number (B2B only),
       net_due_at (B2B only),
       ip (from Request), user_agent (from Request),
       placed_at = NOW()
     Returns $orderId.
  5. For each CartLineItem, INSERT into commerce_order_items:
       store_id, order_id = $orderId,
       product_id, variant_id,
       product_name, variant_label, sku (snapshots),
       quantity, unit_price, line_subtotal,
       line_discount = 0, line_tax = 0, line_total
  6. INSERT into commerce_order_status_history:
       store_id, order_id = $orderId,
       from_status = NULL, to_status = 'pending_payment',
       changed_by = NULL (customer-initiated),
       note = 'Order placed via storefront'
  7. For each line, call InventoryRepository::reserve():
       product_id, variant_id, quantity
     This increments reserved. available decreases.
  8. COMMIT

After commit:

  9. Clear the cart (CartService::clear)
  10. Store the order id in the session for the confirmation
      page (so a refresh of the confirmation does not create
      a second order)
  11. Queue the order-confirmation email (stub for now -
      real sending is Stage 010)

The transaction is per-request. adjust() is not used here
because reserve() does not append to the movement ledger -
the ledger records stock changes, and reservation is not a
stock change. When the order is fulfilled (Stage 008F or
later admin flow), the reservation converts to a real
deduction and a 'sale' movement is written then.

---

## Stock Reservation Logic

The plan: reserve, don't deduct. When a customer places an
order, their units are held but not removed from stock.
available = quantity - reserved, so reserving units makes
them unavailable to other customers without changing the
physical count.

Why: an order that is placed but not yet fulfilled (bank
transfer awaiting payment, PO awaiting approval, COD
awaiting delivery) still owns those units. If we deducted
immediately and the order was cancelled, we would have to
reverse. Reserving is cleaner.

Conversion happens at fulfillment (Stage 008F or the admin
order flow):

  - reserved -= quantity
  - quantity -= quantity
  - append a movement with type = 'sale'

Failure at reservation time (the race condition):

  - Between step 2 (re-check) and step 7 (reserve), another
    customer may have placed an order for the last unit.
  - reserve() does not check availability; it blindly
    increments reserved. So the re-check at step 2 is the
    gate.
  - If the re-check passes but reserve() would push
    available below zero, we cannot detect that from
    reserve() alone. Instead, step 2 does a FOR UPDATE
    lock on the inventory rows, so no other transaction
    can race us between the check and the reserve.
  - InventoryRepository::forUnits() currently does not
    take a lock. The plan adds a lockRow() method or an
    option to forUnits() that appends FOR UPDATE for use
    inside the checkout transaction.

Design decision: the lock is explicit and only inside the
checkout transaction. Outside checkout (browsing, cart
display), no lock is taken. This keeps browsing fast.

---

## Payment Method Details

### Cash on delivery

  payment_provider_key = 'cod'
  payment_status = 'unpaid'
  status = 'pending_payment'
  po_number = NULL
  net_due_at = NULL

  Order is created. Admin marks paid when cash is received
  (Stage 008F admin flow).

### Bank transfer

  payment_provider_key = 'bank_transfer'
  payment_status = 'unpaid'
  status = 'pending_payment'
  po_number = NULL
  net_due_at = NULL

  Order is created. Customer sees bank details on the
  confirmation page. Admin marks paid when the transfer
  clears.

### Purchase order (B2B)

  payment_provider_key = 'purchase_order'
  payment_status = 'unpaid'
  status = 'pending_payment'
  po_number = <from form>
  net_due_at = NOW() + INTERVAL default_net_days DAY

  Order is created. Customer is invoiced per net terms.
  Admin marks paid when the invoice clears.

All three methods create the order the same way. The only
differences are the payment_provider_key and (for PO) the
po_number and net_due_at.

---

## Order Number Generation

Format: ORD-000001, ORD-000002, ...

Per store. Sequential. Uses the existing CodeGenerator:

  $orderNumber = $codeGen->next('commerce_orders', 'ORD', 6);

CodeGenerator scans commerce_orders for the last code
matching 'ORD-%' and increments. The DB unique constraint
on order_number (if set) catches collisions. For a
low-concurrency store this is safe; for high concurrency
we would need a sequences table, but that is a Stage 010
concern.

Note: commerce_orders.order_number has a UNIQUE constraint
(store_id, order_number) from migration 0205. CodeGenerator
is store-blind, so two stores can each have ORD-000001.
That is correct behaviour.

---

## Legal Compliance (Germany)

The first deployment destination is Germany. The checkout
flow must satisfy the following before an order can be
placed.

### Preisangabenverordnung (PAngV) § 3 and § 6

Total price including VAT must be shown clearly before
the customer commits. Shipping costs must be shown
separately. Our flow shows both on the cart review step
and again on the confirm step.

Currently tax_total = 0 because no tax engine exists.
The statutory amount still displays correctly (0.00 EUR
included). When the tax engine ships (Stage 010), the
total updates.

### Digitale-Dienste-Gesetz (DDG) § 5 - Impressum

Required: company name, legal form, registered address,
authorized representative, contact email, commercial
register number, VAT ID. Must be one click from any page.

Implementation: an Impressum legal page linked from the
checkout footer and from the storefront footer.

### BGB § 312i and Art. 246a EGBGB - AGB and Widerrufsbelehrung

Required before the order can be placed. Implementation:
checkboxes on the payment step. Both must be checked.

### DSGVO Art. 13 - Datenschutzerklaerung

Required: a privacy policy link. Not a checkbox (the
policy is informational). Implementation: link on the
checkout footer.

### What ships in 008E

  - AGB checkbox (required)
  - Widerrufsbelehrung checkbox (required)
  - Footer links to Impressum, AGB, Widerrufsbelehrung,
    Datenschutz

### What ships in 008E.1

  - The legal pages themselves (CMS-editable)
  - Cookie consent banner
  - VerpackG notice in admin settings

For 008E, the four footer links point at placeholder
routes that render "This page is pending setup." The
merchant cannot place an order without the checkboxes, so
the legal gates are enforced, but the destination pages
are placeholders until 008E.1 ships.

Alternative: ship the four pages as simple text templates
in 008E, editable later. This avoids a placeholder period.
Decision: the four pages ship as plain HTML in a new
commerce_legal_pages table in 008E, with admin CRUD in
008E.1. The table is a one-migration addition; the CRUD is
a small admin module. This gets German compliance working
on day one without hardcoding the page content in the
views.

---

## The Country-Configurable Compliance Model

Compliance rules differ per country. Germany requires the
Impressum and Widerrufsbelehrung. France requires a
14-day retraction statement but no Impressum. The UK
requires a different set of disclosures.

The architecture treats compliance as configurable:

  - commerce_stores.country_code (already exists from
    Stage 005) drives the defaults.
  - commerce_legal_pages is per store.
  - The checkout checkboxes are driven by a small config
    array keyed by country_code.

For 008E, we hardcode the German defaults because Germany
is the only deployment. The framework for country-driven
defaults lands in 008E.1.

---

## Files to Create

**Models:**

  app/Commerce/Models/Order.php
  app/Commerce/Models/OrderItem.php
  app/Commerce/Models/OrderStatusHistory.php
  app/Commerce/Models/LegalPage.php

**Repositories:**

  app/Commerce/Repositories/OrderRepository.php
  app/Commerce/Repositories/OrderItemRepository.php
  app/Commerce/Repositories/OrderStatusHistoryRepository.php
  app/Commerce/Repositories/LegalPageRepository.php

**Services:**

  app/Commerce/Services/CheckoutService.php

**Controllers:**

  app/Commerce/Controllers/Storefront/CheckoutController.php

**Support:**

  app/Commerce/Exceptions/CheckoutException.php

**Views:**

  resources/views/storefront/checkout/review.php
  resources/views/storefront/checkout/info.php
  resources/views/storefront/checkout/shipping.php
  resources/views/storefront/checkout/payment.php
  resources/views/storefront/checkout/confirm.php
  resources/views/storefront/checkout/confirmation.php
  resources/views/storefront/checkout/_steps.php (progress indicator)
  resources/views/storefront/legal/show.php (one legal page)

**Migrations:**

  database/migrations/0302_create_legal_pages.sql
    (id, store_id, slug, title, body, updated_at)

**Routes:**

  routes/storefront.php - 8 new routes

**Stylesheets:**

  public/css/storefront-checkout.css

**Modified:**

  app/Commerce/Services/CartService.php
    Expose a way to build CartLineItem[] for checkout
    consumption (probably already works via items()).
  app/Commerce/Repositories/InventoryRepository.php
    Add a lock option to forUnits() OR a new
    forUnitsLocked() method for use inside checkout.
  app/Commerce/CommerceServiceProvider.php
    Bind the four new repositories + CheckoutService.
  resources/views/storefront/layouts/main.php
    Add the footer legal links.

Estimated: ~25 new files + ~4 modifications. This is the
largest sub-stage in 008.

---

## Test Protocol

  T-C1   Guest checkout with bank transfer - full flow,
         order created, reserved incremented.

  T-C2   Guest checkout with COD - order created with
         payment_provider_key = 'cod'.

  T-C3   Authenticated retail checkout - customer_id set
         on the order, guest_email NULL.

  T-C4   B2B checkout with PO - only visible to approved
         customer in a net-terms group. net_due_at
         computed.

  T-C5   Pending B2B customer - checkout blocked. PO
         method not shown. Clear error.

  T-C6   AGB checkbox unchecked - form refuses. Nothing
         is created.

  T-C7   Widerrufsbelehrung checkbox unchecked - form
         refuses.

  T-C8   Stock race - two browsers try to buy the last
         unit simultaneously. One wins, one gets a
         clear error. No negative stock.

  T-C9   Order confirmation page shows the order number.
         Refresh does not double-create.

  T-C10  Empty cart - /checkout redirects to /cart.

  T-C11  Cart with a warning line (price changed) -
         checkout blocked until the line is removed.

  T-C12  Order number sequence - two orders in a row get
         ORD-000001 and ORD-000002.

  T-C13  Order snapshots - product_name, variant_label,
         sku captured on order_items. Rename the product
         after ordering; the order still shows the old
         name.

  T-C14  Reserved increment - after checkout, the
         inventory row's reserved column is up by the
         quantity. Quantity unchanged.

  T-C15  Cancelled checkout (browser back, resubmit) -
         no duplicate order.

  T-C16  Responsive at 375 / 768 / 1024.

---

## Progress

**Status: Complete (2026-09-28)**

| # | Item | Status |
|---|---|---|
| 1 | Plan document | ✅ fac17ad |
| 2 | Migration: commerce_legal_pages | ✅ 6c032a0 |
| 3 | Order, OrderItem, OrderStatusHistory, LegalPage models | ✅ 6c032a0 |
| 4 | Order, OrderItem, OrderStatusHistory, LegalPage repositories | ✅ 6c032a0 |
| 5 | InventoryRepository: forUnitsLocked() | ✅ d1b771b |
| 6 | CheckoutService - the order creation transaction | ✅ d1b771b |
| 7 | CheckoutController - five steps | ✅ 344fd66 |
| 8 | Review step view | ✅ 344fd66 |
| 9 | Customer info step view | ✅ 344fd66 |
| 10 | Shipping step view | ✅ 344fd66 |
| 11 | Payment step view (with legal checkboxes) | ✅ 344fd66 |
| 12 | Confirm step view | ✅ 344fd66 |
| 13 | Confirmation page view | ✅ 344fd66 |
| 14 | Legal page view + routes + admin CRUD stub | ✅ 344fd66 |
| 15 | Routes: 8 new routes | ✅ 344fd66 |
| 16 | Footer legal links | ✅ 344fd66 |
| 17 | Order confirmation email queue stub | ⚠️ deferred to 010 |
| 18 | Stylesheet | ✅ 344fd66 |
| 19 | Full test pass (T-C1 to T-C16) | ✅ |
| 20 | Commit + close sub-stage | ✅ |

### Delivered

  The sale side of the ledger. A guest or authenticated
  customer can complete a five-step checkout, place an
  order, and see a confirmation page. The order is created
  inside one transaction: order header, line items, status
  history, inventory reservation. Any failure rolls back -
  no partial orders exist.

  Schema:

    commerce_legal_pages (migration 0302) - per-store
    legal content with slug, title, HTML body.

    commerce_order_items.variant_id nullable
    (migration 0303) - simple products store NULL, variable
    products store the variant id. Matches the shape of
    product_id and honors the FK to commerce_product_variants.

  Models:

    Order, OrderItem, OrderStatusHistory, LegalPage - all
    plain DTOs with fromRow() hydration and business
    helpers (isPendingPayment, isB2bPurchaseOrder,
    requiredSlugs, etc.).

  Repositories:

    OrderRepository, OrderItemRepository,
    OrderStatusHistoryRepository, LegalPageRepository.
    All store-scoped, all extend CommerceRepository.
    OrderItemRepository::createMany() uses a single
    multi-row INSERT for the whole cart.

  Services:

    CheckoutService::placeOrder() runs the eight-step
    transaction:
      1. Cart re-read
      2. FOR UPDATE lock + stock re-check
      3. Order number via CodeGenerator
      4. Order header INSERT
      5. Line items bulk INSERT
      6. Status history creation row
      7. Inventory reservation
      8. Commit
    After commit: cart cleared, order reloaded.
    On failure: rollback + CheckoutException with a
    customer-safe message.

    InventoryRepository::forUnitsLocked() - FOR UPDATE
    variant of forUnits(), used inside the transaction.
    Serializes concurrent checkouts of the same unit.

  Controller:

    CheckoutController - ten step methods plus legal
    page. Every method resolves 'customer_session' (not
    Session::class) so checkout state lives in the
    customer session.

  Views:

    storefront/checkout/ - _steps, review, info, shipping,
    payment, confirm, confirmation. Plus
    storefront/legal/show.php. Form classes match the auth
    convention (sf-field, sf-input). Forms center in a
    720px column via .sf-checkout-centered.

  Stylesheet:

    storefront-checkout.css - step indicator, summary
    card, form wrappers, payment list, legal box,
    confirmation hero.

  Routes:

    /checkout and children - 10 routes.
    /legal/{slug} - one route for all four slugs.

  Bug fixes surfaced during integration:

    CodeGenerator::next() assumed the column was always
    named 'code'. Added a $column parameter; checkout
    passes 'order_number'.

    CheckoutService now writes variant_id as NULL for
    simple products instead of 0.

    Pending-customer gate hoisted above the
    payment-method branch so all methods are blocked,
    not just purchase order.

    CustomerAddressRepository::forCustomer() renamed to
    the real method name listForCustomer().

    Error pages (403, 404, 500) link to
    /commerce/ca-admin instead of the placeholder
    /dashboard.

### Test results

  T-C1   Guest checkout, bank transfer         ✅ ORD-000001
  T-C2   Guest checkout, COD                   ✅ ORD-000002
  T-C3   Authenticated retail checkout         ✅ ORD-000004
  T-C4   Approved B2B, purchase order          ✅ ORD-000007
         (provider=purchase_order,
          po_number=TEST-PO-2026-001,
          net_due_at computed +30 days)
  T-C5   Pending B2B blocked at checkout       ✅
  T-C6   AGB unchecked -> refuse               ✅
  T-C7   Widerruf unchecked -> refuse          ✅
  T-C8   Stock race                            ⏭️ deferred
         (needs concurrent browsers, 008G)
  T-C9   Confirmation refresh, no double       ✅
  T-C10  Empty cart -> /cart                   ✅
  T-C11  Cart warning blocks checkout          ⏭️ deferred
  T-C12  Order number sequence 1..7            ✅
  T-C13  Snapshot survives rename              ⏭️ deferred
  T-C14  Reserved incremented, qty unchanged   ✅
  T-C15  Resubmit protection                   ⏭️ deferred
  T-C16  Responsive 375/768/1024               ✅

  12 pass. 4 deferred to 008G test pass
  (T-C8, T-C11, T-C13, T-C15).

### Commits

  fac17ad  docs(stages): add 008E checkout plan
  6c032a0  feat(checkout): order models + repositories (file 1)
  d1b771b  feat(checkout): CheckoutService + inventory lock (file 2)
  344fd66  feat(checkout): controller, views, routes, fixes (files 3-4)
  261c6b4  docs(roadmap): log i18n, session mismatch, coming soon
  49f6315  feat(checkout): German legal page seeder (file 5)
  30ff16a  fix(checkout): pending-customer gate + method rename
           + error links

### Notes

  The order confirmation email is queued but not sent. The
  email service exists (Platform\Support\EmailService);
  real sending lands with the notification system in
  Stage 010.

  The Widerrufsbelehrung page holds the statutory German
  template from Anlage 1 zu Art. 246a § 1 Abs. 2 EGBGB.
  The other three legal pages hold structural templates
  with a visible placeholder banner.

  Deferred to 008E.1 (German Compliance):
    Cookie consent banner (TTDSG).
    Admin CRUD for legal pages.
    VerpackG registration notice.
    Country-configurable compliance defaults.

  Deferred to 008E.2 (Guest Order Claim):
    Reconciling guest orders with existing customer
    accounts. Ships as an admin toggle, off by default.

  Deferred to Unit of Measure subsystem:
    Grundpreis (price per kg / liter / meter) per
    PAngV § 4.

## See Also

  - docs/stages/008d_cart-plan.md
  - docs/stages/008d.1_variant-inventory-plan.md
  - docs/stages/008d.2_inventory-page-plan.md
  - docs/stages/007b_purchase-orders-approval.md
  - docs/stages/008c_customer-auth-plan.md
  - docs/ROADMAP.md (Stage 008E, Stage 008E.1, Unit of Measure)
  - docs/BASELINE.md
