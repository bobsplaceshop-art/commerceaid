# Git Workflow

**Status:** Locked 2026-09-26
**Model:** Simplified trunk-based
**Applies to:** Every commit, branch, and tag from Stage 008 onwards

This document is the contract for how we use git. It exists so
that six months from now, when the git history has hundreds of
commits, the shape of that history still tells a clear story.

---

## The Model

Single permanent branch. Short-lived feature branches. Tags at
stage boundaries.

    main  ---o---o---o---o---o---o---o---o---o---o---o---
           |                                   |
           ^ stage-007b-complete               ^ stage-008-complete

- **`main`** is the only permanent branch.
- **`main`** is always working. Never push broken code to it.
- **Feature branches** are short-lived. Days, not weeks.
- **Stage completions** are tagged commits on `main`.
- **No `develop` branch.** No `release/*`. No `hotfix/*`.
- **No long-lived divergence.** Merge back within a week.

---

## Why Simplified Trunk-Based

Full git-flow (separate develop, release, hotfix branches) is
designed for teams shipping to a shared production environment
on a release cadence. This project is:

- Solo developer
- Local development environment
- No staging/production split yet
- No release freeze windows

Every piece of git-flow ceremony that assumes those things is
overhead here. The simple model gives us:

1. **Clear history.** `git log --oneline` on main reads top to
   bottom as "what shipped, in order."
2. **Real rollback.** Any commit or tag is a jump-to point.
3. **Isolated experiments.** A feature branch can be broken,
   abandoned, or force-pushed without touching main.
4. **Milestone markers.** Tags provide stable references for
   stage boundaries that survive rebases and squashes.

When the project grows a team or gains a deployment pipeline
with a real staging environment, we revisit this document and
upgrade to git-flow. Until then, simple wins.

---

## Branch Naming

Every branch that is not `main` follows this pattern:

    <type>/<kebab-case-description>

### Types

    feature/    - New user-facing or developer-facing feature
    fix/        - Bug fix (behavior was wrong)
    docs/       - Documentation only, no code change
    chore/      - Tooling, config, dependencies, no product change
    refactor/   - Code change with no behavior change

### Examples

    feature/storefront-routing
    feature/product-listing-page
    feature/cart-session
    feature/checkout-flow
    feature/customer-registration
    fix/reject-button-onclick-quote
    fix/sidebar-active-scroll
    docs/baseline-section-17
    chore/composer-update
    refactor/customer-repository-extract

### Rules

1. **Lowercase, kebab-case.** No spaces, no underscores, no capitals.
2. **Short.** Two to five words. Long names are hard to type
   and easy to typo.
3. **Descriptive of the work, not the intent.** `feature/cart-session`
   not `feature/make-things-better`.
4. **One feature per branch.** If you find yourself wanting to name
   a branch `feature/a-and-b`, split into two branches.

---

## Commit Messages

Conventional Commits format:

    <type>(<scope>): <short summary>

    <optional body>

    <optional footer>

### Type (required)

Same types as branches:

    feat      - New feature
    fix       - Bug fix
    docs      - Documentation only
    chore     - Tooling, config, dependencies
    refactor  - Code change with no behavior change
    test      - Adding or fixing tests

### Scope (optional but encouraged)

Short label for the area touched. Not enforced, but useful in logs.

Examples: `repo`, `catalog`, `customers`, `admin`, `sidebar`,
`routes`, `baseline`, `migration`, `seeder`.

### Summary (required)

- Imperative mood. "Add feature" not "Added feature".
- Lowercase first letter (after the colon).
- No trailing period.
- Under 72 characters.
- Explains *what* changed, not *why*. The body covers the why.

### Body (optional but encouraged)

- Blank line after the summary.
- Wrap at 72 characters.
- Explain *why* the change was made, not just what.
- Reference patterns, stage docs, or baseline sections when relevant.
- Use the same ASCII-only rules as the codebase. No em dashes.
  Use ` - ` (space hyphen space) instead.

### Footer (optional)

- `Refs: <stage-or-issue>` for cross-references.
- `Co-authored-by: Name <email>` for pair work.
- `BREAKING CHANGE: <description>` when applicable.

