# Admin Guide

**Audience:** The merchant operating this store.
**Scope:** How to use the admin panel day to day.
**Not in scope:** Developer or operator documentation.
See `docs/ARCHITECTURE.md` and `docs/STATUS.md` for that.

This guide answers the "how do I..." questions a store
owner has when running the shop. It is not a
specification and does not cover every field or option.
When a form field's purpose is not obvious, the form
itself carries a hint below the field. Read those hints.

---

## Table of Contents

1. Getting Started
2. Catalog
3. Sales
4. Settings
5. Storefront
6. Common Tasks
7. Troubleshooting
8. Glossary

---

## 1. Getting Started

### Logging in

Go to `/commerce/ca-admin` in your browser. Enter
your admin email and password.

If you have forgotten your password, click **Forgot
password** on the login page. A reset link is sent to
your email.

### The dashboard

The dashboard is your landing page after login. It
shows:

  - **Products / Orders / Customers** counters for the
    whole store.
  - **Inventory health** — a card with two links:
    *Low stock* and *Out of stock*. Click either to
    jump to the inventory list pre-filtered to that
    state.

Everything else is reachable from the sidebar.

### The sidebar

The sidebar is grouped by area:

  - **Dashboard** — the landing page.
  - **Catalog** — Products, Categories, Brands,
    Inventory, Attributes, Media, Reviews.
  - **Sales** — Customer Groups, Customer Approvals.
  - **Settings** — Store Settings, Legal Pages,
    Users, Roles.

Click a group header to expand or collapse it. The
state is remembered on this browser.

### The setup wizard

The first time you log in, a setup wizard may
redirect you to `/commerce/ca-admin/setup`. It has
eight tabs:

  - **Business** — company name, address, contact.
  - **Branding** — logo, favicon, colors.
  - **Currency** — the store's default currency.
  - **Checkout** — guest checkout, required fields.
  - **Payments** — which payment methods to accept.
  - **Shipping** — shipping origin and options.
  - **Tax** — tax rates.
  - **Advanced** — anything the wizard did not cover.

Some tabs are placeholders for features that ship in
a later stage. The wizard can be reopened any time
from **Settings > Store Settings**. It is safe to
skip and configure later.

---

## 2. Catalog

### Products

`Catalog > Products` lists every product in the
store. Filters at the top: search, status, category,
brand, stock state.

**To create a product**, click **New product**.

The editor has five tabs:

  1. **Basic** — name, slug, brand, categories,
     short and long descriptions.
  2. **Images** — the gallery and the variant-image
     card (for colors).
  3. **Pricing** — price, compare-at price (for a
     "was" strike-through), tax class, minimum order
     quantity, cost price.
  4. **Inventory** — stock status, and (for variable
     products) a link to the variations manager.
  5. **Shipping** — weight, dimensions.

A product can be one of two kinds:

  - **Simple** — a single sellable item with one SKU.
  - **Variable** — a set of variants, one per
    combination of attribute values (e.g. Color × Size).

**To publish**, click the **Publish** button in the
sticky action bar. Unpublished products do not appear
on the storefront.

**To archive** a product, click Archive in the
product list. Archived products are hidden from the
storefront but keep their data and history.

### Variable products

A variable product's variants are generated from the
attributes of its categories. For example, if a
product is in the "T-Shirts" category, and that
category maps the attributes "Color" and "Size" as
variant axes, then the product's variations manager
shows one row per Color × Size combination.

**To manage variants**, open the product editor and
click **Manage variations** in the sidebar. The
variations manager supports:

  - Search by SKU or attribute value.
  - Filter by one axis (e.g. show only Red variants).
  - Bulk actions: enable, disable, or set price for
    multiple rows at once.
  - Pagination (25 rows per page).
  - A **Regenerate** button when the category's
    attributes change. Regenerating wipes existing
    variants and rebuilds them; existing SKUs, prices,
    and stock are lost. Use with care.

### Uploading product images

