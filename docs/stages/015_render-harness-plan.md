# Stage 015 - Responsive Render Harness

**Status:** Planning
**Opened:** 2026-10-17
**Depends on:** Stage 014 (the responsive
layout system, the four breakpoints, the
static checker)
**Blocks:** Nothing. Independent of every
pending sub-stage.
**Consumes:** Chrome (or Edge) on the Windows
dev machine, driven by a PHP script running in
Laragon Terminal.

---

## Purpose

A runtime sibling to `bin/check-responsive.php`.

The static checker walks the view files and
enforces three rules that do not require a
browser: no inline `@media`, no hardcoded
widths above 375px, every admin table wrapped
in a `data-responsive` attribute. Those rules
catch the source-level violations.

The static checker cannot catch the rendered
problems. A flex container whose child cannot
shrink below its content. A grid with no
fallback at a width nobody tested. A
`<picture>` wrapper that changes the layout of
a sibling. Those are only visible after the
CSS cascade runs, the DOM is constructed, and
the layout engine places every box.

Two incidents in the codebase are the plan's
own evidence:

  **The setup nav overflow** (`2026-10-13-6.md`).
  A flexbox child refused to shrink below its
  content, and the parent grid column could not
  shrink either, so the setup sub-nav overflowed
  the viewport on mobile. `overflow-x: auto` was
  present on the wrong element. The code looked
  correct. Two audit sessions missed it. The DOM
  showed `scrollWidth > clientWidth` on the
  container. The static checker cannot see it.

  **The category hero clip** (`2026-09-30-4.md`).
  A `<picture>` wrapper changed the layout of
  its child. The first diagnosis was a CSS
  reasoning from the source files. It was wrong.
  The DOM inspection — computed width, aspect
  ratio, container measurement — was correct.
  The static checker cannot see it.

Both are horizontal-overflow problems. Both
would be caught by one check: does the rendered
page have a horizontal scrollbar when it should
not.

The harness is the tool that runs that check
against every route at four viewports. It
catches the class the static checker cannot.

---

## The engine

Chrome headless, driven by CDP (Chrome DevTools
Protocol) over WebSocket, spawned by a PHP
script. Every layer is present on the machine
today:

  Binary:      C:\Program Files\Google\Chrome\Application\chrome.exe
               (confirmed, 2026-10-17)
  Fallback:    C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe
               (present, but not used unless Chrome is
               removed)
  Client:      A PHP script the harness spawns. It
               speaks CDP over WebSocket using PHP's
               stream_socket_client, upgraded to a
               WebSocket handshake by hand. No Composer
               dependency.

The engine choice matches the codebase's own
pattern for external processes. `bin/check.php`
spawns subprocesses with `proc_open`. The
harness does the same for Chrome. The CDP client
is ~150 lines of PHP, comparable to what
`bin/smoke.php` does with its own test database.

No Puppeteer. No Playwright. No Node.js. The
platform refuses Node.js by design; every headless
browser automation library in the world assumes
Node. The harness speaks CDP directly.

Edge is CDP-compatible. If Chrome is removed from
the machine, the harness's `browser_path` config
key points at `msedge.exe` and nothing else
changes. This is a documented fallback, not a
default.

---

## The execution environment

**Laragon Terminal.** Not WSL.

The machine is Windows 11. Laragon runs Apache,
PHP, and MySQL natively. Chrome is a Windows
application at a Windows path. WSL cannot see it
(the three `ls` checks from the earlier message
returned nothing; `powershell.exe` from WSL is
what finally located it).

The harness is a native Windows tool, like
`composer check` and `composer smoke`. It runs
from `C:\projects\commerceaid>` in Laragon
Terminal. Its PHP process spawns Chrome as a
Windows subprocess. Its CDP client speaks to the
debug port on `127.0.0.1`. Its HTTP client
navigates to `http://localhost:8084/`.

The plan names this explicitly because every
prior session that touched a shell has had the
WSL / Laragon distinction matter. The harness
executes in Laragon. The session file for
Session 1 says so at the top.

---

## The target

`http://localhost:8084/`. Apache native,
reachable from Laragon Terminal. The Chrome
subprocess sees it the same way.

The tunnel (`https://test.awaloglobal.com/`) is
reachable from WSL and from outside the machine
(it serves the same app, 200). The harness does
not use the tunnel by default. Local Apache is
faster, has no TLS handshake per navigation, and
returns the same HTML. The tunnel is a documented
fallback for two cases: testing that the tunnel
itself renders correctly, and testing a route
that requires an external user agent to fetch (a
webhook receiver, a payment return URL that
cannot be driven from inside the browser).

The `config/local.php` key `render.base_url`
carries the target. Default
`http://localhost:8084`.

---

## The routes

Tier 1: every `router->get` route in the app
whose path does not contain a `{`.

