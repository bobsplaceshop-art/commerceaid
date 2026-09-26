# Stage 007B - Purchase Orders & Approval

**Date:** 2026-09-26
**Status:** In Progress (M3 complete, M4 next)

## Summary

Second half of Stage 007 (B2B). Customer approval workflow for
B2B signups, net terms on customer groups, purchase order fields
on orders. The approval queue lets admins gate wholesale
registrations before they can order.

## Progress

| # | Deliverable | Status |
|---|---|---|
| M1 | Migration: customer + order tables | Done |
| M2 | Customer model + repository | Done |
| M3 | Approval controller + queue + history | Done |
| M4 | Group net terms UI | Pending |
| M5 | Full test pass + stage close | Pending |

## M1 - Migration

Files:
- `database/migrations/0205_customer_and_order_tables.sql`
- `database/migrations/0206_fix_approved_by_type.sql`
- `database/migrations/0207_add_approved_by_fk.sql`

Created 10 tables that were referenced in the original product
design but never shipped:

Customer side:
- commerce_customers (with approval columns)
- commerce_customer_addresses
- commerce_customer_password_resets
- commerce_customer_sessions

Order side:
- commerce_orders (with PO + net due columns)
- commerce_order_items
- commerce_order_status_history
- commerce_order_notes
- commerce_order_taxes
- commerce_order_shipments

Plus: `default_net_days` added to `commerce_customer_groups`.

### Migration sequence correction

The first attempt at `0205` failed on a FK type mismatch
(`fk_com_osh_user` - BIGINT vs INT). The migration was rewritten
to fix the three columns that FK to `core_users.id`.

However, `commerce_customers` had already been created in the
partial first attempt (MySQL DDL auto-commits). A re-run with
`CREATE TABLE IF NOT EXISTS` skipped the existing table, so its
`approved_by` column stayed BIGINT UNSIGNED.

Two follow-up migrations correct this:

- `0206_fix_approved_by_type.sql` - ALTER the column type
- `0207_add_approved_by_fk.sql` - ADD the FK that was missing

**Lesson (Pattern 18):** Partial migration application. When a
migration has multiple CREATE TABLE statements and one fails in
the middle, the earlier ones are committed. A re-run with IF NOT
EXISTS skips them, so corrections never apply.

**Prevention:** Put FKs and risky statements in separate
migrations so a failure doesn't leave a partially-built schema.

## M2 - Customer Model + Repository

### `app/Commerce/Models/Customer.php`

DTO with all 24 columns. Behavior methods:

- `isActive()`, `isDisabled()`, `isArchived()` - status
- `isPending()`, `isApproved()`, `isRejected()` - approval_status
- `isEmailVerified()`, `isGuest()`
- `canCheckout()` - isActive() AND isApproved()
- `fullName()`, `initials()` - display helpers

### `app/Commerce/Repositories/CustomerRepository.php`

Tenant-scoped. Every query filters by `store_id`.

Reads:
- `paginate($filters, $page, $perPage)`
- `listPending($limit)`
- `findById($id)`, `findByEmail($email)`, `emailExists($email)`
- `count()`, `countPending()`

Writes:
- `create(Customer $c)`, `update(Customer $c)`

Approval workflow:
- `approve($id, $userId)` - sets approved, records actor
- `reject($id, $userId, $reason)` - sets rejected with reason
- `resetToPending($id)` - clears approval state

All approval methods are idempotent (WHERE clause guards against
re-applying the same state).

### Provider binding

`CustomerRepository` registered as singleton in
`CommerceServiceProvider::register()`.

## M3 - Approval Controller, Queue, History

### What Shipped

**Migration 0208** - `commerce_customer_approval_history` table:

- `id`, `store_id`, `customer_id`, `from_status`, `to_status`,
  `reason`, `actor_id`, `created_at`
- FKs: store_id -> commerce_stores (BIGINT), customer_id ->
  commerce_customers (BIGINT), actor_id -> core_users (INT)
- Indexes: `idx_com_cah_customer (customer_id, created_at)`,
  `idx_com_cah_store (store_id, created_at)`
- Append-only. Never updated, never deleted.

**Model** - `app/Commerce/Models/CustomerApprovalHistory.php`

DTO with 9 typed properties (all with defaults). Behavior methods:

- `isCreation()`, `isApproval()`, `isRejection()`, `isReset()`
- `hasReason()`, `hasActor()`
- `actionLabel()`, `badgeVariant()`, `actorDisplay()`

**Repository** - extended `CustomerRepository`:

Modified methods (all now transactional + write history):
- `create(Customer)` - initial history row with from_status = NULL
- `approve($id, $userId)` - history row from current -> approved
- `reject($id, $userId, $reason)` - history row with reason
- `resetToPending($id, ?string $reason = null)` - history row with
  optional reason

