# Stage 008C - Customer Registration, Login, Account

**Date opened:** 2026-09-27
**Status:** Planned
**Depends on:** Stage 008B (storefront shell + Store model)

---

## Purpose

Customer-side authentication and account management. Distinct
from admin authentication - different user table, different
session cookie, different login endpoint, different password
reset flow.

Also integrates the approval workflow from Stage 007B: customers
registering into a group with `requires_approval = 1` land in a
`pending` state. They can log in and browse but cannot check
out until approved by an admin in the customer approvals queue.

---

## Scope

**In scope:**

- Registration (email + password + optional profile fields)
- Login / logout
- Forgot password + reset flow
- Account dashboard
- Address book (create, edit, delete, set default)
- Password change
- Order history view (stub - orders don't exist until 008E)
- Separate customer session cookie (`ca_customer_session`)
- Approval workflow integration (pending users can log in but
  cannot check out)

**Out of scope (later stages):**

- Email verification on signup (deferred)
- Social login (never)
- Two-factor authentication (never for customers)
- Wishlist (a later sub-stage)
- Reviews (a later sub-stage)
- Order detail view (008F)

---

## The Session Strategy

Two independent session cookies:

  ca_session              admin (existing, unchanged)
  ca_customer_session     customer (new)

Both are `CorporateAid\Core\Session` instances, just with
different config. A single user could be logged in as admin in
one tab and as customer in another without interference.

The `CommerceServiceProvider` binds a second Session for the
customer cookie via a named binding. Customer auth code resolves
that binding explicitly. Admin code resolves the standard
`Session::class` binding.

Why not share one cookie with prefixed keys: cross-contamination
risk. A bug in one auth path could expose the other's user. Two
cookies is a hard boundary.

---

## The CustomerAuthService

Parallel to `CorporateAid\Core\Auth`. Same shape, different
subject.

    __construct(CustomerRepository $customers, Session $session)
    attempt(string $email, string $password): ?Customer
    login(Customer $customer): void
    logout(): void
    check(): bool
    id(): ?int
    user(): ?Customer

Where it differs from admin Auth:

- Uses the customer session, not the admin session
- Tracks `last_login_at` on the customer row
- Records customer sessions in `commerce_customer_sessions`
- No permission checks (customers have no RBAC)

---

## Registration Flow

Form fields:

  email           required, valid email, unique per store
  password        required, min 8 chars
  password_confirmation   required, must match
  first_name      required
  last_name       required
  phone           optional
  company         optional
  accepts_marketing  checkbox, optional

Group assignment:

  If the customer selects a group during signup, that group is
  applied. Otherwise the store's default group is used.

Approval:

  If the assigned group has `requires_approval = 1`, the customer
  is created with `approval_status = 'pending'`. Otherwise
  `approval_status = 'approved'`.

  Pending customers can log in and browse. Checkout is gated by
  `Customer::canCheckout()` which returns false for pending.

After registration:

  - Customer is automatically logged in (even if pending)
  - Redirect to account dashboard
  - Dashboard shows approval status if pending

---

## Login Flow

Form fields:

  email
  password

Behavior:

  - Validates credentials against the customer table
  - On success, writes the customer to the customer session
  - Records `last_login_at` on the customer row
  - Inserts a row in `commerce_customer_sessions`
  - Redirects to the account dashboard OR the intended page

Rate limiting:

  Uses the existing RateLimiter from Platform. Same protection
  pattern as admin login.

Error messaging:

  Generic "Invalid email or password" to avoid user enumeration.

---

## Forgot Password Flow

Step 1: user submits email.
Step 2: platform generates a token, stores its SHA-256 hash in
        `commerce_customer_password_resets`, sends an email with
        the token in a link.
Step 3: user clicks the link, submits new password.
Step 4: platform verifies the token hash, updates the customer's
        password, marks the reset row `used_at = NOW()`.

Token expiry: 60 minutes.

Single-use: `used_at` marks consumed tokens. A token cannot be
used twice.

No email sending yet — the email service exists but real sending
is deferred. For dev, the reset link is logged to the app log
so the flow can be tested end-to-end.

---

## Account Dashboard

Route: GET /account

Shows:

  - Welcome message with the customer's name
  - Approval status banner if pending or rejected
  - Quick links to orders, addresses, password change
  - Recent orders (empty until 008E)

---

## Address Book

Routes:

  GET  /account/addresses              list
  GET  /account/addresses/create       create form
  POST /account/addresses              store
  GET  /account/addresses/{id}/edit    edit form
  POST /account/addresses/{id}         update
  POST /account/addresses/{id}/delete  delete
  POST /account/addresses/{id}/default set as default

Address fields:

  label, type (billing/shipping/both),
  first_name, last_name, company,
  address_line_1, address_line_2,
  city, state, postal_code, country_code,
  phone, email, is_default

Default semantics:

  A customer can have at most one default billing address and
  one default shipping address. Setting a new default clears the
  old one. Setting `is_default = 1` on a `type = 'both'` address
  sets both defaults.

---

## Password Change

Route: GET/POST /account/password

Form fields:

  current_password
  new_password
  new_password_confirmation

Behavior:

  - Verify current password
  - Validate new password rules
  - Update `password_hash`
  - Rotate customer session

---

## Order History (stub)

Route: GET /account/orders

Until checkout ships in 008E, this page shows an empty state:
"You have no orders yet. When you place an order, it will
appear here."

No order controller needed yet.

---

## Approval Workflow Integration

The customer can log in and browse regardless of approval
status. The gate is at checkout, not at login.

Customer::canCheckout() returns true only when:
  - status = 'active' AND
  - approval_status = 'approved'

For pending customers, the account dashboard shows a banner:
"Your account is pending review. You can browse and add items
to your cart, but you cannot place orders until approved."

For rejected customers, the banner says:
"Your account is not approved. Contact support for details."

The rejection reason is never shown to the customer.

---

## Routes to Add

    GET  /account/register          registration form
    POST /account/register          register action
    GET  /account/login             login form
    POST /account/login             login action
    POST /account/logout            logout action
    GET  /account/forgot-password   forgot form
    POST /account/forgot-password   send reset link
    GET  /account/reset-password    reset form (with ?token=...)
    POST /account/reset-password    reset action
    GET  /account                   dashboard
    GET  /account/orders            order history (stub)
    GET  /account/addresses         address list
    GET  /account/addresses/create  new address form
    POST /account/addresses         store address
    GET  /account/addresses/{id}/edit  edit form
    POST /account/addresses/{id}    update address
    POST /account/addresses/{id}/delete  delete
    POST /account/addresses/{id}/default  set default
    GET  /account/password          change password form
    POST /account/password          change password action

Admin login stays at /login (existing). Customer login lives
at /account/login to avoid the collision.

---

## Files to Create

**Services & support:**

    app/Commerce/Services/CustomerAuthService.php
    app/Commerce/Support/CustomerController.php (base class)
    app/Commerce/Support/CustomerPasswordReset.php

**Repository extension:**

    app/Commerce/Repositories/CustomerRepository.php
      add setPassword, updateLastLogin,
      createPasswordReset, findByResetToken, consumePasswordReset
    app/Commerce/Repositories/CustomerSessionRepository.php (new)

**Controllers:**

    app/Commerce/Controllers/Storefront/AuthController.php
    app/Commerce/Controllers/Storefront/AccountController.php

**Views:**

    resources/views/storefront/auth/register.php
    resources/views/storefront/auth/login.php
    resources/views/storefront/auth/forgot-password.php
    resources/views/storefront/auth/reset-password.php
    resources/views/storefront/account/dashboard.php
    resources/views/storefront/account/orders.php
    resources/views/storefront/account/addresses.php
    resources/views/storefront/account/address-form.php
    resources/views/storefront/account/password.php

**Routes:**

    routes/storefront.php (add ~20 routes)

**Provider:**

    app/Commerce/CommerceServiceProvider.php (bind CustomerAuthService + customer Session + CustomerSessionRepository)

**Plan doc:**

    docs/stages/008c_customer-auth-plan.md (this file)

**Estimated: ~15 new files.**

---

## The StorefrontController Base — Extended

Existing `StorefrontController` renders via the storefront
layout. For 008C we extend it with customer context:

    $this->render('...', [...])               // unchanged
    $this->customer()                         // returns ?Customer

The `customer()` helper resolves the customer from the customer
session via CustomerAuthService.

A new `CustomerController` extends `StorefrontController` and
adds guards:

    $this->requireCustomer()     // redirects to /account/login if not logged in
    $this->renderAccount(...)    // renders inside the account shell

Actually — for consistency with the admin pattern, we might not
need a separate base class. The `StorefrontController` can provide
`customer()` and controllers can call it as needed. The "require
customer" guard goes in a middleware alias.

**Middleware:** add `customer.auth` alias mapped to
`CustomerAuthMiddleware`. Used on account routes.

---

## Middleware

New middleware: `CustomerAuthMiddleware`

    Location:  app/Commerce/Middleware/CustomerAuthMiddleware.php
    Alias:     customer.auth
    Registered: app/Core/Http/Kernel.php $map

Behavior:

  - Reads the customer session
  - If a valid customer is present, allow the request
  - Otherwise, redirect to /account/login?intended=...

Open redirect defense: the `intended` parameter is validated
(same rule as admin AuthController).

---

## Design Decisions

**1. Distinct cookie.** Session isolation between admin and
customer audiences. One user can be logged in as both
simultaneously without state collision.

**2. Separate base class NOT needed.** StorefrontController
already injects the container. Add a `customer()` helper. Any
controller that needs a customer calls it. Middleware enforces
auth on protected routes.

**3. No email verification.** Deferred. Storefront registration
without email verification is acceptable for a B2B/B2C hybrid
that will add verification in a later stage.

**4. Password reset uses SHA-256 hash of the token.** Token is
generated with random_bytes(32), hex-encoded to 64 chars,
hashed with SHA-256 for storage. The hash is what's stored;
the raw token goes only in the email link. This prevents token
theft from a DB dump.

**5. Approval gates checkout, not login.** Customers can
register, log in, and browse even if pending. Only checkout is
blocked. Matches Shopify B2B.

**6. Intended redirect preserved.** After login, the customer
returns to the page they were trying to reach. Same pattern as
admin login.

---

## Deferred

  - Email verification on signup
  - Real email sending (reset link logged to disk for dev)
  - Per-customer net terms (group-level only)
  - Wishlist, reviews
  - Order history (empty until 008E)
  - Account deletion (GDPR data request flow is Stage 011+)

---

## Test Protocol

After each file lands:

    php bin/verify-file.php <path> [--view]
    php bin/lint.php

After the full sub-stage:

  T1  Register a new customer, verify DB row created
  T2  Login with the new customer
  T3  Forgot password generates a reset token in the log
  T4  Reset password flow completes, old password rejected
  T5  Logout clears the customer session
  T6  Access /account without login redirects to /account/login
  T7  Access /account with login shows dashboard
  T8  Add an address, set as default, edit, delete
  T9  Change password, old password rejected after
  T10 Pending approval: register into Wholesale group,
      verify pending status, verify checkout is blocked
      (via Customer::canCheckout())
  T11 Admin session and customer session coexist (log in as
      both, navigate between tabs, no state collision)
  T12 Responsive at 375/768/1024

---

## Progress

**Status: Complete (2026-09-27)**

| # | Item | Status |
|---|---|---|
| 1 | Plan document | ✅ |
| 2 | CustomerSession config + binding | ✅ |
| 3 | CustomerAuthService | ✅ |
| 4 | CustomerRepository extensions | ✅ |
| 5 | CustomerSessionRepository | N/A - deferred (session tracking added later if needed) |
| 6 | CustomerAuthMiddleware | ✅ |
| 7 | AuthController + 4 views | ✅ |
| 8 | AccountController + 5 views + CustomerAddress model/repo | ✅ |
| 9 | Routes (9 auth + 11 account) | ✅ |
| 10 | Full test pass + 6 commits | ✅ |

### Delivered beyond the plan

  - **RedirectGuard** extracted to Platform\Support.
    Shared open-redirect check used by both admin and
    customer auth controllers. Preempts the licensing
    flows which will have multiple redirect-after-action
    paths.

  - **Time Discipline** documented in docs/baseline.
    MySQL owns timestamp writes and comparisons. Fixes a
    real bug where PHP/MySQL timezone drift invalidated
    every password reset token on creation.

  - **Plaintext reset log line** (RESET-LINK-FOR-COPY).
    Makes manual dev testing practical without JSON
    parsing gymnastics.

  - **Footer CSS consolidation** (a storefront fix found
    during testing, landed in the same stage because the
    account views surfaced the issue).

### Bugs found during test pass

  1. **CustomerAuthMiddleware not bound in container.**
     Kernel alias existed, container binding did not.
     `/account` and other guarded routes threw 500 at
     dispatch time. Fixed in c97f124.

  2. **Reset token expiry computed in PHP.**
     `date('Y-m-d H:i:s', time() + 3600)` compared against
     MySQL's `NOW()`. With PHP on UTC and MySQL on
     Africa/Nairobi, every token appeared expired at
     creation. Fixed in 2d52d47 - MySQL now computes
     `DATE_ADD(NOW(), INTERVAL 60 MINUTE)`.

  3. **Footer CSS cascade unknowable.**
     Five competing definitions of `.sf-footer-columns-inner`
     from the 008B.2 addendum. Mobile layout was
     left-aligned with empty right gutter. Consolidated
     into one authoritative block. Fixed in 53db57e.

### What's deferred

  - Email sending (reset link goes to log; real email
    lands with Stage 010)
  - Email verification on signup
  - Wishlist, reviews (later stages)
  - Customer session tracking table (commerce_customer_sessions
    exists, but the tracking write is not yet wired)

---

## See Also

  - docs/stages/008_storefront-plan.md
  - docs/stages/008b_storefront-visual-identity.md
  - docs/stages/007b_purchase-orders-approval.md
  - docs/BASELINE.md
  - docs/git-workflow.md
