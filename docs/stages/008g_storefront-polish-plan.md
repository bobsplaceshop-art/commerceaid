# Stage 008G - Storefront Polish

**Date opened:** 2026-09-28
**Status:** Planned
**Depends on:** Stages 008A-008F.1 (all complete)

---

## Purpose

Close the storefront loop. Six independent items that
together make the storefront feel finished: search,
out-of-stock hiding, empty-category hiding, image gallery,
PWA, and a final responsive audit.

Each item is shippable on its own. This stage works through
them in priority order. The stage closes when the priority
items ship; anything left becomes a follow-up sub-stage.

---

## Priority Order

  1. Search (this stage) - the header search bar and a
     dedicated /search page.
  2. Out-of-stock hiding - an admin toggle. When on, listing
     pages hide products with no sellable variant.
  3. Empty-category hiding - categories with zero visible
     products disappear from the home grid and category
     index.
  4. Product image gallery - thumbnail click swaps the main
     image on the product detail page.
  5. PWA and offline - service worker, per-store manifest,
     offline page visual.
  6. Responsive audit - final pass at 375 / 768 / 1024.

Items 1-4 ship in this stage. Item 5 is deferred to 008G.4
if it does not fit. Item 6 is the closing audit.

---

## Item 1 - Search

### What exists today

  - The header has a search toggle button
    (data-sf-search-toggle) that opens an inline input
    (JS in app.js). The input has no form, no action, no
    route.
  - The catalog page at /products supports ?q= as a filter
    within the filter sidebar. That is browsing, not
    searching.

### What 008G ships

  A dedicated /search page with its own UX:

    GET /search?q=<query>

  A search page differs from a filtered catalog in three
  ways:

    1. The results headline is "N results for <query>"
       instead of "Products".
    2. The filter sidebar is absent - search is about
       finding, not narrowing.
    3. The empty state suggests alternatives: "No results
       for <query>. Try a different spelling, or browse
       our categories."

  The header search form changes from a button toggle to a
  real form with method=GET action=/search. Submit on Enter
  and on a small submit button. The form is small enough to
  fit the header without expanding.

### The query

  Reuse ProductRepository::paginatePublished(['q' => $q]).
  The existing buildPublishedFilters() already handles the
  ?q= keyword on name, SKU, and short_description. No new
  repository method. Full-text indexing is deferred - the
  LIKE-based filter is fine for a first pass on a small
  catalog.

### Empty query

  GET /search with no q redirects to /products. No point
  showing an empty search page.

### Files

  app/Commerce/Controllers/Storefront/SearchController.php
  resources/views/storefront/search/index.php
  resources/views/storefront/partials/header.php (search
    form)
  routes/storefront.php (one route)
  public/assets/css/storefront-search.css (new)

---

## Item 2 - Out-of-Stock Hiding

### What it does

  An admin toggle. When on, listing pages exclude products
  whose every sellable variant has available <= 0. Direct
  product URLs still work and show a "Sold out" state so
  bookmarks and SEO survive.

  Variable products count as in stock if ANY variant is
  sellable.

### Where the toggle lives

  Store Settings -> General (new). Key:
    commerce.catalog.hide_out_of_stock
  Type: boolean. Default: false.

  Adding the definition to StoreSettingDefinitions makes it
  appear in the admin settings automatically (the settings
  infrastructure from 008E.1).

### Where the filter runs

  ProductRepository::buildPublishedFilters() gains an
  optional condition when the setting is on:

    AND EXISTS (
        SELECT 1 FROM commerce_inventory i
         WHERE i.product_id = p.id
           AND i.store_id = p.store_id
           AND i.available > 0
    )

  The repository gains a `$hideOutOfStock` constructor
  flag OR reads the setting per call. Constructor flag is
  cleaner: one StoreSettingsRepository lookup at the top
  of the controller method, passed as a filter key.

  Decision: add 'hide_out_of_stock' => true|false to the
  filter array. The filter builder respects it.

### Direct product URL

  The product detail page checks the product's sellable
  state. If sold out and the setting is on, it renders a
  "Sold out" badge instead of the Add to Cart button. The
  page still loads - SEO and bookmarks survive.

### Files

  app/Commerce/Support/StoreSettingDefinitions.php (one
    definition)
  app/Commerce/Repositories/ProductRepository.php (filter
    condition)
  app/Commerce/Controllers/Storefront/CatalogController.php
    (read the setting, pass the flag)
  app/Commerce/Controllers/Storefront/CategoryController.php
    (same)
  app/Commerce/Controllers/Storefront/HomeController.php
    (same, for the home sections)
  resources/views/storefront/product/show.php (sold-out
    state)

---

## Item 3 - Empty-Category Hiding

### What it does

  Categories with zero visible products disappear from the
  home grid, category index, and header nav. Categories
  with children (even if the parent itself has no products)
  remain visible if any child has visible products. Direct
  category URLs still work and show an empty state.

