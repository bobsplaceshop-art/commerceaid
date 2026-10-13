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
4. Marketing
5. Settings
6. Storefront
7. Common Tasks
8. Troubleshooting
9. Glossary

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

The sidebar is grouped by area. Groups appear in this
order:

  - **Dashboard** — the landing page (ungrouped).
  - **Business** — Setup.
  - **Catalog** — Products, Categories, Brands,
    Inventory, Attributes, Media, Reviews.
  - **Sales** — Orders, Customers, Customer Groups,
    Customer Approvals, Returns, Coupons.
  - **Marketing** — Subscribers, Subscriber lists,
    Campaigns.
  - **Settings** — Store Settings, Legal Pages, Tax,
    Shipping, Users, Roles.

Click a group header to expand or collapse it. The
state is remembered on this browser.

Some items carry a numeric badge on the right. The
**Orders** badge counts orders awaiting action. The
**Reviews** badge counts reviews pending moderation.

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

### Role-based visibility

What you see in the sidebar depends on your role.
A merchant with a **Customer Support** role, for
example, sees Orders and Customers but not Settings
or Roles. If an item you expect is missing, it is
because your role does not have the permission for
it. Ask a Super Administrator to grant the permission
or assign a broader role.

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

`Sales > Orders` lists customer orders. Filters at the
top: status, payment state, date range, and a search
box for the order number or customer name. Status tabs
sit above the list.

Click an order to open its detail page. The detail
page shows:

  - **Line items** with quantity, unit price, and
    line total.
  - **Customer** and shipping address.
  - **Totals breakdown** — subtotal, discount,
    shipping, VAT (with rate), grand total. German
    stores see the "inkl. X% MwSt." note here.
  - **Internal notes** (only visible to admin users).
  - **Status history** — every transition, with
    timestamp and the acting user.

**Status workflow.** Orders move through:

  Pending payment → Paid → Processing → Packed
  → Shipped → Delivered → Completed

Refunded, cancelled, and failed are terminal states.
A terminal order cannot be moved forward.

**To change status**, click the **Change status**
button in the sticky action bar. A modal opens asking
for the new status and a reason note. The reason is
written to the status history ledger and cannot be
edited afterward.

**To add a note**, click **Add note** in the sticky
action bar. Notes are internal — customers never see
them.

### Customers

`Sales > Customers` lists customer accounts. Tabs
across the top filter by approval status (All,
Pending, Approved, Rejected). Filters below: search,
group, account state, pagination.

Click a customer to open their detail page. It
shows:

  - **Contact details** — name, email, phone,
    company, tax ID, date of birth.
  - **Group membership** and net-terms if B2B.
  - **Addresses** — billing and shipping, with
    default flags.
  - **Recent orders** — the last ten.
  - **Approval history** — every approval action.

**To edit a profile**, click **Edit profile** in the
sticky action bar. Email is read-only by design — a
customer changes their own email from their account
page, and the change must be verified.

**To change a group**, click **Change group**. A
modal opens. Selecting a new group changes the
customer's pricing tier immediately (price lists are
per-group).

**To change account state**, click **Enable**,
**Disable**, or **Archive**. A disabled customer
cannot log in. An archived customer is hidden from
the active list but keeps their order history.

**To manage addresses**, scroll to the Addresses
card. Each address has Edit, Delete, and Set as
default buttons. Deleting the default address
promotes the next address to default automatically.

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

### Returns

`Sales > Returns` is the admin returns queue. It
backs the customer-facing return flow at
`/account/returns`, and enforces the German
Widerrufsrecht (14-day right of withdrawal).

**Four tabs** across the top:

  - **Open** — new requests awaiting decision, plus
    approved but not yet received.
  - **Received** — the warehouse has confirmed the
    goods arrived.
  - **Closed** — refunded, rejected, or cancelled.
  - **All** — every request.

Filters below: search by request number, and a
date range.

Click a request to open its detail page. It shows:

  - **Line items** — what the customer wants to
    return, at what quantity.
  - **Customer** and **original order**.
  - **Reason** — the customer's stated reason.
  - **Timeline** — every action taken, with
    timestamp and actor.
  - **State-aware action buttons** in the sticky
    action bar.

