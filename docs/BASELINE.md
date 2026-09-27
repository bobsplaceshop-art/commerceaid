# BASELINE — Index

**Status:** Locked 2026-09-26
**Applies to:** All future code in Stage 007B onwards
**Structure:** One file per section, in `docs/baseline/`

This is the index. Full content lives in the section files listed
below. Read in order or jump to the section you need.

Why split: a single large markdown file is hard to review, easy to
corrupt, and impossible to diff cleanly. One file per topic keeps
each section reviewable.

Anything in these documents was **read from source**. Anything not
documented is either unknown or unverified.

---

## Sections

| # | File | Topic |
|---|---|---|
| 1 | [01-layers.md](baseline/01-layers.md) | Layered architecture (as-built) |
| 2 | [02-assumptions.md](baseline/02-assumptions.md) | Assumptions vs reality — **read this first** |
| 3 | [03-controllers.md](baseline/03-controllers.md) | Controller convention |
| 4 | [04-flash.md](baseline/04-flash.md) | Flash flow (write vs read) |
| 5 | [05-routes.md](baseline/05-routes.md) | Route registration |
| 6 | [06-views.md](baseline/06-views.md) | View structure and rules |
| 7 | [07-sidebar.md](baseline/07-sidebar.md) | Sidebar nav items |
| 8 | [08-repositories.md](baseline/08-repositories.md) | Repository pattern |
| 9 | [09-models.md](baseline/09-models.md) | Model / DTO pattern |
| 10 | [10-helpers.md](baseline/10-helpers.md) | Global helper functions |
| 11 | [11-session.md](baseline/11-session.md) | Session API |
| 12 | [12-router.md](baseline/12-router.md) | Router internals |
| 13 | [13-middleware.md](baseline/13-middleware.md) | Middleware aliases |
| 14 | [14-provider.md](baseline/14-provider.md) | Service provider |
| 15 | [15-naming.md](baseline/15-naming.md) | Naming conventions |
| 16 | [16-precedence.md](baseline/16-precedence.md) | Precedence + supersedes + cascade |

---

## Reading Order

**If you're new to the project:** read 1, 2, then whichever section
matches the code you're about to write.

**If you're auditing an existing bug:** start with 2 (assumptions
table). Most bugs we've shipped trace back to one of those 20 rows.

**If you're adding a new admin page:** read 3, 5, 6 in that order.

**If you're adding a repository or model:** read 8, 9.

---

## Updating This Document

Any change to a locked pattern requires updating the relevant
section file in the same commit. The section files are the source
of truth; the index is just a map.

---

## See Also

- `docs/ARCHITECTURE.md` — three-tier layer contract
- `docs/NAMING-CONVENTION.md` — suite-wide naming rules
- `docs/REMEMBER.md` — session patterns and bug history
- `docs/RESPONSIVENESS.md` — mandatory responsive directive
- `docs/ROADMAP.md` — canonical build sequence
- `docs/stages/README.md` — stage log index