New methods:
- `approvalHistory($customerId, $limit = 50)` - timeline per customer
- `recentApprovalHistory($limit = 20)` - store-wide (unused yet)
- `countByApprovalStatus(?string $status = null)` - badge counts
- `transition(...)` - private transaction wrapper
- `writeHistory(...)` - private append-only writer
- `currentActorId()` - resolves the acting user from session

**Controller** - `CustomerApprovalController`:

- `index` - list + filters + badge counts for all tabs
- `history` - JSON timeline for a single customer
- `approve` - POST, single-click, flash success
- `reject` - POST, reason required, flash success
- `reset` - POST, reason optional, flash success

**Views** - `resources/views/admin/customer_approvals/`:

- `index.php` - queue table with status badges, filter tabs,
  action buttons (approve/reject/reset/history), inline pagination
- `_reject-modal.php` - reject flow, reason required
- `_reset-modal.php` - reset flow, reason optional
- `_history-modal.php` - timeline modal, fetch-based

**Routes** - 5 new endpoints under
`/commerce/ca-admin/customer-approvals`:

- `GET  /` - list
- `GET  /{id}/history` - JSON timeline
- `POST /{id}/approve`
- `POST /{id}/reject`
- `POST /{id}/reset`

**Sidebar** - added "Customer Approvals" item under Sales group.

### Design Decisions

1. **Reasons are admin-only.** Customers see a generic "pending
   review" message, never the internal reason.
2. **Reset reason is optional.** Approval and rejection are
   binary; reset is usually "give them another chance" and often
   has no specific reason. Optional keeps the field meaningful.
3. **History is a domain timeline, not an audit log.**
   `core_audit_logs` records HTTP requests for compliance.
   `commerce_customer_approval_history` records state changes for
   the customer-facing timeline. They serve different purposes and
   live in different tables.
4. **Transaction wrapper on every transition.** If the history
   insert fails, the state change rolls back. No state changes
   without a corresponding history row.
5. **Idempotent actions.** Re-approving an approved customer is a
   no-op (WHERE clause guards). No spurious history rows.

### Bugs Hit in M3

#### Bug 1 - Reject button onclick attribute truncated

**Symptom:** Clicking the reject (red X) icon did nothing. No
console error visible without opening DevTools. The approve
(checkmark) button worked fine.

**Root cause:** The onclick attribute was wrapped in double
quotes AND used `json_encode($c->fullName())`, which produces
double-quoted JSON. For "Sarah Njeri", the rendered HTML was:

    onclick="RA_RejectCustomer.open(1, "Sarah Njeri")"

The browser parsed the attribute as ending after `open(1, `.
The remaining tokens became garbage attributes.

**Fix:** Use single quotes around the onclick attribute:

    onclick='RA_RejectCustomer.open(1, "Sarah Njeri")'

Matches the working pattern in `products/index.php`.

**Known limitation:** Names containing single quotes (e.g.
"O'Brien") will still break the attribute. The proper fix is
data-* attributes + addEventListener (Pattern 13 in REMEMBER.md
uses this approach for inventory). Documented as a future
refactor.

**Where we hit it:** M3-6i, T5 of the first test pass. The
approve button worked, the reject button did not. Root cause was
found by reading the rendered DOM via DevTools.

#### Bug 2 - Sidebar scrolls to top, active item invisible

**Symptom:** On `/commerce/ca-admin/customer-approvals`, the
sidebar did not scroll to show the active item. The page loaded
with the sidebar at scrollTop=0. The active item (Customer
Approvals) was off-screen.

**Root cause:** Two-part.

Part A: The sidebar's scroll container resets to scrollTop=0 on
every page load. No logic scrolled the active item into view.

Part B (the real bug): `$isActive()` was marking TWO items as
active on every admin page. The Dashboard href is
`/commerce/ca-admin`, which is a strict prefix of every other
admin route. Prefix matching correctly highlighted the actual
active item AND falsely highlighted Dashboard.

When we added scroll-to-active logic (fixing Part A), it found
the FIRST `.ra-nav-item.is-active` element (Dashboard) and
scrolled there. So the sidebar stayed at top and the real active
item remained invisible.

**Fix:** Two changes in `sidebar.php`:

1. Added `scrollActiveIntoView()` calling
   `scrollIntoView({ block: 'nearest' })` after `applyState()`.
2. Added an exact-match special case in `$isActive` for the
   `/commerce/ca-admin` href. Every other item continues to
   prefix-match.

**Result:** Only one item is marked active. The scroll finds the
right element. Both bugs fixed by one conceptual change.

