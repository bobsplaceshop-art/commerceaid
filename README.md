# Anaya Global — Commerce Platform

A white-labelled, multi-tenant e-commerce platform. This deployment
is branded for **Anaya Global**, but the codebase is designed to be
re-sold and re-deployed for other customers with no code changes.

## What it is

- Standalone PHP 8.2+ / MySQL 8 application
- Runs on shared cPanel hosting (Apache + `.htaccess`)
- No Node.js, no build step, no external services
- Multi-tenant from day one — one install can host multiple storefronts
- Fully white-labelled — every customer-visible string is configuration

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `ctype`, `tokenizer`, `dom`, `gd`, `curl`, `zip`
- MySQL 8.0+ or MariaDB 10.6+
- Apache with `mod_rewrite`
- Composer 2.x
- SMTP account (for email)

## Local development

This project is developed on **Laragon** (Windows) with a WSL shell
for file operations.

- **Windows path:** `C:\projects\commerceaid`
- **WSL path:** `/mnt/c/projects/commerceaid`
- **Apache vhost:** `http://localhost:8081`
- **MySQL:** `127.0.0.1:3306` (via Laragon)
- **Mailpit:** `127.0.0.1:1025` / `http://127.0.0.1:8025`

**Setup:**

1. Copy `config/local.php.example` to `config/local.php` and fill in your DB credentials.
2. Copy `config/cli.php.example` to `config/cli.php` for CLI tools.
3. Run `composer install` (in Laragon Terminal).
4. Create the database `anaya_commerce` (via HeidiSQL or `mysql` CLI).
5. Run migrations: `php bin/migrate.php`.
6. Seed initial data: `php bin/seed.php`.
7. Open `http://localhost:8081`.

## Architecture

See `docs/ARCHITECTURE.md`, `docs/NAMING-CONVENTION.md`, and
`docs/SPECIFICATION.md`.

Commerce-specific docs are added in `docs/COMMERCE-*.md` as the
platform is built.

## License

Proprietary. Sold as a white-labelled product. Each install is
licensed to the deploying customer; the customer owns the code,
data, and infrastructure after delivery.