That filter is the whole rule. Two hundred
routes exist in the four files under `routes/`.
Half are POST. Of the GET routes, most are
static paths (`/`, `/products`, `/cart`,
`/checkout`, `/login`, `/commerce/ca-admin/products`,
etc.). The dynamic-id routes
(`/products/{slug}`, `/orders/{orderNumber}`,
`/commerce/ca-admin/customers/{id}`) are the
second tier.

The harness iterates the tier-1 set. The count
is ~100 (the exact number is computed at build
time by reading the route files; the harness
prints it in its opening summary).

The dynamic-id routes are deferred to a
follow-up session. They need fixtures — a real
product, a real order, a real customer id — that
change as the dev database evolves. The static
routes do not.

### Admin login

The admin GET routes require authentication.
The harness logs in once at the start of the
run:

  - POST `/login` with `admin@anayaglobal.test` /
    `ChangeMe!2026`.
  - Reuse the session cookie for every subsequent
    navigation.

The cookie is a Chrome session cookie. The
harness sets it via CDP's `Network.setCookie`
before the first admin navigation. One call, one
login, every route after that has the session.

The credentials are a config key
(`render.admin_email`, `render.admin_password`),
defaulting to the dev credentials the README
already documents.

### Storefront routes

Public. No authentication. The harness
navigates directly.

---

## The viewports

Four. The same four Stage 014's test protocol
names:

  1440   desktop large
  1024   tablet landscape
  768    tablet portrait / mobile landscape
  375    mobile

The harness iterates the tier-1 routes at each
viewport. That is ~100 routes × 4 viewports =
~400 navigations. Each navigation is:

  - Emulation.setDeviceMetricsOverride with
    width, height 900, deviceScaleFactor 1,
    mobile false.
  - Page.navigate to the route.
  - Page.loadEventFired (wait for the load event).
  - A 200ms settle after load (fonts, lazy
    images).
  - Runtime.evaluate the check.

One navigation takes ~500ms including the settle.
400 × 500ms = ~3.5 minutes for the whole run.
Acceptable for a manual or nightly tool.

---

## The check

    document.documentElement.scrollWidth > window.innerWidth

One `Runtime.evaluate` call per navigation. The
return value is a JSON object:

    {
      "scrollWidth": 1440,
      "clientWidth": 1440,
      "innerWidth": 1440,
      "overflow": false
    }

`scrollWidth > clientWidth` is the horizontal
overflow signal. On a page that fits, the two
are equal. On a page that overflows,
`scrollWidth` is larger.

A second signal the harness records: the
`document.body.scrollWidth` and
`document.body.getBoundingClientRect().width`.
These distinguish the document-level overflow
from a container-level overflow. The first is
the harness's primary check. The second
documents where it happened, so the fix
session starts with a location, not a search.

The check tolerates a 1px rounding error. A
page with `scrollWidth = 1441` and
`clientWidth = 1440` is reported only if the
delta is ≥ 2px. The scrollbar itself adds a
pixel in some Chromium versions; the tolerance
is what makes the check usable.

The check exempts three known-intentional
horizontal scrolls:

  - `.ra-table-responsive-wrap[data-responsive="scroll"]`
    inside its own scroll container.
  - `.ra-tab-strip` and its siblings (the four
    tab-strip families Stage 014 Session 3 made
    scroll horizontally).
  - `.sf-checkout-line-info` (a known 1px
    rounding case from Stage 014).

The exemptions are a named list in the harness
source. Adding one is one entry. The list is
the harness's only configuration besides the
viewport set and the route filter.

---

## The output

Same shape as `bin/check-responsive.php`:

    Checking rendered layout...

      Routes checked: 98
      Viewports:      1440, 1024, 768, 375
      Navigations:    392
      Violations:     3

      /products                   @ 375   scrollWidth 412 > clientWidth 375
      /categories/women           @ 768   scrollWidth 790 > clientWidth 768
      /commerce/ca-admin/setup    @ 375   scrollWidth 410 > clientWidth 375

    FAILED: 3 violation(s).

Exit codes:

  0   no violations
  1   at least one violation
  2   the harness could not run (Chrome missing,
      Apache not reachable, login failed)

Exit code 2 is the harness saying "I cannot
test." That is distinct from "the test found
problems." A CI pipeline that treats exit 1 as
a build failure treats exit 2 as a
configuration failure.

---

## Composer wiring

    "render": "php bin/render-harness.php"

Separate from `composer check`. Chrome is
~4.5 MB. Spawning it, running 400 navigations,
and waiting for layout on every commit is too
slow for the check command.

Run manually, or on a nightly schedule in CI.
The plan names the schedule as a follow-up, not
part of Session 1.

---

## The two named test cases

The harness is accepted as working when it
catches the two incidents the plan's Purpose
section names, reproduced as deliberate bugs.

Test 1 — the setup nav overflow:

  1. Add `width: 2000px` to `.ra-setup-nav` (a
     one-line CSS change to reproduce the layout
     bug the session named).
  2. Run the harness.
  3. Confirm it reports `/commerce/ca-admin/setup`
     at 375 with `scrollWidth > clientWidth`.
  4. Revert the CSS change.

