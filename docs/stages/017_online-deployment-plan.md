# Stage 017 - Online Deployment & Update System

**Status:** Planning
**Opened:** 2026-10-19
**Depends on:** Core framework, existing Migrator, existing error pipeline.
**Blocks:** Any future stage that requires database schema changes (e.g., Travel & Logistics).

---

## Purpose

The platform is deployed on shared hosting (cPanel / Laragon). When we make changes locally (new migrations, new code), we need a way to push those changes to the live server without destroying the live database or locking the merchant out.

Currently, `bin/migrate.php` and `bin/seed.php` are CLI-only. A merchant cannot run these. If a developer uploads new code via FTP but forgets to run the migration, the site crashes (e.g., "Table not found"). If the developer runs a migration and it fails halfway, the database is left in a broken state.

This stage builds an **in-app Update System**. It detects pending migrations, puts the storefront into a safe maintenance mode, takes a backup, and applies the migrations with one click.

---

## The Flow

1. **Developer pushes code.** New migration files land in `database/migrations/`.
2. **Merchant visits the admin panel.** The system boots and checks the database.
3. **The Check:** `App::run()` compares the files on disk to the `core_migrations` table.
4. **The Gate:** If there are pending migrations, `MaintenanceMiddleware` intercepts all storefront traffic and returns a 503 (Maintenance) page. Admin traffic is allowed through to a special update screen.
5. **The Update Screen:** The admin sees a warning: *"Database updates pending. Please back up and run migrations."*
6. **The Action:** The admin clicks "Run Updates".
7. **The Execution:** The system takes a database backup (or at least dumps the schema), runs the pending migrations in a transaction, and clears the cache.
8. **Success:** The site goes live.

---

## The Components

### 1. The Migration Checker

We will add a method to `App::loadRoutes()` or `App::run()`. If the app is not running on CLI, it instantiates `Migrator` and calls a new method `getPendingMigrations()`.

- If pending migrations exist, the system sets a global flag (`App::$needsUpdate = true`).
- If no pending migrations exist, it continues normally.

### 2. Maintenance Mode (Middleware)

A new middleware, `MaintenanceMiddleware`, is added to the global stack.

- If `App::$needsUpdate` is true AND the user is NOT an admin, the middleware redirects to `/maintenance`.
- If the user IS an admin, the middleware allows access to `/commerce/ca-admin/update`, but redirects all other admin pages to that update screen.

### 3. The Update Screen (Controller & View)

- **Route:** `GET /commerce/ca-admin/update`
- **Controller:** `AdminUpdateController`
- **View:** Displays the list of pending migration filenames.
- **Action Button:** A form that POSTs to `/commerce/ca-admin/update/run`.
- **Backup Step:** The controller offers a "Download Database Backup" button before running.

### 4. The Migration Runner

When the admin submits the form:
- The system runs `bin/migrate.php` logic internally.
- It **wraps the entire process in a try/catch**.
- If a migration fails, it captures the SQL error and displays it on the screen, advising the merchant to restore the backup.
- We do NOT wrap the migrations in a single SQL transaction. MySQL DDL statements (like `ALTER TABLE`) implicitly commit and cannot be rolled back. The system relies on the backup for recovery.

### 5. Cache Clearing

After a successful migration, the system clears the `storage/cache/` directory and any OPcache (via `opcache_reset()`).

---

## What is NOT in this stage

- **Git Pull:** The platform does not have Git installed on the live server. The merchant uploads files via FTP or cPanel File Manager. The update system only handles the database and cache.
- **Automatic Updates:** The system does not phone home to check for new releases. It only checks what is on disk vs. what is in the DB.
- **Schema Rollbacks:** We will not write `down()` migrations. If an update fails, the merchant restores the pre-update backup.

---

## Sessions

### Session 1 - The Check and the Gate
- Add `Migrator::getPendingMigrations()`.
- Add `MaintenanceMiddleware` and bind it in the Kernel.
- Update `App::run()` to check for pending migrations.
- Create the `/maintenance` route and view.

### Session 2 - The Admin Update Screen
- Create `AdminUpdateController`.
- Create the update screen view (list of pending files, backup button).
- Add the POST route to run the migrations.
- Wire the `Migrator` into the controller to run safely and catch errors.

### Session 3 - The Docs and Test
- Test the flow locally by adding a fake migration file and refreshing the browser.
- Update the `LOCAL-ENVIRONMENT.md` with the deployment steps.
- Close the stage.