### Examples

    feat(catalog): add variant bulk enable/disable

    Adds bulk action buttons above the variations table on the
    product variations page. Selected rows can be enabled or
    disabled in one click instead of one at a time.

    Server-side, the controller loops the posted ids and calls
    the existing variant update method. No new SQL.

    Refs: stage 006F

    fix(sidebar): only exact-match dashboard href is active

    Prefix matching in $isActive marked /commerce/ca-admin active
    on every admin page. The scroll-to-active logic then scrolled
    to the wrong item (dashboard at top). Fixed by requiring exact
    match for the dashboard href only.

    Pattern 22 in docs/REMEMBER.md.
    Baseline item 21 in docs/baseline/02-assumptions.md.

    docs(baseline): document partial include notation

    Corrected baseline section 2 item 7 which previously said
    both dot and slash notation are tolerated. The View class
    actually resolves both, but the codebase convention is
    dot-notation for view names and slash-form for partials
    under admin/products. Documented the actual convention.

### What Not To Do

- Do not commit WIP. If you need to save state, use `git stash`
  or push to a scratch branch (see below).
- Do not mix unrelated changes in one commit. Split them.
- Do not write vague summaries. "Fix bug" is not acceptable.
  "Fix null check in customer approval controller" is.
- Do not use em dashes in commit messages. WSL sometimes
  mangles them. Use ASCII ` - ` or restructure.

---

## Workflow — Starting a Feature

    # 1. Make sure main is up to date and clean
    git checkout main
    git pull --ff-only        # if a remote exists, otherwise skip
    git status                # should be clean

    # 2. Create a feature branch
    git checkout -b feature/storefront-routing

    # 3. Work in small commits, commit often
    git add -A
    git commit -m "feat(storefront): add product listing route"

    # 4. When done, ensure the branch builds and tests pass

    # 5. Merge back to main
    git checkout main
    git merge --no-ff feature/storefront-routing

    # 6. Delete the branch (safe delete, only if merged)
    git branch -d feature/storefront-routing

    # 7. Optionally tag if this closes a stage
    git tag -a stage-008a-complete -m "..."

---

## Why `--no-ff`

When you merge with `--no-ff`, git creates a merge commit even
when a fast-forward is possible. This is intentional.

Without it, if a feature branch has 5 commits, merging into main
makes main's history look like 5 unrelated commits. With `--no-ff`,
those 5 commits stay grouped under one merge commit titled with
the branch name. Reading main's log shows:

    Merge branch 'feature/storefront-routing'
    ├── feat(storefront): add product listing route
    ├── feat(storefront): product grid component
    ├── feat(storefront): filter sidebar
    ├── feat(storefront): pagination
    └── test(storefront): product listing page coverage

The feature is a unit. It can be reverted as a unit with
`git revert -m 1 <merge-hash>`.

---

## Scratch Branches

When you need to save state without committing to a proper
feature branch:

    git checkout -b scratch/experiment-xyz
    # do work
    git commit -m "wip: experimenting with X"
    git push origin scratch/experiment-xyz    # if remote exists

Scratch branches are your own. They can be force-pushed, rebased,
or deleted without notice. Never merge a scratch branch into main.
Extract the useful work into a proper feature branch.

---

## Tags

Tag every stage completion on `main`.

    git tag -a stage-008a-complete -m "Stage 008A - Storefront routing

    Milestone: routing layer for the public storefront is live.
    ..."

Tag naming: `stage-NNNx-complete` where NNN is the stage number
and x is the optional sub-stage letter.

Tags are always **annotated** (`-a`), never lightweight. They
carry tagger metadata and a message. They are the permanent
markers of project state.

List tags:

    git tag -l

Jump to a tag:

    git checkout stage-007b-complete

Return to latest:

    git checkout main

Show tag metadata:

    git show stage-007b-complete --no-patch

---

## Reverting a Change

Three ways, in increasing severity:

### 1. Undo an uncommitted change

    git checkout -- path/to/file.php

### 2. Undo a single commit (keep history)

    git revert <commit-hash>

Creates a new commit that undoes the target. Safe for shared
branches. Never rewrites history.

### 3. Undo a feature (revert the merge)

    git revert -m 1 <merge-commit-hash>

Reverts an entire merged feature. Useful when a whole feature
needs to be backed out.

### What Not To Do

- Never `git push --force` to `main`. Ever.
- Never `git rebase` a branch that has been pushed and shared.
- Never edit a commit that is already on `main` unless you
  know exactly why and have a local backup.

---

## Checking State Before You Commit

    git status                    # what's changed
    git diff                      # what's different in tracked files
    git diff --cached             # what's staged
    git log --oneline -10         # recent commits
    git branch -a                 # local + remote branches
    git tag -l                    # all tags

