# Stage 008B - Storefront Visual Identity & Catalog

**Date:** 2026-09-27
**Status:** Complete

## Summary

The full public-facing storefront, from visual identity through
catalog, category, and product detail pages. This stage turned
the storefront shell from 008A into a working, designed store.

Split across two sub-stages:

- **008B** - the core visual identity plus the four customer
  pages (home, catalog, category index/detail, product detail)
- **008B.2** - an extended pass adding trust rows, product card
  polish, footer redesign, and the section collapse rule

Both shipped together in this session. Both are covered here.

## Context

008A delivered the shell (layout, header, footer, home placeholder)
and closed a long-standing gap by adding the missing Store model.
The storefront existed but looked like a placeholder.

008B made it look like the customer's design reference. The
reference was supplied mid-session along with a written design
brief. Every visual decision in this stage traces back to that
brief or the reference image.

## What Shipped

### The four customer pages

**Home (`/`)**
- Hero: three-column CSS composition with brand emblem,
  wordmark, script slogan, four trust indicators, script accent
- Trust row (below hero): Premium Quality / Fast & Reliable /
  Dedicated Support / Global Fashion
- Shop by Category: 7-card grid with gold-divider heading
- Promotional panels: 4-panel lifestyle grid with text overlays
- New Arrivals: product grid (collapses when empty)
- Promotional strip: black band with brand message
- Trust strip: same four items as the top trust row, presented
  as a wider band
- Best Sellers: product grid (collapses when empty)
- Newsletter: "Stay in Style" script heading with disabled form

**Catalog (`/products`)**
- Filter sidebar: search, category, brand, price range, in-stock
- Active filter chips with per-chip clear
- Sort dropdown with 5 options
- Product grid with the redesigned card
- Inline pagination

**Product detail (`/products/{slug}`)**
- Breadcrumb through categories
- Media column: main image + 4 thumbnails
- Sticky info column: brand, title, short description, price
  block with save badge, stock, variant picker (disabled),
  add to cart, wishlist, trust line
- Full description
- Related products grid

**Category index (`/categories`)**
- 7-card grid, gold heading dividers
- Each card: image, name, arrow button

**Category detail (`/categories/{slug}`)**
- Breadcrumb with parent category
- Hero banner
- Subcategory chips
- Product grid (uses the new card)
- Inline pagination

### The four stylesheets

Loaded in order:

1. `storefront.css` (1010 lines) - core: CSS variables, reset,
   container, utility bar, header, footer, buttons, breadcrumbs,
   alerts, section headings, footer newsletter + payment badges
2. `storefront-home.css` (695 lines) - hero, trust row, category
   grid, promo panels, product sections, promo strip, trust
   strip, newsletter
3. `storefront-catalog.css` (802 lines) - catalog page, filters,
   chips, product grid, product card, pagination, categories
   index, category detail, subcategory chips
4. `storefront-product.css` (296 lines) - product detail layout

Total: ~2800 lines of storefront CSS.

### The design system

- **Palette:** cream (`#F8F1E8`), soft beige (`#EDE1D2`),
  champagne gold (`#C6A15B`), light gold (`#D8BF8A`),
  charcoal (`#111111`), muted grey (`#777777`)
- **Typography:** Playfair Display (display serif), Inter
  (interface sans), Dancing Script (script accent)
- **Motion:** `cubic-bezier(.22,.61,.36,1)`, three durations
  (120/200/320ms)
- **Radius:** 2px (sharp, editorial)
- **Container:** 1360px max-width

Every value lives in a CSS custom property under `:root` so
Stage 012 (theme engine) can override the entire palette per
store with a single `<style>` block.

### Product card redesign

The card appears on every listing surface (home sections,
catalog, category detail, product related). It gained:

- Category label above the name
- Discount badge in `-XX%` format
- Wishlist heart always visible (was hover-only)
- Gold Add to Cart button (was black outline)
- Rating block renders only when count > 0
- Quick view hidden on touch devices

Every interactive element carries `data-sf-card-action` or
`data-sf-card-block` attributes for future theme builder control.

### Footer redesign

Restructured from a simple 4-column layout into:

- **Top row:** world map SVG, brand wordmark, script slogan,
  social icons (6)
- **Middle row:** five columns - Quick Links, Customer Service,
  Company, Contact, Newsletter placeholder
- **Bottom row:** legal copyright + payment method badges
  (Visa, Mastercard, PayPal, Verve as simplified SVG
  recreations + Secure Payments lock)

