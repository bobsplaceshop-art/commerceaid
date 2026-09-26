# Stage 008 - Storefront & Search

**Date opened:** 2026-09-26
**Status:** Planned - awaiting first sub-stage (008A)
**Depends on:** Stages 006 (catalog), 007 (B2B) - both complete

---

## Purpose

Build the public-facing storefront. This is the first user-facing
surface of the platform. Every prior stage was admin-only. Stage
008 changes that.

The customer browsing the storefront is the actual product. The
admin interface exists to serve them. Everything in 008 is built
around that principle: fast pages, clear navigation, mobile-first
layout, no surprises.

---

## Scope - Locked

Seven sub-stages. Each is a self-contained shippable chunk.
Order is dependency-driven - each sub-stage builds on the ones
before it.

| #    | Sub-stage                                    | Complexity | Files |
|------|----------------------------------------------|------------|-------|
| 008A | Storefront shell & public routing            | Small      | ~4    |
| 008B | Product listing, detail, category pages      | Medium     | ~8    |
| 008C | Customer registration, login, account        | Large      | ~12   |
| 008D | Cart                                         | Large      | ~10   |
| 008E | Checkout flow                                | Very large | ~12   |
| 008F | Order lifecycle (customer-facing)            | Medium     | ~4    |
| 008G | PWA + search + polish                        | Medium     | ~6    |
|      | **Total**                                    |            | **~56** |

### Deferred to a Future Stage

- **Product reviews.** Not load-bearing. Defer until the storefront
  has real traffic.
- **Wishlist.** Retention feature. Defer until core flow is proven.
- **Email verification on signup.** Welcome email can go out
  immediately; verification gates can land in a later stage.
- **Real payment integrations.** Bank Transfer, Pay on Delivery,
  and B2B Purchase Order / Net Terms are the 008 payment methods.
  Stripe, M-Pesa, PayPal, etc. land in Stage 010.

---

## Dependency Graph

    008A (shell)
      |
      +-- 008B (product browsing)
      |     |
      |     +-- 008C (auth) ------+
      |     |                     |
      |     +-- 008D (cart) ------+-- 008E (checkout)
      |                                 |
      |                                 +-- 008F (order history)
      |
      +-- 008G (PWA + polish) - depends on everything

Parallel opportunities:

- 008B and 008C can be developed in either order after 008A
- 008D can start once 008B is stable (products must be viewable
  to add them to a cart)
- 008G can start once 008F is done

Sequential when risky:

- 008E must come after both 008C and 008D
- Order creation in 008E writes to tables that 008F reads

---

## Locked Decisions

These were agreed before the plan document was written. They
shape the code structure.

### D1 - Separate customer session namespace

Two distinct session cookies:

    ca_session          - admin (existing, unchanged)
    ca_customer_session - customer (new in 008C)

The admin session and customer session do not share a cookie or
any state. A customer never accidentally sees admin state. An
admin logged in on one tab is invisible to the storefront.

The Session class already supports custom cookie names via
config. We add a second binding for the customer session in the
Commerce provider.

**Consequence:** `Storefront\AuthController` uses the customer
Session binding. `Admin\AuthController` uses the existing one.
Both are `CorporateAid\Core\Session` instances, just with
different config.

### D2 - Separate storefront CSS

The storefront gets its own design system:

    public/assets/css/app.css          - admin (existing)
    public/assets/css/setup.css        - admin (existing)
    public/assets/css/storefront.css   - public (new)

Different visual language:

- Admin: dense tables, dark chrome, tool-like, information-heavy
- Storefront: airy, product-focused, marketing-friendly,
  mobile-first

Trying to force one stylesheet to serve both would constrain
the storefront severely. Two stylesheets. Two layouts. Two
design languages for two audiences.

### D3 - Dedicated storefront routes file

    routes/commerce.php   - admin (existing, 367 lines)
    routes/storefront.php - public (new)

Loaded together by App::loadRoutes(). No App.php change needed.
The existing file is already admin-heavy and adding public routes
there would blur the boundary.

**Consequence:** Storefront routes are visually distinct. Easy to
audit. Easy to apply different middleware patterns if needed.

### D4 - Cart persistence strategy

**Guests:** cart lives in `$_SESSION['cart']`.

**Logged-in customers:** cart lives in database tables
`commerce_carts` and `commerce_cart_items`.

**On login:** if a guest cart exists in session and a customer
cart exists in DB, merge them (session takes precedence on
conflicts - the guest was presumably adding to their cart
moments before logging in).

**On logout:** the DB cart persists. Session cart is cleared.

Industry standard. Simple to reason about. Prevents the
"my cart disappeared" complaint that session-only carts cause.

### D5 - Payment methods for 008E

The checkout flow supports exactly three payment options:

