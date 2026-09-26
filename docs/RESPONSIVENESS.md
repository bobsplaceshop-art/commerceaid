# Responsiveness Directive

**Status:** Mandatory
**Applies to:** Every view, component, and module in the platform
**Effective:** 2026-09-26

---

## The Rule

This platform is a fully responsive, PWA-enabled e-commerce site.
Responsiveness is not a nice-to-have. It is a build requirement.

**Any UI that does not work at 375px wide is broken.**

---

## Breakpoints

The platform targets four widths:

| Name | Width | Primary device |
|------|-------|----------------|
| xs | 375px | Mobile portrait |
| sm | 640px | Mobile landscape |
| md | 768px | Tablet portrait |
| lg | 1024px+ | Tablet landscape / desktop |

Every view must be usable at xs. Every data table must not overflow
its container. Every form must be fillable with a thumb.

---

## Mandatory Patterns

### Tables

Admin tables must:

1. Wrap in a `.ra-table-responsive-wrap` container with `overflow-x: auto`
2. Hide secondary columns below 768px using the `.ra-hide-sm` class
3. Stack action buttons vertically below 768px
4. Keep the primary identifier (product name, category name) visible at all widths

Example:

    <div class="ra-table-responsive-wrap">
        <table class="ra-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="ra-hide-sm">SKU</th>
                    <th class="ra-hide-sm">Reserved</th>
                    <th>Available</th>
                    <th>Actions</th>
                </tr>
            </thead>
            ...
        </table>
    </div>

### Forms

Admin forms must:

1. Use Bootstrap's `row`/`col-md-*` grid
2. Have all fields stack to full width below 768px
3. Have the sticky action bar adjust its offsets on narrow screens
4. Be tested at 375px with the browser's device emulator

### Modals

Modal panels must:

1. Use `max-width` not `width` so they shrink on narrow screens
2. Use `max-height: calc(100vh - 2rem)` so the panel fits within the viewport
3. Make the body scrollable, header and footer fixed
4. Be reachable in one thumb-tap

### Sidebar

The admin sidebar must:

1. Collapse to an off-canvas drawer below 900px
2. Be reachable via a hamburger button in the topbar
3. Auto-close when a nav item is tapped

---

## Storefront Specific

The public storefront (Stage 008+) must:

1. Be usable at 375px on every page
2. Have a mobile-first design for product grids, cart, and checkout
3. Serve a responsive PWA manifest per store
4. Work offline for the storefront shell
5. Load in under 3 seconds on a 3G connection (per Lighthouse)

---

## Verification

Every new view is verified:

1. **At 375px** (mobile portrait) — no horizontal scroll on the body
2. **At 768px** (tablet) — all primary functionality accessible
3. **At 1024px** (desktop) — full layout
4. **At 1440px+** — content maxes at 1400px, centered

Test with Chrome DevTools' device emulator before merging. Any PR
that introduces horizontal scroll on a mobile viewport is rejected.

---

## Rules for Future Work

- New admin tables MUST include `ra-table-responsive-wrap` and use
  `ra-hide-sm` on non-essential columns.
- New modals MUST use the shared `.ra-modal` styles from `setup.css`.
- New forms MUST use the grid system, not fixed pixel widths.
- New cards MUST use flex or grid, not tables-as-layout.
- Never hardcode a pixel width on a content container.

---

## CI Check (Future)

When automated testing is added:

- Lighthouse PWA audit runs on every deploy
- Responsive audit fails on any viewport smaller than 375px
- Horizontal scroll on a mobile viewport = red build

---

## Why This Matters

The Anaya Global storefront will be accessed from:

- Mid-range Android phones (Chrome)
- iPhones (Safari)
- Tablets (portrait and landscape)
- Desktops and laptops

A broken mobile experience loses sales. This directive ensures
every page works on the smallest device a customer might use.
