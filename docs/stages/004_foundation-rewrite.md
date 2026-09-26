# Stage 004 - Foundation Rewrite & First Login

**Date:** 2026-09-25
**Status:** Complete

## Summary

Rebuilt the Core / Platform / Commerce foundation so the
application layers are strictly one-directional. This was triggered
by a debugging cycle in which every fix revealed another leak from
the inherited Recruit Aid foundation.

After the rewrite: login works end to end, the store resolution
middleware runs on every admin request, and the setup wizard
correctly gates incomplete stores.

## The Problem Before

The foundation we inherited had product knowledge baked into the
framework:

- `Core\App` imported and bound Recruit classes directly
- `Platform\Services\DashboardService` queried `recruit_*` tables
- `Platform\Services\NotificationRuleService` imported a Recruit
  repository
- `Platform\Middleware\GuestMiddleware` hardcoded a redirect to
  `/dashboard` (a Recruit route)
- Views referenced the pre-rename namespace `\RecruitAid\*`
- Multiple Recruit strings in CSS, JS, config, and views

Every symptom we chased traced back to one of those.

## The Rewrite

### New contract: `docs/ARCHITECTURE.md`

Wrote a 334-line architecture contract. Defines:

- Three layers (Core → Platform → Commerce), strictly one-way
- Core knows nothing about Platform or Commerce
- Platform knows nothing about Commerce
- Commerce may use both
- Never the reverse

This document is the invariant. Every future change is measured
against it.

### App bootstrapper

`Core\App` rewritten as a pure bootstrapper. Zero imports from any
product namespace. Loads Core services, then Platform services, then
delegates to product providers:

    PRODUCT_PROVIDERS = [
        \CorporateAid\Commerce\CommerceServiceProvider::class,
    ];

### Product provider

New `Commerce\CommerceServiceProvider` holds every Commerce binding
in one place. Called by `App::registerProductProviders()` and
`App::loadRoutes()`.

Adding a future product (HR Aid, CRM Aid) means:
1. Drop `app/HR/` in
2. Add one line to `PRODUCT_PROVIDERS`
3. Register its middleware aliases in Kernel

Nothing else changes.

### Kernel middleware registry

Clean `$map` with both Platform and Commerce aliases:

    // Platform
    auth, guest, csrf, permission, ratelimit, audit
    // Commerce
    store, setup.gate

Platform middlewares never hardcode product paths. `GuestMiddleware`
and `AuthMiddleware` accept a redirect target via constructor.

### Routes

Rewrote three files:

- `routes/auth.php` - `/login`, `/logout` via `Commerce\AuthController`
- `routes/web.php` - placeholder
- `routes/commerce.php` - PWA, storefront placeholder, admin routes

### Views

- `layouts/auth.php` - bright palette, branding from config
- `auth/login.php` - carries `?intended=` through the form
- `admin/setup.php` - the setup wizard
- `admin/dashboard.php` - new placeholder

### Controllers

- `AuthController` - with open-redirect defense on `?intended=`
- `SetupController` - wizard show/save/skip
- `ManifestController` - fixed constructor to take Container
- `DashboardController` - moved out of a route closure

### Commerce middleware

- `StoreResolutionMiddleware` - resolves store by session, domain,
  path, or default
- `SetupGateMiddleware` - redirects to wizard on first admin visit,
  shows banner afterward

## Security Preserved

- Password hashing via Argon2id (unchanged)
- CSRF on all state-changing requests (unchanged)
- Rate limiting on auth endpoints (unchanged)
- Session rotation and idle timeout (unchanged)
- Audit logging on every state-changing HTTP request (unchanged)
- **New:** Open-redirect defense in `AuthController::login()` -
  `?intended=` is validated before use (must start with `/`, not
  `//`, no scheme, no newlines)

## Known Issues Resolved

1. `classmap-authoritative` made new classes invisible until
   `composer dump-autoload -o` - flipped to `false` for dev
2. Apache OPcache cached stale PHP after `app/` edits - added
   Apache reload to the dev workflow
3. Silent heredoc failures left files missing without warning -
   established a policy of `ls`/`head` verifying every write

## Verification

Full end-to-end test passed:

    1. GET /                     200  storefront placeholder
    2. GET /manifest.json        200  valid JSON
    3. GET /login                200  login page (guest)
    4. POST /login               302  to /commerce/ca-admin
    5. GET /commerce/ca-admin    200  dashboard (authenticated)
    6. GET /commerce/ca-admin/setup  200  wizard
    7. POST /logout              302  to /login

## Database Changes

None in this stage. The schema from Stage 003 remains the source of
truth, plus migration `0102_add_country_to_stores.sql` which was
applied during the setup wizard work.

## Files Created

    app/Core/App.php                                  (rewritten)
    app/Core/Http/Kernel.php                          (rewritten)
    app/Platform/Middleware/GuestMiddleware.php       (rewritten)
    app/Platform/Middleware/AuthMiddleware.php        (rewritten)
    app/Platform/Services/NotificationRuleService.php (rewritten)

    app/Commerce/CommerceServiceProvider.php          (new)
    app/Commerce/Controllers/Admin/DashboardController.php (new)
    app/Commerce/Middleware/StoreResolutionMiddleware.php  (recreated)

    routes/auth.php                                   (rewritten)
    routes/web.php                                    (rewritten)
    routes/commerce.php                               (rewritten)

    resources/views/admin/dashboard.php               (new)
    resources/views/auth/login.php                    (rewritten)
    resources/views/layouts/auth.php                  (rewritten)

    docs/ARCHITECTURE.md                              (rewritten)

## Files Deleted

    app/Platform/Services/DashboardService.php        (Recruit leak)
    public/_diag.php                                  (diagnostic)

## Decisions Made

1. **Layered architecture locked.** `docs/ARCHITECTURE.md` is the
   contract. Any code that violates it is wrong by definition.

2. **Product provider pattern.** Future products register through
   `ServiceProvider::register()` and `routes()`. App.php imports
   nothing product-specific.

3. **Middleware redirects are injected.** No hardcoded paths in
   reusable middleware.

4. **Open-redirect defense required.** Any `?intended=`, `?next=`,
   `?redirect=` param must pass an internal-path check before use.

5. **Dev workflow updated.** After every `app/` change:
   - `composer dump-autoload -o`
   - Apache reload
   - Test with fresh browser session if auth is involved

## What's Next

Stage 005 - Admin dashboard shell:
- Real sidebar navigation
- Topbar with user menu
- Dashboard tiles (products, orders, customers placeholders)
- Setup wizard polish
- Branding customizer foundations

## Stage Log Reference

See `docs/stages/README.md` for the running index.
