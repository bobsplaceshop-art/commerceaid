# German Compliance Tracker

**Status:** Living document
**Opened:** 2026-10-02
**Applies to:** Any deployment serving customers in Germany
**Owner:** Whoever is building or maintaining the platform

---

## Purpose

This document tracks every German legal requirement the
platform has encountered, how the platform addresses it,
and where the reasoning lives.

It exists because compliance findings have been scattered
across stage plans, session files, and code comments. When
a new feature touches an area the platform has already
resolved, the answer should be one grep away, not one
conversation away.

Every entry has:

  - **The requirement** - what the law actually says
  - **The exposure** - what happens if we get it wrong
  - **How we address it** - what the platform does
  - **Where the code lives** - file paths so the next
    reader can audit the implementation
  - **Stage** - which stage shipped the fix
  - **Status** - Done / Partial / Not started / Deferred

Status values:

  Done         - implemented and tested
  Partial      - implemented, one piece deferred
  Not started  - identified, no code yet
  Deferred     - deliberately not built, with reasoning

---

## The Compliance Map

| # | Requirement | Status | Stage |
|---|-------------|--------|-------|
| 1 | Impressum (§ 5 TMG) | Done | 008E.1 |
| 2 | AGB (Terms) | Done | 008E.1 |
| 3 | Widerrufsrecht (Right of Withdrawal) | Done | 008E.1 |
| 4 | Datenschutzerklärung (Privacy Policy) | Done | 008E.1 |
| 5 | Cookie consent banner (TTDSG § 25) | Done | 008E.1 |
| 6 | Cookie consent API for third-party scripts | Done | 008E.1 |
| 7 | Country-driven compliance gate | Done | 008E.1 |
| 8 | Widerrufsrecht 14-day window enforcement | Deferred | 008H |
| 9 | Google Fonts self-hosting (DSGVO) | Not started | 008G.4c |
| 10 | EXIF stripping on image upload (DSGVO) | Partial | 008G.4a |
| 11 | Service worker consent exemption (TTDSG § 25) | Done | 008G.4b plan |
| 12 | Newsletter double opt-in | Not started | 008I |
| 13 | Newsletter unsubscribe link | Not started | 008I |
| 14 | Newsletter: no third-party transfer (DSGVO) | Not started | 008I |
| 15 | Media deletion rules (DSGVO Art. 17) | Done | 008G.4a plan |
| 16 | Data minimisation (DSGVO Art. 5) | Done | 008G.4a plan |
| 17 | Copyright attribution (UrhG § 13) | Partial | 008G.4a |
| 18 | Font Awesome attribution (OFL, MIT, CC BY) | Deferred | 008G.4c |
| 19 | Sub-processor disclosure for external services | Ongoing | - |

---

## 1. Impressum (§ 5 TMG)

**Requirement:** Every commercial website accessible in
Germany must publish an Impressum (imprint) with the
operator's legal name, address, contact details, and (for
companies) registration number, VAT ID, and authorized
representative.

**Exposure:** Warning letters (Abmahnungen) from competitors
and consumer protection associations. Typical cost per
Abmahnung: €500-2000 in legal fees, plus the requirement to
sign a cease-and-desist declaration with a penalty clause.

**How we address it:** The platform provides a Legal Pages
admin UI where the operator publishes an Impressum. The
page is required at checkout time for German stores via the
ComplianceMap. Content is the operator's responsibility;
the platform supplies the infrastructure.

**Where the code lives:**
  - `app/Commerce/Support/ComplianceMap.php` - required
    slugs per country
  - `app/Commerce/Controllers/Admin/LegalPageController.php`
  - `database/migrations/0302_create_legal_pages.sql`

**Stage:** 008E.1

**Status:** Done.

---

## 2. AGB (Terms and Conditions)

**Requirement:** Under § 305 BGB, standard terms and
conditions must be presented in a way that a reasonable
customer can be expected to notice them, and the customer
must have the opportunity to review them before
concluding the contract.

**Exposure:** The clause may not be enforceable. In a
dispute, the terms are treated as never agreed.

**How we address it:** The AGB page is a required legal
page for German stores. Checkout displays a mandatory
agreement checkbox linking to the AGB. Orders cannot be
placed without the checkbox ticked.

**Where the code lives:**
  - Same infrastructure as the Impressum.
  - The checkout legal agreement gate lives in
    `app/Commerce/Services/CheckoutService.php`
    (ComplianceMap::requiredLegalSlugs()).
  - Checkout view: `resources/views/storefront/checkout/*`.

**Stage:** 008E.1

**Status:** Done.

---

