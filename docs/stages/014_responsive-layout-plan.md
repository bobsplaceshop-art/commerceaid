# Stage 014 - Responsive Layout System

**Status:** Planning
**Opened:** 2026-10-12
**Depends on:** every stage that ships a view
**Blocks:** every future stage that ships a view

---

## Purpose

The platform has no responsive system. It has a
single `@media (max-width: 900px)` block in `main.css`
and a single utility class (`ra-hide-sm`). That is not
a strategy.

Every view that has been built assumed a desktop
viewport. When the viewport narrows, the layout
degrades silently. Some views survive. Most do not.

Visible symptoms today:

  - Products list: the product name column collapses
    to one word per line at every width. The column
    has no minimum width and the table has no layout
    strategy.
  - Every admin list table: the same latent risk.
    Only the variations table happens to work
    because its content is short.
  - Filter bars: six filter controls in a row. At
    mobile widths, they do not wrap or collapse.
  - Sticky action bars: hardcoded to the viewport
    bottom with a left offset that assumes the
    sidebar width. On mobile the sidebar is a
    drawer, and the offset is wrong.
  - Modals: fixed max-width. On mobile they run
    past the viewport edge.
  - Card grids: `grid-template-columns: repeat(
    auto-fit, minmax(240px, 1fr))`. Below 240px
    the grid breaks. There is no explicit
    breakpoint.
  - Storefront pages: the mobile experience is
    untested since Stage 008B.

The stage fixes the class of problem, not the
instances. It defines the breakpoints, the component
treatments, the test protocol, and the enforcement
rule. After this stage ships, every new view has a
documented contract to follow.

---

## The breakpoints

Four. Defined once, in `main.css`, as CSS custom
properties and as a documented list.

| Name          | Range           | Rationale                          |
|---------------|-----------------|------------------------------------|
| Desktop       | >= 1200px       | Full layout. Sidebar visible.       |
| Tablet        | 900px - 1199px  | Sidebar persistent. Tables show     |
|               |                 | all columns. Filter bars inline.    |
| Mobile        | 640px - 899px   | Sidebar becomes a drawer. Tables    |
|               |                 | use a responsive treatment.         |
|               |                 | Filter bars collapse.               |
| Small mobile  | < 640px         | Single column. Stacked cards.       |
|               |                 | Larger touch targets.               |

