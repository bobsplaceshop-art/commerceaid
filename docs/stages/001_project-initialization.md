# Stage 001 - Project Initialization & Foundation

**Date:** 2026-09-25
**Status:** Complete

## What Was Done

Created the Anaya Global commerce platform project from scratch,
reusing the framework and shared services from the sibling
`recruitaid` project. The new project lives at
`C:\projects\commerceaid` (Windows) / `/mnt/c/projects/commerceaid`
(WSL) and is served by Laragon on `http://localhost:8081`.

## Scope

- Folder structure created
- Framework (`app/Core/`) and shared services (`app/Platform/`)
  copied from `recruitaid`
- Design system assets copied (CSS, JS, fonts)
- Reusable view shell copied (layouts, partials, errors, auth,
  account, notifications, dashboard)
- Shared routes copied (`auth.php`, `web.php`)
- Suite-wide documentation copied (`ARCHITECTURE.md`,
  `NAMING-CONVENTION.md`, `SPECIFICATION.md`)
- Generic password-reset email template copied

## Folder Structure

    commerceaid/
    ├── app/
    │   ├── Core/               (framework - from recruitaid)
    │   ├── Platform/           (shared services - from recruitaid)
    │   ├── Commerce/           (empty - Commerce-specific code)
    │   └── Helpers/
    ├── bin/                    (migrate.php, seed.php)
    ├── config/                 (app, branding, database, mail)
    ├── database/
    │   ├── migrations/
    │   └── seeders/
    ├── docs/
    │   ├── ARCHITECTURE.md
    │   ├── NAMING-CONVENTION.md
    │   ├── SPECIFICATION.md
    │   └── stages/
    ├── public/                 (index.php, .htaccess, assets)
    ├── resources/
    │   ├── emails/
    │   └── views/
    ├── routes/
    ├── storage/
    ├── themes/commerce/
    └── vendor/

## Environment

- **Host OS:** Windows 11
- **Dev shell:** WSL Ubuntu (file ops, heredocs, scaffolding)
- **Runtime:** Laragon Terminal (PHP 8.3.33, MySQL 8.4.3, Composer 2.10.3)
- **Database GUI:** HeidiSQL (127.0.0.1:3306)
- **Mail:** Mailpit (127.0.0.1:1025 / http://127.0.0.1:8025)
- **Node.js:** Not installed - this project never uses it

## Command Split (Locked)

- **WSL** for file creation, heredocs, greps, inspection
- **Laragon Terminal** for PHP execution, Composer, migrations
- **HeidiSQL** for schema inspection, ad-hoc queries
- **Browser F12** for JS debugging, service worker checks

PHP can technically run in WSL but all testing happens in Laragon.

## Deliverables

- `C:\projects\commerceaid\` - full project scaffold
- `docs/stages/` - this stage log

## What's Next

Stage 002 - strip Recruit-specific code from the imported
framework and rebrand for Anaya Global.
