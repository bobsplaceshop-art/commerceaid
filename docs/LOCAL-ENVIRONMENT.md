# Local Environment Notes

Operational notes for this Windows 11 / WSL / Laragon setup.
Not deployment documentation; just what every session needs
to know about which shell does what.

---

## The two shells

**WSL** (`sely@DESKTOP-...`) is for authoring: heredocs,
`grep`, `find`, `cat`, `python3`, file operations under
`/mnt/c/projects/commerceaid/`.

**Laragon Terminal** (`C:\projects\commerceaid(main -> origin)`)
is for execution: PHP, Composer, Apache, MySQL CLI, the
`composer *` scripts, `bin/*.php` tools.

The two see the same files. They do not see the same
processes, the same environment variables, or the same
binaries.

---

## The rules

1. **Never run `php`, `composer`, `mysql`, or `apache` from
   WSL.** WSL has PHP but not MySQL; it can read the source
   but not execute the app.

2. **Never run `curl http://localhost:8084` from WSL.** The
   WSL-to-Windows loopback does not reach Apache. It returns
   `000`. The browser works (native Windows). Laragon's
   `curl` works (native Windows). WSL's `curl` does not.

3. **Chrome is a Windows binary.** WSL's `which chrome`,
   `ls "/c/Program Files/Google/Chrome/..."` return nothing.
   The correct discovery command is `powershell.exe -Command
   "Test-Path '...'"` from WSL, or `dir "..."` from Laragon.

4. **Path notation.** PHP on Windows accepts forward slashes
   in `is_file()` and `proc_open()`. Use `C:/Program Files/...`
   in PHP source, not `C:\\Program Files\\...`. The single
   form avoids escaping questions in heredocs.

5. **Composer scripts run from Laragon.** `composer check`,
   `composer smoke`, `composer render`. From WSL they will
   fail at the first `php` invocation or the first MySQL
   query.

---

## What lives where

| Concern | Tool | Why |
|---|---|---|
| Source edits | WSL | Native Linux tooling |
| `composer install` | Laragon | Needs PHP |
| `php bin/migrate.php` | Laragon | Needs PHP + MySQL |
| `php bin/seed.php` | Laragon | Needs PHP + MySQL |
| `php bin/smoke.php` | Laragon | Needs PHP + MySQL |
| `php bin/render-harness.php` | Laragon | Needs PHP + Chrome |
| `composer check` | Laragon | Runs PHP |
| `curl localhost:8084` | Laragon | WSL loopback fails |
| `curl test.awaloglobal.com` | Either | Real network, both reach it |
| `mysql` CLI | Laragon | WSL has no MySQL client |
| `git` | Either | Filesystem is shared |

---

## Chrome

Chrome is at:

    C:/Program Files/Google/Chrome/Application/chrome.exe

Edge (fallback, CDP-compatible) is at:

    C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe

Both are native Windows. `bin/render-harness.php` spawns
Chrome via `proc_open`, connects to its debug port on
`127.0.0.1`, and drives it over CDP. The harness works only
from Laragon.

The Chrome path is configurable in `config/local.php`:

    'render' => [
        'chrome_path' => 'C:/Program Files/Google/Chrome/Application/chrome.exe',
        ...
    ],

---

## Apache and the tunnel

Apache listens on `localhost:8084` (native Windows). The
storefront and admin panel are reachable at
`http://localhost:8084/`.

`https://test.awaloglobal.com/` is a Cloudflare tunnel to
the same Apache. It exists because the payment provider
webhooks (Stripe, PayPal, M-Pesa, Pesapal, Flutterwave) need
a publicly reachable callback URL. The tunnel is not needed
for the render harness; local Apache is faster.

---

## Stale file metadata

If a JS or CSS edit does not appear to take effect, and
Apache is serving an old version despite hard reloads and
Apache restarts, see `docs/REMEMBER.md` Pattern 38.

The short version: `versioned_asset()` uses `filemtime()`,
which reads stale metadata when the file was written from
WSL. The fix is to rename the file (a URL the browser has
never seen) or to edit from the Windows side once.

---

## See also

  - `docs/REMEMBER.md` Pattern 38 (WSL-to-Windows cache
    staleness), Pattern 39 (literal `?>` in a PHP comment
    closes the block), Pattern 40 (the cascade check has
    four directions).
  - `docs/stages/015_render-harness-plan.md` (the harness
    plan).
