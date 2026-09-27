# Debugging Workflow

**Status:** Locked 2026-09-27
**Applies to:** Every dev session

This document describes how to catch bugs during development
before they reach a browser test pass. The tools it describes
are cheap to run and cover most of the failure modes we've hit
in prior stages.

---

## The Tools

| Tool | What it catches | Where it runs |
|---|---|---|
| `bin/watch-log.sh` | PHP warnings, notices, exceptions, anything logged | A dedicated terminal |
| Dev overlay | JS errors, unhandled promise rejections, console.error | Automatic, in the browser, when `app.debug` is on |
| Browser DevTools | Network failures, CSP violations, per-request errors | F12 on demand |
| `composer lint` | PHP syntax errors on every file | On demand |
| `composer analyse` | Type errors, undefined methods, missing classes | On demand |
| Pre-commit hook | Runs the linter before every commit | Automatic on `git commit` |

---

## The Baseline Workflow

During a dev session, keep **three terminals** open:

### Terminal 1 — WSL (authoring)

    cd /mnt/c/projects/commerceaid

Run heredocs, greps, file operations here. This is where files
are written.

### Terminal 2 — WSL (log watcher)

    cd /mnt/c/projects/commerceaid
    bash bin/watch-log.sh

Runs continuously. Any error, warning, or explicitly-logged
message streams into view in real time. Leave it open while
testing in the browser.

### Terminal 3 — Laragon Terminal (execution)

    cd C:\projects\commerceaid

Run migrations, seeders, composer commands here. Also where
`php bin/migrate.php`, `composer analyse`, and `composer lint`
happen.

### Browser — with DevTools open

Open DevTools (F12) before you start testing. Keep the Console
and Network tabs available. The dev overlay will surface JS
errors on the page itself, but DevTools gives the full picture
for network and CSP issues.

---

## What Each Tool Actually Catches

### Real bugs we've hit and where they'd have surfaced

| Bug | Caught by |
|---|---|
| 404 on `/` after route file moved | DevTools Network — a 404 appears on the page |
| Store model missing | Log watcher — array access warnings streamed in real time |
| Reject button quote collision | Dev overlay — silent JS parse error on click |
| Dashboard false-active | Not caught — silent visual bug, only visible by inspection |
| Nav labels wrapping | Not caught — visual, needs eyeballing |
| loremflickr images blocked | DevTools Console — CSP violation message |
| Google Fonts CSP violation | DevTools Console — CSP violation message |
| Category label not passed | Not caught — silent, no error produced |

**The pattern:** the tools catch ~60% of bugs automatically.
The remainder need visual inspection or a test pass. That's
fine. The point is to catch the automatic-detectable class
without manual effort.

---

## When to Run What

### On every file write

After writing or editing a PHP file, run:

    php bin/verify-file.php <path> [--view] [--expect=X]

This checks: file exists, no backticks, no mangled chars, no
flash reads (if --view), any expected substrings. Then it runs
the full project linter.

### On every commit

The pre-commit hook runs `bin/lint.php` automatically. You'll
see output like:

    Linting PHP files...
    Checked NNN file(s).
    All files passed syntax check.

If it fails, the commit is blocked. Fix the error, then commit.

### Before a test pass

Run the full check:

    composer check

This runs the linter and PHPStan. Both should pass with zero
errors. If PHPStan finds something, decide: fix or ignore with
reason.

### When something looks wrong

1. Check the log watcher terminal first. Most server-side
   issues appear there.
2. Check DevTools Console for JS and CSP errors.
3. Check DevTools Network for 404s, timeouts, or failed
   requests.
4. If still stuck, check `storage/logs/app.log` directly with
   `tail -100`.

---

## Debug Mode

The dev overlay only renders when `config('app.debug')` is true.
This is set in `config/local.php` (gitignored). During
development, keep it `true`. In production, set it to `false`.

With `debug = true`:

- The dev overlay renders in the top-right corner of every page
- It catches `window.onerror`, `unhandledrejection`, and
  `console.error`
- Errors display for 8 seconds then auto-dismiss
- Up to 5 items stack at once

With `debug = false`:

- The overlay script still loads but never renders
- Zero runtime cost

To verify debug mode is on:

    Browser Console:
    document.getElementById('ra-debug-overlay') !== null

Or check `config/local.php` directly.

---

## Common Scenarios

### "The page loads but something looks wrong"

Visual inspection. The dev overlay won't catch layout issues.
Open DevTools Elements tab, inspect the affected region.

### "A button click does nothing"

Check the Console. A JS error from the click handler appears
either in DevTools or the overlay. Common cause: an inline
onclick attribute that got truncated by quote collision
(see Pattern 13 in REMEMBER.md).

### "A request returns the wrong thing"

DevTools Network tab. Click the failing request, inspect the
Response. The dev overlay only catches JS errors, not HTTP
errors.

### "The page is blank"

The log watcher shows PHP errors. If the log is empty, the
issue is client-side — check the Console for CSP violations
or missing assets.

### "An AJAX call silently fails"

DevTools Network. Any fetch or XHR that returns non-200 will
appear in Network. The dev overlay would catch an
`unhandledrejection` if the promise wasn't caught.

---

## When to Add a Test

The tools above catch automatic-detectable bugs. They don't
catch:

- Logic errors (`$total = $subtotal + $tax` when it should be
  `* $taxRate`)
- Business rule violations ("pending customers can't check out")
- Regression — a change breaks something that used to work

Those need **unit tests** (assert behavior of a specific
method) or **integration tests** (assert the full request
cycle). Both are deferred to a future stage.

Until then, the manual test pass is the last line of defense.
See the test protocol at the end of every stage doc.

---

## The Anti-Pattern to Avoid

Do not skip the checks because "it's a small change."

The bugs that cost hours are the ones that looked small.
The dev overlay and log watcher exist specifically because
small changes have non-obvious side effects.

If you're tempted to bypass the hook with `--no-verify`,
stop and reconsider. The hook takes 1-2 seconds. A bug
that ships takes much longer.

---

## See Also

- `docs/git-workflow.md` - branching and commit workflow
- `docs/REMEMBER.md` - patterns that led to these tools
- `docs/BASELINE.md` - verified codebase conventions
- `bin/watch-log.sh` - the log watcher script
- `bin/lint.php` - the syntax linter
- `bin/verify-file.php` - the post-write verifier