**Action buttons depend on the request's current
state:**

  - **Requested** → Approve, Reject.
  - **Approved** → Reject, Mark received.
  - **Received** → Process refund.
  - **Terminal** (refunded, rejected, cancelled) →
    no actions.

**To approve**, click Approve. The customer's order
detail and the "Return in progress" badge update
immediately.

**To reject**, click Reject. A modal asks for a
reason. The reason is stored and visible in the
customer's return detail page.

**To mark received**, click Mark received. A modal
opens with **per-line received quantities**. Set
each line to what the warehouse actually counted,
not what the customer requested. A customer who
requested three and the warehouse received two is
refunded for two. Lines you do not adjust default
to the requested quantity.

**To process the refund**, click Process refund.
This updates the order's `refunded_total` and
`payment_status`, and the per-line
`returned_quantity`. The customer's order detail
immediately shows the "N of M returned" annotation
and the "Refunded" totals row.

**The money movement is separate.** The refund
action records the amount owed. Calling the payment
provider's refund API is the Payments (Deep) stage.
Until that ships, the recorded `refunded_total` is
the authoritative amount you owe the customer.

### Coupons

`Sales > Coupons` lists discount codes. The list
shows code, type, value, usage, and status.

**To create a coupon**, click **New coupon**.

Fields:

  - **Code** — the string the customer enters.
    Uppercase, no spaces.
  - **Description** — internal, shown to admin users
    only.
  - **Type** — one of:
    * **Percentage** — e.g. 15 = 15% off.
    * **Fixed amount** — e.g. 25 = 25 currency
      units off.
    * **Free shipping** — waives the shipping
      charge.
  - **Value** — the number for the type above.
    Ignored for free shipping.
  - **Valid from / Valid to** — the window the code
    can be used. Leave either blank for no bound.
  - **Minimum order subtotal** — the cart must
    reach this before the code applies.
  - **Usage limit** — maximum total redemptions
    across all customers.
  - **Per-customer limit** — maximum redemptions
    per customer.
  - **Applies to** — all products, or specific
    categories. When specific categories, pick them
    from the list.

**To disable a coupon** without deleting it, set
status to Disabled in the editor.

**To delete**, click Delete in the list. A modal
appears. **A coupon with redemptions cannot be
deleted** — the redemption history must be
preserved. Disable it instead.

**How customers use it.** The cart page has a
coupon field below the subtotal. Enter the code and
click Apply. The discount line appears in the totals.
The customer can remove the coupon at any time.

**Validation rules.** The system checks, in order:
code exists and is active, valid window, usage
limits, per-customer limit, minimum order. The first
failure short-circuits with a specific message —
"Coupon expired", "Coupon already used", "Minimum
order not met" — not a generic rejection.

---

## 4. Marketing

### Subscribers

`Marketing > Subscribers` lists everyone who has
signed up to receive email from the store. The list
shows email, name, status, source, and consent date.

**Statuses:**

  - **Pending** — signed up but has not clicked the
    confirmation link yet (double opt-in).
  - **Confirmed** — consent is verified; the
    subscriber receives campaigns.
  - **Unsubscribed** — the subscriber opted out.
  - **Suppressed** — the system opted them out
    (hard bounce, repeated soft bounce, or a spam
    complaint).

Filters: status, source, list, date range. A search
box matches the email.

**To export the list**, click **Export CSV** at the
top of the page. The export includes every field
relevant to a DSGVO audit: email, name, status,
consent timestamp, consent IP, consent source. Use
this export as the proof of consent when your data
protection officer asks.

Click a subscriber to open their detail page. It
shows:

  - **Consent record** — when, from where, from
    which IP the consent was collected.
  - **List memberships** — which lists they belong
    to.
  - **Event history** — every email sent, opened,
    clicked, bounced, or unsubscribed.

**To unsubscribe a subscriber manually**, click
**Unsubscribe** in the sticky action bar. A modal
asks for a reason. The reason is stored and shown
in the subscriber's event history. Use this when a
customer asks by phone or email to be removed.

**Never delete a subscriber record.** The consent
record is a legal artefact. Use Unsubscribe.

### Subscriber Lists

