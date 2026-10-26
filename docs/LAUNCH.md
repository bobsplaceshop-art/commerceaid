# Launch Guide

**Audience:** The operator deploying the platform to production hosting.
**Scope:** Everything needed to take the codebase from `main` to a live storefront.

This document is a checklist, written against the codebase as it
stands at `main`. Every path, every flag, every credential name was
read from the source files, not from memory or the roadmap. If this
document and the codebase disagree, the codebase wins.

Assumptions:

- Hosting supports PHP 8.2+ / MySQL 8+ / Apache with `mod_rewrite`,
  `mod_headers`, `mod_setenvif`.
- The client has a domain and a DNS provider.
- The client has accounts with their chosen payment providers.
- The client has an SMTP provider.

If any of those is not true, resolve it before proceeding.

---

## 1. Hosting requirements

- **PHP 8.2 or later** (8.3 recommended).
- **MySQL 8.0 or MariaDB 10.6**.
- **Apache** with `mod_rewrite`, `mod_headers`, `mod_setenvif`.
- **Extensions:** `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`,
  `json`, `ctype`, `tokenizer`, `dom`, `gd` (WebP + JPEG), `curl`,
  `zip`.
- **Cron** (two jobs, see section 9).
- **SSL** (Let's Encrypt via cPanel is standard).
- **500 MB** disk minimum (install + media library).

No Node.js, Docker, Redis, or build step. The platform has none.

---

## 2. DNS and SSL

1. Point the domain's `A` record at the host's IP.
2. Point `www` at the same IP (CNAME to the root domain works).
3. Wait for propagation (1-24 hours).
4. In cPanel, install a Let's Encrypt certificate for the domain
   and `www`.
5. Enable "Force HTTPS Redirect."

---

## 3. Upload the codebase

Two options.

**Git clone** (if the host provides shell access):

    cd /home/<user>/
    git clone <repo-url> commerceaid
    cd commerceaid
    git checkout main

**Upload via cPanel File Manager or SFTP:**

Download the codebase as a ZIP, upload, extract. The `public/`
directory must be the document root. The rest (`app/`, `config/`,
`database/`, `resources/`, `vendor/`) lives outside the document
root.

---

## 4. Install Composer dependencies

In the host's terminal (or cPanel Terminal):

    cd /home/<user>/commerceaid
    composer install --no-dev --classmap-authoritative --optimize-autoloader
    composer dump-autoload -o

The `--no-dev` flag skips testing tools. `--classmap-authoritative`
improves runtime performance. If Composer is not available, upload
the `vendor/` directory from your local `composer install` run.

---

## 5. Configure the database

In cPanel:

1. Create a MySQL database (e.g. `anaya_commerce`).
2. Create a MySQL user with full privileges on that database.
3. Record host, port, database name, username, password.

---

## 6. Write the config files

**Do not copy `config/local.php.example`.** The example carries
development defaults (Laragon URLs, Mailpit, Chrome path, dev
admin credentials). Write `config/local.php` from scratch with the
production values.

`config/local.php`:

    <?php
    declare(strict_types=1);

    return [
        'database' => [
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'anaya_commerce',
            'username' => 'the_db_user',
            'password' => 'the_db_password',
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
        ],
        'app' => [
            'env'      => 'production',
            'debug'    => false,
            'url'      => 'https://yourdomain.com',
            'timezone' => 'Europe/Berlin',
            'key'      => '',
        ],
        'mail' => [
            'host'       => 'smtp.mailprovider.com',
            'port'       => 587,
            'username'   => 'the_smtp_user',
            'password'   => 'the_smtp_password',
            'encryption' => 'tls',
            'from_email' => 'no-reply@yourdomain.com',
            'from_name'  => 'Your Store Name',
        ],
    ];

Fill in `app.key` before saving. Generate one:

    php -r "echo bin2hex(random_bytes(32));"

The key signs newsletter confirm and unsubscribe tokens. Without it,
those links are signed with an empty string and will not verify.

`config/cli.php`:

    <?php
    declare(strict_types=1);

    return [
        'database' => [
            'host'     => 'localhost',
            'port'     => 3306,
            'database' => 'anaya_commerce',
            'username' => 'the_db_user',
            'password' => 'the_db_password',
            'charset'  => 'utf8mb4',
            'collation'=> 'utf8mb4_unicode_ci',
        ],
    ];

The CLI config is database-only. `app.key` from `local.php` is
visible to CLI scripts because the config stack merges `local.php`
under `cli.php`.

Set `app.debug` to `false`. Set `app.url` to the live domain with
`https://`.

---

## 7. Run migrations

    cd /home/<user>/commerceaid
    php bin/migrate.php

Expected output: one line per migration, ending with a "Done" line.
If a migration fails, stop and read the error. Do not proceed.

---

## 8. Run seeders

    php bin/seed.php

Seeders are idempotent. Running twice is safe.

A fresh install creates:

- The default store.
- The admin user: **`admin@anayaglobal.test` / `ChangeMe!2026`**.
- Currencies, roles, permissions.
- Customer groups, legal page placeholders, newsletter defaults,
  form permissions.
- Navigation menus and items.
- Header, footer, trust bar, and homepage blocks.

**Change the admin password immediately after first login.** The
seeded password is public. First action in the admin:
Settings > Users > Edit > Change password.

---

## 9. Configure cron

Two jobs.

**Newsletter queue worker** — drains `commerce_email_queue` and sends
campaigns. `--batch` accepts 1-500, default 100:

    * * * * * php /home/<user>/commerceaid/bin/send-emails.php --batch=100 >> /home/<user>/commerceaid/storage/logs/cron-emails.log 2>&1

**Abandoned-cart cleanup** — cancels unpaid orders older than N
minutes, releases reserved stock. `--minutes` accepts 1 and up,
default 60:

    */15 * * * * php /home/<user>/commerceaid/bin/cleanup-abandoned-orders.php --minutes=60 >> /home/<user>/commerceaid/storage/logs/cron-abandoned.log 2>&1

In cPanel: Cron Jobs → add both. Verify after 15 minutes by checking
the log files in `storage/logs/`.

---

## 10. Configure payment providers

All payment credentials are entered in the admin, not config files.
Settings > Store Settings > Payments.

**Stripe** — publishable key, secret key, webhook signing secret.
Stripe Dashboard webhook URL:

    https://yourdomain.com/webhooks/stripe

Events: `checkout.session.completed`,
`checkout.session.async_payment_succeeded`, `charge.refunded`.

**PayPal** — client ID, secret, webhook ID. PayPal Dashboard
webhook URL:

    https://yourdomain.com/webhooks/paypal

Events: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`.

**M-Pesa** — consumer key, consumer secret, shortcode, passkey.
Daraja dashboard webhook URL:

    https://yourdomain.com/webhooks/mpesa

**Pesapal** — consumer key, consumer secret, IPN ID. Pesapal
dashboard IPN URL:

    https://yourdomain.com/webhooks/pesapal

**Flutterwave** — public key, secret key, secret hash, redirect URL.
Flutterwave dashboard webhook URL:

    https://yourdomain.com/webhooks/flutterwave

All five webhook routes are POST and are registered in
`routes/commerce.php`. No authentication on the route; each
provider is verified by signature or by pending-request lookup in
`WebhookController`.

If the host blocks inbound HTTP from payment providers, use a
Cloudflare tunnel or a subdomain proxy.

---

## 11. Configure SMTP

The SMTP settings are in `config/local.php` (section 6). After the
site is live, verify by sending a test email from the admin.

---

## 12. Configure the storefront

In the admin:

1. **Settings > Store Settings > Branding** — store name, logo,
   favicon, colors.
2. **Settings > Store Settings > Fonts** — display, body, script
   fonts. Each selection downloads the WOFF2 locally. Wait for the
   download to complete.
3. **Settings > Legal Pages** — Impressum, AGB, Widerruf,
   Datenschutz. All four required for German stores. Checkout is
   blocked if any is missing.
4. **Settings > Tax** — the three seeded classes (Standard,
   Reduced, Zero) are ready. Add a rate for each class. Mark
   inclusive for consumer stores (PAngV § 4).
5. **Settings > Shipping** — add at least one method and one zone.
6. **Catalog > Products** — upload real products.
7. **Catalog > Categories** — create the merchant's categories.
8. **Design > Pages > Homepage** — the seeded layout has Hero,
   Trust Bar, Category Grid, New Arrivals, Best Sellers, Promo
   Panel, Newsletter. Adjust per block.
9. **Design > Header Builder** and **Design > Footer Builder** —
   the seeded nav has Home, New In, Sale plus the store's
   categories. The trust bar items are also edited here.
10. **Settings > Users** — change the admin password. Create team
    users.

---

## 13. Post-launch smoke test

Every step must pass.

**Storefront:**
- Homepage loads. Hero, trust bar, categories, products, promo,
  newsletter, footer all render.
- Category page loads with products and filters.
- Product page shows images, price, add-to-cart.
- Add to cart. Mini-cart badge updates.
- Cart page shows the item at the correct price.
- Apply a coupon. The discount line appears.
- Checkout three steps load.
- Complete checkout with a test payment method (Stripe test card
  `4242 4242 4242 4242`, or PayPal sandbox).
- Confirmation page appears. The admin Orders list shows the order
  with the correct total and status.
- Cancel the order from the admin. Stock is released in
  Catalog > Inventory > Movements.

**Search:**
- Type a product name into the header search. Autocomplete dropdown
  appears with the matching product.
- Press Enter. `/search?q=<term>` loads with results.

**Newsletter:**
- Submit the footer newsletter form. Confirmation email arrives.
- Click the confirmation link. Subscriber moves from Pending to
  Confirmed in Marketing > Subscribers.

**Cron:**
- Wait 15 minutes. Check `storage/logs/cron-abandoned.log` and
  `storage/logs/cron-emails.log`. No errors.

**Production readiness:**
- `config/local.php` has `'debug' => false` and a real `app.key`.
- `.htaccess` is deployed with CSP headers.
- SSL is active.
- `https://yourdomain.com/app/` returns 404.
- `https://yourdomain.com/config/local.php` returns 404.
- `composer check` runs clean locally against the deployed code.

If all pass, the site is live.

---

## 14. Backups

Two layers.

**Host's backups** — most cPanel hosts provide daily automated
backups. Confirm the retention period and the restore process.

**Operator backups** — export the database weekly (`mysqldump` or
cPanel's phpMyAdmin export). Download `storage/uploads/` weekly.
Store both in a separate location. The codebase is in git and does
not need separate backups. The database and the uploaded media are
the data.

---

## 15. After launch

**First week:**
- Monitor the admin dashboard daily.
- Check `storage/logs/app.log` for errors.
- Check Sales > Orders for the first real orders.
- Verify the cron logs are clean.

**First month:**
- Run `composer check` against the deployed code once.
- Review the abandoned-cart log for recovery opportunities.
- Confirm newsletter delivery and bounce handling.
- Add a second admin user for the merchant's operator.

**Ongoing:**
- Monitor disk usage (`storage/uploads/` grows with media).
- Run `php bin/cleanup-abandoned-orders.php --minutes=60` if the
  cron ever misses.
- Run `php bin/cleanup-orphan-drafts.php --dry-run` if the page
  builder refuses to publish with "child draft references missing
  parent draft." This repairs drafts orphaned by an older
  `deleteDraftTree()` behaviour (fixed in `c637fb1`).

---

## Troubleshooting

**Blank white page.** `config/local.php` is missing or has a syntax
error. Check `storage/logs/app.log`.

**Admin login fails.** The seeded admin is
`admin@anayaglobal.test` / `ChangeMe!2026`. If that was already
changed, use the password reset link on the login page.

**Checkout blocked with "Legal pages missing."** The store's country
is DE. Publish all four legal pages in Settings > Legal Pages.

**Newsletter confirmation email does not arrive.** Check SMTP
settings. If `app.key` is empty, confirmation tokens will not
verify — regenerate and redeploy.

**Cron does not fire.** Check the crontab entries in cPanel. The
path to `bin/send-emails.php` must be absolute. Test manually:
`php /path/to/bin/send-emails.php --batch=1`.

**Payment webhooks return 404.** The provider cannot reach
`https://yourdomain.com/webhooks/<provider>`. Confirm the URL in
each provider's dashboard. Some hosts block inbound HTTP from
certain countries; use a tunnel if needed.

**Page builder refuses to publish.** "child draft #X references
missing parent draft #Y" — this is the orphaned-draft bug fixed in
`c637fb1`. On an install that predates the fix, run
`php bin/cleanup-orphan-drafts.php --dry-run` then without the flag.

**Fonts do not apply after selection.** The download may not have
completed. Re-select the font and save. Check the Fonts tab to
confirm the active font.

---

End of launch guide.
