# Anaya Global Commerce Platform - Roadmap

**Status:** Locked 2026-09-26
**Supersedes:** prior roadmap, updated for B2B split

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

---

## Before the Next Stage

The project is being placed under git. Every prior stage was
developed in place with no version control history. Starting with
Stage 008, every change is committed, tagged, and diffable.

This is a hard prerequisite. Stage 008 introduces the public
storefront, cart, checkout, and customer account lifecycle. Those
surfaces are user-facing and cannot be developed without rollback
capability.

Git setup is a separate work item. See
`docs/git-setup.md` (to be written) for details.

---

## In Progress

None. Stage 007B closed 2026-09-26.

---

## Planned Stages

### Stage 008 - Storefront & Search

**Goal:** Public-facing storefront. Product pages, category
browsing, search, filters, cart, checkout, customer account.

**Deliverables:**

- Storefront routing (flat URLs: /products, /categories, /cart,
  /checkout, /account)
- Mobile-first responsive design per docs/RESPONSIVENESS.md
- Product grid, product detail, category listing
- Faceted search: category, brand, price, availability, attributes
- Cart (guest + customer, merged on login)
- Checkout flow: cart review -> customer info -> shipping ->
  payment -> confirmation
- Customer account: dashboard, orders, addresses, wishlist,
  password change
- Product reviews with moderation
- Wishlist (guest + customer)
- Order models fully wired (Customer, Order, OrderItem,
  OrderStatusHistory repositories and services)
- B2B checkout with purchase order / net terms
- Customer registration + login (customer-side, distinct from
  admin login)
- Approval workflow integrated into signup (pending -> approved
  gates checkout)
- Storefront PWA: manifest, service worker, offline shell
- Dashboard storefront links

**Depends on:** Stages 006 (catalog), 007 (B2B)

### Stage 009 - Forms & Submissions

**Goal:** Native form builder as a differentiator. Customer
inquiries, wholesale applications, custom order requests.

**Deliverables:**

- commerce_forms, commerce_form_fields, commerce_form_submissions
- Field type registry (text, email, phone, number, textarea,
  select, radio, checkbox, file, date, address, product_selector)
- Conditional logic engine (show/hide fields based on other values)
- Form builder admin: create, drag-drop ordering, live preview
- Submission management: list, filter, mark read/replied, CSV
- Form rendering in themes
- Product inquiry form attachment
- Theme builder integration (form section type)
- Webhook on submission
- Notification rules on submission
- Spam defense (honeypot, rate limit)

**Depends on:** Stage 008 (storefront), 007 (customers)

### Stage 010 - Payments & Shipping (Deep)

**Goal:** Full payment provider integrations and shipping engine.

**Deliverables:**

- Payment provider registry (see Stage 012 for admin UI)
- Reference implementations: M-Pesa, Stripe, PayPal, Pesapal,
  Flutterwave, Manual, COD, Bank Transfer
- Webhook handling for each provider
- Refund processing
- Shipping zones, methods, rates (flat, free-over-threshold,
  weight-based, price-based, local pickup)
- Shipping carrier integrations (DHL, FedEx as optional)
- Tax engine: tax classes, rates per region, inclusive/exclusive

**Depends on:** Stage 008 (checkout)

### Stage 011 - Advanced Commerce

**Goal:** Subscriptions, gift cards, multi-location inventory,
BOPIS, store credit.

**Deliverables:**

- Subscriptions: recurring products, billing cycles
- Gift cards: purchase, redeem, balance tracking
- Multi-location inventory: warehouses, transfers
- BOPIS: buy online pick up in store
- Store credit: refunds to credit
- Loyalty points

**Depends on:** Stages 006-010

### Stage 012 - Theme Engine & Page Builder

**Goal:** Full storefront theming system with 20+
industry-specific themes.

**Deliverables:**

- Theme contract (directory structure, theme.json manifest)
- Theme registry: install, activate, list, preview
- Theme customizer: colours, fonts, layout, live preview
- Page builder: section registry, drag-drop ordering
- Section types: hero, product grid, category grid, testimonials,
  rich text, video, banner, newsletter, FAQ, form, spacer
- Navigation management
- At least 20 themes shipped: Modern, Minimal, Fashion, Luxury,
  Electronics, Furniture, Grocery, Restaurant, Services, Beauty,
  Home & Living, Gifts, Pharmacy, Wholesale/B2B, Kids, Sports,
  Auto Parts, Real Estate, Digital Products, Marketplace

**Depends on:** All prior stages

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
