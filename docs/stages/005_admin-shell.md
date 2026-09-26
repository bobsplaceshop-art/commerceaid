# Stage 005 - Admin Shell & Setup Hub

**Date:** 2026-09-25
**Status:** Complete

## Summary

Built the admin shell (sidebar + topbar + content area) and the
multi-tab setup hub. Every admin page now renders inside a
consistent layout with themed navigation, breadcrumbs, and user
menu. The setup hub supports eight tabs, four of which persist
configuration to `commerce_stores`.

## What Shipped

### Admin shell

- `layouts/app.php` - the shell wrapper (sidebar + topbar + content)
- `partials/sidebar.php` - navigation with grouped sections and lock states
- `partials/topbar.php` - breadcrumbs, search stub, theme toggle, settings shortcut, user menu
- `partials/user-dropdown.php` - sign-out form (account links deferred)
- `partials/theme-dropdown.php` - dark/light/system picker (existing, unchanged)

### Theme system

- Server-side theme preference read from `core_users.theme_preference`
- Pre-paint script resolves `localStorage` + server value before first paint
- Toggle persists to DB via `POST /account/theme`
- `UserRepository::updateThemePreference()` added
- Auth layout also has a pre-paint script (best-effort, reads localStorage only)

### Setup hub

Route: `/commerce/ca-admin/setup` redirects to `/setup/business`.
Each tab has GET (show) and POST (save) routes.

| Tab | Status | Persistence |
|---|---|---|
| Business | Real | name, slug, tagline, legal_name |
| Currency & Region | Real | currency, country, timezone, locale, formats, units |
| Branding | Real (colours/PWA), stub (logo/favicon) | colours, PWA fields |
| Checkout | Real | guest checkout, require phone, require company |
| Payments | Stub | form only, static provider list |
| Shipping | Stub | form only |
| Tax | Stub | form only |
| Advanced | Stub | form only, includes disabled danger zone |

### Components

- `Controllers/Admin/AdminController.php` - base class with `render()` that injects shell context
- `Controllers/Admin/SetupController.php` - tab routing + save dispatch
- `Controllers/Admin/DashboardController.php` - dashboard
- `Controllers/AccountController.php` - theme persistence endpoint
- `public/assets/css/setup.css` - sub-nav + payment grid + danger zone styling

### Database

- Migration `0102_add_country_to_stores.sql` - adds `country_code` to `commerce_stores`
- No new tables

## Design Decisions

1. **Setup hub is a settings page.** Tabs stay accessible after initial
   setup completes; the wizard framing is only in the header text.

2. **Real persistence vs form-only stubs.** Tabs with a data model
   write to the DB. Tabs without one accept and flash. Users are told
   the difference on the stub tabs ("This section is under construction").

3. **Locked sidebar items.** Catalog/Sales nav items are greyed while
   setup is incomplete, with a lock icon. Prevents dead-end clicks.

4. **Theme: system is the default.** Respects OS preference. User
   choice (dark/light) persists to DB. Clearing cookies reverts to
   system, which is expected.

5. **Post-tab saves.** Each tab saves its own section. A failed tax
   save doesn't block a currency edit.

## Known Issues / Deferred

- **Catalog/Sales pages** - routes 404 until Stage 006+
- **Account pages** (`/account`, `/account/password`) - dropdown links
  removed until pages exist
- **Settings gear** in topbar - points at `/commerce/ca-admin/settings`
  which 404s until we build a dedicated settings page (distinct from
  the setup hub)
- **Store switcher** - hidden; meaningless until a second store exists
- **Notification bell** - deferred; needs NotificationService API audit
- **Logo/favicon upload** - stub note in Branding tab; awaits FileService
  integration for store branding assets

## Verification

End-to-end tested:

    1. Login with admin credentials               OK
    2. Dashboard renders inside admin shell       OK
    3. Setup redirects to /setup/business         OK
    4. Each tab renders correctly                 OK
    5. Business save persists to DB               OK
    6. Validation errors render on failed save    OK
    7. Stub tabs flash honest messages            OK
    8. Theme toggle persists across login/logout  OK
    9. Logout returns to clean /login             OK
    10. Open-redirect defense on ?intended=       OK

## Files Created

    app/Commerce/Controllers/Admin/AdminController.php
    app/Commerce/Controllers/Admin/DashboardController.php
    app/Commerce/Controllers/AccountController.php

    resources/views/layouts/app.php                         (rewritten)
    resources/views/layouts/auth.php                        (rewritten)
    resources/views/partials/sidebar.php                    (rewritten)
    resources/views/partials/topbar.php                     (rewritten)
    resources/views/partials/user-dropdown.php              (rewritten)
    resources/views/admin/dashboard.php                     (rewritten)

    resources/views/admin/setup/_nav.php
    resources/views/admin/setup/index.php
    resources/views/admin/setup/business.php
    resources/views/admin/setup/currency.php
    resources/views/admin/setup/branding.php
    resources/views/admin/setup/checkout.php
    resources/views/admin/setup/payments.php
    resources/views/admin/setup/shipping.php
    resources/views/admin/setup/tax.php
    resources/views/admin/setup/advanced.php

    public/assets/css/setup.css

    database/migrations/0102_add_country_to_stores.sql

## Files Modified

    app/Commerce/Controllers/Admin/SetupController.php      (tabs)
    app/Commerce/Controllers/AuthController.php             (intended=/logout defense)
    app/Platform/Repositories/UserRepository.php            (updateThemePreference)
    app/Platform/Models/User.php                            (default theme fallback)
    routes/auth.php                                         (account/theme endpoint)
    routes/commerce.php                                     (setup tab routes)

## What's Next

Stage 006 - Catalog foundations. Real products, categories, brands.
First real Commerce module with its own tables, controllers, views.

After that:
- Stage 007 - Shipping engine
- Stage 008 - Payments
- Stage 009 - Tax engine
- Stage 010 - Storefront theme engine
