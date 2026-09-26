# Project Status

**As of:** 2026-09-26
**Version:** 0.2.0-alpha
**Stage:** 007B complete. Next: git setup, then Stage 008.

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
- Setup wizard with 8 tabs (business, currency, branding,
  checkout, payments, shipping, tax, advanced)
- Grouped collapsible sidebar with localStorage persistence
- Sidebar active item scrolls into view on load
- Dark / light / system theme with per-user preference
- Dashboard with inventory health card
- Responsive across all viewports down to 375px
- Sticky action bars on all edit pages

### Catalog

- Categories: tree with attribute mapping
- Brands: flat list
- Attributes: full CRUD + 12 input types
- Products: 5-tab editor (basic, pricing, inventory, shipping, SEO)
- Product variations: auto-generate from category attributes
- Variation manager: search, filters, bulk actions, pagination
- Inventory: list, filters, adjust modal, movement ledger
- CSV exports (inventory + movements)

### B2B (007A + 007B complete)

- Customer groups with default / B2B / approval flags
- Price lists with tier pricing per group
- Product-level minimum order quantity
- **Net terms field on groups** (0 = prepay, 30 = net-30, etc.)
- Customer model + repository with approval workflow
- **Customer approval queue** at /commerce/ca-admin/customer-approvals
- **Per-customer approval history timeline** with reasons and actor
- **Reset reason** (optional), **reject reason** (required)
- Reject modal, reset modal, history modal (all AJAX-aware)
- Filter tabs with counts on every tab
- Approval history table (append-only, transactional writes)
- Customer + order schema (10 tables ready for storefront)
- Purchase order fields on orders (po_number, net_due_at)

---

## What Doesn't Work Yet

- Public storefront (no customer-facing pages)
- Customer registration / login (only admins can log in)
- Shopping cart
- Checkout flow
- Payment capture
- Shipping rate calculation
- Tax calculation at checkout
- Product reviews
- Wishlist
- Forms builder
- Theme customizer
- Additional themes

---

## Current Focus — Before Stage 008

**Git setup.** Every stage so far was developed in place with no
version control. Stage 008 introduces user-facing surfaces
(storefront, cart, checkout, customer account). Those cannot be
built without rollback capability.

Work items:

1. `git init` in the project root
2. Comprehensive `.gitignore` (vendor/, storage/logs,
   storage/uploads, config/local.php, config/cli.php, .env,
   OS cruft)
3. First commit: entire project state up to and including 007B
4. Tag the commit `stage-007b-complete`
5. Establish branching model (trunk-based with feature branches,
   or git-flow)
6. Optional: local bare repo for offline backup

Details in `docs/git-setup.md` (to be written).

---

## Database State

- 47 `core_*` tables (inherited from Recruit Aid)
- **28 `commerce_*` tables** (was 24 at start of 007B)
- **29 migrations** applied (`0001` through `0208`)
- 6 seeders applied:
  - currencies (10)
  - roles and permissions (6 roles, 11 permissions)
  - admin user
  - default store
  - customer groups (Retail, Wholesale, VIP)
  - **test B2B customer** (Sarah Njeri, pending state)

Test data in dev:
- 1 admin user: `admin@anayaglobal.test` / `ChangeMe!2026`
- 1 store: `Anaya Global` (slug: anaya)
- 3 products: Comb, Wool Crew Neck Sweater, T-Shirt
- 1 variable product with 12 variations (T-Shirt)
- 3 customer groups (Retail default, Wholesale B2B with 30-day
  net terms, VIP)
- 1 category with attribute mapping
- 3 attributes: Color, Size, Care Instructions
- **1 B2B customer in pending state** (Sarah Njeri, Mombasa
  Traders Ltd)
- **Several approval history rows** from test passes

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

---

## Known Issues

- None blocking. All UI verified in last session:
  - Customer approval queue
  - Reject / reset / history modals
  - Net terms field on group edit
  - Sidebar scroll-to-active
  - Inventory adjust modal
  - Customer groups CRUD
  - Price list management
  - Toggle persistence (via EditView helper)
  - Tab persistence across saves
  - Modal display bug (fixed)

Deferred / cosmetic:
- Topbar search (coming soon) input is disabled
- Settings gear in topbar links to /commerce/ca-admin/settings
  which 404s
- `docs/REMEMBER.md` has a duplicated "Known Issues" section
  from an earlier append operation - needs a cleanup pass

---

## Patterns Locked

See `docs/REMEMBER.md` for the full pattern list (22 total).

Quick reference:

- Pattern 1-14: earlier session patterns
- Pattern 15: EditView helper for snake/camel case
- Pattern 16: Tab scroll to active
- Pattern 17: FKs to core_* are INT UNSIGNED
- Pattern 18: Partial migration application
- Pattern 19: Pre-flight inventory before touching closed code
- Pattern 20: One file per message during critical work
- Pattern 21: StoreContext required for repository DB access
- **Pattern 22: Prefix-match active state breaks on strict-prefix
  routes**

---

## Verified Baseline

`docs/BASELINE.md` + `docs/baseline/*.md` (16 sections) documents
every verified codebase convention. Read this before writing any
new code. It supersedes any assumption not backed by a file read.

Key corrections it caught:
- Flash is layout-only
- View paths use dot-notation
- Controllers have no constructor
- Route params arrive as strings
- `/commerce/ca-admin` needs exact-match for active state

---

## Next 3 Stages

- **Git setup** (prerequisite to Stage 008)
- **008**: Storefront - public site, cart, checkout, account
- **009**: Forms builder

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
2. Read `docs/REMEMBER.md` (full context + 22 patterns)
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