`Marketing > Subscriber lists` manages named lists.
A subscriber can belong to more than one list.

**The default list** is created for you when the
store is set up. It is called "Newsletter" and the
footer signup form joins new subscribers to it. You
cannot delete the default list, but you can rename
it.

**To create a list**, click **New list**. Fields:
name, slug, description. The slug appears in URLs
and cannot contain spaces.

**To edit a list**, click its row. You can rename,
change the description, or mark it as the new
default.

**To delete a list**, click Delete. **A list with
members cannot be deleted.** Remove members first,
or archive the list by renaming it (e.g. prefix
with "Archived —").

**What lists are for.** Different audiences get
different campaigns. A "Product updates" list gets
launch announcements. A "Promotions" list gets sale
alerts. The default "Newsletter" list is the widest
— anyone who signed up from the footer.

### Campaigns

`Marketing > Campaigns` lists every campaign the
store has drafted, sent, or cancelled. Statuses:
Draft, Scheduled, Sending, Sent, Cancelled.

**To compose a campaign**, click **New campaign**.

Fields:

  - **Subject** — the email subject line.
  - **Preheader** — the preview text most email
    clients show next to the subject. Optional but
    recommended.
  - **List** — which subscriber list receives it.
  - **Body** — the rich-text editor. Same editor as
    the legal pages.

**The composer uses the same editor as Legal Pages.**
Formatting toolbar: bold, italic, headings, lists,
links, and a code view for raw HTML. Click the
`< >` icon to switch between visual and HTML modes.

**To save a draft**, click **Save draft** in the
sticky action bar.

**To preview**, click **Preview**. A new tab opens
with the full preview page on the admin shell. This
shows what the campaign will look like with the
store header, the body, and the unsubscribe footer.

**The detail page also has an inline preview.** Open
the campaign detail page (not the editor) and scroll
to the **Body preview** card. The body renders
inside a framed white area, on true email-client
defaults — white background, dark text, 600px max
width, 24px padding. This is exactly what the
recipient's email client will show, isolated from
the admin theme. The preview frame grows to fit the
content, so long campaigns show in full without an
inner scrollbar.

**To send**, click **Send campaign** in the sticky
action bar. A confirmation modal appears. The
campaign queues one row per confirmed subscriber
and the CLI worker (`bin/send-emails.php`) drains
the queue.

**The worker is a cron job.** Your hosting provider
installs a crontab entry such as:

    * * * * * php /path/to/bin/send-emails.php --batch=100

Without the cron, the queue fills but no email
leaves. Ask your hosting provider to install the
cron entry if you are not sure it is in place.

**Bounce handling.** A hard bounce (user unknown,
domain does not exist) suppresses the subscriber
permanently. A soft bounce (mailbox full, try again
later) increments a counter; three consecutive soft
bounces suppress the subscriber. A successful send
resets the counter to zero.

**To cancel a campaign before it sends**, edit it
and change status to Cancelled, or delete the
draft. Once sending has started, cancellation stops
the queue but does not recall emails already sent.

**To delete a draft**, click Delete in the list.
Sent campaigns cannot be deleted — the send history
is a legal record.

### Forms

`Marketing > Forms` is the form builder. You can create
forms without code, embed them in the storefront, and
manage the submissions they receive. There is no
submission cap and no storage limit — submissions live
in your own database and are never sent to a third
party.

Common uses: contact forms, quote requests, B2B
onboarding, event registrations, surveys, and any
other structured input your business needs.

#### Creating a form

Click **New form**. The editor has two sections: the
metadata card at the top, and the field list below.

Fields on the metadata card:

  - **Name** — shown in the admin list and in the
    browser tab title. Internal.
  - **Slug** — the public URL path. A form with slug
    `contact` renders at `/forms/contact`. Lowercase,
    no spaces. The slug is editable while the form is
    a draft and becomes read-only the first time you
    publish.
  - **Description** — optional, shown above the form
    to the customer.
  - **Success message** — the text shown after a
    successful submission. If left blank, a stock
    message is used.
  - **Notify email** — optional. If set, a copy of
    every submission is emailed to that address.
  - **Honeypot** — on by default. Renders an
    invisible field that bots fill in and humans do
    not. Submissions that trip the honeypot are
    silently accepted but not stored.
  - **Require sign-in** — off by default. When on, a
    customer must be logged in to view or submit the
    form. Use for B2B quote requests, account
    enquiries, and anything that should not be public.