In the **Images** tab, drag files onto the upload
zone or click **Browse**. Each image is processed
through the media pipeline, which generates multiple
sizes and formats for the storefront. See
**Settings > Media** for library management.

**To make an image the primary**, click the star
icon on its tile. The primary image is the one shown
on product cards.

**To reorder images**, drag a tile to its new
position.

**Variant (color) images.** For products with a
Color axis, a second card appears below the gallery:
one slot per color value. Assign an image to a slot
by clicking **Assign** and picking from the media
library. Customers see the assigned image when they
hover or click a color swatch on the storefront.

### Categories

`Catalog > Categories` shows the category tree. Each
category can have a parent and any number of children.

**To create a category**, click **New category**.
Fields: name, slug, parent, description, image,
display order.

**Category image guidance.** The category page
renders the image as a wide banner. The recommended
size is **1600 × 533 pixels (3:1 aspect ratio)**.
PNG, JPEG, or WebP, max 5 MB. Images with a different
aspect ratio are cropped to fill the banner — keep
important content centered.

**Mapping attributes.** A category maps the
attributes that are relevant to its products. The
attributes with `is_variant_axis = 1` generate
variations; the others are informational. See
**Attributes** below.

### Brands

`Catalog > Brands` lists brands. A brand has a name,
slug, optional website, and optional logo.

**To create a brand**, click **New brand**. A brand
can be assigned to products in the product editor's
Basic tab.

### Attributes

`Catalog > Attributes` lists the store's attribute
vocabulary — Color, Size, Material, Care
Instructions, and so on.

Each attribute has:

  - A **name** (e.g. "Color").
  - An **input type** (text, select, color, number,
    etc.). Twelve types are available.
  - An **is_variant_axis** flag.
  - A set of **values** (Red, Blue, Green).

**Variant axes.** Only attributes with
`is_variant_axis = 1` generate variants on a variable
product. Most attributes are informational only —
they show on the product page but do not create
variants. If you are unsure, leave the flag off and
turn it on only if you want variants.

### Inventory

`Catalog > Inventory` lists every sellable unit with
its stock quantity, reserved quantity, and available
quantity.

**To adjust stock**, click the pencil icon on a row.
A modal opens. Enter the new quantity and a reason
(the reason appears in the ledger). Saving writes a
new ledger entry.

**Stock on hand vs available.** Available = quantity
minus reserved. Reserved units are held by active
carts. Available is what the storefront offers.

**The movement ledger** is at `Catalog > Inventory >
Movements`. It is append-only. Every adjustment,
sale, and transfer is a row. The ledger cannot be
edited — corrections use a compensating entry.

### Media

`Catalog > Media` is the media library. It shows
every uploaded file, filterable by name or context.

**Files are uploaded from where they are used.** To
upload a product image, use the product editor's
Images tab. To upload a store logo, use Settings >
Branding. The Media library is for browsing, not
uploading.

**To replace a file**, click Replace on its tile and
choose a new file. The old file's references are
preserved — the new file takes their place.

**To delete a file**, click Delete. The file is
soft-deleted. If any entity still references it, the
deletion is blocked with a warning. Remove the
references first.

### Reviews

`Catalog > Reviews` is the review moderation queue.
Customers submit reviews from the product page.
Every review enters as **Pending**.

Three tabs: **Pending | Approved | Rejected**.

**To approve a review**, click the checkmark icon on
its row. The review becomes visible on the product
page.

**To reject**, click the X. The review stays in the
store but is hidden from the storefront.

**To delete**, click the trash icon. A confirmation
modal appears. Deletion is permanent.

**What customers see.** Approved reviews appear in
the Reviews tab on the product page. The star
average and count also appear in the buy box under
the product title. Pending and rejected reviews are
not visible to customers.

**Verified purchase.** If the reviewer has a
completed or delivered order containing the product,
their review carries a "Verified purchase" badge.
The badge is automatic — nothing to configure.

---

## 3. Sales

### Orders

`Sales > Orders` lists customer orders. Filters:
status, payment state, date range.