1. **Pay on Delivery.** Order is created. Payment collected at
   delivery. `payment_provider_key = 'cod'`, `payment_status = 'unpaid'`.

2. **Bank Transfer.** Order is created. Customer receives bank
   details. `payment_provider_key = 'bank_transfer'`,
   `payment_status = 'unpaid'`. Admin marks paid manually when the
   transfer clears.

3. **Purchase Order (B2B only).** Available when the customer is
   approved AND in a group with `default_net_days > 0`. Customer
   enters their PO number. `payment_provider_key = 'purchase_order'`,
   `payment_status = 'unpaid'`, `net_due_at` computed from the
   group's net terms. `po_number` stored on the order.

Real integrations (Stripe, M-Pesa, PayPal, Pesapal, Flutterwave)
land in Stage 010. The checkout flow is written so that adding a
new provider is a config change, not a rewrite.

### D6 - Product reviews: deferred

Not built in Stage 008. The `commerce_product_reviews` table can
be designed later. Not on the critical path.

### D7 - Wishlist: deferred

Not built in Stage 008. Session + DB persistence patterns are
already established by the cart. Adding wishlist later is a
small piece of work that mirrors the cart implementation.

### Bonus - No Redis

The platform stays on file-based sessions and MySQL. No Redis,
no Memcached. This is a hard constraint from
`docs/SPECIFICATION.md` (shared cPanel hosting compatibility).

---

## Architectural Notes

### Storefront controller base

Admin controllers extend `AdminController` which provides
`$this->render()` injecting the admin shell.

Storefront controllers extend a new `StorefrontController` which
provides `$this->render()` injecting the storefront shell
(layouts.storefront, not layouts.app).

Same pattern, different layout.

### Storefront authentication

`Storefront\AuthController` uses a `CustomerAuth` service,
distinct from the admin `Auth` service. The two services are
parallel - both wrap a Session, both check a password hash, both
set a user in the session. They do not share state.

**Password hashing:** same Hasher class (Argon2id). Same cost
parameters. The distinction is only about which session and
which user table.

### Customer model reuse

`commerce_customers` already exists (Stage 007B). The
`Customer` model is already written. Stage 008C extends
`CustomerRepository` with authentication methods:

    findByEmail - exists
    updateLastLogin - new
    setPassword - new
    create - exists

### Approval workflow integration

Registration lands in `pending` state when the customer's chosen
group has `requires_approval = 1`. Otherwise the customer is
immediately `approved`.

The distinction:

- Guest customers cannot register into an approval-required group
  without selecting that group explicitly (or being invited).
- The default group (Retail) has `requires_approval = 0`, so
  most customers are instantly approved.
- Wholesale requires approval. Registering as Wholesale means the
  signup goes into the pending queue (visible in
  `/commerce/ca-admin/customer-approvals`).

### Order creation contract

When a customer submits the checkout form:

1. Validate the cart (not empty, all items in stock or allowed
   for backorder)
2. Validate the customer info (existing customer OR guest details)
3. Validate the shipping address (from address book or new)
4. Validate the payment method
5. **Begin transaction**
6. Create `commerce_orders` row
7. Create `commerce_order_items` rows
8. Create `commerce_order_status_history` row (`from_status = NULL,
   to_status = 'pending_payment'`)
9. Reserve inventory (via `InventoryRepository::reserve`)
10. If `payment_provider_key = 'purchase_order'`, set
    `net_due_at = NOW() + INTERVAL default_net_days DAY`
11. **Commit transaction**
12. Clear the cart
13. Redirect to confirmation page
14. Queue the order-confirmation email (async, actual send is
    Stage 010)

Every step inside the transaction. If step 9 fails (insufficient
stock), the whole thing rolls back. No half-created orders.

### Route prefix

Storefront routes use flat URLs from the ROADMAP:

    /                        home
    /products                product listing
    /products/{slug}         product detail
    /categories              category index
    /categories/{slug}       category listing
    /search                  search results
    /cart                    cart
    /cart/add                POST - add item
    /cart/update             POST - change quantity
    /cart/remove             POST - remove item
    /checkout                cart review
    /checkout/info           customer info step
    /checkout/shipping       shipping step
    /checkout/payment        payment step
    /checkout/confirm        order placed
    /account                 customer dashboard
    /account/orders          order history
    /account/orders/{id}     order detail
    /account/addresses       address book
    /account/password        change password
    /login                   customer login
    /register                customer registration
    /logout                  customer logout

The `/login` and `/register` routes at the ROOT are distinct from
the admin `/login` at the root of the admin namespace. Actually,
wait - this creates a conflict. The existing admin login is at
`/login`.

**Resolution:** The admin login stays at `/login`. The customer
login moves to `/shop/login` OR the admin login moves to
`/admin/login`.

