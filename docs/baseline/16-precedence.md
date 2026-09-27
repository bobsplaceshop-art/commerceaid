# 16. Precedence

Section of: [BASELINE](../BASELINE.md)

## What This Baseline Supersedes

This baseline is the authoritative source for how the codebase
behaves as of Stage 007B M2 complete. It supersedes:

- Any individual file read that contradicts it.
- Any earlier assumption stated in chat.
- Any pattern inferred from a non-authoritative source.
- Any comment in the code that contradicts an actual behavior.

When the baseline and the code disagree, the code is right and the
baseline needs to be corrected. The baseline is a map, not a law.

## What Supersedes This Baseline

Three things take precedence over this document:

1. Source code. The files themselves are always the final word.
2. The Router and Kernel. If the pipeline does something the
   baseline does not describe, the pipeline wins.
3. The database schema. If a column exists that the baseline does
   not mention, the column is real.

When you find a discrepancy, fix the baseline in the same commit
that fixes the code.

## Update Rule

Any change to a locked pattern requires updating the relevant
section file in the same commit. The section files are the source
of truth. The index at docs/BASELINE.md is only a map.

Changes that trigger an update:

- A controller stops extending AdminController
- Flash handling moves from the layout to the view
- View rendering switches away from dot-notation
- Route middleware order changes
- A new global helper is added
- The provider stops binding a service as a singleton

Changes that do not trigger an update:

- New controller added (follows existing conventions)
- New repository added (follows existing conventions)
- New view added (follows existing conventions)
- New route added (follows existing conventions)
- Typo fixes

## When to Break a Rule

If a rule in this baseline feels wrong for a good reason:

1. Stop and say so.
2. Write the reason down. Why is the existing rule wrong? What
   problem does it cause? What breaks if we keep it?
3. Update the baseline section file first.
4. Then write the code.

Silent rule-breaking is a bug. Every divergence between the
baseline and reality is a future incident waiting to happen.

Explicit rule-changing is progress. Documented changes become part
of the baseline for the next person.

## Time Discipline

Every timestamp stored in the database is written by MySQL
(`NOW()`, `DATE_ADD()`). Every comparison of stored timestamps
happens in MySQL. PHP never computes a timestamp that MySQL
will later compare.

Why: PHP and MySQL have independent clocks. They can drift
because of timezone configuration, DST transitions, container
vs host time, or a server move. A PHP-computed `expires_at`
compared against a MySQL `NOW()` will silently fail on any
drift. The failure mode is quiet - the write looks correct,
the read looks correct, but the comparison is wrong.

The rule:

    DO     - Let MySQL write timestamps: NOW(), DATE_ADD(),
             DATE_SUB(), TIMESTAMPDIFF()
    DO     - Compare stored timestamps in SQL: WHERE
             expires_at > NOW()
    DO     - Read timestamps into PHP and format them for
             display
    DON'T  - Compute a date in PHP (date(), time() + N) and
             store it in a column that MySQL will later compare
    DON'T  - Convert a MySQL timestamp to a PHP timestamp, do
             arithmetic, convert back, and store

Exceptions:

  - Session idle timeout: sessions are file-based, so PHP
    time() is PHP-against-PHP. Correct as-is.
  - Pure display formatting: a date read from the DB can be
    formatted by PHP for a view. That is a read, not a
    comparison.
  - Log file timestamps: those are PHP's log lines, not
    database rows. PHP's clock is authoritative for its own
    files.

Where this was violated:

  - commerce_customer_password_resets.expires_at was computed
    in PHP at insert time and compared against MySQL NOW()
    at read time. In a dev environment where PHP was UTC and
    MySQL was Africa/Nairobi, every token appeared expired
    the moment it was created. Fixed by making MySQL compute
    the expiry: DATE_ADD(NOW(), INTERVAL N MINUTE).

Where this is done correctly:

  - RateLimiter (Platform\Support): INSERT with DATE_ADD,
    SELECT with TIMESTAMPDIFF, DELETE WHERE reset_at < NOW().
    All three use MySQL's clock.
  - Every created_at, updated_at, used_at, last_login_at,
    approved_at, rejected_at column: DEFAULT
    CURRENT_TIMESTAMP or explicit NOW() in the INSERT or
    UPDATE.

Relationship to precedence: this is a rule about code, like
the cascade check. It does not override source code. When code
and this rule disagree, the code is examined and either the
code or the rule is corrected - never silently diverged.

---

## Cascade Check Before Every Patch

Before writing any patch that modifies an existing file, run the
cascade check. It is mandatory. It applies to every patch, not
just patches to "closed" or "risky" code.

The check, in order:

1. **Read the entire file.** Not just the target lines. A patch
   written against a fragment misses context.
2. **List what the file depends on.** Imports, parent classes,
   injected services, interfaces implemented. Anything it calls
   out to.
3. **Grep for every reference to the file.** Class name, method
   names, public properties. This is the "who calls me" list.
4. **State the cascade risk explicitly.** Not "no impact" -
   name what could be affected and confirm it is not. Silent
   gaps in this step are what the rule exists to prevent.
5. **Patch.** If a downstream file needs updating in the same
   patch, include it. If the impact is wider than expected,
   stop and discuss before patching.

**Why this is mandatory:** the bugs that have cost the most
time in this project are not the ones in the file being edited.
They are in the callers, siblings, or downstream consumers who
assumed the old shape. A grep costs seconds. A missed cascade
costs a debugging cycle.

**Exception class:** return-shape annotations. Adding
`@return array<string,mixed>` to a shared method does not break
runtime, but breaks PHPStan at every caller that assumed a
narrower shape. Every return-type annotation on a shared method
must be checked against callers in the same patch.

**Relationship to precedence:** the cascade check does not
override the code. It is a procedure, not a rule about the code.
Source code is still the final authority; the cascade check is
how you reach a correct patch.

See `docs/REMEMBER.md` Pattern 23 for the incident history and
the full worked example.

---

## The Cost of a Stale Baseline

A stale baseline is worse than no baseline at all. If the document
says one thing and the code does another, the next person to touch
the code will follow the document and ship a bug.

The rule is simple: if you notice a mismatch between the baseline
and reality, either fix the baseline or fix the code. Never leave
the two out of sync.

## Cross-Reference

Every section file links back to the index at docs/BASELINE.md.
The index lists every section. There is no hidden content outside
the index.

If a section file is deleted, its entry in the index must be
removed in the same commit.