#### Adding fields

Below the metadata card is the field list. Click
**Add field** to open the field editor modal.

Each field has:

  - **Label** — what the customer sees. "Email
    address", "Company name", "How did you hear
    about us".
  - **Key** — the internal name used in the stored
    submission and in any webhook payload. Lowercase,
    underscores, no spaces. The key is generated from
    the label the first time the field is saved and
    is immutable from then on. Changing it would
    orphan the old submissions.
  - **Type** — one of twelve input types (listed
    below).
  - **Required** — a required field blocks submission
    if empty.
  - **Placeholder** — optional grey text shown inside
    the input before the customer types.
  - **Help text** — optional text shown below the
    input to explain what is expected.
  - **Step** — which step of a multi-step form the
    field belongs to. Step 1 by default. Leave as 1
    for a single-step form.
  - **Conditional visibility** — optional. When set,
    the field is only shown to a customer when another
    field in the same form has a specific value.
    See Conditional logic below.
  - **Type-specific config** — depends on the type.
    A "Dropdown" field asks for a list of options.
    A "Number" field asks for minimum and maximum
    values. The config sections appear and disappear
    as you change the type.

The twelve input types:

  - **Text** — single-line free text.
  - **Email** — validated as an email address.
  - **Phone** — validated as a phone number.
  - **Number** — accepts digits; minimum and maximum
    are configurable.
  - **Date** — date picker.
  - **URL** — validated as a web address.
  - **Long text** — multi-line free text. Use for
    messages, descriptions, comments.
  - **Dropdown** — a single-select list. You provide
    the options.
  - **Multi-select** — a list where the customer can
    pick more than one option.
  - **Checkbox group** — a set of checkboxes. Same
    storage as multi-select, different presentation.
  - **Yes / No** — a single toggle.
  - **File upload** — lets the customer attach a
    file. Not currently in use on the storefront;
    reserved for a future stage.

**To reorder fields**, use the up and down arrow
buttons on each row. The position column shows the
current order.

**To edit a field**, click the pencil icon. The modal
opens with the field's current configuration.

**To delete a field**, click the trash icon. A
confirmation modal appears. Deletion is permanent —
the field is removed from the form and from future
submissions. Existing submissions are preserved.

#### Conditional logic

Every field can be shown conditionally. For example,
a "Tell us about your project" field can be hidden
until the customer selects "Project enquiry" from a
"Reason for contact" dropdown.

One rule per field. Set it in the field editor under
**Conditional visibility**:

  - **When** — pick another field in the same form.
    A field cannot depend on itself.
  - **Operator** — one of six:
    * **equals** — the referenced field has exactly
      this value.
    * **not equals** — the referenced field has any
      value except this one.
    * **contains** — the referenced field's value
      includes this substring.
    * **is empty** — the referenced field has no
      value.
    * **is not empty** — the referenced field has a
      value.
    * **greater than** / **less than** — numeric
      comparison.
  - **Value** — the value to compare against.
    Ignored for "is empty" and "is not empty".

Rules are evaluated on the client as the customer
types (fields appear and disappear live) and again
on the server when the form is submitted (fields
whose condition is not met are neither validated nor
stored).

#### Multi-step forms

A form is multi-step when any field has a step
greater than 1.

On the storefront, the form renders one step at a
time with a **Next** button to advance and a **Back**
button to return. A step indicator shows "Step N of
M". The **Submit** button appears on the final step.

The step structure is presentation only. The whole
form is validated and stored as one unit. A customer
who reloads mid-form starts again from step 1.

Set a field's step in the field editor. All fields
with the same step number render together.

#### Publishing and unpublishing

A form starts as a draft. Only drafts are visible in
the admin. The public URL is not live.

**To publish**, click Publish in the status card. A
form with zero fields cannot be published — add at
least one field first. The edit page will tell you
this if you try.

Once published, the form is reachable at
`/forms/{slug}`. Anyone with the URL can submit it,
unless **Require sign-in** is on.

