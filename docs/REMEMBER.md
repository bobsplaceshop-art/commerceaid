# REMEMBER — Session Handoff Document

**Project:** Anaya Global Commerce Platform
**Path (Windows):** C:\projects\commerceaid
**Path (WSL):** /mnt/c/projects/commerceaid
**Document date:** 2026-09-28
**Current status:** Stage 008K.3 complete. Next: 008K.4 (admin roles).

---

## Session Close Protocol

Every session ends the same way. No exceptions. Short session,
short entry.

Five steps:

1. Write `docs/sessions/YYYY-MM-DD.md`. Copy the template in
   `docs/sessions/README.md`.
2. Update the distilled docs: patterns in this file, stage in
   `docs/STATUS.md`, sequence in `docs/ROADMAP.md`, stage
   plan in `docs/stages/`.
3. Commit the session file. Its own docs commit.
4. `git push backup main`. Then `git push backup --tags` if
   any tags were created.
5. Leave the working tree clean.

The session file answers three questions that git cannot:
what was tried and rejected, what decisions were made and
why, and what the next session should open with. Keep it
short - it links to sources, it does not duplicate them.

Full protocol: `docs/sessions/README.md`.

---

## 1. What This Project Is

A white-labelled, multi-tenant, standalone e-commerce platform built as a single
product for multiple clients. First deployment is branded for Anaya Global. The
same codebase will be resold to other customers with only configuration changes.

The platform is:

- PHP 8.2+ / MySQL 8 / Apache (shared cPanel hosting compatible)
- Custom minimal MVC framework (no Laravel/Symfony)
- Bootstrap 5 via CDN + vanilla JS (no Node.js, no build step)
- Multi-tenant from day one (shared DB, store_id column)
- Fully white-labelled (every customer-visible string is configuration)

Suite context: This is a module in the Corporate Aid Solutions suite. Sibling
product is Recruit Aid (an ATS at C:\projects\recruitaid). Naming conventions
are locked in docs/NAMING-CONVENTION.md. Architecture contract is in
docs/ARCHITECTURE.md. Build sequence is in docs/ROADMAP.md.

