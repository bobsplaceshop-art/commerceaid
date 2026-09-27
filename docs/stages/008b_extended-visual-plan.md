# Stage 008B.2 - Extended Visual Pass

**Date opened:** 2026-09-27
**Status:** In progress
**Depends on:** Stage 008B (visual identity, catalog, category, product pages)

---

## Purpose

The customer supplied a detailed visual reference and clarified
that every visible element on the storefront is theme-builder
managed. This extended pass closes the remaining visual gaps
between our first pass (008B) and the reference image.

Scope is deliberately bounded:

  - Everything that is markup or CSS only.
  - Nothing that requires customer-supplied photography.
  - Nothing that requires new database tables.
  - Nothing that requires business logic changes.

---

## What Ships in This Pass

| # | Item | File |
|---|---|---|
| 1 | Section collapse rule - empty sections render nothing | home.php |
| 2 | Trust row below hero | home.php |
| 3 | Trust strip below New Arrivals | home.php |
| 4 | Product card: category label, discount %, always-visible wishlist, conditional rating, gold CTA | _product-card.php |
| 5 | Footer: data-sf-section hooks, payment method badges row | footer.php |
| 6 | Home: curated Unsplash for 7 categories + 4 promo panels | home.php |
| 7 | CSS additions for the above | storefront-home.css, storefront-catalog.css, storefront.css |
| 8 | CSP: allow images.unsplash.com | public/.htaccess |

---

## The Section Collapse Rule

**Data-driven sections hide completely when empty.**

If a section's data is empty, its entire HTML - heading, wrapper,
and content - does not render. The section below fills the gap
seamlessly. Looking at the page, you cannot tell that a section
was skipped.

**Data-driven sections (collapse when empty):**

  - categories            (no visible categories)
  - promo-panels          (no configured panels)
  - new-arrivals          (no published products)
  - best-sellers          (no published products)

**Brand/messaging sections (always render):**

  - hero                  (static composition)
  - trust-row             (static)
  - promo-strip           (static)
  - trust-strip           (static)
  - newsletter            (static CTA)

The theme builder (Phase 011+) will allow toggling brand/messaging
sections on/off per store. Until then, they render unconditionally.

---

## The Theme Builder Contract

Every top-level storefront element carries a `data-sf-*` attribute
so the future theme builder can identify it, reorder it, toggle
it, and configure it.

### Section identifiers (homepage)

    data-sf-section="hero"
    data-sf-section="trust-row"
    data-sf-section="categories"
    data-sf-section="promo-panels"
    data-sf-section="new-arrivals"
    data-sf-section="promo-strip"
    data-sf-section="trust-strip"
    data-sf-section="best-sellers"
    data-sf-section="newsletter"

Order in the file today is fixed. When the theme builder ships,
order will be data-driven (commerce_homepage_sections.sort_order).
The wrapper structure is designed so reordering requires no HTML
changes - only iteration over a different array.

### Page identifiers

    data-sf-page="catalog"
    data-sf-page="category"
    data-sf-page="product"
    data-sf-page="home"         (implicit - the page IS the home)

### Block identifiers within pages

    data-sf-catalog-block="filters"
    data-sf-catalog-block="sort"
    data-sf-catalog-block="active-filters"
    data-sf-block="add-to-cart"     (product detail)

### Card identifiers within product cards

    data-sf-card-action="wishlist"
    data-sf-card-action="quick-view"
    data-sf-card-action="add-to-cart"
    data-sf-card-block="rating"

### Entity identifiers

    data-sf-category-id="{id}"
    data-sf-product-id="{id}"

### What the theme builder will do with these

  - Toggle any identified element on/off
  - Reorder top-level sections
  - Configure section data sources (which category, how many
    products, sort key)
  - Show a live preview rendering the storefront in an iframe
    with the current store's data

---

## The Header and Footer Are Also Configurable

Every element in the header and footer will be editable:

Header:
  - Logo (emblem + wordmark + subtitle)
  - Navigation items (label, href, order)
  - Action icons (search, account, cart)
  - Utility bar trust badges
  - Language and currency selectors