## 3. Widerrufsrecht (Right of Withdrawal)

**Requirement:** Under §§ 312g, 355 BGB (and the
Verbraucherrechterichtlinie it implements), consumers in
Germany have a 14-day right to withdraw from distance
contracts without giving a reason. The seller must inform
the customer of this right and provide a model withdrawal
form.

**Exposure:** If the seller fails to inform the customer,
the withdrawal period extends from 14 days to 12 months
plus 14 days. Fines up to €50,000 in serious cases.

**How we address it:** A "Widerrufsbelehrung" (Right of
Withdrawal instruction) is a required legal page. It must
exist before checkout can proceed for German stores. The
page carries the mandatory model withdrawal form and the
14-day notice.

**Where the code lives:**
  - Same Legal Pages infrastructure.
  - The checkout gate refuses to open checkout when the
    Widerrufsbelehrung is missing.

**Stage:** 008E.1

**Status:** Done for the instruction page. The actual
14-day window enforcement (returns processing, refund
tracking) ships in Stage 008H.

**See also:** Entry 8 below.

---

## 4. Datenschutzerklärung (Privacy Policy)

**Requirement:** DSGVO Art. 13 and 14 require the
controller to inform data subjects about the processing
of their personal data: identity of the controller,
purposes, legal basis, recipients, retention, and rights.

**Exposure:** Fines up to €20 million or 4% of global
annual turnover (whichever is higher). In practice, German
DPAs focus on specific gaps, but the risk is real.

**How we address it:** The platform provides a Privacy
Policy page. Content is the operator's responsibility; the
platform ensures the page exists before checkout and
provides the DSGVO-relevant infrastructure (data deletion,
data export) elsewhere.

**Where the code lives:**
  - Same Legal Pages infrastructure.

**Stage:** 008E.1

**Status:** Done.

---

## 5. Cookie Consent Banner (TTDSG § 25)