Corporate Connect (the suite's integration layer) is deferred to a future
stage. It is NOT part of this build.

---

## 2. Current Environment

| Tool | Value |
|---|---|
| OS | Windows 11 |
| WSL | Ubuntu (file ops, heredocs, greps) |
| Laragon | 2026 v8.7.0 |
| Apache | Port 8081 (vhost 00-commerce-aid.conf) |
| PHP | 8.3.33 (Laragon Terminal only, NOT in WSL) |
| MySQL | 8.4.3 (port 3306) |
| Composer | 2.10.3 |
| Mailpit | 1025 / 8025 |
| Node.js | NOT INSTALLED (and never will be - by design) |

- URL: http://localhost:8081
- Admin URL: http://localhost:8081/commerce/ca-admin
- Login: admin@anayaglobal.test / ChangeMe!2026
- Database: anaya_commerce

### Environment Rules (Locked)

1. WSL is for authoring (heredocs, greps, file ops, find). WSL has PHP but not
   MySQL. Never emit mysql commands from WSL.
2. Laragon Terminal is for all PHP execution (php -l, composer, migrations,
   seeders, smoke tests). Also has MySQL CLI.
3. HeidiSQL is for schema inspection and ad-hoc SQL queries.
4. Browser DevTools (F12) is for JS debugging, service worker checks, network
   inspection.
5. After every app/ change: reload Apache. OPcache caches stale PHP.
6. After every new class file: composer dump-autoload -o.
7. After every heredoc write: verify with ls + head. Multiple silent failures
   happened. Never assume a write landed.
8. After every Python patch: grep the changed line. php -l alone doesn't catch
   all textual corruption.

### Command Emission Format

Header column names the tool, raw command below it, nothing prepended inside
the code block:

    WSL:      ls -la /mnt/c/projects/commerceaid/
    Laragon:  php -l C:\projects\commerceaid\app\Core\App.php
    HeidiSQL: SELECT * FROM commerce_products;

Never prepend [WSL] inside the code block - it breaks copy-paste.

---

## 3. Architecture - Locked

Three layers, strictly one-way:

    Commerce\*   (product code - controllers, services, repositories)
        depends on
    Platform\*   (shared infra - auth, RBAC, settings, files, notifications)
        depends on
    Core\*       (framework - HTTP, DB, view, router)

Rules:

- Core knows nothing about Platform or Commerce
- Platform knows nothing about Commerce
- Commerce may use both
- Never the reverse

Core\App is a pure bootstrapper. Zero product knowledge. Product registration
goes through CommerceServiceProvider (via App::PRODUCT_PROVIDERS array).

---

## 4. Completed Stages

| # | Stage | Status |
|---|---|---|
| 001 | Project initialization & foundation | Done |
| 002 | Framework import & Recruit cleanup | Done |
| 003 | Database schema (core + commerce) | Done |
| 004 | Foundation rewrite & first login | Done |
| 005 | Admin shell & setup hub | Done |
| 006A | Catalog migrations | Done |
| 006B | Catalog models & repositories | Done |
| 006C | Categories admin CRUD | Done |
| 006D | Brands admin CRUD | Done |
| 006E | Products list & editor | Done |
| 006F | Product variations | Done |
| 006G | Inventory overview | Done |
| 007A | Customer groups & price lists | Done |
| 007B | Purchase orders & approval | Done |
| 008A | Storefront shell & Store model | Done |
| 008B | Storefront visual identity & catalog | Done |
| 008B.2 | Extended visual pass | Done |
| 008C | Customer registration, login, account | Done |
| 008D | Cart | Done |
| 008D.1 | Variant-level inventory | Done |
| 008D.2 | Per-product inventory page | Done |

## 5. Database Schema - Summary

47 core tables (core_*) + 20 commerce tables (commerce_*).

### Core (inherited from Recruit Aid, unchanged)

core_users, core_roles, core_permissions, core_role_permissions,
core_user_roles, core_user_permissions, core_password_resets,
core_audit_logs, core_activities, core_notifications,
core_notification_rules, core_system_settings, core_files,
core_entity_files, core_custom_fields, core_custom_field_values,
core_sequence_counters, core_rate_limits, core_migrations,
core_modules, core_module_licenses, core_module_state,
core_integrations, core_integration_logs, core_scheduled_tasks,
core_scheduled_task_runs, core_health_checks, core_updates_available,
core_updates_history, core_update_manifest, core_currencies,
core_notes, core_feature_flags, core_webhooks,
core_webhook_deliveries, core_email_templates, core_data_requests,
core_consent_log, core_login_history, core_workflow_definitions,
core_workflow_states, core_workflow_transitions, plus a few more.

Core tables got store_id columns added via migration 0101:
core_audit_logs, core_activities, core_notification_rules,
core_sequence_counters.

### Commerce Tables

Store:
- commerce_stores
- commerce_store_domains
- commerce_store_users
- commerce_store_currencies

Catalog:
- commerce_products
- commerce_product_variants
- commerce_product_images
- commerce_product_videos
- commerce_product_tags
- commerce_product_tag_links
- commerce_categories
- commerce_product_categories
- commerce_brands
- commerce_attribute_definitions
- commerce_attribute_values
- commerce_product_attribute_values
- commerce_variant_attribute_values
- commerce_category_attributes

Inventory:
- commerce_inventory
- commerce_inventory_movements

### Key Schema Rules

- Every tenant-scoped table has store_id NOT NULL FK to commerce_stores
- Attribute definitions are store-independent (global vocabulary)
- commerce_inventory.variant_id = 0 sentinel for simple products
- commerce_inventory.available is a STORED GENERATED column
  (quantity - reserved)
- commerce_inventory_movements is append-only (never delete)
- FKs to core_* tables use INT UNSIGNED (matches inherited schema)
- FKs to commerce_* tables use BIGINT UNSIGNED
- SKU and slug are unique per store (composite keys)

---

## 6. Key Files and Their Roles

### Core framework (app/Core/)

- App.php - Bootstrapper. Registers Core + Platform, delegates to providers.
- Container.php - DI container.
- Router.php - Route registration + dispatch. {param} syntax.
- Request.php - Wraps superglobals. query(), input(), path(), header().
- Response.php - html(), json(), redirect(), noContent().
- Session.php - Session wrapper. get, set, flash, getFlash, flashOldInput,
  old, clearOldInput.
- Csrf.php - CSRF token. token(), verify(), rotate().
- Auth.php - attempt(), login(), logout(), user(), check(), can().
- Database.php - PDO wrapper. select, selectOne, scalar, execute, insert,
  beginTransaction, commit, rollBack.
- View.php - Renders views from resources/views/. Dot-notation.
- Config.php - Config accessor.
- Http/Kernel.php - Middleware registry + onion pipeline.

### Platform (app/Platform/)

Models: User, File, EntityFile, Notification, CustomField, CustomFieldValue.

Repositories: UserRepository, RoleRepository, CustomFieldRepository,
FileRepository, EntityFileRepository, NotificationRepository,
PasswordResetRepository, RateLimitRepository.

Services: SystemSettingService, NotificationService, NotificationRuleService,
FileService, CustomFieldService.

Support: Logger, Hasher, EmailService, RateLimiter, ActivityLogger,
AuditLogger, CodeGenerator.

Middleware: Auth, Guest, CSRF, Permission, RateLimit, AuditLogger.

CustomFields engine with 12 input types. Deletion engine with confirmation
phrases.

### Commerce (app/Commerce/)

Context:
- Context/StoreContext.php - resolved store per request
- Exceptions/StoreContextMissingException.php

Controllers:
- Controllers/AuthController.php - admin login/logout
- Controllers/AccountController.php - theme preference AJAX
- Controllers/Pwa/ManifestController.php - PWA manifest
- Controllers/Admin/AdminController.php - base class with render()
- Controllers/Admin/DashboardController.php
- Controllers/Admin/SetupController.php - setup hub with tabs
- Controllers/Admin/CategoryController.php
- Controllers/Admin/BrandController.php
- Controllers/Admin/ProductController.php - includes variant methods
- Controllers/Admin/AttributeController.php

Middleware:
- Middleware/StoreResolutionMiddleware.php
- Middleware/SetupGateMiddleware.php

Models:
- Category, Brand, Product, ProductVariant, Inventory, InventoryMovement

Repositories:
- CommerceRepository.php - base, enforces store_id
- StoreRepository, CategoryRepository, BrandRepository, ProductRepository,
  ProductVariantRepository, InventoryRepository, AttributeRepository,
  CategoryAttributeRepository

Services:
- StoreService, CatalogService

Support:
- Support/Validator.php - shared validation helper

Provider:
- CommerceServiceProvider.php - all bindings for Commerce

### Routes (routes/)

- auth.php - /login, /logout, /account/theme
- web.php - empty placeholder
- commerce.php - everything else (storefront placeholders, admin, setup,
  catalog, variants)

### Views (resources/views/)

Layouts: layouts/app.php (admin shell), layouts/auth.php (login).

Partials: partials/sidebar.php (grouped, collapsible, localStorage
persistence), partials/topbar.php, partials/user-dropdown.php,
partials/theme-dropdown.php.

Storefront/admin: admin/dashboard.php, admin/setup/* (8 tabs),
admin/categories/*, admin/brands/*, admin/products/* (5 tabs + variants
page), admin/attributes/*.

Assets: public/assets/css/app.css (design system, inherited),
public/assets/css/setup.css (our additions).

---

## 7. Design Patterns - Locked

### Model Pattern (DTO)

Every model is a final class with public typed properties. Every property
MUST have a default value - empty declarations crash on access.

    final class Foo {
        public int $id = 0;
        public string $name = '';
        public ?string $description = null;

        public static function fromRow(array $row): self { ... }
    }

CRITICAL: This was a bug class we hit. Every typed property gets a default.

### Repository Pattern

Repositories extend CommerceRepository. Every query filters by storeId().
Find methods return ?Model; list methods return array<Model>. Includes
create, update, archive, restore, softDelete.

### Controller Pattern

Controllers extend AdminController. Use render() to render inside the admin
shell. On validation failure, flash errors and old input:

    $session->flash('foo_errors', $errors);
    $session->flashOldInput($data);

On GET (showCreate and showEdit), read them:

    'errors' => $session->getFlash('foo_errors', []),
    'old'    => $_SESSION['_old'] ?? [],

Never forget this - it was a bug on Brands and Categories.

### Validation

All field validation goes through CorporateAid\Commerce\Support\Validator:
- required, maxLength, minLength
- url (strict http/https + dotted host + filter_var)
- email, inArray, timezone (IANA list), hexColor, countryCode, slug
- positiveInt, nonNegativeNumber

### Sticky Action Bar Pattern

Every admin edit page ends with a fixed action bar:

    <div class="ra-sticky-actions">
        <div class="ra-sticky-actions-inner">
            <a href="..." class="ra-btn ra-btn-outline">Cancel</a>
            <button type="submit" form="ra-foo-main-form" class="ra-btn ra-btn-primary">
                Save changes
            </button>
        </div>
    </div>
    <div class="ra-sticky-spacer"></div>

Uses HTML5 form="" attribute to submit from outside the form element.
Positioned fixed at viewport bottom. The sticky-spacer prevents content
from hiding behind it.

### Sidebar Navigation

Grouped, collapsible, persisted to localStorage['ra-nav-groups']. Server
default: active group expanded.

### Theme System

- core_users.theme_preference ENUM ('dark', 'light', 'system')
- Pre-paint script resolves localStorage then server value
- AJAX POST /account/theme persists
- Default is 'system' (respects OS)

---

## 8. Product Variations - The Feature We Just Finished

### What It Does

A variable product has one or more variations - concrete sellable units
defined by combinations of attribute values. E.g. a T-Shirt with Color
(Red, Blue) x Size (S, M, L) = 6 variations.

### The Rules (Locked)

1. Attributes are global vocabulary (Color, Size, Material)
2. Categories declare which attributes are relevant via
   commerce_category_attributes mapping
3. Product inherits attributes from its categories
4. Only is_variant_axis = 1 attributes generate variations
5. Informational attributes (Care Instructions, Country of Origin) are
   mapped to categories for filtering but don't generate variations
6. Variations auto-generate when a variable product's variations page is
   loaded and no variations exist yet
7. Drift detection warns when category attributes change after variations
   exist
8. Regenerate wipes existing variations and rebuilds (with type-delete
   confirmation)
9. Archive not delete individual variations (they'd regenerate)
10. Incomplete axes (mapped but no values) block generation with a clear
    warning

### The Flow

1. Admin creates attributes (Color, Size) with is_variant_axis = 1
2. Admin adds values to each attribute
3. Admin creates a category (e.g. T-Shirts) and maps attributes to it
4. Admin creates a variable product and assigns it to that category
5. Admin clicks Manage Variations in the product sidebar
6. Variations auto-generate from the category's variant axes
7. Admin edits SKU/price/stock per variation
8. Admin clicks Save all variations

### The Variation Page Features

- Attribute summary (Color, Size) with link to edit categories
- Search by SKU or attribute value (400ms debounce)
- Group filter chips for the first axis (Red (6), Blue (6))
- Bulk actions (Enable, Disable, Set price) for checked rows
- Pagination (25 rows/page)
- Sticky action bar with Save
- Regenerate button when drift is detected
- Type-delete confirmation for regenerate

### File Locations

- Controller: app/Commerce/Controllers/Admin/ProductController.php
  (methods: variants, regenerateVariants; helpers: categoryVariantAxes,
  expectedCombinations, detectDrift, autoGenerate)
- Repository: app/Commerce/Repositories/ProductVariantRepository.php
- Category mapping repo: app/Commerce/Repositories/CategoryAttributeRepository.php
- View: resources/views/admin/products/variants.php
- Sidebar button: resources/views/admin/products/_sidebar.php

---

## 9. Known Issues and Deferred Work

### Currently Being Tested

Product variations page - just built, awaiting final verification of search
+ bulk actions + pagination behavior.

### Deliberately Deferred

- Image upload for products/variants (needs media library integration)
- Rich text editor for descriptions (plain textarea for now)
- Product tags UI (repository support exists, no admin UI)
- Discounts / promotions (Stage 011)
- Payment providers (Stage 008)
- Shipping zones (Stage 007)
- Tax classes (Stage 009)
- Storefront theme engine (Stage 010)
- Forms builder (Stage 009 in revised roadmap)

### Small Known Issues

- Topbar Search (coming soon) input is disabled - no global search yet
- Settings gear in topbar links to /commerce/ca-admin/settings which 404s
- products/_delete-modal.php is a placeholder - variations are archived not
  deleted, so it's rarely used

---

## 10. Recurring Bug Patterns and How to Handle Them

Each pattern below shows the symptom, the guardrail we adopted to catch or
prevent it, and the exact command or workflow to run when you suspect it.

### Pattern 1 - Silent Python Patch Failures

What breaks: str.replace() and re.sub() fail silently when whitespace does
not match. The patch appears to run but nothing changes. We hit this 6+ times.

How to handle it:

1. Before patching, always grep the exact target string to confirm it exists
   verbatim: grep -n "target text" path/to/file.php
2. After patching, always grep the result to confirm the change landed:
   grep -n "new text" path/to/file.php
3. If the pattern is fragile (multi-line, whitespace-sensitive), do not
   patch. Rewrite the whole file with a heredoc instead.

Never trust "the patch ran without error" as proof the change landed.

### Pattern 2 - Typed Property Without Default

What breaks: PHP 8 throws "Typed property X must not be accessed before
initialization" when a controller creates a fresh model instance and a
property was never assigned.

How to handle it:

1. Every typed property in every model MUST have a default value:
   - Nullable types: = null
   - Non-nullable string: = ''
   - Non-nullable int: = 0
   - Non-nullable float: = 0.0
   - Non-nullable bool: = false
   - Non-nullable array: = []
2. When adding a new model, always set defaults at declaration, never rely
   on the controller to set every property.
3. Verify with grep: search for bare property declarations with no default.

### Pattern 3 - Nested Forms

What breaks: HTML disallows form inside form. Browsers silently ignore the
inner form and submit the outer form's action instead. We hit this with the
variant generator inside the product editor.

How to handle it:

1. Never nest a form inside another form.
2. If a tab or card needs its own form, either:
   - Move it to a separate page (Model A approach - what we did for variants)
   - Render the inner forms outside the outer form element, and associate
     their buttons with the form via the HTML5 form attribute
3. The sticky action bar pattern uses the form attribute for exactly this
   reason.

### Pattern 4 - php -l Does Not Catch Everything

What breaks: php -l parses but does not compile. Textual corruption like a
missing quote can pass lint. We hit this with a missing single quote in
array access which parsed cleanly but broke at runtime.

How to handle it:

1. After every Python patch that modifies PHP code, grep the changed lines
   and read them by eye.
2. Verify the file loads by hitting a URL that uses it, not just by linting.
3. When a patch touches string literals, always grep for the resulting
   string in context.

### Pattern 5 - Sticky Positioning Inside overflow:auto

What breaks: position: sticky does not work reliably inside a parent that
has overflow: auto or overflow: hidden. The sticky element renders but never
sticks. We hit this with the action bar on edit pages.

How to handle it:

1. For elements that must stay pinned to the viewport, use position: fixed
   instead of position: sticky.
2. When using position: fixed, offset the element to account for the shell:
   left: var(--ra-sidebar-w, 260px); right: 0; bottom: 0;
3. Add a spacer element after the fixed bar so content does not hide behind
   it.
4. Test by scrolling the page - the bar should stay visible at the bottom
   through the whole scroll.

### Pattern 6 - Composer classmap-authoritative

What breaks: If composer.json has classmap-authoritative: true, Composer
ignores PSR-4 rules at runtime. New classes added since the last dump-autoload
are invisible and produce "Class not found" errors even though the files
exist on disk. We hit this when first adding Commerce classes.

How to handle it:

1. During development, composer.json must have
   classmap-authoritative: false.
2. After adding ANY new class file, always run:
   composer dump-autoload -o
3. When deploying to production, flip classmap-authoritative back to true
   for the performance gain, but only after running dump-autoload.
4. Verify with a one-line php command that checks class_exists for the
   new class. Expected: true.

### Pattern 7 - Route Order

What breaks: Routes are matched in registration order. A wildcard route
registered before a specific one swallows the specific route. Example:
/products/{id} registered before /products/create means /products/create
gets captured as id="create".

How to handle it:

1. Always register specific routes before wildcards in routes/commerce.php.
2. Group by specificity: create/new/edit endpoints first, then {id}/edit,
   then {id} alone.
3. When adding a new route, check that no earlier route could match it by
   prefix.
4. Verify by hitting the URL in the browser after every route change - do
   not trust the code to look right.

### Pattern 8 - Apache OPcache Serving Stale PHP

What breaks: Edit a PHP file, reload the browser, see old behavior. The log
shows no error but the code path is clearly outdated. We hit this repeatedly
in early stages and spent hours debugging code that had already been fixed.

How to handle it:

1. After editing any file under app/, always reload Apache. Laragon menu
   -> Apache -> Reload (or Restart).
2. If behavior still looks stale after reload, restart Apache fully.
3. When in doubt, check the actual file content via WSL cat before assuming
   the browser is wrong.

### Pattern 9 - Foreign Key Type Mismatch

What breaks: Migration fails with "Referencing column X and referenced
column Y in foreign key constraint Z are incompatible." We hit this when
adding FKs from commerce tables to core_files.

How to handle it:

1. Before writing a migration that adds an FK, check the parent column type
   with DESCRIBE.
2. Match types exactly:
   - FKs to core_* tables use INT UNSIGNED (matches inherited schema)
   - FKs to commerce_* tables use BIGINT UNSIGNED
3. If you inherit a schema with mixed types, follow the parent - never
   assume.
4. Grep for existing FKs in database/migrations/ before adding new ones.

### Pattern 10 - Deleting Records That Other Records Reference

What breaks: Trying to delete a category, attribute, or brand that is
referenced by products. The DB refuses with a foreign key constraint error,
or worse, cascades to data you did not intend to delete.

How to handle it:

1. Before any delete action, check usage:
   - Use a repository method like productCount(), usageCount(), or
     descendantIds() to count dependents
   - Show the admin a modal with the counts and a "type X to confirm"
     phrase
2. Prefer archive over delete. The DB keeps the record for audit; the UI
   hides it from active listings.
3. If the user must delete, use soft delete (deleted_at timestamp). The
   record can be recovered; nothing cascades.

### Pattern 11 - UI Showing Save in the Wrong Place

What breaks: Save buttons rendering mid-page, or not appearing at all.
We hit this on category edit (save in the middle) and attribute edit
(save missing entirely after a file rewrite).

How to handle it:

1. Every admin edit page uses the sticky action bar pattern (Section 7).
2. When rewriting any edit page, verify the sticky bar is present with
   grep ra-sticky-actions across resources/views/admin/*/edit.php
3. Verify the form id is set and the button references it via the form
   attribute.
4. Test in browser: scroll down, the bar should stay at the bottom.

### Pattern 12 - Validation Not Surfacing

What breaks: User submits invalid data. Form clears silently. No error
messages shown. We hit this on Brands and Categories.

How to handle it:

1. Every controller method that renders a form must read validation state:
   - showCreate() reads the foo_errors flash
   - showCreate() reads the old input from session
   - Both are passed to the view
2. Every store/update method must write validation state before redirecting:
   - Flash the error array
   - Flash the old input
3. The view uses the err() and val() helpers to render error messages
   and preserve input.
4. Test by submitting a blank form - errors should appear under each
   invalid field, and valid fields should keep their old values.

---

## 11. Immediate Next Steps

**Stage 008D.2 (Per-Product Inventory Page) is complete.**

What shipped in 008D:

  008D    Cart — session + DB cart, merge on login, mini-cart
          in header, stock checks per variant.
  008D.1  Variant-level inventory — the data-model fix.
          Every sellable unit has exactly one inventory row.
          Variable products have no parent row. The cart
          checks the specific variant's row.
  008D.2  Per-product inventory page — inventory list grouped
          by product, detail page with inline bulk adjustment
          and recent-movements block, mobile-responsive tables.

### Next: Stage 008E - Checkout

The sale side of the ledger. Multi-step checkout with order
creation, inventory reservation (via
InventoryRepository::reserve), B2B purchase order support for
approved customers, and stub payment providers (COD, bank
transfer, PO). Full scope in docs/ROADMAP.md.

### Prerequisites for 008E

  - 008D.2 complete                      OK
  - commerce_orders schema exists        OK (Stage 007B)
  - commerce_order_items schema exists   OK (Stage 007B)
  - Order status history schema exists   OK (Stage 007B)
  - Cart merge on login works            OK (008D)
  - Per-variant stock enforcement works  OK (008D.1)

### Also queued (see ROADMAP backlog)

  - Stage 007C - Procurement (suppliers, LPOs, GRN, expenses)
    Sequenced after 008E so the ledger gets receipts AND sales
    in the same era.
  - Data Import (CSV / Excel)
    Bulk-load products, categories, customers, suppliers.
    Wait until schema is frozen (after 007C + 008E).
  - Conversion-Focused Product Recommendations
    Ad-style product grid on product detail and cart pages.
    Lands in 008G polish or 011 store builder.

## 12. Files Referenced in This Document

- docs/ARCHITECTURE.md - layered architecture contract
- docs/NAMING-CONVENTION.md - suite-wide naming rules
- docs/ROADMAP.md - canonical build sequence
- docs/stages/README.md - stage log index
- docs/stages/001_* through 006e_* - individual stage docs
- docs/CHANGELOG.md - chronological history (Recruit Aid heritage)
- config/local.php - environment-specific (gitignored)
- config/cli.php - CLI credentials (gitignored)
- composer.json - PSR-4 mappings + dependencies

---

## 13. Quick Sanity Checks

If something feels broken, run these three checks in order.

First, rebuild Composer's autoloader and lint the bootstrapper (Laragon
Terminal):

    cd C:\projects\commerceaid
    composer dump-autoload -o
    php -l app\Core\App.php