Click an order to see its detail page: line items,
customer, shipping address, payment, and status
history.

**Status workflow.** Orders move through:

  Pending payment → Paid → Processing → Packed
  → Shipped → Delivered → Completed

Refunded, cancelled, and failed are terminal states.

You update status manually from the order detail
page. The order status history records every change
with a timestamp and the acting user.

### Customer Groups

`Sales > Customer Groups` lists the store's customer
segments — Retail, Wholesale, VIP, Staff.

A group has:

  - A **name** and slug.
  - A **default** flag (only one group is the default
    for new customers).
  - A **B2B** flag for wholesale ordering.
  - An **approval required** flag for gated sign-up.
  - An optional **net terms** (pay within 30 days).

**Tier pricing.** A group can have a price list with
tiered pricing — a different price for each quantity
range. To edit, open the group and click **Prices**.

**Minimum order quantity.** A product can require a
minimum quantity for a group. Set in the product
editor's Pricing tab.

### Customer Approvals (B2B)

If a customer group requires approval, new
registrations enter a queue at
`Sales > Customer Approvals`.

**To approve a customer**, click Approve. They can
now check out. Their account starts receiving the
group's pricing.

**To reject**, click Reject. The customer can still
log in but cannot place orders. The history tab on
their row shows every approval action taken.

---

## 4. Settings

### Store Settings

`Settings > Store Settings` has five tabs.

**General** — contact email, contact phone, store
slogan, "hide out-of-stock products" toggle.

**Branding** — store logo, favicon, PWA name and
colors. Uploads go through the media pipeline.

**Fonts** — the display font, body font, and script
font used across the storefront. Fifty curated
open-source fonts are available. Selecting a font
downloads it to your server; the storefront serves
it locally. No external font requests. Changing a
font takes a moment while the download runs; the
page will not reload until it finishes.

**Compliance** — the cookie consent banner and
related legal settings. See Legal Pages below.

**Advanced** — custom key-value settings. Use only
if you know what you are doing.

### Legal Pages

`Settings > Legal Pages` edits the four German legal
pages required by law:

  - **Impressum** — provider identification (§ 5 DDG).
  - **AGB** — terms and conditions (§ 312i BGB).
  - **Widerruf** — withdrawal instruction
    (Art. 246a EGBGB).
  - **Datenschutz** — privacy policy (DSGVO Art. 13).

Each page has a rich-text editor. Content is your
responsibility. The platform provides the
infrastructure; it does not supply the legal text.

**Required for German stores.** Checkout is blocked
if any of the four pages is missing. The Compliance
map reads the store's `country_code` to decide which
pages are required.

### Users

`Settings > Users` lists the admin users who can log
in. Each has a role.

**To add an admin user**, click **New user**. Choose
a role from the six defaults. Assign only as much
permission as the user needs.

**To change a role**, edit the user. Role changes
take effect on the user's next page load.

### Roles

`Settings > Roles` lists the six default roles:

  - **Super Administrator** — full access, including
    settings, users, roles.
  - **Store Administrator** — full access to a single
    store's data.
  - **Catalog Manager** — products, categories,
    inventory.
  - **Order Manager** — orders, fulfilment, refunds.
  - **Marketing Manager** — promotions, discounts,
    content.
  - **Customer Support** — customer and order
    assistance.

**To create a custom role**, click **New role** and
assign individual permissions. Custom roles are
rarely needed unless your team has specialized
duties.

---

## 5. Storefront

### What customers see

Open the storefront at `/` (or your domain) in a new
tab. The storefront is fully separate from the admin
panel. Changes you make in the admin appear on the
storefront after you save.

**Guest preview.** Log out of the admin and open the
storefront to see what a first-time visitor sees.
Log back in to the admin to continue work.

### The installable app (PWA)

The storefront is installable as an app on phones
and desktops. Customers see an "Install" prompt in
their browser, or a download icon in the header.
Once installed, the store opens as a standalone
window with an offline shell.

No configuration is required. The install prompt
appears automatically once a browser determines the
site is installable.

