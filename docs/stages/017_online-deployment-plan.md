# Stage 017 - Online Deployment & Update System

**Status:** Planning
**Opened:** 2026-10-19
**Depends on:** Core framework, existing Migrator, existing error pipeline.
**Blocks:** Any future stage that requires database schema changes (e.g., Travel & Logistics).

---

## Purpose

The platform is deployed on shared hosting. When developers push code locally, new migration files land in `database/migrations/`. The live site must apply these migrations without destroying data, but auto-locking the site the moment files are uploaded is hostile to the customer.

This stage builds an **In-App Update System** that:
1. Detects pending updates.
2. Notifies the admin.
3. Allows the admin to schedule the update for a specific time.
4. Displays a "Scheduled Maintenance" banner (Store Notice) to customers.
5. Executes the update safely during the scheduled window.

---

## The Flow

1. **Developer pushes code.** New migration files land in `database/migrations/`.
2. **System Boot:** `App::run()` compares the files on disk to the `core_migrations` table.
3. **Notification:** The system detects pending migrations. It does NOT lock the site. Instead, it shows a red notification badge in the admin panel: *"Database updates pending."*
4. **Scheduling:** The admin visits `/commerce/ca-admin/update`. They see the pending files. They set a date/time for the update (e.g., Tonight at 2:00 AM).
5. **Store Notice:** The platform reads the scheduled update time. If the time is within the next 24 hours, it displays a banner on the storefront: *"Scheduled maintenance: [Date] at [Time]. The store will be briefly unavailable."*
6. **The Gate (Execution):** When the scheduled time arrives (checked by a cron or on boot), the store enters Maintenance Mode (503) automatically for non-admins.
7. **Execution:** The system applies the migrations, takes a backup first, and clears the cache.
8. **Success:** The site goes live. The maintenance banner disappears. The pending badge clears.

---

## The Components

### 1. The Migration Checker
Add `Migrator::getPendingMigrations()`. In `App::run()`, set a global flag `App::$hasPendingUpdates = true` if files exist on disk that aren't in the DB.

### 2. The Admin Notification
- Add a badge to the admin topbar (next to notifications) and the "Update" sidebar item.
- The admin is never locked out. They can still browse the admin panel while the update is pending.

### 3. The Store Notice Banner
- A new setting in `commerce_store_settings`: `general.store_notice`.
- The `StorefrontController` reads this setting once per request.
- If a scheduled update is within 24 hours, it dynamically injects a notice into the storefront layout.
- Customers see the notice, but the site remains fully functional.

### 4. The Scheduled Update
- The admin sets `system.update.scheduled_at` (DATETIME).
- A new cron script `bin/run-scheduled-updates.php` runs every 5 minutes.
- It checks if `NOW() >= scheduled_at`. If yes, it triggers the update process.

### 5. Maintenance Mode (Middleware)
- A `MaintenanceMiddleware` sits on the storefront.
- If an update is currently *running*, the middleware returns a 503 for non-admin users, showing a "We'll be right back" page.
- Admin users bypass this gate.

### 6. The Migration Runner
- Wraps `Migrator::run()` in a try/catch.
- Takes a database backup (or exports the schema) before running.
- Clears OPcache and the `storage/cache/` directory on success.

---

## What is NOT in this stage
- **Git Pull:** The update system assumes files are uploaded via FTP/cPanel. It only handles database and cache.
- **Automatic Updates:** The system does not phone home. It only looks at what is on disk.
- **Schema Rollbacks:** We rely on the pre-update backup for recovery.

---

## Sessions

### Session 1 - The Check and the Notice
- Add `Migrator::getPendingMigrations()`.
- Update `App::run()` to set the pending flag.
- Add `general.store_notice` setting.
- Update the storefront layout to render the notice.

### Session 2 - The Admin Update Screen
- Create `AdminUpdateController` and view.
- Allow scheduling a time.
- Display the pending files.
- Offer a manual "Run Now" button.

### Session 3 - The Maintenance Gate and Execution
- Add `MaintenanceMiddleware`.
- Create `bin/run-scheduled-updates.php`.
- Wire the execution logic.
- Test the full flow locally (add a fake migration, schedule it, watch it run).
