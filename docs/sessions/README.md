# Session Log

One file per working session. Kept forever. Mostly links.

`git log` answers "what changed." This directory answers
"why, and what next." Both are needed. They are not the same
thing.

## The Protocol

Every session ends with a close pass. No exceptions. Even a
short session gets an entry.

Five steps:

1. Write the session file. `YYYY-MM-DD.md`.

   **Same-day sessions:** if a second session happens on
   the same calendar day, the second file is named
   `YYYY-MM-DD-2.md`. A third is `YYYY-MM-DD-3.md`.

   **Before writing, check whether the target file
   already exists.** A previous session on the same day
   may have already used the bare `YYYY-MM-DD.md` name.
   Overwriting would lose the earlier session's record.

   Detect:

       ls docs/sessions/ | grep "$(date +%Y-%m-%d)"

   If the bare name is taken, use the -2 suffix. The
   index table at the bottom of this file is the
   canonical list of what exists.

2. Update the distilled docs. Patterns in `docs/REMEMBER.md`.
   Current stage in `docs/STATUS.md`. Sequence in
   `docs/ROADMAP.md`. Stage plan in `docs/stages/`. The
   session file links to those; it does not replace them.

3. Commit the session file. A docs commit, on its own.

4. Push. `git push backup main`. Then check the backup is in
   sync.

5. Leave the tree clean. No uncommitted work. If work is in
   flight, commit to a scratch branch and note it in the
   session file.

## What Goes In A Session File

Short. 30-80 lines. Five sections, in order:

- Worked on - one line per thread.
- Shipped - commit hashes and one-line descriptions.
- Bugs found - symptom in one line, pattern number in
  `docs/REMEMBER.md`.
- Decisions made - ADRs added, patterns locked.
- Next session opens with - the one line the next session
  reads first.

## What Does Not Go In A Session File

- Full commit messages. `git show <hash>` has them.
- Full bug analyses. `docs/REMEMBER.md` has them.
- Full stage scopes. `docs/stages/` has them.
- Chat transcripts. Never. Summaries only.

## The Index

| Date | Session | Focus |
|------|---------|-------|
| 2026-09-28 | 001 | 008G close - search, gallery, responsive audit, four admin button bugs |
| 2026-09-29 | 002 | 008G.1 Wishlist - full stack, Patterns 28 & 29 added |
| 2026-09-29-2 | 003 | 008G.2 Recently Viewed - full stack, no new patterns |
| 2026-09-29-3 | 004 | 008G.3 Message Center - self-contained, Pattern 30 added |
| 2026-09-30 | 005 | 008G.4a Upload Core - media pipeline, compliance tracker, 008I + 008J logged |
| 2026-09-30-2 | 006 | 008G.4a.1 Media Library - store scoping via entity_files |
| 2026-09-30-3 | 007 | 008G.4a.2 session 1/3 - product media backend (schema, model, repo, category image) |
| 2026-09-30-4 | 008 | 008G.4a.2 session 3/3 - storefront consumption (real images on every surface) |
| 2026-10-04 | 009 | 008G.4c sessions 3/4a/4b/5 - self-hosted fonts, icons, theme fixes |
| 2026-10-05 | 010 | 008G.5 reviews, 008G.4b PWA, 008G.4d category filters, 008J container |
| 2026-10-06 | 011 | 008J close, 008K.1 orders, 008K.2 customers, 008H pause |
| 2026-10-07 | 012 | 008K.3 admin users, password reset, redirect-target fixes |
| 2026-10-10 | 013 | 008K.4 admin roles - RBAC surface, permission vocabulary, multi-store |
| 2026-10-10-2 | 014 | Product page bug arc - swatch gate, gallery reader split (008K.5 s3 in flight) |

**Naming:** the `Date` column matches the file name
(without extension). If two sessions happened on the same
calendar day, the second is `YYYY-MM-DD-2.md` and the third
is `YYYY-MM-DD-3.md`. The session number is a monotonic
counter across all sessions, unrelated to the date.

## Why Not Just git log

- `git log` shows what changed. Not what was tried and
  rejected.
- Commit messages are terse by convention. The four-bug story
  behind `4ce7699` is a 100-line message nobody reads with
  `git log --oneline`.
- `grep -r "nested form" docs/` finds a reference in one
  second. `git log -S` is slower and matches only what was
  typed into a commit.
- The one thing git cannot do: "what should the next session
  open with." That is a human judgment made at end-of-session.