### The query

  CategoryRepository gains a method:

    listVisibleForStorefront(int $storeId): array

  A category is "visible" when it has at least one published
  product OR at least one child that is visible.

  The recursive nature (a category is visible if any
  descendant has products) suggests a single recursive CTE
  query or a two-pass approach: load all categories, mark
  which have products, then propagate up the tree.

  For a small catalog a two-pass PHP implementation is fine.
  A MySQL 8 recursive CTE is possible and cleaner.

### Files

  app/Commerce/Repositories/CategoryRepository.php (new
    method)
  app/Commerce/Controllers/Storefront/HomeController.php
    (use the visible list)
  app/Commerce/Controllers/Storefront/CategoryController.php
    (same)
  resources/views/storefront/partials/header.php (nav
    items filtered by visibility)

---

## Item 4 - Product Image Gallery

### What it does

  On the product detail page, clicking a thumbnail swaps the
  main image. The current implementation shows the same
  image in 5 sizes (main + 4 thumbs) using picsum seeded by
  slug. 008G adds the JS to swap.

  Not part of this item: real product images. Those come
  from the media library, a future stage. The swap works
  on the placeholder images today and continues to work
  unchanged when real images land.

### Files

  public/assets/js/storefront-gallery.js (new)
  resources/views/storefront/product/show.php (wire the
    thumbs with data attributes)
  public/assets/css/storefront-product.css (active-thumb
    state)

---

## Item 5 - PWA and Offline (deferred to 008G.4)

  Deferred unless time allows. The service worker and
  manifest are worth a dedicated session. The storefront
  works fine without them; they add polish.

  If 008G closes before PWA ships, the ROADMAP gets a new
  008G.4 entry for it.

---

## Item 6 - Responsive Audit

  After items 1-4 ship, walk every storefront page at
  375 / 768 / 1024:

    home, catalog, product, category index, category
    detail, search, cart, checkout (all five steps),
    account (all pages incl. orders, order detail,
    addresses, password, claims), legal pages.

  Fix any layout issue found. Record results in this doc.

---

## Scope

**In scope:**

  - /search route, controller, view.
  - Header search form (replaces the toggle button stub).
  - Hide-out-of-stock admin toggle + query filter.
  - Hide-empty-categories + filtered header nav.
  - Product image gallery swap.
  - Responsive audit.

**Out of scope (deferred):**

  - Service worker + manifest (008G.4).
  - Attribute-based faceted search on the search page.
  - Full-text index (MySQL FULLTEXT) for search ranking.
    LIKE-based is sufficient for the first pass.
  - Real product images (media library stage).

---

## Files to Create

  app/Commerce/Controllers/Storefront/SearchController.php
  resources/views/storefront/search/index.php
  public/assets/css/storefront-search.css
  public/assets/js/storefront-gallery.js

## Files to Modify

  app/Commerce/Support/StoreSettingDefinitions.php
  app/Commerce/Repositories/ProductRepository.php
  app/Commerce/Repositories/CategoryRepository.php
  app/Commerce/Controllers/Storefront/CatalogController.php
  app/Commerce/Controllers/Storefront/CategoryController.php
  app/Commerce/Controllers/Storefront/HomeController.php
  resources/views/storefront/partials/header.php
  resources/views/storefront/product/show.php
  public/assets/css/storefront-product.css
  routes/storefront.php

**Estimated: ~14 files. 6-8 messages.**

---

## Test Protocol

  T-G1    Header search form submits to /search?q=...
  T-G2    /search with results renders the results grid
          and "N results for <query>".
  T-G3    /search with no results renders the empty state
          with suggestions.
  T-G4    /search with empty q redirects to /products.
  T-G5    Out-of-stock hiding off: a sold-out product
          appears in listings (or hidden - depends on
          current behavior).
  T-G6    Out-of-stock hiding on: a sold-out product
          disappears from /products, /categories/{slug},
          and home sections.
  T-G7    Sold-out product's direct URL still loads and
          shows a "Sold out" badge.
  T-G8    Variable product with one in-stock variant stays
          visible when hiding is on.
  T-G9    Empty category hides from the home grid and the
          header nav.
  T-G10   Direct empty category URL still loads.
  T-G11   Product image gallery: click thumbnail, main
          image swaps.
  T-G12   Responsive audit at 375 / 768 / 1024.

---

## Progress

| # | Item | Status |
|---|---|---|
| 1 | Plan document | This file |
| 2 | Search: route + controller + view | Pending |
| 3 | Header search form | Pending |
| 4 | Search CSS | Pending |
| 5 | Out-of-stock toggle | Pending |
| 6 | Out-of-stock filter in ProductRepository | Pending |
| 7 | Product detail "Sold out" state | Pending |
| 8 | Empty-category hiding | Pending |
| 9 | Header nav filtered by category visibility | Pending |
| 10 | Product image gallery | Pending |
| 11 | Responsive audit | Pending |
| 12 | Commit + close sub-stage | Pending |

---

## See Also

  - docs/stages/008_storefront-plan.md
  - docs/stages/008b_storefront-visual-identity.md
  - docs/stages/008e.1_german-compliance-plan.md
    (settings infrastructure)
  - docs/ROADMAP.md (Stage 008G)
  - docs/BASELINE.md