Before every commit, ask:

1. Is the working tree exactly what I want to commit?
2. Am I about to commit any file that should be gitignored?
   (config/local.php, config/cli.php, storage runtime files)
3. Does the commit message say what changed and why?
4. Is this a single logical change, or should it be two commits?

---

## The Pre-Commit Checklist

Before merging a feature branch into main:

1. `git status` on the feature branch is clean.
2. All files lint. `php -l` passes on every PHP file touched.
3. Manual testing passes. The feature works end to end.
4. No debugging files (`_diag_*.php`, `_smoke_*.php`) are
   committed.
5. No `console.log`, `var_dump`, or `dd()` left in the code.
6. No em dashes in commit message. ASCII ` - ` if needed.
7. The commit message describes what and why.
8. `.gitignore` still covers what it should.

Any failure on the list is a stop sign. Fix, re-verify, then merge.

---

## Backup

A bare repo mirror lives at:

    C:\Users\Admin\Documents\RepoBackup\commerceaid.git
    /mnt/c/Users/Admin/Documents/RepoBackup/commerceaid.git

It is registered as the `backup` remote in the primary repo. Google
Drive Desktop syncs that folder to the cloud, giving geographic
redundancy without a paid service or a GitHub account.

### Why bare

A bare repo has no working tree - just the git object store, refs,
and history. You cannot edit files in it, only push to and clone
from it. That is exactly what a backup wants to be.

### Why not sync the primary folder directly to Drive

Two reasons:

1. **Mid-commit sync corruption.** Git writes to `.git/` in
   multiple steps. Drive Desktop might upload intermediate states
   out of order and leave the repo corrupt on restore.
2. **Vendor bloat.** The `vendor/` directory is ~400 files of
   third-party code. Drive would sync every one of them,
   wasting bandwidth and cloud quota.

A bare repo avoids both. Git pushes are atomic at the ref level,
and the bare repo contains only what git needs.

### Sync workflow

After every commit you want to preserve:

    git push backup main
    git push backup --tags

Or configure a push mirror so one command pushes both:

    git config remote.backup.push "refs/heads/main:refs/heads/main"
    git config --add remote.backup.push "refs/tags/*:refs/tags/*"
    git push backup

Once pushed, Drive Desktop syncs the bare repo to the cloud in
the background. No further action.

### Restore procedure

If the primary machine is lost:

1. Sign into Google Drive on a new machine.
2. Download or mount the `RepoBackup/commerceaid.git` folder.
3. `git clone <path-to-commerceaid.git> commerceaid`
4. `cd commerceaid`
5. `cp config/local.php.example config/local.php` (edit DB creds)
6. `cp config/cli.php.example config/cli.php` (edit CLI creds)
7. `composer install`
8. `php bin/migrate.php`
9. `php bin/seed.php`

Full project restored, all history intact.

### Verification

Check the backup has what you expect:

    git -C "/mnt/c/Users/Admin/Documents/RepoBackup/commerceaid.git" log --oneline -5
    git -C "/mnt/c/Users/Admin/Documents/RepoBackup/commerceaid.git" tag -l
    git -C "/mnt/c/Users/Admin/Documents/RepoBackup/commerceaid.git" branch -a

Compare against the primary:

    git -C /mnt/c/projects/commerceaid log --oneline -5
    git -C /mnt/c/projects/commerceaid tag -l

If they match, the backup is current. If they don't, push.

### Not a remote in the git sense

The `backup` remote is a local filesystem path. There is no HTTP
endpoint, no SSH, no authentication. It works because the folder
is on this machine and Drive Desktop handles off-machine sync.
This is intentional. A hosted remote (GitHub, GitLab, Bitbucket)
can be added later alongside `backup` if the project gains
collaborators.

## What This Document Does Not Cover

- **CI/CD pipelines.** No automated test running yet. When tests
  are added, a CI section is added here.
- **Remote hosting.** GitHub, GitLab, self-hosted - not chosen
  yet. When a remote is added, this section gains a "Remote
  and Backup" subsection.
- **Submodules.** Never used in this project.
- **LFS (Large File Storage).** Not needed. Media files go in
  `storage/uploads/` which is gitignored.
- **Signing commits.** Optional GPG signing is a future enhancement.

---

## See Also

- `docs/BASELINE.md` - verified codebase conventions
- `docs/REMEMBER.md` - bug patterns and detection rules
- `docs/ROADMAP.md` - build sequence
