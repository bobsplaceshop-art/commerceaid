# Stage 003 - Database Schema (Core + Commerce)

**Date:** 2026-09-25
**Status:** Complete

## What Was Done

Built the full database schema for the Anaya Global install. The
database contains the platform's shared core (identity, RBAC,
audit, notifications, custom fields, files, settings, sequences,
integrations, webhooks, workflow engine, currencies, email
templates) plus the Commerce-specific multi-tenant anchor
(stores, store domains, store users, store currencies).

## Database

- **Name:** `anaya_commerce`
- **Charset:** `utf8mb4` / `utf8mb4_unicode_ci`
- **Engine:** InnoDB
- **MySQL:** 8.4.3 (via Laragon)

## Migrations Applied (19 total)

### Core platform (17 migrations)

| #    | File                                | Creates / Alters |
|------|-------------------------------------|------------------|
| 0001 | create_core_tables.sql              | core_users, core_roles, core_permissions, core_role_permissions, core_user_roles, core_password_resets, core_audit_logs (initial), core_system_settings |
| 0002 | add_theme_preference_to_users.sql   | ALTER core_users |
| 0003 | change_theme_default_to_system.sql  | ALTER core_users |
| 0004 | create_custom_fields.sql            | core_custom_fields, core_custom_field_values |
| 0005 | create_activities_table.sql         | core_activities |
| 0006 | create_sequence_counters.sql        | core_sequence_counters |
| 0007 | create_rate_limits.sql              | core_rate_limits |
| 0008 | seed_default_settings.sql           | Seeds core_system_settings |
| 0009 | add_confirm_password_limits.sql     | ALTER settings |
| 0010 | create_notifications.sql            | core_notifications |
| 0011 | add_notification_digest_tracking.sql| ALTER notifications |
| 0012 | create_notification_rules.sql       | core_notification_rules |
| 0013 | create_audit_logs.sql               | core_audit_logs (final version) |
| 0014 | create_files.sql                    | core_files |
| 0015 | create_entity_files.sql             | core_entity_files |
| 0016 | create_platform_foundation.sql      | core_modules, core_module_licenses, core_module_state, core_integrations, core_integration_logs, core_scheduled_tasks, core_health_checks, core_updates_available, core_updates_history, core_update_manifest |
| 0017 | create_strategic_foundation.sql     | core_currencies, core_notes, core_feature_flags, core_webhooks, core_webhook_deliveries, core_email_templates, core_data_requests, core_consent_log, core_login_history, core_user_permissions, core_workflow_definitions, core_workflow_states, core_workflow_transitions, core_scheduled_task_runs |

### Commerce (2 migrations)

| #    | File                            | Creates / Alters |
|------|---------------------------------|------------------|
| 0100 | create_commerce_stores.sql      | commerce_stores, commerce_store_domains, commerce_store_users, commerce_store_currencies |
| 0101 | add_store_id_to_core.sql        | ALTER core_audit_logs, core_activities, core_notification_rules, core_sequence_counters to add store_id |

## Result

47 tables. All foreign keys, indexes, and constraints in place.
No Recruit-specific tables (recruit_*) present.

## Key Design Decisions

### Multi-tenancy (Level 2 for Phase 0)

- Shared database, store_id column on tenant-scoped tables
- Shared core tables (core_audit_logs, core_activities,
  core_notification_rules) gained nullable store_id - NULL for
  platform-level events, set for store-specific events
- core_sequence_counters PK changed to (name, store_id) so each
  store has its own ORD-000001, INV-000001, etc. Sentinel
  store_id = 0 represents "no store" (MySQL PKs cannot include
  NULL)
- Commerce-specific tables will all carry store_id NOT NULL

### Migration numbering

- 0001-0099 reserved for core platform migrations
- 0100+ reserved for Commerce-specific migrations
- Future modules (HR Aid, CRM Aid) will use higher ranges
  (0200, 0300, etc.) if ever added to this install

### Extraction from Recruit Aid

Recruit Aid's migrations were copied and split. Recruit-specific
migrations (0004, 0006, 0009, 0010, 0011, 0017, 0019, 0020, 0026)
were skipped. Mixed migrations were extracted:

- 0005_create_custom_fields_and_stages.sql kept only
  core_custom_fields and core_custom_field_values
- 0008_security_hardening.sql kept only the
  core_sequence_counters table (dropped the Recruit seed rows)

## Verification

- `php bin/migrate.php` applied all 19 migrations without error
- SHOW TABLES in anaya_commerce confirms 47 tables
- DESCRIBE core_sequence_counters shows PK on (name, store_id)
- DESCRIBE core_audit_logs shows store_id as second column
- No recruit_* tables present

## What's Next

Stage 004 - Commerce service layer: StoreContext,
StoreRepository, StoreService, StoreResolutionMiddleware, plus
the initial routes and view stubs so the app renders something
at http://localhost:8081.