**To unpublish**, click Unpublish. The public URL
returns a 404. Submissions already received are
preserved.

The slug becomes read-only on first publish. If you
need a different public URL, duplicate the form and
give the copy a new slug.

#### Duplicating

**To duplicate**, click Duplicate in the status card.
A copy is created with:

  - The same fields, options, and conditional rules.
  - The same metadata (description, success message,
    notify email, honeypot, require sign-in).
  - A new slug suffixed with `-copy`. If a form with
    that slug already exists, a numeric suffix is
    added.
  - Zero submissions. The copy starts with an empty
    submission history.

Use duplicate when you want to start a new form from
a similar one. Event registrations for recurring
events, contact forms for different departments, and
similar patterns.

#### Archiving and deleting

**To archive**, click Archive. The form is hidden
from the active list and the public URL returns a
404. Submissions are preserved and still viewable
from the submissions card.

**To delete**, click Delete in the danger zone. A
confirmation modal appears. Deleting a form soft-
deletes it: the record is hidden from every list and
the public URL stops working, but the row and its
submissions stay in the database for audit.

There is no undelete button in the admin. If you
delete a form by mistake, ask your developer to
restore it from the database.

#### Submissions

Every submission a form receives appears at
`Marketing > Forms > {form} > Submissions`. The
submissions list shows one row per submission, with
its status, submitter, and received date.

**The five tabs** across the top filter by status:

  - **New** — submissions you have not reviewed yet.
    This is the default view; new submissions land
    here.
  - **Read** — submissions you have reviewed but
    chosen to keep.
  - **Spam** — submissions you have flagged as spam.
  - **Archived** — submissions you have decided to
    hide from the active list without deleting.
  - **All** — every submission for this form.

Each tab shows a count. The four rightmost tabs also
count submissions in the state they represent, so you
can see at a glance how many are waiting for review.

**To search**, type into the search box. The list
matches against the submitter's name and email. This
is the fastest way to find a submission when a
customer calls and asks about their enquiry.

**To filter by date**, set a From or To date. The
range is inclusive of both endpoints.

**To export the current view to CSV**, click Export
CSV top-right. The file downloads with one row per
submission and one column per form field, plus the
submission id, received date, status, submitter name,
submitter email, submitter IP, and any internal
notes. The export respects the active filters — if
you are viewing the Spam tab, the export contains
only spam submissions.

**To change a submission's status**, click into the
submission to open its detail page. The status
dropdown is in the Status card. Pick a new status
and click Update status.

**To add an internal note**, scroll to the Internal
notes card on the detail page. Type your note. Notes
are saved when you update the status. Notes are
visible only to admin users.

**To bulk-update**, on the list page check the box
on each row you want to act on. A bulk action bar
appears above the table with four options:

  - **Mark read** — moves the checked submissions
    from New to Read.
  - **Mark spam** — moves them to Spam.
  - **Archive** — moves them to Archived.
  - **Delete** — permanently removes them.

Delete asks for confirmation. The other three take
effect immediately. Bulk actions ignore submissions
that are already in the target status — checking a
submission that is already Read and clicking Mark
read is a no-op, not an error.

**To delete one submission**, open it and click
Delete submission in the Danger zone at the bottom
of the detail page. A confirmation modal appears.

There is no undelete. A deleted submission is gone.
Export it to CSV first if you want a record.

**Notifications.** Every new submission triggers a
notification through the store's rule engine. The
default is no notification — you have to configure a
rule first. See the Notification rules section in
your store's Settings for the exact mechanism. A
submission arrives in the list whether or not a rule
is configured; the notification is a convenience,
not a delivery mechanism.

#### The public URL

A published form renders at `/forms/{slug}` in the
storefront. The customer sees:

  - Your store's header and footer.
  - The form description (if set).
  - The form fields, in the order you defined.
  - The Submit button.

After a successful submission, the success message
replaces the form. The customer can submit again from
the same page unless you turn the form off.

Submissions arrive in the admin at
`Marketing > Forms > {the form} > Submissions`.
That surface, with filters and CSV export, ships in
a later session of the Forms stage. Until then,
submissions are stored but not viewable from the
admin panel.

#### Permissions