---

## 6. Common Tasks

**"I want to change my logo."**
Settings > Branding > Logo. Upload a new image. Save.
Hard-refresh the storefront.

**"The category banner looks wrong."**
The banner crops your image to a 3:1 aspect. For
best results, upload a source image that is already
3:1 (recommended 1600 × 533). Keep important content
centered — the sides may crop on very wide or very
narrow screens.

**"I uploaded a product image but it's not showing."**
Check the product editor's Images tab. Is the image
assigned to the gallery? Is there a primary flag on
one of them? Hard-refresh the storefront. If the
image is still missing, check the Media library — is
the file there and not soft-deleted?

**"How do I hide an out-of-stock product?"**
Settings > Store Settings > General > "Hide
out-of-stock products from listings". Toggle on.
Save.

**"Why is my font not showing?"**
The font downloads on first selection. If the page
was closed before the download finished, the font
file may be missing. Re-select the font and save
again. Check the Fonts tab — the select will show
the currently active font.

**"How do I add a product to the home page?"**
The home page category grid shows top-level
categories. Assign the product to a category that
appears on the home page. If the category is not
already on the home page, add it in the home page
configuration (a future feature; in the current
release, the home page uses a fixed category grid).

---

## 7. Troubleshooting

**Login problems.**
Check that the email is correct. Use the password
reset. If the reset email does not arrive, check
with your mail provider — some hosting providers
block transactional email. Configure your SMTP
settings under Settings > Store Settings > Advanced
(if SMTP is configurable in your deployment).

**Image upload fails.**
Check the file size — the maximum is 5 MB for
product images, 1 MB for logos and favicons.
Check the format — PNG, JPEG, WebP, and GIF are
accepted. If the upload still fails, check the
server's PHP upload limits with your hosting
provider.

**Stock shows wrong.**
Open Inventory and check the row's quantity. If it
looks wrong, adjust it with a reason. The movement
ledger records what changed. The available quantity
is quantity minus reserved; a customer with an
unfinished cart is holding some units.

**Order status is stuck.**
Open the order detail page. Change the status
manually. If a status cannot be changed, the order
may be in a terminal state (cancelled, refunded,
failed). Terminal orders cannot be moved forward.

---

## 8. Glossary

**Product.** A catalog item. Simple or variable.

**Variant (or Variation).** A concrete sellable
combination of attribute values — a specific
product in a specific color and size.

**Attribute.** A product option type — Color, Size,
Material.

**Attribute value.** One option — Red, Medium,
Cotton.

**Variant axis.** An attribute that generates
variants. Attributes without this flag are
informational only.

**Category.** A grouping of products. Categories
form a tree — a category can have a parent and any
number of children.

**Collection.** A curated grouping of products
across categories. (Not yet built in the current
release.)

**Brand.** A manufacturer or label. Distinct from
category.

**Store.** Your shop. Every piece of data belongs to
a store. The platform supports multiple stores per
installation; you have one.

**Platform.** The underlying software. Distinct
from your store.

**SKU.** Stock Keeping Unit — the unique code that
identifies a specific sellable item.

**MOQ.** Minimum Order Quantity. A per-product
minimum.

**Net terms.** An agreement to pay for an order
within N days of invoice, instead of at checkout.
B2B feature.

**PWA.** Progressive Web App. The storefront's
installable mode on phones and desktops.

**Media library.** The store's uploaded file
collection. Browsed at Settings > Media.

**Media pipeline.** The automatic processing every
uploaded image goes through: multiple sizes,
multiple formats, metadata stripping.

**Legal pages.** The four compliance pages required
for German stores: Impressum, AGB, Widerruf,
Datenschutz.

---

## Getting Help

If this guide does not answer your question, check
the form hints in the admin panel. Most fields carry
a short hint below them describing what the field is
for. If a page is not self-explanatory and the hint
is missing, this is a documentation gap — report it.

For technical issues (server errors, broken pages),
contact your hosting provider. The platform's
technical documentation is in `docs/`.