Every logical block carries a `data-sf-section` attribute.

### Section collapse rule

**Data-driven sections render NOTHING when their data is empty.**
Not an empty state - the section is absent, and the section
below fills the gap seamlessly. Looking at the page, you cannot
tell a section was skipped.

**Data-driven (collapse when empty):**
- categories
- promo-panels
- new-arrivals
- best-sellers

**Brand/messaging (always render):**
- hero
- trust-row
- promo-strip
- trust-strip
- newsletter

This rule governs both the current state and the future theme
builder preview.

### Store builder contract

Every top-level storefront element carries a `data-sf-*`
attribute so the future theme builder can identify, reorder,
toggle, and reconfigure. Documented in
`docs/stages/008b_extended-visual-plan.md`.

Attribute families:

- `data-sf-section` - home sections and footer blocks
- `data-sf-page` - page wrappers
- `data-sf-card-action` - product card interactives
- `data-sf-card-block` - optional card blocks
- `data-sf-catalog-block` - catalog page blocks
- `data-sf-category-id` - category cards
- `data-sf-product-id` - product detail wrapper

### Repository extension

`ProductRepository::paginatePublished()` gained a `featured`
filter so the home page can query featured products from the
storefront context without using admin filters.

### Routes

Four new routes added to `routes/storefront.php`:

- `GET /products` - CatalogController::index
- `GET /products/{slug}` - ProductController::show
- `GET /categories` - CategoryController::index
- `GET /categories/{slug}` - CategoryController::show

### CSP update

`public/.htaccess` added Google Fonts domains
(`fonts.googleapis.com` for CSS, `fonts.gstatic.com` for font
files). Also briefly added loremflickr.com for a keyword-driven
image service experiment, then removed it when the service
proved unreliable (see Bugs below).

## Files Created

**Controllers:**
- `app/Commerce/Controllers/Storefront/CatalogController.php`
- `app/Commerce/Controllers/Storefront/CategoryController.php`
- `app/Commerce/Controllers/Storefront/ProductController.php`

**Views:**
- `resources/views/storefront/partials/utility-bar.php`
- `resources/views/storefront/catalog/index.php`
- `resources/views/storefront/catalog/_product-card.php`
- `resources/views/storefront/category/index.php`
- `resources/views/storefront/category/show.php`
- `resources/views/storefront/product/show.php`

**Stylesheets:**
- `public/assets/css/storefront.css`
- `public/assets/css/storefront-home.css`
- `public/assets/css/storefront-catalog.css`
- `public/assets/css/storefront-product.css`

**Docs:**
- `docs/stages/008_storefront-plan.md`
- `docs/stages/008b_extended-visual-plan.md`

## Files Modified

- `app/Commerce/Controllers/Storefront/HomeController.php`
- `app/Commerce/Repositories/ProductRepository.php`
- `resources/views/storefront/home.php`
- `resources/views/storefront/layouts/main.php`
- `resources/views/storefront/partials/header.php`
- `resources/views/storefront/partials/footer.php`
- `routes/storefront.php`
- `public/.htaccess`
- `app/Core/App.php` (added `storefront` to route file load list)

## Bugs Hit and Fixes

### Bug 1 - Route loader didn't know about storefront.php

**Symptom:** `/` returned 404 after we moved the placeholder
from `routes/commerce.php` to the new `routes/storefront.php`.

**Root cause:** `App::loadRoutes()` used a hardcoded array of
route file names (`['auth', 'web', 'commerce']`). The new file
wasn't in the list.

**Fix:** Added `'storefront'` to the array. The loader iterates
the array and includes each file that exists.

**Pattern:** any new route file needs an entry in
`App::loadRoutes()`'s `$routeFiles` array. Documented in the
baseline.

### Bug 2 - Store model didn't exist

**Symptom:** storefront hero showed the fallback "Our Store"
instead of the store name from the database.

**Root cause:** `StoreRepository::findById()` returned an array,
not a model. `$store->name` was property access on an array,
which silently produced null. The `catch (\Throwable)` in
`resolveStore()` swallowed the resulting warning.

**Fix:** Created `Models/Store.php` (the missing DTO, 38
properties, `fromRow()`, `isEmpty()`, business helpers) and
refactored `StoreRepository`, `StoreService`, and
`StoreResolutionMiddleware` to return `Store` objects. Views
continued using `$store->name` unchanged; the property access
became valid.