Decision: **admin login stays at `/login`** (it's the entry point
for the primary user - the shop owner). **Customer login lives
at `/account/login`** and registration at `/account/register`.
Both are within the account namespace, which is where they
belong logically.

Updates to the route table above:

    /account/login           customer login
    /account/register        customer registration
    /account/logout          customer logout

This keeps `/login` and `/logout` for admin, and everything
customer-facing lives under `/account/*`.

### Mobile-first requirement

Every storefront page must be tested at 375px width. The
RESPONSIVENESS.md directive applies platform-wide, not just to
admin. Horizontal scroll on a mobile viewport is a bug.

---

## Test Strategy Per Sub-Stage

Each sub-stage ships with a manual test pass. No automated tests
yet (no test framework installed). The manual tests follow the
pattern established in earlier stages:

- T1: page loads
- T2: primary functionality works
- T3: edge cases handled
- T4: no console errors
- T5: responsive at 375 / 768 / 1024
- T6: unrelated pages still work

Test results are recorded in the sub-stage doc.

---

## File Layout Preview

    app/Commerce/Controllers/Storefront/
        HomeController.php
        CatalogController.php
        CategoryController.php
        ProductController.php
        CartController.php
        CheckoutController.php
        AccountController.php
        AuthController.php
        OrdersController.php
        SearchController.php

    app/Commerce/Services/
        CartService.php
        CustomerAuthService.php
        CheckoutService.php

    app/Commerce/Repositories/
        CartRepository.php
        CartItemRepository.php
        OrderRepository.php
        OrderItemRepository.php
        OrderStatusHistoryRepository.php

    app/Commerce/Support/
        StorefrontController.php  (base class)

    resources/views/storefront/
        layouts/main.php
        home.php
        catalog/index.php
        catalog/_product-card.php
        product/show.php
        category/show.php
        cart/index.php
        cart/_mini.php
        checkout/review.php
        checkout/info.php
        checkout/shipping.php
        checkout/payment.php
        checkout/confirm.php
        account/dashboard.php
        account/orders.php
        account/order-detail.php
        account/addresses.php
        account/address-form.php
        account/password.php
        auth/login.php
        auth/register.php
        auth/forgot-password.php
        errors/*
        partials/header.php
        partials/footer.php

    routes/storefront.php

    public/assets/css/storefront.css
    public/assets/js/storefront.js

    database/migrations/
        0300_carts.sql              (008D)
        0301_orders_extend.sql      (008E - if any columns needed)

---

## What This Stage Does Not Do

- **Email sending.** Order confirmation emails are queued but
  not sent. EmailService exists, but real sending is deferred.
  Every transactional email lands in Stage 010 with the
  notification system.
- **Real payment processing.** Bank Transfer and PO are stubs
  that work. Stripe/M-Pesa are Stage 010.
- **Shipping rate calculation.** Shipping method is a stub
  ("Flat Rate: $10" or similar). Real zones and rates are Stage 010.
- **Tax calculation.** Tax is $0 in Stage 008. Real tax engine is
  Stage 010.
- **Multi-currency.** The store has one currency. Multi-currency
  is a future stage.
- **Guest checkout for B2B.** B2B requires login and approval.
  Guests can only check out as retail customers.
- **Product reviews.** Deferred.
- **Wishlist.** Deferred.
- **Email verification.** Deferred.
- **Password reset emails.** The form exists, but no email is
  sent (same rule as above).

---

## Estimated Total Effort

Rough comparison against prior stages:

- Stage 006 (catalog) + Stage 007 (B2B): 2 major efforts
- Stage 008 (storefront): 1.5x that combined size

This is the largest single stage. Expect multiple sessions. Each
sub-stage is a natural checkpoint.

Progress will be tracked per sub-stage in this document via a
Progress section added as each sub-stage ships.

---

## Progress

| #    | Sub-stage                                    | Status   |
|------|----------------------------------------------|----------|
| 008A | Storefront shell & public routing            | Pending  |
| 008B | Product listing, detail, category pages      | Pending  |
| 008C | Customer registration, login, account        | Pending  |
| 008D | Cart                                         | Pending  |
| 008E | Checkout flow                                | Pending  |
| 008F | Order lifecycle (customer-facing)            | Pending  |
| 008G | PWA + search + polish                        | Pending  |

---

## See Also

- docs/ROADMAP.md - full project sequence
- docs/BASELINE.md - verified codebase conventions
- docs/REMEMBER.md - 22 bug patterns
- docs/RESPONSIVENESS.md - mobile-first directive
- docs/CATALOG-OVERVIEW.md - what catalog data is available
- docs/stages/007a_customer-groups.md - pricing and group model
- docs/stages/007b_purchase-orders-approval.md - approval
  workflow and customer model