Second, check the file tree and error log (WSL):

    ls -la /mnt/c/projects/commerceaid/app/Commerce/
    tail -40 /mnt/c/projects/commerceaid/storage/logs/app.log

Third, hit the admin URL in the browser:

    http://localhost:8081/commerce/ca-admin

If the admin shell loads, the framework is healthy. If it 500s, check the
log tail. If specific routes 404, check that routes/commerce.php has the
route registered.

---

End of REMEMBER.md

---

## 17. Permanent Fixes Applied

### Pattern 13 - Inline onclick with dynamic data breaks on quote collisions

**Problem:** an inline `onclick` attribute passes dynamically-
generated data (a name, a title, a description) through a
PHP-to-HTML-to-JS boundary. The data contains a character
that is valid in one layer but breaks another.

The classic case: `onclick='foo("<?= $name ?>")'` where
`$name` contains a double quote. It breaks the JS string
first. Or `onclick='foo(<?= json_encode($name) ?>)'` where
`$name` contains a single quote. It breaks the outer HTML
attribute first, then the JS parse.

**The tell:** clicking a button throws
`Invalid or unexpected token` at the JS console, or the
page's HTML parse is subtly wrong after the button. The
error location is inside a `<script>` block or an inline
attribute. The failing line contains user-entered data.

**Rule:** never pass data to inline `onclick` attributes.
Always use `data-*` attributes plus a delegated click
listener. The data is HTML-escaped by `e()` at render time
and read back at click time via `getAttribute()`.

**Before:**

    <button onclick='RA_DeleteProduct.open(<?= (int) $product->id ?>, <?= json_encode($product->name) ?>) '>

**After:**

    <button data-ra-delete-product
            data-product-id="<?= (int) $product->id ?>"
            data-product-name="<?= e($product->name) ?>">

**And in the JS file:**

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ra-delete-product]');
        if (!btn) return;
        e.preventDefault();
        RA_DeleteProduct.open(
            btn.getAttribute('data-product-id'),
            btn.getAttribute('data-product-name')
        );
    });

**Why `json_encode` is not safe here:** `json_encode` escapes
`"`, `\`, `/`, and control characters. It does NOT escape
`'`. A product name like `Men's Shorts` produces
`"Men's Shorts"` - valid JSON, but the `'` in `Men's`
closes the single-quoted HTML attribute early. The browser
then parses the rest of the name as HTML. Any `<` or `>` in
the name is treated as a tag.

**Where we hit it:**

  - Stage 007B - the approval queue reject button. The
    customer name contained an apostrophe. First
    manifestation.
  - Stage 008G audit - the product delete button. Product
    id=4 name contained two apostrophes. The browser
    closed the single-quoted onclick early, the HTML
    parser recovery swallowed the next form tag, and the
    Publish button null-submit error was the symptom.
    Second manifestation, in a completely different
    feature.

**Prevention checklist:**

1. Grep for `onclick=` in views. Every match with a PHP
   echo inside is a candidate.
2. If the data being echoed is user-entered (name, title,
   description, email), it is a bug. Convert to data
   attributes.
3. If the data is a numeric id or a fixed enum, it is safe.
   Keep it inline if you prefer.
4. After conversion, verify the button works with a value
   that contains `'`, `"`, and `<`.

---

### Pattern 15 - snake_case / camelCase mismatch in views

**Problem:** Views called $val('manage_stock') and $valBool('is_b2b'),
but model properties were camelCase ($manageStock, $isB2b). The
toggles showed wrong state because the read silently returned the
default. We patched this three times before deciding it needed a
permanent solution.

**Solution:** app/Commerce/Support/EditView.php

Provides two static methods:

    EditView::valClosure($old, $item)
    EditView::valBoolClosure($old, $item)

Both return closures that:

1. Check old input first (by snake_case key)
2. Fall back to model property, translating snake_case to camelCase
3. Fall back to the default

**Every admin edit view** now uses these helpers instead of inline
closures:

    $val     = \CorporateAid\Commerce\Support\EditView::valClosure($old ?? [], $product ?? null);
    $valBool = \CorporateAid\Commerce\Support\EditView::valBoolClosure($old ?? [], $product ?? null);

**Rule going forward:** never define $val or $valBool inline again.
Always use EditView. This eliminates the entire class of bug where
the field name in a view doesn't match the model property name.

### Pattern 16 - Tab content below the fold on page load

**Problem:** Loading a URL like /products/3/edit?tab=inventory
highlighted the Inventory tab but didn't scroll to it. The active
content was below the viewport, forcing the admin to scroll
manually.

**Solution:** Small JS block in the multi-tab page:

    function scrollToActiveTab() {
        var hash = window.location.hash;
        var params = new URLSearchParams(window.location.search);
        var tabParam = params.get('tab');
        var targetId = hash.startsWith('#tab-') ? hash : (tabParam ? '#tab-' + tabParam : '');
        if (!targetId) return;

        var nav = document.querySelector('.ra-product-tabs');
        if (nav) nav.scrollIntoView({ behavior: 'instant', block: 'start' });
    }

    window.addEventListener('load', scrollToActiveTab);

**Rule going forward:** any page with Bootstrap tabs that can be
reached via ?tab=xxx must include scroll-to-active-tab behavior on
load.

---

## 18. Additional Patterns (Discovered Stage 007A-007B)

### Pattern 17 - FKs to core_* tables use INT UNSIGNED

**Problem:** Migrations declared `BIGINT UNSIGNED` on columns that
FK'd to `core_users.id`, `core_files.id`, and other `core_*` PKs.
MySQL rejected with "Referencing column X and referenced column Y
in foreign key constraint Z are incompatible."

**Rule:** All `core_*` tables use `INT UNSIGNED` PKs. Any FK from
a `commerce_*` table to a `core_*` table must also be
`INT UNSIGNED`. FKs to other `commerce_*` tables use
`BIGINT UNSIGNED`.

**Prevention:** Before writing any migration with a new FK, run
`DESCRIBE {parent_table};` and match the type exactly.

**Where we hit it:** migrations 0200, 0205.

### Pattern 18 - Partial migration application

**Problem:** A migration with multiple CREATE TABLE statements
failed in the middle (on a FK type mismatch). MySQL DDL
auto-commits, so the earlier tables were created. When the
migration was corrected and re-run, `CREATE TABLE IF NOT EXISTS`
skipped those tables — so corrections to their shape never
applied.

**Detection:** After any failed migration, verify the state of
every table it created (DESCRIBE each one). Do not assume that a
successful re-run leaves everything correct.

**Prevention:** Put each CREATE TABLE in its own migration file,
or put risky statements (FKs, ALTERs) at the end. If a migration
does partially apply, add a follow-up migration that ALTERs the
wrong tables rather than editing the original.

**Where we hit it:** migration 0205 left `commerce_customers`
with `approved_by BIGINT UNSIGNED`. Fixed by migrations 0206 and
0207 (ALTER the type, then ADD the FK).

### Pattern 19 - Pre-flight inventory before touching closed code

**Problem:** Repeatedly, edits to "closed" deliverables broke
neighbouring features because we did not check what depended on
them.

**Rule:** Before any message that modifies existing files, produce
a pre-flight table:

- Files touched
- Reference audit (grep for usages)
- Impact assessment (what could break)
- Rollback plan (how to undo)

Only then write code. This is enforced for any change to:

- `CommerceServiceProvider.php`
- Anything under `app/Core/`
- Anything under `app/Platform/`
- Any file that other modules import

**Where we hit it:** Every time we patched `App.php`,
`NotificationRuleService.php`, `CommerceServiceProvider.php`,
`_tab_inventory.php`, `customer_groups/edit.php`, and others.

