# Stage Log - Anaya Global Commerce Platform

Running record of what was built, when, and why. Each stage is a
self-contained document under this folder.

The canonical build sequence lives in docs/ROADMAP.md. This log
records what actually shipped, in order.

| #     | Stage                                 | Date       | Status |
|-------|---------------------------------------|------------|--------|
| 001   | Project initialization and foundation | 2026-09-25 | Done   |
| 002   | Framework import and Recruit cleanup  | 2026-09-25 | Done   |
| 003   | Database schema (core and commerce)   | 2026-09-25 | Done   |
| 004   | Foundation rewrite and first login    | 2026-09-25 | Done   |
| 005   | Admin shell and setup hub             | 2026-09-25 | Done   |
| 006A  | Catalog migrations                    | 2026-09-25 | Done   |
| 006B  | Catalog models and repositories       | 2026-09-25 | Done   |
| 006C  | Categories admin CRUD                 | 2026-09-25 | Done   |
| 006D  | Brands admin CRUD                     | 2026-09-26 | Done   |
| 006E  | Products list and editor              | 2026-09-26 | Done   |
| 006F  | Product variations                    | 2026-09-26 | Done   |
| 006G  | Inventory overview                    | 2026-09-26 | Done   |
| 007A  | Customer groups and price lists       | 2026-09-26 | Done   |
| 007B  | Purchase orders and approval          | 2026-09-26 | Done   |
| 008   | Storefront and search                 | -          | Planned |
| 009   | Forms and submissions                 | -          | Planned |
| 010   | Payments and shipping (deep)          | -          | Planned |
| 011   | Advanced commerce                     | -          | Planned |
| 012   | Theme engine and page builder         | -          | Planned |

## How to read the log

Each stage document follows the same structure:

1. Summary — what was built and why
2. Files created and modified
3. Schema changes (if any)
4. Design decisions
5. Bugs hit and their resolutions
6. Verification steps
7. What's next

Files with a letter suffix (006A, 006B, 007A, 007B) are sub-stages
of a larger stage, split to keep each document reviewable.

## Current focus

**Stage 008 - Storefront & Search.** This is the public-facing
delivery. Storefront routing, product browsing, cart, checkout,
customer account, order lifecycle. The customer + order schema
from 007B is already in place and will be fully wired in 008.

Before starting 008, the project is being placed under git so
every future change is versioned, diffable, and reversible.

## Completed since session start

Since this build session began (2026-09-25), the following have
been delivered:

- Full framework, admin shell, setup wizard, login
- Complete catalog: categories, brands, attributes, products,
  variants, inventory
- Multi-tenant foundation with StoreContext enforcement
- Responsive directive and reusable modal system
- B2B foundation: customer groups, price lists, tier pricing
- Customer + order schema (10 new tables)
- Customer model, repository, approval workflow
- Customer approval queue with per-transaction history log
- Reset/reject reasons with admin-only visibility
- Net terms field on customer groups
- 22 documented bug patterns in REMEMBER.md
- Baseline of verified codebase conventions (16 sections)

## See also

- docs/ROADMAP.md - canonical build sequence
- docs/REMEMBER.md - full session handoff
- docs/ARCHITECTURE.md - layered architecture contract
- docs/NAMING-CONVENTION.md - suite naming rules
- docs/RESPONSIVENESS.md - mandatory responsive directive
- docs/BASELINE.md - verified codebase conventions index
- docs/STATUS.md - current state at a glance