Forms use four permissions, seeded per store:

  - `forms.view` — see the form list and read the
    fields.
  - `forms.manage` — create, edit, publish, unpublish,
    archive, duplicate, and delete forms.
  - `forms.submissions.view` — read the submissions
    for a form.
  - `forms.submissions.manage` — edit submission
    status, add internal notes, export.

The default roles are:

  - **Super Administrator** — all four.
  - **Store Administrator** — all four.
  - **Marketing Manager** — all four.
  - **Customer Support** — `forms.submissions.view`
    only.

A merchant can create a custom role with any subset.


---

## 5. Settings

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

### Tax

`Settings > Tax` manages tax classes and rates. Two
tabs: **Classes** and **Rates**.

#### Tax classes

A tax class is a category of products taxed the same
way. The store ships with three seeded classes:

  - **Standard** — the default class. Most products
    belong here. German rate: 19%.
  - **Reduced** — for goods taxed at the reduced
    rate. German rate: 7%. Food, books, some
    services.
  - **Zero** — for goods taxed at 0%. Exports,
    certain agricultural products.

**The default class cannot be deleted.** It is the
fallback for products without an explicit
assignment.

**To create a class**, click **New class**. Give it
a name. Save.

**To assign a class to a product**, open the
product editor's Pricing tab and pick the class.
Products without an explicit class use the default.

#### Tax rates

A tax rate is the percentage applied to a class in
a specific country (and optionally a state). The
same class has different rates in different
countries.

**To create a rate**, click **New rate**. Fields:

  - **Tax class** — which class the rate applies to.
  - **Country** — ISO two-letter code. `DE` for
    Germany, `US` for United States, `KE` for Kenya.
  - **State / region** — optional. Some countries
    (US, Canada, India, Brazil) tax by subdivision.
    Leave blank for flat-rate countries.
  - **Rate percent** — e.g. `19` for 19%, `7.5` for
    7.5%. Three decimal places are supported.
  - **Price is inclusive** — the critical flag. See
    below.
  - **Shipping is taxable** — whether the shipping
    charge is included in the taxable amount.

**Inclusive vs exclusive.** This is the single most
important field on a tax rate, and getting it wrong
mis-charges every customer.

  - **Inclusive** — the price shown to the customer
    already includes the VAT. The customer sees the
    gross price. VAT is backed out of it for
    reporting. This is the standard for retail in
    Germany (PAngV § 4) and most of Europe.
  - **Exclusive** — the price shown is net. The VAT
    is added on top at checkout. This is the
    standard in the United States and most B2B
    contexts.

If you are a German store selling to consumers,
your rate is inclusive. If you are a US store, your
rate is exclusive. If you are uncertain, ask your
accountant.

**Per-product override.** A single product can
override its class's rate. Useful for products
whose tax treatment differs from the rest of their
class. Set it in the product editor's Pricing tab:
a percent override field, plus a three-state select:

  - **Inherit from class** (default) — use the
    class rate.
  - **Inclusive** — force inclusive pricing for this
    product.
  - **Exclusive** — force exclusive pricing for this
    product.

### Shipping

`Settings > Shipping` manages shipping methods and
zones. Two tabs: **Methods** and **Zones**.

#### Shipping methods

A method is a way of getting the goods to the
customer. Five types ship:

  - **Flat** — a fixed charge regardless of cart.
  - **Free over** — free above a threshold, flat
    below it.
  - **Weight based** — a rate per unit weight.
  - **Price based** — a rate per currency unit of
    subtotal.
  - **Local pickup** — always free. Use for customers
    who collect in person.

**To create a method**, click **New method**.
Fields:

  - **Code** — stable identifier. Written to orders
    for history. Cannot be changed once orders
    reference it.
  - **Label** — what the customer sees, e.g.
    "Standard shipping (2-3 days)".
  - **Description** — optional longer text.
  - **Type** — one of the five above.
  - **Type-specific fields** — appear based on the
    type. For example, flat asks for the rate;
    free-over asks for both the rate and the
    threshold.
  - **Active** — inactive methods are hidden from
    checkout but kept for history.
  - **Sort order** — lower numbers appear first in
    the customer's method picker.

**A method with recent orders cannot be deleted.**
Disable it instead.

