# Stage 002 - Framework Import & Recruit Cleanup

**Date:** 2026-09-25
**Status:** Complete

## What Was Done

The framework and shared services copied from Recruit Aid carried
Recruit-specific bindings, namespaces, and strings. This stage
removed every Recruit reference from the imported code and
rebranded the project for Anaya Global.

## Problems Found (Diagnostic)

1. `app/Core/App.php` imported 15 Recruit classes and bound them
   directly in `registerServices()`. The framework was not
   module-aware - it was Recruit-specific.
2. `app/Platform/Services/DashboardService.php` queried
   `recruit_vacancies`, `recruit_client_organisations`, and
   `recruit_engagements` directly. Violated the naming convention's
   ownership rule.
3. `app/Platform/Services/NotificationRuleService.php` imported
   a Recruit repository and had four Recruit-specific recipient
   resolver methods.
4. Broken namespaces in views - `partials/topbar.php` and
   `auth/forgot-password.php` referenced `\RecruitAid\*` (the
   pre-rename namespace). These were latent bugs in Recruit Aid
   too.
5. Strings and identifiers - `recruitaid_session` cookie,
   "Recruit Aid" in ~30 places across config, views, CSS, and JS.

## What Changed

### App.php - rewritten

- Removed all 15 Recruit imports
- Removed all Recruit service/repository/validator bindings
- Removed Recruit route loading
- Version bumped to `0.1.0-alpha`
- Route loading now includes a `commerce` file (empty for now)

### Platform/Services/DashboardService.php - deleted

Recruit-specific queries don't belong in Platform. A Commerce-owned
dashboard service will be written when needed.

### Platform/Services/NotificationRuleService.php - rewritten

- Removed Recruit repository dependency
- Removed Recruit-specific resolvers (`assigned_recruiter`,
  `engagement_member`, `organisation_client_admin`,
  `hiring_manager`)
- Kept generic resolvers only: `role`, `permission`, `user`,
  `owner`

### Views - namespaces fixed, strings replaced

- `partials/topbar.php` - `\RecruitAid\Core\App` became
  `\CorporateAid\Core\App`
- `auth/forgot-password.php` - same fix
- All visible "Recruit Aid" text became "Anaya Global"
- `RA_DeletionModal` became `CA_DeletionModal` (JS + PHP references)

### Config - rebranded

- `app.php` - name became "Anaya Global", session cookie became
  `ca_session`
- `branding.php` - fully rewritten with Anaya Global defaults
- `database.php`, `mail.php` - comments and email defaults updated

### Constants - renamed

- `RA_APP_START` became `CA_APP_START`
- `RA_BASE_PATH` became `CA_BASE_PATH`
- `RA_PUBLIC_PATH` became `CA_PUBLIC_PATH`

## Files Created

- `public/index.php` - new front controller with `CA_*` constants
- `config/local.php.example`
- `config/cli.php.example`
- `config/branding.php` - Anaya Global defaults
- `composer.json` - package `corporateaid/commerce-platform`
- `.gitignore`
- `README.md`

## Files Modified

- `app/Core/App.php`
- `app/Core/Session.php`
- `app/Platform/Services/NotificationRuleService.php`
- `config/app.php`, `config/database.php`, `config/mail.php`
- `routes/web.php`, `routes/auth.php`
- `public/.htaccess`
- Multiple views, CSS, JS (string replacements)

## Files Deleted

- `app/Platform/Services/DashboardService.php`
- `public/assets/css/app.css.bak.safety` (copy artifact)

## Decisions Made

1. Flat namespace - `CorporateAid\Core\`,
   `CorporateAid\Platform\`, `CorporateAid\Commerce\`. No deeper
   nesting. Matches the suite naming convention.
2. Session cookie - `ca_session` (deployment-neutral, not
   customer-branded).
3. Constants - `CA_*` prefix ("Corporate Aid").
4. Branding - every customer-visible string lives in
   `config/branding.php` or `commerce_stores` (per-tenant).
5. Corporate Connect - deferred. Not part of this build.

## Verification

- Every modified file passed `php -l`
- Composer autoload rebuilt successfully (307 classes)
- No remaining `Recruit`, `recruitaid`, `RA_`, or `recruit_`
  references in `app/`, `config/`, `routes/`, `resources/`
  (grep confirmed)

## What's Next

Stage 003 - build the database schema: core platform tables plus
the Commerce multi-tenant anchor.