**Detection rule:** Any admin page should have exactly one active
nav item. Verify in the browser console:

    document.querySelectorAll('.ra-nav-item.is-active').length

Expected: 1. Anything else is a bug.

**Pattern reference:** Pattern 22 in `docs/REMEMBER.md`.
Baseline item 21 in `docs/baseline/02-assumptions.md`.

### Test Results

Full end-to-end pass on 2026-09-26:

| # | Test | Result |
|---|---|---|
| T1 | Queue loads, badges correct (pending 1, approved 0, rejected 0, all 1) | PASS |
| T2 | History modal on fresh customer | PASS |
| T3 | Approve writes history, flash, counts update | PASS |
| T4 | History after approval shows correct timeline | PASS |
| T5 | Reset with reason works | PASS |
| T6 | History after reset shows reason block | PASS |
| T7 | Reject with reason works | PASS |
| T8 | History after rejection shows all 3 entries | PASS |
| T9 | Approve again, history shows 4 entries | PASS |
| T10 | Idempotency — DB count matches UI count | PASS |
| T11 | Sidebar nav still works | PASS |
| T12 | Nothing else broke (dashboard, products, categories, groups) | PASS |

### Artifacts

- Migration: `0208_customer_approval_history.sql`
- Model: `CustomerApprovalHistory.php` (9 properties, 10 methods)
- Repository: `CustomerRepository.php` (510 lines, 15 public + 4 private methods)
- Controller: `CustomerApprovalController.php` (321 lines, 5 public + 4 private methods)
- View: `index.php` (297 lines)
- Modals: `_reject-modal.php`, `_reset-modal.php`, `_history-modal.php`
- Routes: 5 new endpoints
- Sidebar: 1 new item + scroll-to-active logic

## What's Next

### M4 - Group Net Terms UI

- Add `default_net_days` field to customer group edit form
- Update `CustomerGroupController` to save the field
- Field type: number input, 0 = prepay, 30/60/90 = net terms

### M5 - Stage Close

- Full re-test with M4 changes
- Update `docs/stages/README.md` index
- Mark 007B complete in `docs/ROADMAP.md`
- Close the stage

## Schema

### commerce_customers

- 24 columns including approval state
- Unique key: `(store_id, email)`
- `approval_status` ENUM('pending','approved','rejected') DEFAULT 'approved'
- FKs: store_id -> commerce_stores, customer_group_id -> commerce_customer_groups, approved_by -> core_users (INT UNSIGNED)

### commerce_customer_groups (modified)

- Added `default_net_days` SMALLINT UNSIGNED NOT NULL DEFAULT 0

### commerce_orders (with PO support)

- `po_number` VARCHAR(60) NULL - customer's PO reference
- `net_due_at` DATETIME NULL - when payment is expected
- `billing_address_json`, `shipping_address_json` - immutable snapshots
- Full order status enum (pending_payment -> paid -> processing -> packed -> shipped -> delivered -> completed + cancelled/failed/refunded/partial_refunded/on_hold)

### commerce_customer_approval_history (new)

- `id`, `store_id`, `customer_id`, `from_status`, `to_status`,
  `reason`, `actor_id`, `created_at`
- FKs: store_id -> commerce_stores, customer_id -> commerce_customers, actor_id -> core_users
- Append-only. No updates, no deletes.

### Design decisions

1. One group per customer
2. Price lists per group
3. Tier pricing lives in the price list
4. MOQ is per product
5. **Customers are separate from core_users** (different lifecycle)
6. **Orders store address snapshots** (legal record at order time)
7. **Order items store product_name, sku snapshots** (products may be renamed/deleted)
8. **PO number is metadata on the order**, not a separate entity
9. **Approval history is a domain timeline**, not an audit log.
   Distinct from `core_audit_logs`.
10. **Reset reason is optional**, approval/rejection reason is required.

## Bugs Hit Earlier in the Stage

### Pattern 17 - FKs to core_* tables use INT UNSIGNED

All `core_users`, `core_roles`, `core_files` tables use
`INT UNSIGNED` PKs. Any FK from a `commerce_*` table to a
`core_*` table must be `INT UNSIGNED`, not `BIGINT UNSIGNED`.

Before writing a migration with a new FK, run
`DESCRIBE {parent_table};` and match the type exactly.

### Pattern 18 - Partial migration application

MySQL DDL auto-commits. If a migration has multiple CREATE
TABLE statements and one fails in the middle, the earlier ones
are committed. A re-run with `CREATE TABLE IF NOT EXISTS` skips
them, so corrections never apply.

Detection: after a failed migration, verify the state of every
table it created. If a table exists but has the wrong shape, add
a follow-up migration to fix it (ALTER).

Prevention: put all FKs and risky statements in separate
migrations so a failure doesn't leave a partially-built schema.
