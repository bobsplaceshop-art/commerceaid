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

### Before every patch (mandatory)

Before writing any patch that modifies an existing file, run the
cascade check. Full rule in `docs/REMEMBER.md` Pattern 23, summary
here:

1. Read the entire file, not just the target lines.
2. List what the file depends on.
3. Grep for every reference to the file's class name, method
   names, and public properties.
4. State the cascade risk explicitly. Name what could be affected
   and confirm it is not.
5. Patch. Include downstream updates in the same patch if needed.

Two commands to run before every patch:

    # Who does this file call out to?
    grep -n "^use \|extends \|implements \|new \|::" <file>

    # Who calls into this file?
    grep -rn "<ClassName>\|<methodName>" app/ routes/ resources/

The first tells you the file's outward dependencies. The second
tells you who might be affected by a change.

Skip this step only if the change is a pure addition (a new
method, a new class) that no existing caller can reach. Even
then, the return-shape exception applies: if a new method returns
an array that a caller will assume a shape for, state the shape
in the annotation.

### On every file write

After writing or editing a PHP file, run:

    php bin/verify-file.php <path> [--view] [--expect=X]

This checks: file exists, no backticks, no mangled chars, no
flash reads (if --view), any expected substrings. Then it runs
the full project linter.

### Post-patch verification (mandatory)

After every patch, verification has two parts, both required:

1. **PHPStan scoped to the file.** Confirms the annotations are
   syntactically correct and resolved.
2. **`git status --short <file>`.** Confirms the file is actually
   modified on disk.

PHPStan alone is not proof. A cached analysis can report clean on
a file the patch never touched. If `git status` does not show the
file as `M`, the patch did not run. Stop and diagnose before
moving on.

This rule was added after the Config.php annotation was verified
via PHPStan but never actually written to disk. The next batch
scan caught it. The `git status` check would have caught it in
seconds.

### Every batch ends with a directory-level scan

At the end of each batch of files, run PHPStan against the entire
directory, not just each file.

    php vendor/bin/phpstan analyse app/Core app/Helpers --memory-limit=512M

A directory-level scan catches two things per-file scans miss:

1. Files that were silently skipped in the batch.
2. Stale entries in `phpstan.neon`'s `ignoreErrors` section.
   PHPStan reports unmatched ignores as errors, which only
   surfaces when the whole directory is scanned.

When the directory scan is clean, the batch is coherent. Commit
it. Do not proceed to the next batch on a per-file clean only.

### Annotation rules learned in Batch 2

Four rules from the second batch of the PHPStan level 6 pass. Each
came from a specific miss and cost a round-trip.

**1. PDO-bound params are `array<int|string,mixed>`, not
`array<int,mixed>`.** PDO's `execute()` accepts both positional
(`[1, 2]`) and named (`[':id' => 1]`) bindings. Annotating
`Database::insert()` as `array<int,mixed>` broke every caller
that passes named bindings (found in StoreRepository).
The correct shape is `array<int|string,mixed>` on every method
that feeds PDO.

**2. Methods that take AND return arrays need both annotations
in one patch.** The `validate()` methods on four controllers
had two errors each - a `$data` param error and a return type
error. PHPStan reported the param error first and suppressed the
return error until the param was fixed. Patching only the param
produced a second round-trip. When a method returns an array
shape, add both annotations in the same anchor.

**3. Existence checks must be anchored to the specific method,
not the file.** A check like `if '@return array<string,string>'
in content` matched a *different* method's annotation (the
`collectInput()` helper also returns `array<string,mixed>` in
some cases, and had already been annotated). The correct check
is a two-line sequence that uniquely identifies the target
method signature.

**4. Stale-ignore checks require a FULL project scan.** The
`app/Core + app/Helpers` directory scan that preceded the Batch 1
ignore cleanup did not see the rest of the codebase. Removing
five ignore patterns as "stale" was wrong - four of them still
had live matches in files outside Core/Helpers. Directory scans
prove a batch is locally clean. Only a full-project scan proves
an ignore pattern is truly stale. Every ignore pattern was
restored in Batch 2 with a documented reason for the removal
attempt.

These four rules apply to every remaining batch. Rule 1 and
rule 2 are the highest-value - they apply to any method that
touches PDO or that validates and returns an error map.

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

## The CSS Override Pattern

When adding new styling to an existing component, edit the
original rule in place. Do not append a revised version at
the bottom of the file.

The 008B.2 addendum did the latter. It appended a new
`.sf-footer-columns-inner` block at the bottom with three
rules inside media queries. Two additional definitions were
left untouched earlier in the file. The result: five
competing rules for one selector, and the effective cascade
was only knowable by reading the entire file top to bottom
and understanding source-order precedence.

The bug that surfaced: mobile footer was left-aligned with
an empty right gutter. Reading any single rule in isolation
looked correct. Only the whole stack revealed that an
unqualified `grid-template-columns: 1fr` at line 867 was
overriding the intended two-column mobile layout from
line 782.

**The rule:**

  - Edit the original rule. Do not append a revision.
  - If a rule needs to change at a breakpoint, wrap the
    change in a media query and place it next to the original.
  - Delete superseded rules when editing. A rule that is
    never meant to apply is not "kept for safety" - it is a
    landmine for the next reader.
  - One selector, one source of truth.

**The test:** if you have to read the file top-to-bottom to
predict what a selector will do, the cascade has a problem.
Consolidate.

**Where we hit it:** Storefront footer, 008B.2 addendum,
surfaced during 008C testing.

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