**Requirement:** TTDSG § 25 (the German implementation of
the ePrivacy Directive's Article 5(3)) requires informed
consent for storing information on a user's device or
accessing information already stored, unless the storage
is strictly necessary for providing an expressly requested
service.

**Exposure:** Fines up to €300,000 per violation. Abmahnung
risk.

**How we address it:** A storefront cookie consent banner
with category-level opt-in (necessary, functional,
analytics, marketing). Non-essential categories are
default-off. Banner state is stored in a cookie and
reflected in `window.CAConsent`.

The banner script itself and the necessary cookies are
loaded without consent (they fall under the
strictly-necessary exemption).

**Where the code lives:**
  - `app/Commerce/Support/ComplianceMap.php`
  - `public/assets/js/cookie-consent.js`
  - `public/assets/css/storefront-cookie-banner.css`
  - `resources/views/storefront/partials/_cookie-banner.php`
  - Store setting: `compliance.cookie_banner.enabled`
    (default true for German deployments)

**Stage:** 008E.1

**Status:** Done.

---

## 6. Cookie Consent API for Third-Party Scripts

**Requirement:** Every third-party script that stores or
reads information on a user's device requires consent
before it runs. Consent must be granular, revocable, and
provably obtained.

**Exposure:** Same as entry 5. Also: adding analytics or
advertising scripts without consent is the single most
common Abmahnung trigger in Germany.

**How we address it:** A public JavaScript API
(`window.CAConsent.has(category)` and
`window.CAConsent.on('change', callback)`) that any
third-party script can query before initializing. The
consent banner persists choices; the API surfaces them
live.

There is no "add a script tag and hope for the best"
pattern. Any script the operator adds is expected to check
`CAConsent.has('marketing')` before loading.

**Where the code lives:**
  - `public/assets/js/cookie-consent.js`
  - Documented in the 008E.1 plan.

**Stage:** 008E.1

**Status:** Done.

**Note for future integrations:** Any feature that adds a
third-party script (analytics, chat, reviews, ads) must
gate its load on `CAConsent.has(category)`. This is
non-negotiable for German deployments.

---

## 7. Country-Driven Compliance Gate

**Requirement:** Different countries require different
legal pages. Germany requires impressum, agb, widerruf,
datenschutz. Other EU countries have their own
requirements. The platform must not assume a single set.

**Exposure:** A German store that has not published the
required pages cannot legally complete a sale. A
non-German store that is forced to publish German pages
has an administrative burden.

**How we address it:** A `ComplianceMap` class maps
country codes to required legal slugs. The store's
`country_code` (from `commerce_stores`) selects the map.
Checkout refuses to proceed if any required page is
missing.

Adding a country is one entry in the map.

**Where the code lives:**
  - `app/Commerce/Support/ComplianceMap.php`
  - `app/Commerce/Services/CheckoutService.php`
    (calls `ComplianceMap::requiredLegalSlugs()`)

**Stage:** 008E.1

**Status:** Done. Germany is wired.

---

## 8. Widerrufsrecht 14-Day Window Enforcement

**Requirement:** A consumer's 14-day right of withdrawal
starts when the consumer (or a third party they designate)
receives the goods. Requests beyond the window must be
refused unless the seller offers a longer policy.

**Exposure:** Honouring requests outside the window is a
cost the operator takes on voluntarily. Refusing requests
inside the window exposes the operator to a
Verbraucherschutz complaint and, in some cases, a court
order.

**How we address it:** This is deferred to Stage 008H
(Returns & Refunds). The plan for 008H enforces a 14-day
window from delivery, with a configurable override per
store.

**Where the code lives:** Not yet built. The plan is in
`docs/ROADMAP.md` under Stage 008H.

**Stage:** 008H (planned)

**Status:** Deferred.

---

## 9. Google Fonts Self-Hosting (DSGVO)

**Requirement:** Loading fonts from Google's CDN
(fonts.googleapis.com) transmits the visitor's IP address
to Google. The Bavarian DPA (BayLDA) has stated that this
is a transfer of personal data and requires consent.
LG München I (2022) ordered €100 in damages for embedding
Google Fonts without consent.

**Exposure:** Abmahnung risk. €100+ in damages per
identified violation. Ongoing liability while the store is
live.

**How we address it:** The current storefront loads Google
Fonts from `fonts.googleapis.com`. This is the DSGVO
liability. The fix is a self-hosted font manager that
downloads open-source fonts locally and serves them from
the store's own domain, eliminating the third-party
request.

Until the fix ships, the site is exposed. This is a
priority for German deployments.

**Where the code lives:**
  - Current (problematic): `resources/views/storefront/layouts/main.php`
    contains `<link href="https://fonts.googleapis.com/...">`.
  - Planned fix: ROADMAP backlog entry "Self-Hosted Font
    Manager (008G.4c)".

**Stage:** 008G.4c (planned)

**Status:** Not started. **HIGH PRIORITY.**

---

## 10. EXIF Stripping on Image Upload (DSGVO)

**Requirement:** Photographs carry metadata: GPS
coordinates, camera make and model, timestamps, sometimes
the photographer's name. Under DSGVO Art. 4/5, GPS
coordinates in particular are personal data, and storing
or serving them without a legal basis is a violation.

**Exposure:** Fines. Also, a customer uploading a photo
with GPS data may not realise the location of their home
or business is embedded in the image.

**How we address it:** Every image uploaded through the
media pipeline is re-encoded via GD. GD's encoders write
fresh pixel data without carrying through the APP1
metadata segment (JPEG) or the textual chunks (PNG). The
result is a visually identical image with no metadata.

Non-image uploads (PDFs, CSVs) carry no EXIF and pass
through unchanged.

Failure to strip (GD error, corrupt image) causes the
upload to be rejected. Storing an image with un-stripped
EXIF is worse than no image.

**Where the code lives:**
  - `app/Commerce/Support/MediaProcessor::stripMetadata()`
  - Called from `MediaRepository::storeFromUpload()`.
  - Documented in the 008G.4a plan, Compliance Notes
    section.

**Stage:** 008G.4a

**Status:** Partial. The strip is implemented. What remains
is a data audit on any pre-existing uploads (there are
none yet in dev, but a production deploy should verify
before shipping).

**Note:** This is a defence in depth. Even with EXIF
stripped, the storefront should not display GPS-sourced
metadata in any template. Audit for `exif_*` function
usage before shipping product media.

---

## 11. Service Worker Consent Exemption (TTDSG § 25)

**Requirement:** TTDSG § 25 requires consent for storing
information on a user's device, unless the storage is
strictly necessary for providing an expressly requested
service.

**Exposure:** The PWA service worker caches assets in the
browser. If a German regulator takes a strict reading,
registering it without consent could be a violation.

**How we address it:** The German regulatory reading is
that functional caching for a user-initiated feature
(the customer visits a site that registers a service
worker, or installs the PWA) is exempt under § 25(2)(2).
The service worker is treated as an operational feature,
not a tracking mechanism.

The cookie consent banner is about cookies, not service
workers. No consent banner change is needed.

**Where the code lives:**
  - `docs/stages/008g.4_pwa-plan.md` (documented position)
  - `public/sw.js` (the service worker itself, from 008A)

**Stage:** 008G.4b (planned)

**Status:** Done (documented). The service worker ships in
008G.4b with this exemption applied.

---

## 12. Newsletter Double Opt-In

**Requirement:** German law requires provable consent for
marketing emails. Consent must be:
  - Given freely
  - Specific to the purpose
  - Informed (the customer knows what they are signing up
    for)
  - Unambiguous (an active opt-in, not a pre-checked box)
  - Recorded (who, when, from where)

The double opt-in procedure (confirmation email, then
click to activate) is the standard way to satisfy this.

**Exposure:** Sending marketing emails without provable
consent is a DSGVO violation and a UWG (Unfair
Competition Act) violation. Abmahnung risk. Typical
Abmahnung cost: €1,000-5,000.

**How we address it:** Not yet built. The newsletter and
campaign system is logged as Stage 008I. It will implement
double opt-in by default for German deployments, with a
per-country single opt-in option for other regions.

Every subscriber record will carry:
  - Consent timestamp
  - Subscriber IP
  - Source (which form, which page)
  - Confirmation token and confirmation timestamp

**Where the code lives:** Not yet built. Plan to be written
for Stage 008I.

**Stage:** 008I (planned)

**Status:** Not started.

---

## 13. Newsletter Unsubscribe Link

**Requirement:** Every commercial email must include a
working unsubscribe mechanism (UWG § 7, DSGVO Art. 21).
The unsubscribe must be free of charge and immediately
effective.

**Exposure:** Abmahnung risk. Fines. Email deliverability
collapse if the operator ignores it.

**How we address it:** Not yet built. Every campaign email
sent by the platform will include a signed unsubscribe
link. The link hits a public route
(`/newsletter/unsubscribe/{token}`), sets the subscriber's
status to `unsubscribed`, and displays a confirmation page.

The customer can opt back in from their account
preferences page (`/account/messages/preferences`, already
built in 008G.3).

**Where the code lives:** Not yet built. Plan to be written
for Stage 008I.

**Stage:** 008I (planned)

**Status:** Not started.

---

## 14. Newsletter: No Third-Party Transfer (DSGVO)

**Requirement:** DSGVO Art. 28 requires a Data Processing
Agreement (DPA) with any processor that handles personal
data on the controller's behalf. Sending email addresses to
Mailchimp, Klaviyo, SendGrid Marketing, or any similar
service makes them a processor.

Schrems II adds another layer: if the processor is in the
US (or transmits data to the US), the controller must also
implement Standard Contractual Clauses, a Transfer Impact
Assessment, and specific disclosures in the
Datenschutzerklärung.

**Exposure:** Fines up to €20 million or 4% of global
turnover. Every customer who uses the platform would have
to do the DPA and TIA work themselves if we relied on a
third-party service.

**How we address it:** The inbuilt newsletter and campaign
system (Stage 008I) keeps the subscriber list entirely
within the store's own infrastructure. No third-party
processor. No data transfer outside the EU. No DPA to sign
with a marketing service.

The store's own SMTP provider is the only processor
involved in sending, and that provider is chosen by the
operator under their own terms.

**Where the code lives:** Not yet built. Plan to be written
for Stage 008I.

**Stage:** 008I (planned)

**Status:** Not started.

---

## 15. Media Deletion Rules (DSGVO Art. 17)

**Requirement:** DSGVO Art. 17 (right to erasure) gives a
data subject the right to have their personal data
deleted, subject to certain exceptions (legal retention
obligations, etc.).

**Exposure:** Fines for failing to honour a valid erasure
request. Also reputational harm.

**How we address it:** Documented policy:
  - Store branding media (logo, favicon, PWA icons) is
    business-owned. Not deleted on customer request.
  - Product media is business-owned. Not deleted on
    customer request.
  - Customer avatars (future) are customer-owned and
    cascade-delete when the customer account is deleted.
  - Any file linked to a deleted entity is soft-deleted by
    `FileService::unlink()` when the last link is removed.
  - No hard delete. Files are soft-deleted. A future
    cleanup job can purge soft-deleted files older than
    N days after verifying no restore is pending.

**Where the code lives:**
  - `app/Platform/Services/FileService::unlink()`
  - `app/Platform/Services/FileService::softDelete()`
  - Documented in the 008G.4a plan, Compliance Notes
    section.

**Stage:** 008G.4a

**Status:** Done.

---

## 16. Data Minimisation (DSGVO Art. 5)

**Requirement:** Personal data must be "adequate, relevant
and limited to what is necessary" for the purposes for
which it is processed.

**Exposure:** Fines. Also: storing more than necessary
increases the breach impact if there is a data leak.

**How we address it:** `core_media_meta` stores only:
  - Dimensions (technical, not personal)
  - Alt text (operator-authored)
  - Credit (attribution, operator-authored)
  - Context (fixed string)
  - Timestamps

No customer identifiers on media rows. No file paths in
the metadata table. No EXIF. No geolocation.

The `uploaded_by` column on `core_files` references
`core_users`, not `commerce_customers`. For future
storefront-facing uploads, `uploaded_by` will be null.

Every feature added to the platform should be audited
against this principle.

**Where the code lives:**
  - `database/migrations/0314_create_media_meta.sql`
  - Documented in the 008G.4a plan, Compliance Notes
    section.

**Stage:** 008G.4a

**Status:** Done.

---

## 17. Copyright Attribution (UrhG § 13)

**Requirement:** The author of a work has the right to be
attributed. When a customer uploads a photograph or
illustration created by someone else, the correct practice
is to record the creator's name alongside the file.

**Exposure:** The photographer or illustrator can demand
removal and potentially damages.

**How we address it:** The `credit` column on
`core_media_meta` holds this value. It is optional and
populated by the operator when applicable. The column
exists from day one so that a future media page can
expose the input.

**Where the code lives:**
  - `database/migrations/0315_add_media_meta_credit.sql`
  - `app/Commerce/Models/MediaMeta` has a `$credit`
    property.

**Stage:** 008G.4a

**Status:** Partial. The storage is there. The admin UI to
enter the value ships in the media page (008G.4a.1).

---

## 18. Font Awesome Attribution (OFL, MIT, CC BY)

**Requirement:** Font Awesome Free is licensed under MIT
(non-font files), SIL OFL 1.1 (the font files themselves),
and CC BY 3.0 (documentation). The SIL OFL requires that
the font not be sold on its own and that the reserved
font name not be used by modified versions. Attribution is
embedded in the files themselves.

**Exposure:** Low. The license is permissive and the
attribution requirement is satisfied by keeping the
license files.

**How we address it:** When Font Awesome Free is bundled
(planned for Stage 008G.4c), the `LICENSE.txt` file and
the license headers inside each font file ship with the
assets. Self-hosted. No external CDN.

**Where the code lives:** Not yet bundled.

**Stage:** 008G.4c (planned)

**Status:** Deferred.

---

## 19. Sub-Processor Disclosure for External Services

**Requirement:** DSGVO Art. 28 requires the controller to
maintain a list of processors and sub-processors. DSGVO
Art. 13 requires this list to be disclosed to data
subjects in the Datenschutzerklärung.

**Exposure:** Non-disclosure is a violation. A customer
reading the privacy policy expects to find every external
service that receives their data.

**How we address it:** The platform tracks every external
service it makes requests to. The current list:

  External services in the shipped code:
    - Google Fonts (fonts.googleapis.com) - DSGVO liability,
      fix scheduled for 008G.4c
    - Bootstrap CDN (cdn.jsdelivr.net) - ships CSS and JS
    - Picsum Photos (picsum.photos) - development placeholder
      for product images; to be removed when real product
      media ships

  External services that are the operator's choice:
    - SMTP provider (operator-configured)
    - Payment providers (Stage 010)
    - Shipping carriers (Stage 010)

The Datenschutzerklärung (Legal Pages admin) is where the
operator discloses these. The platform is responsible for
minimising the shipped list. The operator is responsible
for disclosing what remains.

**Where the code lives:**
  - `public/.htaccess` (Content-Security-Policy
    `connect-src`, `img-src`, `font-src`, `style-src`,
    `script-src` lists the current external origins).

**Stage:** Ongoing.

**Status:** Ongoing. Every new integration must audit
this entry and update the CSP if needed.

---

## Updating This Document

When a new German (or EU-wide) legal requirement is
identified:

1. Add a row to the Compliance Map table at the top.
2. Add a section below with the full entry.
3. Note the stage where it is addressed (or the stage
   where it will be addressed).
4. If the requirement affects a stage currently in
   progress, update the stage plan in the same commit.

When a compliance item moves from Not started / Deferred to
Done / Partial:

1. Update the status in the map.
2. Update the status in the section.
3. Note the commit or stage where the fix landed.

---

## See Also

  - `docs/SPECIFICATION.md` - architectural context
  - `docs/ROADMAP.md` - stage sequence
  - `docs/stages/008e.1_german-compliance-plan.md` - the
    original compliance stage
  - `docs/stages/008g.4a_upload-core-plan.md` - media
    pipeline compliance notes
  - `docs/ARCHITECTURE.md` - layer contract