### Pattern 20 - One file per message during critical work

**Problem:** A message that created two model files and one
repository file was silently lost — the heredocs never ran.
Hours of debugging followed. The root cause was heredoc batching
limits: messages containing too many large heredocs can lose
parts silently.

**Rule:** When creating or modifying files that a closed
deliverable depends on, split the work into **one file per
message**. Each message: write, verify with `ls` + `head` +
`php -l`, report. Next message only after confirmation.

**Detection:** After every file write, `ls -la` the file and
`php -l` it. If the file doesn't exist or lint fails, stop and
re-send that single file. Do not proceed to the next file.

**Cost:** More round trips. **Benefit:** No more silent failures.

**Where we hit it:** M2 of Stage 007B (Customer model +
CustomerRepository + provider binding in one message). The whole
message was lost.

### Pattern 21 - StoreContext is required for any repository read/write

**Problem:** A smoke test called `CustomerRepository::countPending()`
from the CLI and got "No store has been resolved for this request."

**Rule:** `CommerceRepository::storeId()` throws
`StoreContextMissingException` if no store is resolved. In web
requests, `StoreResolutionMiddleware` populates it before any
controller runs. In CLI scripts (cron jobs, seeders, smoke
tests), you must set it manually:

    $container->get(StoreContext::class)->set($storeId, $storeSlug, 'cli');

Otherwise any repository query throws.

**Why this is correct:** It prevents accidental cross-store
queries. A repository method that reads the DB without a store
context has no way to know which store's data to return — it
would either fail with a SQL error or, worse, return data from
the wrong store. The exception is the defense-in-depth layer
that guarantees tenant isolation.

**Where we hit it:** M2-4 smoke test of Stage 007B.

### Pattern 22 - Prefix-match active state breaks on strict-prefix routes

**Problem:** The sidebar's `$isActive()` closure in
`resources/views/partials/sidebar.php` uses prefix matching to
highlight the current nav item. This works for most items:

- `/commerce/ca-admin/products` highlights Products
- `/commerce/ca-admin/products/123` also highlights Products (prefix)
- `/commerce/ca-admin/categories` highlights Categories

But `/commerce/ca-admin` (Dashboard) is a **strict prefix** of
every other admin route. So on `/commerce/ca-admin/products`,
the closure returned `is-active` for BOTH Products AND Dashboard.
On `/commerce/ca-admin/customer-approvals`, it returned
`is-active` for BOTH Customer Approvals AND Dashboard.

The extra active class was latent from Stage 005 — the browser
silently applied `is-active` to two elements. It became visible
in Stage 007B M3-6i when we added scroll-to-active: the sidebar
scroll searched for `.ra-nav-item.is-active` and found Dashboard
first (it renders before every other group), so it scrolled to
top. The active page (Customer Approvals) stayed off-screen.

**Detection:** Run this in the browser console on any admin page:

    document.querySelectorAll('.ra-nav-item.is-active').length

Expected: **1** (or 0 for pages not represented in the nav).

If it returns 2 or more, a strict-prefix route is matching. Look
at which hrefs are active:

    document.querySelectorAll('.ra-nav-item.is-active').forEach(function(el) {
        console.log(el.getAttribute('href'), el.textContent.trim());
    });

**Rule:** Any active-state check that uses prefix matching must
special-case routes that are strict prefixes of their siblings.
In this codebase, `/commerce/ca-admin` is the only such route
today. The exact-match list lives explicitly in `$isActive`:

    if ($href === '/commerce/ca-admin') {
        return $cur === $href ? 'is-active' : '';
    }

If a future route is added that becomes a strict prefix of its
siblings, it needs the same treatment. The list stays explicit
with comments — not inferred by a clever rule that misses edge
cases.

**Why not a general "always require exact match" rule:** Prefix
matching is correct for detail routes. `/products/123/edit` should
highlight the Products list item. Requiring exact match everywhere
would break that. The special case is scoped to the one route that
needs it.

**Prevention checklist when adding a nav item:**

1. Ask: is this href a strict prefix of any sibling item's href?
2. If yes, add an exact-match case to `$isActive`.
3. After adding, verify with the browser console query above.
4. Any page showing more than one active item is a bug.

**Where we hit it:** Stage 007B M3-6i. The symptom was "sidebar
scrolls to top on reload, active item not visible". The root cause
was "two items marked active, scroll picks the first one".

**Related:**
- Baseline item 21 in `docs/baseline/02-assumptions.md`
- Sidebar rewrite in Stage 007B M3-6i

### Pattern 23 - Cascade check before every patch

**Problem:** patches that fix the target file but break a caller, a
sibling, or a downstream consumer. The bug fixed, another bug
introduced. Silent because both files pass lint.

**Rule:** before writing any patch that modifies an existing file:

1. Read the entire file, not just the target lines.
2. List what the file depends on (imports, parent classes,
   injected services).
3. Grep for every reference to the file's class name, method
   names, and public properties. This is the "who calls me" list.
4. For each change, state the cascade risk explicitly. Not "no
   impact" - name what could be affected and confirm it is not.
5. If a downstream file needs updating in the same patch,
   include it. If the impact is wider than expected, stop and
   discuss before patching.

**Exception class to watch for:** return-shape annotations.
Adding `@return array<string,mixed>` to a method whose callers
assume a narrower shape does not break runtime but breaks
PHPStan at the caller. Every return-type annotation on a shared
method must be checked against callers.

**Cost:** one grep plus one moment of thought.

**Benefit:** no cross-file surprises.

**Where we hit it:** Pre-emptively adopted. Prior incidents that
would have been caught by this rule:

- Sidebar scroll fix (007B M3-6i) - the `$isActive` change
  affected the scroll-to-active logic in the same file, which
  was not checked until the symptom appeared.
- Reject button quote collision (007B) - the onclick pattern
  worked in products/index.php but was not checked against the
  approvals view before writing.
- Route loader change (008B) - `App::loadRoutes()` needed the
  new route file added to its array. Missed on the first pass,
  caught by a 404.

**See also:**
- Pattern 19 in this document (pre-flight inventory before
  touching closed code). Pattern 23 extends it from "touching
  closed code" to "every patch".

---

### Pattern 24 - PHPStan annotation conventions

**Context:** during the level 6-8 PHPStan pass (2026-09-27),
four rules emerged from specific misses. Each cost a
round-trip. They are locked now.

**The conventions:**

    Route params:           array<string,string>
    PDO-bound params:       array<int|string,mixed>
    Controller render data: array<string,mixed>
    Filter builders:        array{0:string,1:array<int,mixed>}
    Model fromRow rows:     array<string,mixed>
    Logger context:         array<string,mixed>
    Constraints / options:  array<string,mixed>

**Why `array<int|string,mixed>` for PDO:** PDO execute()
accepts positional and named bindings. Annotating a method
that feeds PDO as `array<int,mixed>` breaks every caller
that passes named bindings.

**Why methods that take AND return arrays need both
annotations in one patch:** PHPStan reports the param error
first and suppresses the return error until the param is
fixed. Patching only the param produces a second
round-trip.

**Anchoring rule:** existence checks must be anchored to
the specific method signature, not the file. Use a two-line
sequence that uniquely identifies the target method.

**See also:** Pattern 25 documents the pass itself.

---

### Pattern 25 - PHPStan level 8 is the baseline

**Context:** On 2026-09-27 the codebase moved from PHPStan
level 5 to level 8 in three passes. The progression:

  Level 6: 242 annotations, zero bugs found (pure
           documentation).
  Level 7: 23 fixes, five real bugs found (false-return
           guards on PDO, session_name, glob, file, fopen).
  Level 8: 1 fix, one real bug found (post-commit null row).

Six runtime bugs caught total. Level 6 was cost. Levels 7 and
8 paid for the whole effort.

**Rule:** the project is frozen at level 8. Every future commit
must pass:

    php vendor/bin/phpstan analyse --memory-limit=512M

Zero errors, zero baseline, zero stale ignores. Any error at
level 8 in new code is a real finding, not a documentation gap.
The annotation pass is done; the discipline is now write-time.

**Annotation conventions (see Pattern 24):**

    Route params:           array<string,string>
    PDO-bound params:       array<int|string,mixed>
    Controller render data: array<string,mixed>
    Filter builders:        array{0:string,1:array<int,mixed>}
    Model fromRow rows:     array<string,mixed>
    Logger context:         array<string,mixed>
    Constraints / options:  array<string,mixed>

**Why not levels 9-10:** levels 9-10 require exhaustive
handling of `mixed` at every boundary. Every `json_decode`,
every `$_POST` access, every database row needs narrowing.
On a 132-file codebase that is thousands of annotation points.
The DEV community consensus is that levels 9-10 produce
diminishing returns outside libraries and highly stable code.
The right call is level 8.

**What levels 7 and 8 caught that levels 0-6 missed:**

  - PDO::query() / prepare() can return false. Two call
    sites in Migrator assumed PDOStatement. If the query
    failed, code called fetchAll() on false and fataled.
  - session_name() returns string|false. destroy() passed
    it to setcookie() unguarded.
  - glob() and file() return list<string>|false. Iterating
    or mapping on false would fatal.
  - fopen() returns resource|false. Four subsequent stream
    functions assumed resource.
  - json_encode() returns string|false. Three call sites
    passed it to string-typed receivers without guard.
  - A repository re-fetched a row after commit and passed
    the possibly-null result to a strictly-typed hydrator.

Each of these would only manifest when the failure branch
was actually hit. Levels 5 and 6 do not check them. Level 7
and 8 do.

**See also:** docs/DEBUGGING.md for the annotation rules and
docs/baseline for the codebase conventions.

---

### Pattern 26 - Shared partial guards must reflect the partial's own requirement, not one caller's shape

**Problem:** a shared partial (or a shared wrapper around it)
guards on a variable that only some callers provide. The
guard silently no-ops on the callers that do not set it. The
feature appears to work on the caller it was developed
against, and silently fails on every other caller.

The failure is invisible until someone uses the second
caller. Then the symptom shows up far from the cause: a
button does nothing, a form does not submit, a modal never
opens.

**Rule:** when writing a guard at the top of a shared
partial, ask three questions:

1. What does this partial actually need to render?
2. Is that need expressed by a variable every caller will
   set, or by a proxy for "which page am I on?"
3. If it is a proxy, refactor. Guards are about the
   partial's requirements, not its callers' context.

**The tell:** the guard condition references a variable
(`$mode`, `$context`, `$page`) that is not naturally part of
the partial's data. If a caller has to be told to pass a
value they do not otherwise use, the guard is wrong.

**Detection:** after adding a shared partial with a guard,
grep for every view_partial() call to it. For each caller,
confirm the guard's variable is set and has the expected
value in that caller's context. If any caller lacks it,
the guard is a latent bug.

**Where we hit it:** Stage 008G audit, `_action-forms.php`.

The partial guarded on `$mode !== 'edit'`. Its two
callers: `_sidebar.php` (via `edit.php`, where `$mode` was
`'edit'`) and `variants.php` (which has no `$mode` - it
defaulted to `'create'`). The guard fired on
`variants.php` and the four hidden action forms never
rendered. The Publish button called
`document.getElementById('ra-action-publish').submit()`
on a null element and threw
`Cannot read properties of null (reading 'submit')`.