**Pattern:** any entity with a repository but no model is a
latent bug. Documented as a pending refactor pattern.

### Bug 3 - Reject button quote collision (from 007B, carried)

**Symptom:** clicking the reject (X) icon did nothing.

**Root cause:** the onclick attribute used double quotes to
wrap `json_encode()` output which also used double quotes. The
browser truncated the attribute at the first inner quote.

**Fix:** single-quoted attribute. Matches the working pattern
in `products/index.php`.

**Pattern:** 13 in REMEMBER.md.

### Bug 4 - Dashboard falsely active on every admin page

**Symptom:** on the storefront approvals page, two nav items
were marked active: Dashboard AND Customer Approvals.

**Root cause:** `$isActive()` used prefix matching. Dashboard's
href (`/commerce/ca-admin`) is a strict prefix of every other
admin route, so it matched everything.

**Fix:** exact-match special case in `$isActive` for that one
href.

**Pattern:** 22 in REMEMBER.md.

### Bug 5 - Nav labels wrapping

**Symptom:** multi-word nav labels ("Bags & Accessories",
"Beauty & Lifestyle", "New In") wrapped to two lines on desktop.

**Root cause:** no `white-space: nowrap` on `.sf-nav-link`, and
insufficient horizontal room between 900-1140px.

**Fix:** added `white-space: nowrap` plus a tight-range media
query (900-1140px) reducing gaps and font sizes.

### Bug 6 - loremflickr images blocked (008B.2)

**Symptom:** category and promo panel images rendered as broken
image icons despite the CSP allowing loremflickr.com.

**Root cause:** loremflickr.com was unreachable from the
development environment. The CSP was correct; the service
itself wasn't responding.

**Fix:** switched categories and promo panels to picsum.photos
with namespace-prefixed seeds (`cat-{slug}`, `promo-{id}`).
Removed loremflickr from the CSP.

**Lesson:** test external services directly before adding them
to the CSP. A CSP entry that's correct doesn't help if the
service isn't reachable.

## Decisions

### 1. Separate customer session namespace

The customer session uses its own cookie (`ca_customer_session`
planned for 008C) distinct from the admin session. Two audiences,
two cookies, zero cross-contamination.

### 2. Separate storefront CSS

The storefront does not share CSS with the admin. Different
visual language (airy, editorial, warm) vs (dense, dark,
tool-like). Four files, each under ~1000 lines.

### 3. Section collapse, not empty state

Data-driven sections vanish entirely when empty. Not an empty
state card, not a "no products yet" message - the section is
absent. The next section fills the gap.

### 4. Images are dev placeholders

Product images use picsum seeded by product slug. Category and
promo images use picsum with namespace-prefixed seeds. Real
product and category photography lands when the media library
is built. Both picsum domains marked as dev placeholders in
the CSP with a note to remove them.

### 5. Store builder contract established early

Every visible element on the storefront carries a `data-sf-*`
attribute. The theme builder (Stage 011+) will use these to
identify, reorder, toggle, and configure. Views do not change
when the theme builder ships - only the source of the data
they receive.

### 6. Curated vs random images

First attempt was curated Unsplash per category slug (would
have matched the target brand aesthetic). Unsplash Source is
deprecated. loremflickr (keyword-driven) was tried and failed.
picsum is the fallback - random per seed but always available.

## Verification

Full test pass at 375 / 768 / 1024+:

- Home renders with all sections
- Catalog renders with filters and pagination
- Product detail renders with media, info, related
- Category index and detail render
- Trust rows and trust strip render
- Footer newsletter column and payment badges render
- Section collapse verified (empty data = no section HTML)
- Product card shows category label, discount badge, gold CTA
- No console errors
- No CSP violations
- No 404s for CSS/JS
- Admin pages unaffected

## What's Next

**Stage 008C - Customer registration, login, account.**

Customer-side authentication (separate cookie, separate user
table), registration flow with approval workflow integration,
forgot password, account dashboard, address book, password
change.

**Stage 008D - Cart.**

Session cart for guests, DB cart for logged-in customers, merge
on login.

**Stage 008E - Checkout.**

Multi-step checkout with order creation, inventory reservation,
B2B purchase order support.

**Pending items captured for future work:**

- Categories with no visible products should hide on the
  storefront (008G)
- Store builder admin UI (Stage 011)
- Configurable search filter fields (Stage 010)
- Media library for real photography (Stage 009+)
- Real rating aggregates (reviews stage)
- Hero image asset from customer