The two existing breakpoints in the codebase are
`900px` (mobile boundary in `main.css`) and `640px`
(author edit's mobile boundary). They map to the
tablet/mobile and mobile/small mobile boundaries in
the new scheme. The first boundary (`1200px`) is new
and marks where the layout starts compressing.

---

## The component treatment matrix

Every shared component gets an explicit rule per
breakpoint. Views do not invent their own media
queries. They use the classes and the shared rules.

### List tables

The hardest single component. Two treatments, chosen
by column count.

**Treatment A - horizontal scroll.** Used when the
table has 8 columns or fewer, or when the columns are
narrow and the content is short.

  - Desktop and tablet: standard table, full width.
  - Mobile and small: table inside a
    `overflow-x: auto` wrapper with `min-width` set
    so the table does not compress. The user scrolls
    sideways.
  - A subtle gradient on the right edge signals
    there is more content.

  Examples: variations, tax classes, shipping
  methods, customer groups, coupons.

**Treatment B - stacked cards.** Used when the table
has more than 8 columns, or when the content is long
and would be unreadable in a scroll.

  - Desktop and tablet: standard table.
  - Mobile and small: each `<tr>` becomes a card.
    Each `<td>` shows its label (from a `data-label`
    attribute on the `<th>`) above the value. The
    action column becomes a row of icon buttons at
    the bottom of the card.

  Examples: products, orders, customers, inventory,
  media, reviews.

### Card grids

  - Desktop: `grid-template-columns: repeat(3, 1fr)`
    or `repeat(4, 1fr)` depending on card content.
  - Tablet: `repeat(2, 1fr)`.
  - Mobile: `repeat(2, 1fr)` if cards are narrow, or
    `1fr` if cards are wide.
  - Small: `1fr`.

  Explicit per component. No `auto-fit` and no
  `minmax` without a media query fallback.

### Filter bars

  - Desktop and tablet: inline row of controls.
  - Mobile and small: a collapsible panel. A
    "Filters" button toggles the panel. The panel
    stacks the controls vertically.

### Sticky action bars

  - Desktop and tablet: `position: fixed`, left
    offset `var(--ra-sidebar-w)`, gradient
    background above the bar.
  - Mobile and small: `position: fixed`, left
    offset 0 (sidebar is a drawer), full width,
    no gradient, larger buttons with full width.
  - Defined once in `main.css`. The six views that
    currently duplicate the block inline will be
    reduced to class usage only.

### Modals

  - Desktop and tablet: centered, `max-width: 720px`,
    rounded, drop shadow.
  - Mobile: full-width sheet from the bottom,
    `border-radius` only on the top corners.
  - Small: full-screen sheet.
  - The shared `.ra-modal*` system already exists in
    `setup.css`. It gains the responsive rules in
    this stage. All 22 existing modals inherit them.

### Sidebar

  - Desktop: persistent, `width: var(--ra-sidebar-w)`.
  - Tablet: persistent, same width.
  - Mobile and small: off-canvas drawer, `transform:
    translateX(-100%)`, toggled by a hamburger
    button in the topbar.
  - Already implemented in the `900px` media query.
    The stage verifies it across all views.

### Page bands

  - Desktop and tablet: negative-margin trick for
    full-bleed.
  - Mobile and small: standard padding, no negative
    margin.

### Forms

  - Desktop: two-column grid for related fields.
  - Tablet: two-column grid preserved.
  - Mobile: single column.
  - Small: single column, larger touch targets
    (44px minimum height for inputs and buttons).

---

## The audit

Every view is inventoried. The inventory lists:

  - The view file path.
  - The primary layout pattern (table, card grid,
    form, single column, custom).
  - The current responsive state (working, broken,
    untested).
  - The component treatments that apply.

The audit is done in three passes:

  **Pass 1 - admin list views.** All tables. Classified
  by column count into Treatment A or B.

  **Pass 2 - admin form and edit views.** All forms.
  All card grids. All filter bars. All sticky action
  bars.

  **Pass 3 - storefront.** Home, catalog, category,
  product detail, cart, checkout, account. Plus the
  standalone error pages.

The audit itself is roughly one session. The output is
a table (view, treatment, notes) that becomes the
work queue for the fix sessions.

---

## The test protocol

Every fix is verified at four widths before the next
fix starts. Four screenshots per view.

  - 1440px - desktop large
  - 1024px - tablet landscape
  - 768px - tablet portrait / mobile landscape
  - 375px - mobile

Verification is visual. The reviewer confirms:

  - No horizontal scrollbar on the body (only inside
    horizontal-scroll table wrappers, where it is
    intentional).
  - No content is clipped or hidden.
  - Text is readable.
  - Buttons and links have a tap target of at least
    44px on mobile.
  - The layout is not collapsed (no columns at 0
    width, no overlapping elements).
  - The theme contract holds in all three themes at
    all four widths.

Two themes minimum: dark, light. System is optional
and matches the OS.

---

## The enforcement rule

A script at `bin/check-responsive.php` that greps
every view for:

  - Inline `@media` blocks in views. All media
    queries belong in `main.css` or `setup.css`.
  - Hardcoded pixel widths above 375px on
    non-scroll containers.
  - `<table>` elements without a `data-responsive`
    attribute (either `scroll` or `cards`).
  - `<div class="row">` from Bootstrap without a
    companion responsive rule (a soft warning, not
    a hard fail, until the audit is complete).

The script exits non-zero on any hard fail. It is
wired into `composer check` as a fourth step after
`lint`, `analyse`, and the (future) theme contract
script.

---

## Sessions

Five or six sessions. Each is self-contained and can
be committed independently.

  **Session 1 - foundations.**
    - Define the four breakpoints.
    - Add the component treatment matrix rules to
      `main.css`.
    - No view changes yet. The shared rules land
      first.

  **Session 2 - admin list tables.**
    - Audit every admin list table.
    - Assign Treatment A or B to each.
    - Implement the treatment.
    - Test at four widths in both themes.

  **Session 3 - admin forms and edit pages.**
    - Move the six duplicate sticky-bar blocks into
      `main.css`.
    - Fix filter bars.
    - Fix card grids.
    - Fix modals.
    - Test at four widths in both themes.

  **Session 4 - storefront.**
    - Audit the storefront.
    - Fix home, catalog, category, product detail,
      cart, checkout, account.
    - Fix the standalone error pages.
    - Test at four widths in both themes.

  **Session 5 - enforcement.**
    - `bin/check-responsive.php`.
    - Wire into `composer check`.
    - Fix any remaining audit findings.
    - Stage close.

Optional: **Session 6 - polish.** Long-tail items
that the audit surfaces but that do not fit the
above.

---

## What is not in this stage

  - Tablet-specific design variations. Tablet uses
    the desktop layout unless it is broken.
  - Landscape / portrait detection.
  - A responsive design system library (Tailwind,
    Bootstrap's full grid system). The admin already
    uses a small hand-rolled set. This stage extends
    that set. It does not replace it.
  - Native app support. PWA is a separate concern
    (already shipped).
  - Accessibility audit. That is its own stage.

---

## The stage's relation to prior work

This stage is a **prerequisite** for every future
stage that ships a view. Stage 009 (Forms) shipped
the admin surface without the responsive treatment.
Stage 010 (Payments) and Stage 011 (Advanced
Commerce) will ship more views. Without this stage,
those views will inherit the same latent bugs.

The plan is therefore scheduled before Stage 010.

---

## Reference

  - `docs/REMEMBER.md` Pattern 38 - the WSL-to-Windows
    cache staleness that will require renaming files
    during this stage as CSS and view edits land.
  - `docs/STATUS.md` - current stage.
  - `docs/ROADMAP.md` - the sequence this stage
    occupies.