The fix: loosened the guard to `$product === null` only.
The partial's real requirement is a product to act on.
`$mode` was a proxy for "is this an edit page?" that broke
on the second caller.

**Related patterns:**

  - Pattern 3 in this document (Nested Forms) - same
    family: markup context changes behaviour.
  - Pattern 27 in this document (Nested forms: the first
    inner form is silently discarded) - a specific
    manifestation of the same class.
  - Pattern 23 in this document (Cascade check before
    every patch) - the discipline that catches this class
    before it ships.

---

### Pattern 27 - Nested forms: the first inner form is silently discarded

**Problem:** a `<form>` element rendered inside another
`<form>` element. HTML disallows this. The browser does not
error. It does not warn. It silently discards the inner
`<form>` opening tag and closes the outer form on the inner
`</form>`.

If there are multiple nested forms, only the first one is
sacrificed. The rest survive, because by the time they
render, the outer form has already been closed.

**The tell:** the first of a series of sibling forms is
missing from the DOM. `document.getElementById('first-form')`
returns null. The second, third, fourth forms all exist.
Console shows null only for the first.

If the buttons that target the first form are still wired
up (they usually are), clicking them throws
`Cannot read properties of null (reading 'submit')`.

**Why it is the first one:** the browser is doing
HTML5-compliant form recovery. On encountering a `<form>`
inside an open `<form>`, the parser closes the outer form
implicitly. Everything after the first inner `<form>` tag
is now top-level HTML. The second, third, and later forms
are accepted normally.

**Detection:** if a set of forms is missing exactly one -
always the first - suspect this pattern. Check the DOM
structure in DevTools.

    var outer = document.getElementById('your-outer-form');
    var inner = document.getElementById('your-inner-form');
    if (outer && inner) {
        console.log('inner is inside outer?', outer.contains(inner));
    }

If `contains` returns true, or inner is null while siblings
survive, this pattern is active.

**The fix:** move the inner form (or forms) out of the outer
form. They become siblings. Buttons that reference them by
ID still work - `document.getElementById()` does not care
about nesting.

The alternative, HTML5 form attribute (`form="form-id"` on
a button outside a form), also works but requires editing
every button. Moving the forms is usually simpler.

**Where we hit it:** Stage 008G audit, `edit.php` plus
`_sidebar.php`.

The four hidden action forms (`ra-action-publish`,
`ra-action-unpublish`, `ra-action-archive`,
`ra-action-restore`) were rendered from `_sidebar.php`,
which is included inside `edit.php`'s
`<form id="ra-product-form">`. They were nested forms.
`ra-action-publish` was silently discarded. The Publish
button called `.submit()` on a null element.

The fix: removed the action-forms include from
`_sidebar.php`. Added it to `edit.php` after `</form>`, as
a sibling of the outer form. All four forms render as
top-level elements.

**Relationship to Pattern 3 in this document:** Pattern 3
covers the case where a developer deliberately nested a
form and expected it to work. Pattern 27 covers the case
where a shared partial is included from multiple contexts
and accidentally ends up nested in one of them. Same HTML
rule, different discovery path.

---

### Pattern 28 - view_partial() does not inherit outer scope

**Problem:** a partial rendered via view_partial() receives
only the array passed to it. It does not inherit the scope
of the calling view. A variable that exists in the outer
view - even one injected by StorefrontController::render()
into $data - is invisible inside a partial unless it is
explicitly passed.

The failure mode is silent. A partial that reads
`$someVar ?? default` returns the default even when the
outer view has a real value. The feature appears to work
but its per-request state is always the default.

**The tell:** a partial's conditional state renders
correctly in the outer view but not inside the partial. Or
a value that changes per-request (customer id, cart count,
wishlist state) is always empty inside a partial while
being correct outside it.

**Detection:** when a partial reads a variable that is not
in its explicit parameter array, grep the partial for the
variable name and grep every caller for whether it passes
that variable. Any caller that does not pass it will see
the default.

    # What variables does the partial expect?
    grep -n '\$[a-zA-Z_]* ?? ' <partial.php>

    # For each, does every caller pass it?
    grep -rn "view_partial.*<partial-name>" resources/views/

**Rule:** cross-cutting view data that any partial might
need must come from one of three sources:

  1. A global helper (csrf_token(), wishlist_product_ids(),
     config(), etc.). Preferred for data that is cheap to
     resolve once per request and needed in many places.
     The helper caches the result in a static.

  2. The layout rendering the partial explicitly passes it.
     Fine when the outer view is the only caller.

  3. A shared() call on the View class before render.
     Available in this codebase but not used yet.

Never rely on the outer view's scope reaching a partial.
It does not.

**Where we hit it:** Stage 008G.1. The product card
partial read `$wishlistProductIds ?? []`. The variable was
injected by StorefrontController::render() into the outer
view's $data. But view_partial() only extracts the array
passed to it, so the partial saw `[]` on every page. Every
wishlisted product rendered with an empty heart.

The fix was a new helper `wishlist_product_ids()` in
app/Helpers/functions.php, mirroring the csrf_token()
pattern. Static-cached, container-resolved, defensive.
Called directly from the partial. No call sites changed.

**Related:** Pattern 26 (shared partial guards reflect the
partial's own requirement). Both patterns are about
partials that need data they do not naturally have. Pattern
26 is about a guard that fails to fire; Pattern 28 is
about a variable that fails to reach.

---

### Pattern 29 - AJAX on pages with no forms must read CSRF from the layout meta tag

**Problem:** a progressive-enhancement JS script reads the
CSRF token from the first `<input name="_csrf">` on the
page. This works on pages that render a form (product
detail, cart, checkout) but fails on pages that do not
(the catalog grid, the home page, the search results page).

The failure is a 403 "Session expired" response. The JS
swallows it (or logs it). The button appears to do nothing.

**The tell:** clicking an AJAX-only control works on some
pages and silently fails on others. The pages where it
fails are the ones with no forms.

**Detection:**

    # In the browser, on a page where the AJAX control fails:
    document.querySelector('input[name="_csrf"]')
    // If this returns null, the JS is sending an empty token.

**Rule:** the storefront layout renders
`<meta name="csrf-token">` in the <head> on every page.
AJAX scripts MUST read this meta tag, not an input field.
Fall back to the input only if the meta tag is absent
(older pages, other codebases).

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && meta.content) return meta.content;
        var input = document.querySelector('input[name="_csrf"]');
        if (input && input.value) return input.value;
        return '';
    }

The meta tag is the canonical source. Inputs are
form-scoped and only exist where forms exist. The meta tag
is layout-scoped and exists everywhere.

**Why the input-only pattern survived so long:** the cart's
add-to-cart AJAX runs on the product detail page, which
has a form. The input was always there. It only broke when
the wishlist introduced an AJAX endpoint on the catalog
page, which has no form. Two features, two call sites, one
worked and one did not.

**Where we hit it:** Stage 008G.1. The heart button on
/products returned 403. Fixed by rewriting
wishlist.js's csrfToken() to read the meta tag first.

**Prevention checklist for any new AJAX endpoint:**

1. Test it on the page with the least form markup that
   will host the control.
2. If that page is not the product detail page, verify
   the CSRF source is the meta tag.
3. If the script reads the input, migrate to the meta
   tag.

---

### Pattern 30 - view_partial() swallows PHP fatals and returns an empty string

**Problem:** a PHP fatal or undefined-variable error inside a
partial rendered via view_partial() does not crash the page.
It is caught by the wrapper, logged, and the wrapper returns
an empty string in place of the partial. The page renders
with a gap where the partial should be.

This is dangerous because the failure is silent to the user
and only surfaces if you are watching the log or notice a
missing UI element.

**The tell:** an entire block of a page is missing. Not one
element gone, not a style broken - a whole section, header,
or panel absent. The rest of the page renders correctly.

**Detection:**

    # The log watcher shows it directly:
    tail -f storage/logs/app.log | grep view_partial

    # Or if you are not watching:
    grep 'view_partial' storage/logs/app.log | tail -20

If nothing is in the log but a partial is missing, the
partial itself did not throw - something upstream (a
controller) returned early or the view call was not reached.

**The trigger case:** a patch removes the block at the top of
a partial that resolves variables. The rest of the partial
still references those variables. Every render throws
"Undefined variable X" on the first reference, is caught
by the wrapper, and returns "".

**Rule:** when patching a partial, always verify the
top-of-file variable resolution block is intact after the
patch. If a patch removes code from a partial, immediately
check that the partial still references only variables that
are defined somewhere.

Concrete check after any patch to a partial:

    # What variables does the partial reference?
    grep -o '\$[a-zA-Z_][a-zA-Z0-9_]*' <partial.php> | sort -u

    # Which are defined at the top of the partial?
    head -50 <partial.php> | grep -o '\$[a-zA-Z_][a-zA-Z0-9_]*' | sort -u

    # Any variable referenced but not defined at the top is
    # either a caller-provided variable (check callers) or a
    # bug.

**Why we hit it:** Stage 008G.3, header partial. An earlier
patch that restructured the header action cluster removed
the block that resolved `$__customer` and `$__unread`. The
bell conditional still referenced `$__unread`. Every
storefront page rendered without a header - the logo, the
search, the nav, the action cluster, all gone.

The log line:

    ERROR: view_partial(storefront/partials/header) failed:
    Undefined variable $__unread

The fix was to restore the resolution block at the top of the
partial.

**See also:**

  - Pattern 26 (shared partial guards reflect the partial\'s
    own requirement) - a related failure mode where a guard
    silently no-ops on a partial.
  - Pattern 28 (view_partial does not inherit outer scope) -
    the other direction: variables that should exist in the
    partial but do not because they were never passed.
  - Pattern 30 is the third leg of the same stool: a partial
    that assumes a variable exists but the variable was
    removed by a patch.

---

### Pattern 31 - require'd view files share scope; do not shadow caller variables

**Problem:** A view file that is `require`d - not
`view_partial`'d - runs in the same PHP variable scope as
its caller and every sibling `require`d before and after
it. Anything the file assigns leaks forward. A loop
variable named `$val` in one tab becomes an array in the
next tab's scope. If the next tab expected `$val` to be
a closure, the call fails with a confusing error at a
location that has nothing to do with the actual bug.

**The tell:** a fatal error whose reported file is not
the file you changed. The error reports the call site;
the call site is a victim, not the cause. The last
`require` in the chain that ran before the crash is the
suspect.

In this codebase specifically: an error that says
"Array callback must have exactly two elements" or
"Call to undefined method" or "Call to a member
function on array" in a tab file, when the tab file
itself has not been touched.

**The rule:**

1. A `require`-ed view shares scope. A `view_partial()`
   call does not. Know which one you are writing.
2. Before adding a variable to a `require`-ed view,
   list the variables the caller has already defined.
   In this codebase that list includes at minimum:
   `$val`, `$valBool`, `$err`, `$product`, `$activeTab`,
   `$mode`, `$old`, `$errors`, `$categories`, `$brands`,
   `$categoryIds`, `$tagIds`, `$inventory`, `$attributes`.
   Any new local variable whose name collides with this
   list is a latent bug.
3. Prefer loop variables that are explicitly not in
   that list. `$axisValue`, `$tileIndex`, `$slotEntry` -
   names that describe the iteration, not the value.