Footer:
  - Brand block (map, wordmark, slogan, socials)
  - Nav columns (headings + links, ordered)
  - Newsletter block (headline, copy, CTA)
  - Payment method badges
  - Legal line

Today all are hardcoded arrays inside the views. When the theme
builder ships, the arrays come from database tables. The views do
not change - they consume the array the same way.

This is why we do not over-engineer the newsletter placement. It
is a placeholder slot. The customer repositions or removes it from
the theme builder.

---

## Image Strategy

### Categories and Promo Panels

Curated Unsplash per slug, so the storefront looks like one
coherent brand:

    women              -> specific women's fashion photo
    bags-accessories   -> specific handbag photo
    children           -> specific children's fashion photo
    beauty-lifestyle   -> specific beauty products photo
    gifts              -> specific gift box photo
    mobile             -> specific mobile accessories photo
    home               -> specific home interior photo

    promo panel IDs map to curated URLs the same way.

Any slug not in the map falls back to picsum.photos with a
deterministic seed so images are stable per slug.

### Products

Still picsum seeded by product slug. Real product photography
comes from the media library (a future stage). The current
placeholder is acceptable because product photography varies by
store - the customer will replace it.

### Hero

Retains the CSS-composed three-column layout. Real hero
photography requires a specific editorial composition (Eiffel
Tower, airplane, globe, phone mockup, hijab woman with bags).
That asset will come from the customer. The swap is a one-file
change when ready.

---

## Deferred From This Pass

  - Real hero photography           customer supplies asset
  - Real category photography       media library (future)
  - Real product photography        media library (future)
  - Real rating aggregates          reviews table (future stage)
  - Section reorder UI              theme builder (Phase 011+)
  - Per-store theming               theme engine (Phase 012+)

---

## Files Changed

| File | Action |
|---|---|
| docs/stages/008b_extended-visual-plan.md | New (this file) |
| resources/views/storefront/catalog/_product-card.php | Rewrite |
| resources/views/storefront/partials/footer.php | Rewrite |
| resources/views/storefront/home.php | Rewrite |
| resources/views/storefront/catalog/index.php | Small tweak |
| resources/views/storefront/category/show.php | Small tweak |
| resources/views/storefront/product/show.php | Small tweak |
| public/assets/css/storefront-catalog.css | Append |
| public/assets/css/storefront-home.css | Append |
| public/assets/css/storefront.css | Append |
| public/.htaccess | Small tweak |

Total: 11 files.

---

## Test Protocol

After every file lands:

  - php bin/verify-file.php <path> (syntax, hygiene, expectations)
  - php bin/lint.php (full project)

After the last file lands:

  - T1: Home page renders correctly at 1024px, 768px, 375px
  - T2: Every data-driven section collapses when its data is empty
  - T3: Product cards render all elements (or hide rating when 0)
  - T4: Footer renders payment badges + existing columns
  - T5: Trust row and trust strip render
  - T6: Curated Unsplash images load for the 7 categories
  - T7: CSP allows images.unsplash.com (no console violations)
  - T8: Admin pages unaffected

---

## Progress

| # | Item | Status |
|---|---|---|
| 1 | Plan document | Done |
| 2 | Product card rewrite | Pending |
| 3 | Footer rewrite | Pending |
| 4 | Home rewrite | Pending |
| 5 | Catalog index tweak | Pending |
| 6 | Category show tweak | Pending |
| 7 | Product show tweak | Pending |
| 8 | CSS: catalog | Pending |
| 9 | CSS: home | Pending |
| 10 | CSS: core (footer badges) | Pending |
| 11 | CSP update | Pending |
| 12 | Full test pass + commit | Pending |

---

## See Also

  - docs/stages/008_storefront-plan.md - original sub-stage plan
  - docs/BASELINE.md - verified codebase conventions
  - docs/REMEMBER.md - bug patterns
  - Customer-supplied design brief (referenced in chat)