Test 2 — the category hero clip:

  1. Add `min-width: 500px` to
     `.sf-category-hero img` on a category page.
  2. Run the harness.
  3. Confirm it reports a `/categories/...` route
     at 375 with `scrollWidth > clientWidth`.
  4. Revert the CSS change.

The two tests prove the harness catches both
incident classes. If either fails to reproduce,
the harness's check or exemption list is wrong
and gets corrected before the session closes.

---

## Sessions

Two.

### Session 1 - the harness

  - `bin/render-harness.php` — the CLI entry.
    Flags, config, lifecycle, the report.
  - `bin/render-harness-cdp.php` (or an
    internal class) — the CDP WebSocket client.
  - `config/local.php.example` gains three
    keys: `render.base_url`,
    `render.admin_email`,
    `render.admin_password`.
  - `composer.json` gains the `render` script.
  - `docs/LOCAL-ENVIRONMENT.md` gains a
    four-line note on Chrome's path and the
    Laragon-only execution rule. This is the
    file the earlier plan proposed writing; the
    harness is the reason to write it.
  - The harness's first run reports whatever
    is currently broken (probably 0-3
    violations; the responsive stage was
    thorough).

### Session 2 - the fix pass

  - The harness's first run against the live
    code is the input. Every violation is
    either a real layout bug (fix it) or a
    false positive (add the element to the
    exemption list, with a comment naming why).
  - The two named test cases are run.
  - The composer wiring is verified in a
    clean shell.
  - The session file records the initial
    finding count and the fix count.

The stage closes after Session 2.

---

## What is not in this stage

  - **Visual diff.** The harness checks for
    horizontal overflow, not pixel correctness.
    A screenshot diff is a separate tool
    (BackstopJS, Percy, Chromatic — all
    Node-based, so a PHP equivalent is its own
    small stage if it ever becomes necessary).
  - **Interaction testing.** Clicking buttons,
    filling forms, submitting. That is
    Playwright / Cypress territory, and it
    requires a test runner the platform does
    not have. The smoke harness covers the
    critical logic paths; the render harness
    covers the layout. Interaction coverage is
    a third tool if it is ever needed.
  - **Accessibility audit.** Axe-core in
    headless Chrome is the standard. It is a
    separate stage.
  - **Performance metrics.** Lighthouse in
    headless Chrome. Separate stage.
  - **Dynamic-id route coverage.** Tier 2
    deferred. The fixtures needed for
    `/products/{slug}`, `/orders/{orderNumber}`,
    `/commerce/ca-admin/customers/{id}` change
    as the dev database evolves. The harness
    covers the static routes first, and adds
    the dynamic ones when a fixture manager
    lands.
  - **Multi-theme coverage.** The check runs
    in light theme only. A dark-theme pass is
    a second run with a different cookie
    setting. Deferred to a follow-up if the
    first pass proves useful.

---

## Open questions

  1. **The `render.base_url` fallback to the
     tunnel.** Is `http://localhost:8084` the
     only target the harness ever needs, or does
     the tunnel case need first-class support?
     The plan names local as the default and
     tunnel as a documented override. If the
     harness ever runs from a machine that is
     not the dev machine (a CI runner), the
     tunnel is the only reachable target.

  2. **The settle time.** 200ms after load may
     not be enough on routes with lazy-loaded
     images. A more reliable signal is
     `document.readyState === 'complete'` plus
     a fixed wait. The 200ms is a starting
     estimate; the harness's first run against
     routes with images will tell whether it is
     sufficient. Adjusting it is a one-line
     change.

  3. **The scrollbar and the tolerance.** A 1px
     difference happens in some Chromium versions
     on pages with an overlay scrollbar. The
     plan's 2px tolerance is the starting
     value. If false positives appear at 1px
     deltas, the tolerance increases. If false
     negatives appear (a real bug at exactly
     1px overflow), the tolerance decreases.
     The two named test cases will not have a
     tolerance problem; the live code will.

  4. **The admin login credential storage.**
     Default dev credentials in
     `config/local.php.example` are correct for
     the harness. Production credentials must
     not be stored there. The harness's login
     flow is the dev store's admin user only.
     A production deployment runs the harness
     in CI with a dedicated test store, not the
     production store.

These are resolved in the plan's decisions
above. Reviewed at Session 1 open.

---

## See also

  - `docs/stages/014_responsive-layout-plan.md` —
    the stage whose tail items the harness
    closes permanently.
  - `bin/check-responsive.php` — the static
    sibling. Its output shape is the model.
  - `bin/smoke.php` — the smoke harness. Its
    boot pattern is the model for the app
    boot (session_name on CLI).
  - `docs/sessions/2026-10-13-6.md` — the
    setup nav overflow incident.
  - `docs/sessions/2026-09-30-4.md` — the
    category hero clip incident.
  - `docs/REMEMBER.md` Pattern 38 — the WSL to
    Windows cache staleness that requires care
    when editing CSS during a fix pass.