4. If a `require`-ed view needs to compute a temporary
   variable, wrap the computation in an IIFE or a
   function. `(function () { ... })()` runs in its own
   scope. No leakage.

**Where we hit it:** Stage 008G.4a.2, File 24, session
2026-09-30. The variant-images card in `_tab_images.php`
iterated axis values with `foreach (... as $val)`.
`$val` is a closure defined at the top of `edit.php`
(line 21) and read by every other tab. The moment the
loop ran, `$val` became an array. The next required file
`_tab_pricing.php` called `$val('taxStatus', 'taxable')`
on the array and died. The stack trace pointed at
`_tab_pricing.php:5` - a file nobody had touched. The
debug cycle took two hours because the reported location
was correct but not the cause.

**Related:**

  - Pattern 28 (`view_partial` does not inherit outer
    scope). Pattern 31 is the reverse: a `require`-ed
    view does inherit outer scope, and it can pollute
    that scope.
  - Pattern 26 (shared partial guards reflect the
    partial's own requirement). Pattern 26 is about a
    partial failing to fire; Pattern 31 is about a
    require'd file firing and doing damage.

**Prevention checklist for adding a `require`-ed view
file:**

  1. Read the caller. List every variable it defines
     before the require line.
  2. In your new file, do not assign to any of those
     names.
  3. Do not use loop variables that look like shortcuts
     (`$val`, `$item`, `$v`). Those names collide with
     the codebase's conventions.
  4. After writing the file, view the rendered page. A
     sibling tab suddenly failing is the symptom.

---

### Pattern 32 - contract drift between producer and consumer shapes

**Problem:** Two producers return data representing the
same concept but with different key names. The consumer
was written against one shape and later repointed at
the other. Every read of a nonexistent key is guarded
by `?? default`, so the read silently returns the
default. No error. No exception. No log entry. The
consumer produces empty output and the operator sees
a feature that "does not work" with no failure signal.

**The tell:** a conditional that should be true is
false. A loop that should iterate is empty. A card
that should render does not. A form field that should
show a value is blank. All with no error in the log
and no exception in the browser console.

The specific tell for this codebase: a `?? 0`,
`?? ''`, or `?? null` in a view or controller that
guards a read of an array key which may or may not
exist in the shape being iterated. Grep for `?? ` in
the consumer. Every match is a candidate.

**The rule:**

1. When repointing a consumer at a different producer,
   read the producer's exact return shape. Print it
   from a CLI script (`print_r`, `var_export`, or
   `json_encode` with `JSON_PRETTY_PRINT`). Read the
   keys with your eyes.
2. Grep the consumer for reads of every key. A read
   guarded by `?? default` is not safe - it is
   silently ambiguous. A missing key produces the
   default and no diagnostics.
3. Prefer explicit `array_key_exists` checks when the
   value matters. `$row['x'] ?? 0` hides a mistake;
   `array_key_exists('x', $row) ? (int) $row['x'] : 0`
   is longer but honest, and PHPStan can flag a
   missing key at analysis time if the shape is
   annotated.
4. When two producers exist for the same concept,
   document the shape of each at the top of the method
   that produces it. `swatchAxesFor()` returns
   `attribute_value_id`; `categoryVariantAxes()`
   returns `id`. Both are "the value's id". Naming
   them consistently across the codebase would be
   ideal, but where the two already exist, the docblock
   is the second-best defense.

**Where we hit it:** Stage 008G.4a.2, File 24, session
2026-09-30. The variant-images card in `_tab_images.php`
read `$axisValue['id']`. But `showEdit()` had been
repointed (File 24a) at `swatchAxesFor()`, which
returns `attribute_value_id` on each value. The `?? 0`
guard turned the miss into a zero, the
`if ($valueId <= 0) continue;` skipped every iteration,
and `$colorValues` stayed empty. The card did not
render. No error, no log line, no console warning.

**Related:**

  - Pattern 15 (snake_case vs camelCase in views). Same
    family: same concept, different key. Pattern 15 was
    fixed by a shared closure that translates between
    the two names. Pattern 32 is the shape-drift
    analogue: two producer methods, two key sets.
  - Pattern 26 (guards that fire on the wrong caller).
    Pattern 26 is about a guard that silently skips a
    whole partial. Pattern 32 is about individual reads
    that silently fall back on defaults.

**Prevention checklist when repointing a consumer:**

  1. Print the new producer's exact output shape.
  2. Diff the keys against every read in the consumer.
  3. Any read guarded by `?? default` is a candidate
     for silent failure. Verify each by hand.
  4. After the change, log the shape at runtime (a
     temporary `error_log` with the count of the built
     structure). If the count is 0 where it should be
     N, the drift is active.
  5. Remove the temporary log once the shape is stable.

---

**Second manifestation: a WHERE clause encodes an invariant
the schema never declared.**

When a repository method returns a filtered slice of a table
based on a nullable column the schema does not treat as
exclusive, the filter is a hidden contract. Every consumer
of that method inherits the filter whether it knows it or
not. If one consumer needs the full set and another needs
the slice, the filtered method is the wrong shape to expose.

The tell: a record renders correctly in one view (hero,
primary image, summary) but not in another (gallery,
thumbnail strip, detail list) - with no error, no
exception, no log entry. The two views read the same table
through different repository methods, and only one method's
WHERE clause is excluding the row. The data is present. The
filter is hiding it.

This is the same underlying failure as the original
manifestation above: a consumer assumes a contract the
producer does not enforce. In the original case the contract
was a key shape. Here it is a row shape - the producer's
WHERE clause silently declares "these rows do not belong to
this concept," but the schema never said so.

**The rule:**

1. When a repository method returns a filtered slice of a
   table, name it for the consumer's intent, not for the
   rows it returns. `listForProduct()` sounds like "every
   image for this product." It actually returns "images
   that are not assigned to any attribute value." Those
   are different things and the name lies about which
   one is returned.
2. Before pointing a new consumer at an existing method,
   read the method's WHERE clause. Not the docblock. The
   docblock may describe intent that the WHERE clause
   does not match, or vice versa.
3. If two consumers need different slices, expose two
   methods. Do not widen the existing one and do not
   narrow the new one. One method, one intent.
4. When the docblock and the WHERE clause disagree, the
   WHERE clause wins at runtime. Fix whichever is wrong
   in the same commit that surfaces the bug.

**Where we hit it:** 2026-10-10. Two bugs in one session,
same class.

  `ProductImageRepository::listForProduct()` filtered on
  `attribute_value_id IS NULL`. Its docblock called the
  result "gallery images for a product." The storefront
  gallery reader inherited the filter. A product whose
  images were all assigned to color values (SHJO) had
  every row excluded, so the thumbnail strip rendered
  empty. The hero rendered anyway because it used a
  different resolver. Two views of the same data, one
  worked, one did not, no error anywhere.

  The class-level docblock declared three exclusive image
  shapes: "gallery image" (attribute null), "swatch preview"
  (attribute set), "variant override" (variant set). The
  schema carries the three as independent nullable columns
  on the same row. A single row can be the primary image
  AND the color red's swatch preview. Nothing in the schema
  prevents it and nothing should. The docblock described a
  mental model the schema never encoded, and the WHERE
  clause enforced a model the schema never required.

  Fixed by adding `listAllForProduct()` (unfiltered) for
  the storefront and keeping `listForProduct()` (filtered)
  for the admin Images tab. Commit `54034c3`.

**Related:**

  - The original Pattern 32 manifestation (shape drift in
    keys). Same debugging motion: read the consumer, read
    the producer, find the mismatch.
  - Pattern 15 (snake_case vs camelCase in views). Also
    the same motion, at the view layer.
  - Pattern 23 (cascade check before every patch). Every
    consumer of a filtered method is a caller whose
    assumption may be wrong. The cascade grep is how the
    full consumer set was enumerated before the 2026-10-10
    fix landed.

**Prevention checklist when a repository method returns a
filtered slice:**

  1. Read the WHERE clause. Confirm every column in it is
     doing what the method name suggests.
  2. Grep for every caller of the method. For each caller,
     decide: does this consumer want the full set or the
     slice? If the answer differs between callers, the
     method is being asked to serve two intents.
  3. Before pointing a new consumer at the method, read
     its WHERE clause and its docblock side by side. If
     they disagree, they disagree about what the method
     promises. Fix the discrepancy before adding the
     caller.
  4. When a filter encodes "these rows belong to this
     concept" and the schema does not enforce the same
     distinction, the filter is a bug waiting to surface.
     The correct fix is usually a second method named
     for the other intent.

---

**Third manifestation: a formula written for one regime
applied to another.**

When a computation is written against one configuration
of the data, and that configuration is one of several
the schema allows, the formula is wrong for every other
configuration. The bug is silent until the second
configuration is exercised. The first one works. The
second one produces a number that is internally
consistent and externally wrong.

The tell: two views of the same data disagree. One page
shows a total. Another page shows a different total for
the same cart. Both come from the same computation, but
each supplies a different input to a formula that
assumed only one kind of input. The difference is not a
bug in either page. It is that the formula was written
for the configuration one page happens to exercise.

For the grand total in particular, the tell is a
number that matches the wrong formula. If the schema
says the price is inclusive and the displayed total is
the subtotal plus the tax, the formula is exclusive.
If the schema says the price is exclusive and the
displayed total is the subtotal, the formula is
inclusive. The formula cannot tell which one it should
be until someone reads the configuration.

**The rule:**

1. When a formula has a branch that depends on
   configuration (is_inclusive, is_shipping_taxable,
   country-specific rules, currency precision), the
   branch must be read by the formula, not assumed.
   A formula that produces the right answer for the
   current store is not correct. It is correct for the
   current store and wrong for every other.
2. When the computation service already knows which
   branch applies (per-class flags, per-store settings,
   per-country rates), it must return that fact to its
   caller. A service that strips configuration from its
   return shape forces every caller to re-derive what
   the service already computed. That re-derivation is
   where the wrong assumption lives.
3. When two consumers of the same computation disagree,
   the computation is not necessarily wrong. The inputs
   are. Read the inputs, not the outputs.
4. When the correct answer requires knowing whether
   the tax is included in the price, the grand total
   formula branches on that flag. There is no single
   formula that works for both regimes.

**Where we hit it:** 2026-10-10/11. The order
pricing engine's grand total was written for exclusive
pricing. German stores (PAngV § 4, inclusive) were
being overcharged 19% on every order that went through
to payment. The cart page did not expose the bug
because the cart passes an empty country code, so no
tax rate resolved, so no tax was added on top. The
checkout payment step exposed it because the address
was known by then and the German rate resolved.

Same service, same call, same cart, two different
totals. Cart: 8,100. Payment: 9,393.28. The cart's
correct number was correct by accident — the empty
country code neutralised the inclusive branch before
it could double-count.

Fixed by having TaxService::calculateTax() return
inclusive_total and exclusive_total alongside the
overall total, and by changing the grand total
formula to add only the exclusive portion on top of
the taxable base. Commit 00d6e81.

Followed by a companion bug: the per-product tax
rate override (migration 0326) inherited is_inclusive
from the class's rate for the destination. When the
class had no rate for the destination, the flag fell
back to false, which forced exclusive pricing even on
an inclusive store. Migration 0327 added an explicit
tax_rate_override_inclusive three-state column so
the merchant can choose. Commit 464ed45.

**Related:**

  - The original Pattern 32 manifestation (key shape
    drift). Same debugging motion: read the consumer,
    read the producer, find the mismatch. Here the
    "producer" is the tax configuration and the
    "consumer" is the grand total formula.
  - The second manifestation (filter enforces a
    nonexistent invariant). Also configuration that
    the reader assumes rather than reads.
  - Pattern 23 (cascade check before every patch).
    Every consumer of a shared computation is a caller
    whose assumption may be wrong.

**Prevention checklist when writing or changing a
computation that has configuration-dependent branches:**

  1. List every configuration value the formula
     depends on (inclusive/exclusive, shipping-taxable,
     rounding rules, currency precision).
  2. For each value, find the source of truth. Grep
     for the flag or setting in the schema and in the
     service that owns it.
  3. Confirm the formula reads that source, not a
     default that happens to match the current store.
  4. Test the formula under each configuration the
     schema allows, even if the current store only
     uses one. If the schema allows inclusive and
     exclusive, test both, and test the mixed case.
  5. When the computation service returns a result,
     check whether the caller can determine the
     configuration from the return shape alone. If
     not, the return shape is missing a fact the
     caller needs.

---

## 19. Documents to Read First When Joining

If you are new to this project and need to get up to speed
quickly, read in this order:

1. `docs/ARCHITECTURE.md` - the layered design contract
2. `docs/NAMING-CONVENTION.md` - every name in the codebase
3. `docs/ROADMAP.md` - the build sequence
4. `docs/RESPONSIVENESS.md` - mandatory responsive directive
5. This document (REMEMBER.md) - current state and patterns
6. `docs/stages/*.md` - what shipped and why
7. `docs/sessions/*.md` - what happened each session, in order
8. `docs/STATUS.md` - current state at a glance

Then to run locally:

    cd C:\projects\commerceaid
    composer install
    cp config/local.php.example config/local.php
    cp config/cli.php.example config/cli.php
    (edit both with your DB credentials)
    php bin/migrate.php
    php bin/seed.php
    http://localhost:8081

Login with admin@anayaglobal.test / ChangeMe!2026.

---

End of REMEMBER.md patterns section.

---

### Pattern 33 - Forgot to bind a class into the container

**Problem:** The container requires an explicit binding for every
class resolved through `$container->get(ClassName::class)`. When
the binding is missing, the first request that touches the class
throws:

    Container: nothing bound for 'Foo\Bar'.

That is the only symptom. There is no earlier warning. The
syntax is valid (`php -l` passes). The class exists on disk
(PHPStan resolves it). The call site references `Foo\Bar::class`,
which is a valid constant. Nothing in the build chain sees the
string `'Foo\Bar'` and asks whether the container can resolve it.

The class of bug hit the project four times in two months:

    MediaRepository             (008G.4a)
    ProductReviewRepository     (008G.5)
    ProductReviewService        (008G.5)
    ProductImageRepository      (008G.4a.2)

Each one threw at runtime. Each was diagnosed from a browser
test. Each cost 20-60 minutes.

**The tell:** a 500 with the exact message above, on a request
that reaches a class which was recently added or recently first
called. The class's file is correct. The route that reaches it is
correct. The problem is that no provider's `register()` method
declares the binding.

In practice the symptom shows up as soon as the new class is
first resolved. A new repository that is only referenced from a
controller will fail on the first HTTP request to that
controller. A new service that is called from inside another
service will fail on the first request that reaches the outer
service.

**The rule (as of 008J, commit f1fbd72):**

The container auto-constructs classes with no binding. When a
class has a constructor whose parameters are all class-typed (and
resolvable through `get()`) or have defaults, the container
builds it on first request. No binding needed.

This means:

1. A repository with constructor `(Database $db, StoreContext
   $ctx)` no longer needs an explicit binding. It resolves on
   `get(FooRepository::class)` without further setup.
2. A repository with a captured primitive still needs an
   explicit binding. Example: `FileService` takes `$basePath`
   as a string argument. The container cannot invent that
   value, so `FileService::class` is still bound explicitly.
3. A service that needs a method called after construction
   still needs an explicit binding. Example:
   `SystemSettingService` binds through a closure that calls
   `setNotificationService()` after `new`. Auto-construction
   would produce an instance missing that wire-up.
4. A class that depends on an interface still needs the
   interface bound. The container can auto-construct a
   concrete class, but if a constructor takes an interface
   parameter, `get()` on the interface reaches the throw
   branch unless a binding provides the concrete
   implementation.

**When adding a new repository or service:**

  - If the constructor takes only class-typed dependencies and
    no post-construction setup, no binding is required as of
    008J.
  - If the constructor takes a primitive (string, int, array)
    without a default, add an explicit binding in the owning
    provider.
  - If the class needs a method called after `new`, add an
    explicit binding.
  - If you are unsure, the fastest check is a CLI smoke test:
    boot the app, `get(Foo::class)`, print the class name. If
    it resolves, no binding is needed. If it throws, the
    message names the missing parameter.

**Where we hit it, in order:**

  1. `MediaRepository` (008G.4a file 10). The repository and
     `MediaProcessor` were written and referenced from
     `MediaController::upload()`. Neither was registered.
     First upload attempt threw.
  2. `ProductReviewRepository` and `ProductReviewService`
     (008G.5). Both were referenced from `ProductController::show()`
     and `ProductReviewController`. Neither was registered.
  3. `ProductImageRepository` (008G.4a.2 file 21). Referenced
     from `ProductController::showEdit()`. Not registered.
  4. `App::class` inside a binding closure (008G.4c). A
     closure tried to resolve `App::class` through the
     container, but `App` is not bound (it owns the
     container). Fixed by capturing the base path at
     registration time.

The fourth is a variant: the class exists but the container
has no way to reach it. Same error message, different cause.

**Related:**

  - `docs/SPECIFICATION.md` ADR-018 documents the decision
    that produced the auto-construction fallback.
  - `docs/baseline/14-provider.md` describes the three service
    providers and their binding patterns.
  - Pattern 34 (whitelisted-update models need the same
    discipline for new columns) is the sibling pattern: both
    are silent failures that only surface on the first request
    that touches the new piece.

**Prevention checklist for adding a new class:**

  1. Write the class.
  2. If the constructor has a primitive parameter without a
     default, or a method call after `new`, add an explicit
     binding.
  3. Otherwise, do nothing. As of 008J, the container builds
     it on demand.
  4. After the first browser test that reaches the class,
     check the app log for `Container:` errors. If one
     appears, the constructor has a shape the container
     cannot auto-resolve. Read the error message; it names
     the parameter and the fix.
  5. Before committing any new class, run a CLI smoke test
     for any route that reaches it. The CLI test boots
     faster than a browser and gives the same failure mode
     for container errors.

---

### Pattern 35 - Two audiences on one origin share a cookie jar

**Problem:** When the same origin serves two authenticated audiences
(admin at `/commerce/ca-admin` and customers at `/account`), the
browser sends the same cookies with every request. A session cookie
scoped to the origin is sent to *every* path on that origin, not just
the path that issued it. This is correct HTTP behaviour and it is the
source of a subtle bug class.

**The tell:** an authenticated user from audience A visits a login or
password-reset route belonging to audience B, and is silently redirected
to a wrong destination (often the storefront home or a generic landing
page) rather than the login form. The user is not told why. The redirect
target is often a default chosen for the *other* audience.

**Concretely in this codebase:** `GuestMiddleware` guards `/login` (admin). Its default redirect target is `/`. When an admin with a live
session visits `/login` - after logout, after a forwarded link, after a
bookmark - the middleware fires, `Auth::check()` returns true, and the
admin is redirected to `/`, the storefront home. From the merchant's
point of view, clicking "Sign out" seemed to land on "a generic page."
The actual sequence: logout succeeded, `/login` was reached, `guest`
bounced to `/`.

**The fix has two parts:**

1. **Per-audience middleware bindings.** `GuestMiddleware` must be bound
   by the product that knows its audience, with an explicit redirect
   target. In `CommerceServiceProvider`:

   ```php
   $c->singleton(GuestMiddleware::class,
       fn() => new GuestMiddleware(
           $c->get(Auth::class),
           '/commerce/ca-admin'
       ));
   ```

   The Platform default (`'/'`) is correct for a product without a
   distinct admin dashboard. Commerce has one, so it overrides.

2. **Cross-audience flows must clear the previous session.** When a
   route's purpose is to hand off to an audience that is not the
   currently authenticated one, explicitly log the current user out
   first. In `AdminUserController::completeInvite()`:

   ```php
   $this->container->get(Auth::class)->logout();
   ```

   This ensures the invitee lands on the login form even if they arrived
   in a browser that was already authenticated as a different user. In
   production (fresh device, no session), the call is a no-op.

**What also helps:** a dedicated post-logout page (`GET /logout/done`)
instead of redirecting to `/login`. The login route is a guest route.
Sending a freshly-logged-out user there is a subtle double-signal: the
user is both "logged out" and "not a guest." A dedicated page reads
cleanly and does not depend on the guest middleware's behaviour.

**Where we hit it:** Stage 008K.3, session 2026-10-10. Fresh Tester
invite-accept in the merchant's browser landed on `/` instead of
`/login`. Root cause: the admin session cookie was still present,
`guest` fired, and the middleware's default target was `/`.

**Related patterns:**

  - Pattern 22 (prefix-match active state) - a different kind of
    "wrong target by default."
  - The `RedirectGuardhelper - validates `?intended=` values so a
    stale or malicious redirect target cannot cross audiences.

**Prevention checklist when adding an authenticated surface:**

1. Ask: is there a second authenticated audience on this origin?
   If yes, the middleware default is a trap.
2. Any `guest`-style middleware needs an explicit redirect target
   matching the audience it protects.
3. Any flow that ends at another audience's login page must log out
   the current session first.
4. Any post-logout redirect must go somewhere that is not a guest
   route.

---

### Pattern 36 - Apache conditional directives no-op after internal mod_rewrite

**Problem:** A conditional directive in `.htaccess` — either
a `SetEnvIf`-guarded `Header` line or a `<If>` block —
silently fails to fire on the intended requests. The
default header set elsewhere in the same file wins.
Nothing in Apache's error log. Nothing in the browser
console except the downstream symptom (a CSP violation,
a wrong redirect, a missing header).

This is not a syntax error. `php -l` and `apachectl -t`
do not see it. The `.htaccess` file is correct on disk.
`AllowOverride` is set correctly. `mod_headers` and
`mod_setenvif` are loaded. Every diagnostic signal says
the block should work.

**Cause:** On some Apache 2.4 builds (observed on
`2.4.68 Win64`), the values of `%{REQUEST_URI}` and
`%{QUERY_STRING}` inside a `.htaccess` conditional are
unreliable once `mod_rewrite` has internally rewritten
the URI to `/index.php`. Apache documents
`%{REQUEST_URI}` as the original request URI, but the
observed behavior after an internal rewrite contradicts
this. `<If>` and `SetEnvIf` both read the same unreliable
value.

**The tell:** the conditional exists, the file parses,
`.htaccess` is being read (proven by the fact that other
directives from the same file are applying), and the
conditional is simply not firing. Two different
conditional mechanisms fail the same way. This narrows
the cause to the variable, not the directive.

**The fix:** match on `%{THE_REQUEST}`, which is the raw
request line from the client. It is never modified by
`mod_rewrite`. Anchor with the HTTP method so only GET
requests match. Add a trailing space after the query
string so a longer prefix does not accidentally match.

The working directive lives in `public/.htaccess`, in
the `mod_headers.c` block, near the default Content
Security Policy line. Its shape:

  - An If block whose condition matches the
    THE_REQUEST variable against the raw request line
    anchored at the intended endpoint path plus its
    identifying query string.
  - Inside the block, unset each header the default
    set, then set the override value.
  - Anchor the trailing query string with a trailing
    space so a longer prefix does not match.

Read the actual `.htaccess` block for the exact bytes.
The live file is the source of truth. Do not reproduce
the block here; it would drift.

**Where we hit it:** Session 2026-10-11-4. The campaign
preview iframe was blocked by the default
`frame-ancestors 'none'` and `X-Frame-Options: DENY`.
Two conditional override attempts (SetEnvIf with an
env-guarded Header, then an If block on REQUEST_URI)
silently no-op'd. Matching on THE_REQUEST fixed it on
the first try. Two hours of investigation compressed
into one working directive.

**Prevention checklist for conditional Apache headers:**

1. If a conditional header directive does not fire,
   suspect the variable before the directive.
2. Prefer THE_REQUEST over REQUEST_URI in `.htaccess`
   when the request goes through any `mod_rewrite`
   rule.
3. Verify with the browser Network tab, not by reading
   the `.htaccess` file. The response headers are the
   source of truth.
4. Confirm the default header is not also being sent.
   Browsers intersect multiple CSP headers; a default
   `frame-ancestors 'none'` plus an override
   `frame-ancestors 'self'` produces a blocked frame.

**Related:**

  - Pattern 22 (prefix-match active state breaks on
    strict-prefix routes). A different silent failure
    where the code looks right and the behavior is
    wrong.
  - The `Header always unset` plus `Header always set`
    pattern that also failed in this session. The
    unset line is only meaningful if the conditional
    fires; the conditional was the problem, not the
    unset.

---

### Pattern 37 - loading="lazy" on an iframe triggers a HEAD request that GET-only routers reject

**Problem:** An iframe with a valid src and
loading="lazy" renders the browser's broken-document
icon. Opening the same URL in a new browser tab returns
200 and the expected HTML. The Network tab shows a
HEAD request to the iframe URL, status 404, before any
GET is attempted.

**Cause:** Chrome sends a HEAD request first when
lazy-loading an iframe, before committing to the GET.
Many minimal routers register only GET routes. A
request to the route with method HEAD finds no
matching registration and returns 404. The browser then
treats the resource as unreachable and does not send
the GET. This failure is cached for the iframe's
lifetime.

**The tell:** the iframe URL works perfectly in a new
browser tab (GET, 200, correct body) but shows the
broken-document icon inside the iframe on the page.
The Network tab shows a HEAD request to the URL with
status 404, immediately followed by no further request.
Removing loading="lazy" resolves it.

**The fix:** remove loading="lazy" from the iframe.
The browser then sends a GET directly. If lazy loading
is a performance requirement, use display: none plus
a scroll listener plus a script that sets the src on
intersection, rather than the native loading attribute.
The native attribute does not help here because the
router cannot service the HEAD preflight.

**Where we hit it:** Session 2026-10-11-4. The campaign
preview iframe partial at
`resources/views/admin/campaigns/_body-preview.php`
had loading="lazy" on the iframe. The iframe URL routed
to AdminCampaignController::preview, which registers
only a GET route. The HEAD returned 404 and the browser
cached the failure. Removing the attribute forced the
GET.

**Prevention checklist for embedding iframes in this
codebase:**

1. Never use loading="lazy" on an iframe whose src
   points at a PHP-routed URL. The router does not
   register HEAD routes.
2. If the iframe must be lazy, use IntersectionObserver
   on a placeholder div and set the iframe's src on
   the first intersection. That defers the browser's
   request entirely, avoiding the HEAD preflight.
3. Verify an iframe's load path in the Network tab,
   not by reading the view. The browser's behavior
   differs from the markup.
4. When an iframe shows the broken-document icon but
   the URL works in a new tab, look at the request
   method first. It is often a HEAD or a preflight.

**Related:**

  - Pattern 36. Both fixes are for the same session,
    but they are independent: one is Apache, one is
    the browser. Neither masked the other; both had
    to be fixed for the iframe to render.

---

### Pattern 38 - Apache on Windows serves stale file bytes after WSL writes to /mnt/c

**Problem:** Edit a JS or CSS file from WSL. Reload the
browser. The browser receives the previous version of the
file. Full page reloads, hard reloads, Apache restarts,
and `versioned_asset()` cache-busters all fail to force a
refresh. The file on disk has the new bytes. The browser
receives the old bytes.

**The tell:** fetch the file from the browser console and
compare its byte count to the file on disk.

    fetch('/assets/js/foo.js?v=' + Date.now())
        .then(r => r.text())
        .then(t => console.log('served:', t.length, 'vs disk:', 9205));

If the served length and the disk length differ, the
server is serving stale bytes. The discrepancy can persist
indefinitely without any error message.

**Why it happens:** on this stack (WSL writing to
`/mnt/c/projects/commerceaid`, Apache running under
Laragon on the Windows side, Apache reading the file
through the Windows filesystem), file metadata changes
made by WSL do not always propagate to what Apache sees.
`filemtime()` from PHP reflects what Apache sees, not what
WSL wrote. So `versioned_asset()`'s cache buster reads the
same unchanged mtime and produces the same unchanged URL.
The browser never refetches. The stale file is served
forever.

**The fix (immediate):** rename the file.

    mv public/assets/js/foo.js public/assets/js/foo-v2.js

Then update every reference to point at the new filename.
A URL the browser has never seen has no cache entry. The
first fetch is guaranteed fresh.

**The fix (durable):** change `versioned_asset()` to use a
content hash instead of `filemtime()`.

    $url .= '?v=' . substr(md5_file($file), 0, 8);

`md5_file()` reads the current bytes on disk. When the
bytes change, the hash changes, the URL changes, the
browser refetches. The stale-metadata problem disappears
because the metadata is no longer involved.

**Where we hit it:**

  - Stage 008G.4c: `app.css` was served stale after an
    edit. Renamed to `main.css`, five references updated.
  - Stage 009 Session 2b: `forms-field-editor.js` was
    served stale after several edits. Renamed to
    `forms-field-editor-v2.js`, one reference updated.

**The cost of not knowing this pattern:** three hours in
Stage 009 Session 2b. The bug masqueraded as a DOM issue,
a JS binding issue, a Service Worker issue, and a
DOMContentLoaded race. Every diagnostic passed. Only the
byte-count comparison between browser-served and
disk-resident content revealed the truth.

**Prevention checklist when a JS or CSS edit does not
appear to take effect:**

1. Check the served bytes vs the disk bytes with the
   `fetch` test above. Do this before anything else.
2. If they match, the file is being served correctly.
   Look elsewhere.
3. If they differ, Apache is stale. Rename the file, or
   save it once from Notepad on the Windows side to force
   a metadata refresh, or edit through Windows tooling.
4. Do not assume a hard reload, an Apache restart, or a
   `?v=` cache buster will fix it. None of them reliably
   do, because the metadata they read is itself the thing
   that is stale.

**Related:** this is a development-environment issue, not
a production bug. On a Linux host (where Apache and the
filesystem are on the same machine), the metadata
propagates normally and the cache buster works. This
pattern applies specifically to WSL-to-Windows file
writes served by Laragon's Apache.

---

### Pattern 39 - Literal `?>` inside a PHP comment closes the PHP block

**Problem:** A `?>` sequence anywhere in a PHP file - including
inside a `//` single-line comment or a `/* */` block comment -
is honoured by PHP's lexer as a close-tag. Everything after it
in the file becomes HTML output. The rest of the file, including
every block opener and closer, is no longer PHP code.

The tell is a `php -l` parse error whose line number is far from
the actual problem. The error names the block that "opened but
never closed" - a `foreach`, an `if`, a function body. The
reported line is where the block opened. The real problem is
upstream, wherever the last `?>` appeared.

**The specific shape:** a comment that documents a PHP short-echo
pattern using the literal characters. For example, a docblock
that shows both the bad form and the good form:

    // Bad:  data-foo="<?= json_encode(...) ?>"
    // Good: data-foo="<?= e(json_encode(...)) ?>"

PHP stops parsing at the first `?>`, which is inside the "Bad"
example. The "Good" example is never parsed as PHP - it is HTML
text. The rest of the file is HTML text.

`php -l` then reports a parse error at whatever block was open
at that point. In the incident that produced this pattern, the
error was "Unclosed '{' on line 249 in bin/check-html.php on
line 467." Line 249 was a `foreach`. Line 467 was past EOF. The
actual problem was four comment lines at 342-345.

**Detection:** `grep -n '?>' <file>`. Any hit that is not the
final line of a pure-PHP file, or inside a deliberate template
string, is suspect. If it is inside a comment, it is a bug.

The corresponding grep for `<?` is also worth running. A literal
`<?` inside a comment does not by itself break parsing, but it
usually appears in the same comment as a `?>` and is a strong
signal.

**The fix:** do not write literal `?>` or `<?=` sequences inside
PHP comments. Describe the pattern in prose, or use a placeholder
token like `[short-echo]`, or move the example into a docblock
that does not contain the actual close-tag characters.

**Where we hit it:** Bin write for `bin/check-html.php`,
2026-10-12. The docblock for the data-* escaping rule documented
the bad-vs-good pattern with literal short-echo examples. Four
comment lines. Three hours of investigation.

The first two runs of the file failed at `php -l` with the same
message. The first fix attempt patched the wrong region because
the parser's reported line number pointed at the walk's outer
`foreach`, not at the comment. The second fix attempt failed
because the heredoc re-wrote the file with a *new* instance of
the same problem - the "fixed" comment block still used literal
short-echo examples. The third attempt replaced the four
comment lines with prose only.

**Why `php -l` does not catch it as a comment problem:** PHP's
lexer does not parse comments before checking for close-tags.
It scans for `?>` character-by-character across the entire byte
stream and treats the first one it finds as the end of the PHP
block, regardless of what syntactic context it appears in. This
is a documented property of the lexer, not a bug.

**Related patterns:**

  - Pattern 4 (php -l does not catch everything). Pattern 39
    is a specific case: `php -l` catches the symptom (a
    dangling block), not the cause (a close-tag inside a
    comment).
  - Pattern 20 (one file per message during critical work).
    The three failed fix attempts violated this by trying to
    patch and fix in the same heredoc.
  - Pattern 1 (silent Python patch failures). The first fix
    attempt's anchor did not match because it contained the
    same `?>` sequences that had already truncated the file.

**Prevention checklist for any file that documents PHP tags:**

1. Never write literal `?>` inside a comment. Use prose or a
   placeholder token.
2. Never write literal `<?php`, `<?=`, or `<?` inside a
   comment either. They are less dangerous but usually co-
   occur with `?>`.
3. After writing any file that contains the literal characters
   `<` and `?` adjacent, run `grep -n '?>' <file>` and read
   every hit.
4. When `php -l` reports a parse error at a block that looks
   closed, do not trust the reported line. Grep for `?>` in
   the file first.
