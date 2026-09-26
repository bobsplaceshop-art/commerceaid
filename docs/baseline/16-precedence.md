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