#### Shipping zones

A zone declares where a method is available. A
method with no zones is offered nowhere.

**To create a zone**, click **New zone**. Fields:

  - **Method** — which method the zone is for.
  - **Country** — ISO two-letter code.
  - **Region** — optional subdivision. Leave blank
    for the whole country.
  - **Rate override** — optional. Overrides the
    method's computed rate for this zone. Use for
    surcharges to remote areas or discounts to
    nearby ones.
  - **Active** — inactive zones are skipped.

**A German store shipping nationally** needs one
zone: method X, country DE, no region. A store
shipping to DE, AT, CH needs three zones.

### Users

`Settings > Users` lists the admin users who can log
in. Each has a role.

**To invite a user**, click **New user**. Fields:
name, email, role. Clicking Send invite emails the
invitee a link to set their password. The invite
link expires in 24 hours.

**To change a role**, edit the user. Role changes
take effect on the user's next page load.

**To deactivate**, click Deactivate in the row's
actions menu. A deactivated user cannot log in but
their history is preserved.

**To resend a password reset**, click **Send
password reset** in the row's actions menu.

**The last Super Administrator cannot be
deactivated or demoted.** The system enforces this
— without at least one Super Administrator, no one
can recover the platform.

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

**To see a role's permissions**, click its row. The
permission matrix opens, grouped by module. Every
permission the platform knows about is listed; the
checked ones belong to the role.

**To create a custom role**, click **New role**.
Give it a name, then check the permissions it should
have. Custom roles are rarely needed unless your
team has specialized duties.

**To edit a role**, open it, change the checked
permissions, and save. The change takes effect for
all users assigned to that role on their next page
load.

**Super Administrator is read-only.** Its
permissions are locked. Every other role is fully
editable, including Store Administrator.

**To delete a role**, click Delete in the list. **A
role with users assigned cannot be deleted.**
Reassign the users first.

---

## 6. Storefront

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

### Newsletter signup

The footer and home page carry a newsletter signup
form. When a customer subscribes, the platform:

  1. Creates a subscriber record with status
     **Pending**.
  2. Sends a double opt-in confirmation email.
  3. On click of the confirmation link, moves the
     subscriber to **Confirmed**.

Until the customer confirms, they are not on any
campaign's recipient list. This is required for
German compliance (UWG § 7).

The confirmation link expires 24 hours after it is
sent. If a customer asks why they did not receive
their confirmation email, ask them to sign up again
or resend it from the subscriber's admin detail
page.

---

## 7. Common Tasks

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

**"How do I create a coupon?"**
Sales > Coupons > New coupon. Enter the code, pick
the type (percentage, fixed amount, or free
shipping), set the value, and optionally set a
valid window and usage limits. Save. Test it on
the storefront by adding a product to the cart and
entering the code in the cart page's coupon field.

**"How do I set up German VAT?"**
Settings > Tax. The three classes (Standard,
Reduced, Zero) are seeded for you. Add a rate:
pick the class, country `DE`, rate percent (usually
19 for Standard, 7 for Reduced), and set **Price is
inclusive** on. Add a second rate for the Reduced
class if you sell any reduced-rate goods. The
product editor's Pricing tab lets you assign each
product to a class.

**"How do I add a shipping method?"**
Settings > Shipping > Methods > New method. Pick a
type, set the parameters, save. Then go to the
Zones tab and add a zone for each country the
method serves — at minimum, `DE` if you ship
nationally. A method with no zone is offered to no
one.

**"How do I send a newsletter campaign?"**
Marketing > Campaigns > New campaign. Write the
subject, preheader, and body. Pick the list. Save
as draft, preview it, then click Send campaign.
Make sure the cron worker is installed
(`bin/send-emails.php`) or the queue will not
drain.

**"How do I remove a customer's data?"**
Two steps:
  - If the customer has an account, disable it from
    Sales > Customers. This prevents future logins.
  - If the customer has subscribed to the
    newsletter, unsubscribe them from Marketing >
    Subscribers.
  
  The platform does not automatically delete order
  history — German tax law requires you to retain
  business records for 10 years (AO § 147). If the
  customer requests erasure of specific personal
  data, use the data request workflow (future
  feature) or contact your data protection officer.

