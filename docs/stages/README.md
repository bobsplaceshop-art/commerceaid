# Stage Log - Anaya Global Commerce Platform

Running record of what was built, when, and why. Each stage is a
self-contained document under this folder.

The canonical build sequence lives in docs/ROADMAP.md. This log
records what actually shipped, in order.

| #      | Stage                                 | Date       | Status |
|--------|---------------------------------------|------------|--------|
| 001    | Project initialization and foundation | 2026-09-25 | Done   |
| 002    | Framework import and Recruit cleanup  | 2026-09-25 | Done   |
| 003    | Database schema (core and commerce)   | 2026-09-25 | Done   |
| 004    | Foundation rewrite and first login    | 2026-09-25 | Done   |
| 005    | Admin shell and setup hub             | 2026-09-25 | Done   |
| 006A   | Catalog migrations                    | 2026-09-25 | Done   |
| 006B   | Catalog models and repositories       | 2026-09-25 | Done   |
| 006C   | Categories admin CRUD                 | 2026-09-25 | Done   |
| 006D   | Brands admin CRUD                     | 2026-09-26 | Done   |
| 006E   | Products list and editor              | 2026-09-26 | Done   |
| 006F   | Product variations                    | 2026-09-26 | Done   |
| 006G   | Inventory overview                    | 2026-09-26 | Done   |
| 007A   | Customer groups and price lists       | 2026-09-26 | Done   |
| 007B   | Purchase orders and approval          | 2026-09-26 | Done   |
| 008A   | Storefront shell and Store model      | 2026-09-27 | Done   |
| 008B   | Storefront visual identity and catalog| 2026-09-27 | Done   |
| 008B.2 | Extended visual pass                  | 2026-09-27 | Done   |
| 008C   | Customer registration, login, account | -          | Next   |
| 008D   | Cart                                  | -          | Planned |
| 008E   | Checkout                              | -          | Planned |
| 008F   | Order lifecycle (customer-facing)     | -          | Planned |
| 008G   | PWA, search, storefront polish        | -          | Planned |
| 009    | Forms and submissions                 | -          | Planned |
| 010    | Payments and shipping (deep)          | -          | Planned |
| 011    | Advanced commerce + store builder     | -          | Planned |
| 012    | Theme engine and page builder         | -          | Planned |

## How to read the log

Each stage document follows the same structure:

1. Summary - what was built and why
2. Files created and modified
3. Schema changes (if any)
4. Design decisions
5. Bugs hit and their resolutions
6. Verification steps
7. What's next

Files with a letter suffix (006A, 007A, 008B, 008B.2) are
sub-stages of a larger stage, split to keep each document
reviewable.

## Current focus

**Stage 008C - Customer Registration, Login, Account.**

Customer-side authentication and account management. Distinct
from the admin login (separate session cookie, separate user
table). Includes registration, login, logout, forgot password,
account dashboard, address book, and password change.

Approval workflow integrated: customers registering into a
group with `requires_approval` land in a pending state. They
can log in and browse but cannot check out until approved by
an admin in the customer approvals queue.

~12 new files, mostly views and one controller.

## Completed since session start (2026-09-25)

- Full framework, admin shell, setup wizard, login
- Complete catalog: categories, brands, attributes, products,
  variants, inventory
- Multi-tenant foundation with StoreContext enforcement
- Responsive directive and reusable modal system
- B2B foundation: customer groups, price lists, tier pricing
- Customer approval workflow with per-transaction history
- Public storefront with visual identity matching the customer
  design brief
- Store builder contract via data-sf-* attributes on every
  storefront element
- Git initialized, backed up, workflow documented
- Static analysis (PHPStan level 5), syntax linter, pre-commit
  hook
- docs/BASELINE.md + 16 section files documenting verified
  conventions
- docs/REMEMBER.md with 22 documented bug patterns

## See also

- docs/ROADMAP.md - canonical build sequence
- docs/REMEMBER.md - full session handoff + patterns
- docs/BASELINE.md - verified codebase conventions
- docs/ARCHITECTURE.md - layered architecture contract
- docs/NAMING-CONVENTION.md - suite naming rules
- docs/RESPONSIVENESS.md - mandatory responsive directive
- docs/git-workflow.md - branching model + commit environment
- docs/STATUS.md - current state at a glance
