# Session Log

One file per working session. Kept forever. Mostly links.

`git log` answers "what changed." This directory answers
"why, and what next." Both are needed. They are not the same
thing.

## The Protocol

Every session ends with a close pass. No exceptions. Even a
short session gets an entry.

Five steps:

1. Write the session file. `YYYY-MM-DD.md`. If two sessions
   happen on the same day, append `-2`.

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
| 2026-09-30 | 003 | 008G.2 Recently Viewed - full stack, no new patterns |
| 2026-10-01 | 004 | 008G.3 Message Center - self-contained, Pattern 30 added |

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