**"How do I change who can see what in the admin?"**
Settings > Roles. Open the role, check or uncheck
permissions, save. Users assigned to that role see
the change on their next page load.

---

## 8. Troubleshooting

**Login problems.**
Check that the email is correct. Use the password
reset. If the reset email does not arrive, check
with your mail provider — some hosting providers
block transactional email. Configure your SMTP
settings under Settings > Store Settings > Advanced
(if SMTP is configurable in your deployment).

**Invite email not arriving.**
The invite link expires 24 hours after send. Check
that the email address is correct. If the invitee
has not received it, resend the invite from
Settings > Users.

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

**A coupon says "Invalid code" but the customer
swears it is correct.**
Check the coupon's valid window, usage limit, and
minimum order. The most common cause is the minimum
order threshold — the customer's cart does not
reach it. The second most common is that the
per-customer limit has been reached. Open Sales >
Coupons and check the coupon's usage count.

**The campaign preview is blank or shows a broken
document.**
Hard-refresh the page (Ctrl+Shift+R). If the
preview still does not render, check the browser
console for errors and contact your developer.
The preview uses an embedded frame that loads from
the admin — it requires the admin to be reachable
from the browser you are using.

**A campaign was sent but no emails arrived.**
Check the queue worker. The campaign is queued
immediately, but sends only when the cron worker
(`bin/send-emails.php`) runs. If the cron is not
installed, the queue fills but no email leaves.
Ask your hosting provider to confirm the crontab
entry exists.

**A subscriber is not receiving campaigns.**
Check their status in Marketing > Subscribers. If
it is Pending, they never clicked the confirmation
link. If it is Unsubscribed or Suppressed, they
have opted out or been opted out by the system. A
suppressed subscriber stays suppressed — a single
hard bounce or three soft bounces is not reversible
without direct intervention from your developer.

---

## 9. Glossary

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
collection. Browsed at Catalog > Media.

**Media pipeline.** The automatic processing every
uploaded image goes through: multiple sizes,
multiple formats, metadata stripping.

**Legal pages.** The four compliance pages required
for German stores: Impressum, AGB, Widerruf,
Datenschutz.

**Coupon.** A discount code that customers enter at
cart to reduce the amount owed. Three types:
percentage, fixed amount, free shipping.

**Tax class.** A category of products taxed the same
way. The store ships with Standard, Reduced, and
Zero.

**Tax rate.** The percentage applied to a class in a
country (and optionally a state). Different
countries have different rates for the same class.

**Inclusive pricing.** The price shown to the
customer already includes VAT. The customer sees
the gross price. Standard for German retail.

**Exclusive pricing.** The price shown is net. VAT
is added at checkout. Standard for US retail and
most B2B contexts.

**Shipping method.** A way of getting goods to the
customer. Five types: flat, free over, weight
based, price based, local pickup.

**Shipping zone.** The countries and regions where a
shipping method is available.

**Subscriber.** Someone who has signed up to receive
email from the store. Has a status: pending,
confirmed, unsubscribed, or suppressed.

**Subscriber list.** A named group of subscribers.
A subscriber can belong to more than one list.

**Double opt-in.** The two-step signup process
required for German compliance. Step 1: the customer
signs up. Step 2: the customer clicks a confirmation
link in the email the platform sends.

**Unsubscribe.** The customer's choice to stop
receiving campaigns. Recorded as the subscriber's
status; never deleted.

**Suppressed.** The system's choice to stop
delivering to a subscriber — usually because of a
hard bounce or a spam complaint.

**Campaign.** A newsletter email sent to one
subscriber list. Composed in the admin, queued for
the CLI worker, drained by cron.

**Widerrufsrecht.** The German 14-day right of
withdrawal from a distance contract. Enforced by
the returns flow.

**PAngV.** Preisangabenverordnung — the German
regulation on price disclosures. Requires inclusive
pricing for consumers, and specific wording for
VAT and shipping notes.

**DSGVO.** Datenschutz-Grundverordnung — the German
name for GDPR.

**TTDSG.** Telekommunikation-Telemedien-Datenschutz-Gesetz
— the German law on consent for cookies and similar
technologies.

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
