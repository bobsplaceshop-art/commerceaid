# Stage 008I - Newsletter & Email Campaigns Plan

**Status:** In progress
**Opened:** 2026-10-11
**Depends on:** 008E.1 (German compliance), 008G.3
(account preferences page), 008G.4a (upload core, for
media in campaign bodies if needed)
**Blocks:** None.

---

## Purpose

An inbuilt newsletter and campaign system. No Mailchimp,
no Klaviyo, no third-party marketing processor. The
subscriber list, the campaigns, and the sending
infrastructure all live inside the store's own
deployment.

See the ROADMAP's "Why inbuilt" section for the
DSGVO, cost, vendor-lock-in, and consent-record
reasoning. It stands.

---

## Scope and sessions

Four sessions:

  Session 1 - schema and services.
    Six tables, models, repositories, three services
    (Subscriber, Campaign, Queue), store settings, and
    the CLI send worker.

  Session 2 - admin surfaces.
    Subscriber list, list management, campaign
    compose/send/stats. Permissions, sidebar, views.

  Session 3 - storefront surfaces and email templates.
    The two existing subscribe forms wired to a new
    endpoint. Double opt-in confirmation page.
    Unsubscribe page. Account preferences toggle.
    Three email templates (opt-in, unsubscribe confirm,
    campaign).

  Session 4 - tracking (opt-in), worker test, stage close.
    If tracking is enabled: pixel endpoint, click
    redirect, event recording. Browser test end to end.
    Stage close.

---

## Schema

Six tables. Migrations 0328 through 0333.

### 0328_create_subscribers.sql

    commerce_subscribers
      id                  BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id            BIGINT UNSIGNED NOT NULL
      email               VARCHAR(190) NOT NULL
      name                VARCHAR(120) NULL
      status              ENUM('pending','confirmed','unsubscribed','suppressed')
                          NOT NULL DEFAULT 'pending'
      consent_source      VARCHAR(60) NULL   -- 'footer','home','account','admin','import'
      consent_ip          VARCHAR(45) NULL
      consent_user_agent  VARCHAR(255) NULL
      consent_at          DATETIME NULL
      confirmation_token  VARCHAR(255) NULL
      confirmation_sent_at DATETIME NULL
      confirmed_at        DATETIME NULL
      unsubscribed_at     DATETIME NULL
      unsubscribe_reason  VARCHAR(255) NULL
      soft_bounce_count   INT UNSIGNED NOT NULL DEFAULT 0
      created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP

      UNIQUE KEY uniq_com_subs_store_email (store_id, email)
      KEY idx_com_subs_store_status (store_id, status)
      FK store_id -> commerce_stores(id) CASCADE

Notes:

  consent_source    Which form the address came from.
                    Fixed vocabulary. Unknown values
                    fall back to null.
  consent_ip        Captured on POST. Truncated to 45
                    chars (IPv6 max).
  consent_user_agent Captured on POST. Truncated.
  confirmation_token Signed token (see "Token strategy").
  soft_bounce_count  Incremented on each soft bounce.
                    Reset to zero on a successful send.
                    At 3, status moves to 'suppressed'
                    and the subscriber receives no
                    further campaigns.
  status 'suppressed' Distinct from 'unsubscribed'.
                    Unsubscribed is the user's choice.
                    Suppressed is the system's (hard
                    bounce, repeated soft bounce,
                    complaint).

### 0329_create_subscriber_lists.sql

    commerce_subscriber_lists
      id          BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id    BIGINT UNSIGNED NOT NULL
      name        VARCHAR(80) NOT NULL
      slug        VARCHAR(80) NOT NULL
      description VARCHAR(255) NULL
      is_default  TINYINT(1) NOT NULL DEFAULT 0
      created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP

      UNIQUE KEY uniq_com_sub_lists_store_slug (store_id, slug)
      KEY idx_com_sub_lists_store (store_id)
      FK store_id -> commerce_stores(id) CASCADE

Notes:

  is_default  Exactly one per store. Enforced in
              code. The footer signup form joins this
              list. The admin can create more
              (Newsletter, Product updates, Promotions).

  Seed per store: 'Newsletter' (is_default=1).

### 0330_create_subscriber_list_members.sql

    commerce_subscriber_list_members
      id             BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id       BIGINT UNSIGNED NOT NULL
      subscriber_id  BIGINT UNSIGNED NOT NULL
      list_id        BIGINT UNSIGNED NOT NULL
      joined_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

      UNIQUE KEY uniq_com_slm_sub_list (subscriber_id, list_id)
      KEY idx_com_slm_list (list_id)
      FK store_id      -> commerce_stores(id) CASCADE
      FK subscriber_id -> commerce_subscribers(id) CASCADE
      FK list_id       -> commerce_subscriber_lists(id) CASCADE

### 0331_create_email_campaigns.sql

    commerce_email_campaigns
      id               BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id         BIGINT UNSIGNED NOT NULL
      list_id          BIGINT UNSIGNED NOT NULL
      subject          VARCHAR(255) NOT NULL
      preheader        VARCHAR(255) NULL
      body_html        MEDIUMTEXT NOT NULL
      body_text        MEDIUMTEXT NULL
      status           ENUM('draft','scheduled','sending','sent','cancelled')
                       NOT NULL DEFAULT 'draft'
      scheduled_at     DATETIME NULL
      sent_at          DATETIME NULL
      sent_count       INT UNSIGNED NOT NULL DEFAULT 0
      failed_count     INT UNSIGNED NOT NULL DEFAULT 0
      created_by       INT UNSIGNED NULL
      created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP

      KEY idx_com_camp_store_status (store_id, status)
      FK store_id   -> commerce_stores(id) CASCADE
      FK list_id    -> commerce_subscriber_lists(id) CASCADE
      FK created_by -> core_users(id) SET NULL

### 0332_create_email_queue.sql

    commerce_email_queue
      id             BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id       BIGINT UNSIGNED NOT NULL
      campaign_id    BIGINT UNSIGNED NOT NULL
      subscriber_id  BIGINT UNSIGNED NOT NULL
      to_email       VARCHAR(190) NOT NULL
      to_name        VARCHAR(120) NULL
      unsubscribe_token VARCHAR(255) NOT NULL
      attempts       INT UNSIGNED NOT NULL DEFAULT 0
      last_error     VARCHAR(500) NULL
      status         ENUM('pending','sending','sent','failed')
                     NOT NULL DEFAULT 'pending'
      scheduled_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      sent_at        DATETIME NULL
      created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

      KEY idx_com_eq_status_scheduled (status, scheduled_at)
      KEY idx_com_eq_campaign (campaign_id)
      FK store_id      -> commerce_stores(id) CASCADE
      FK campaign_id   -> commerce_email_campaigns(id) CASCADE
      FK subscriber_id -> commerce_subscribers(id) CASCADE

Notes:

  to_email and to_name are denormalised at enqueue
  time. If the subscriber's email changes between
  enqueue and send, the campaign still goes to the
  original address. That is correct: the subscriber
  agreed to receive mail at that address.

  unsubscribe_token is generated per enqueue, per
  subscriber, per campaign. See "Token strategy".

### 0333_create_email_events.sql

    commerce_email_events
      id             BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id       BIGINT UNSIGNED NOT NULL
      campaign_id    BIGINT UNSIGNED NULL
      subscriber_id  BIGINT UNSIGNED NULL
      event_type     ENUM('sent','open','click','bounce_hard','bounce_soft','unsubscribe','complaint')
                     NOT NULL
      event_data     JSON NULL
      created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

      KEY idx_com_ee_campaign_type (campaign_id, event_type)
      KEY idx_com_ee_subscriber (subscriber_id, event_type)
      KEY idx_com_ee_created (created_at)
      FK store_id      -> commerce_stores(id) CASCADE
      FK campaign_id   -> commerce_email_campaigns(id) SET NULL
      FK subscriber_id -> commerce_subscribers(id) SET NULL

Notes:

  campaign_id and subscriber_id are nullable so that
  a deletion does not lose the aggregate event record.
  The event row survives; the identity does not.

  event_data carries per-type detail: the URL clicked,
  the bounce reason text, the user agent for an open.

---

## Token strategy

Two token types, both signed. No raw email address
ever appears in a URL.

### Format

    payload   = base64url(json({id, scope, exp}))
    signature = HMAC-SHA256(payload, SECRET)
    token     = payload . '.' . base64url(signature)

Where:

    id      The subscriber id (for confirm) or the
            subscriber id + campaign id (for unsubscribe).
    scope   'confirm' or 'unsubscribe'.
    exp     Unix timestamp.

### Signing secret

A new `SignedToken` helper in `app/Platform/Support/`:

    SignedToken::sign(array $claims): string
    SignedToken::verify(string $token, string $expectedScope): ?array

The secret comes from config. In dev, a fixed string.
In production, config/local.php. The helper throws if
the secret is shorter than 32 bytes.

### Expiry

    Confirmation   24 hours from send.
    Unsubscribe    90 days from send.

Confirmation tokens are short-lived because the
subscriber is actively waiting at their inbox.

Unsubscribe tokens are long-lived because a subscriber
may open an old campaign months after it was sent.
The token is regenerated on every campaign send, so
the most recent token is always valid.

### Verification

    - Split on '.'.
    - Recompute HMAC.
    - Timing-safe compare.
    - Decode payload.
    - Check scope.
    - Check exp > now.

Any failure renders a neutral "this link is no longer
valid" page. Do not leak whether the token was
malformed, expired, or forged.

---

## The queue worker

`bin/send-emails.php`

    Usage:
      php bin/send-emails.php --batch=100

    Behaviour:
      - Selects up to $batch rows from
        commerce_email_queue where status='pending'
        and scheduled_at <= NOW(), ordered by id ASC.
      - For each row:
          - Marks status='sending'.
          - Renders the campaign body with the
            subscriber's name and unsubscribe link.
          - Calls EmailService::send().
          - On success: status='sent', sent_at=NOW(),
            increments commerce_email_campaigns.sent_count,
            writes a 'sent' event, resets the
            subscriber's soft_bounce_count to zero.
          - On failure: increments attempts, stores
            last_error, sets status='pending' with
            scheduled_at = NOW() + backoff (attempts *
            60 seconds, capped at 1 hour). At attempts
            >= 5, status='failed', writes a
            'bounce_hard' event, increments
            campaign.failed_count, moves the subscriber
            to 'suppressed'.
      - Sleeps between sends according to
        newsletter.rate_limit_per_minute.

    Rate limit:
      The setting is a store-wide budget. The worker
      sleeps (60 / rate) seconds between sends. For a
      rate of 60/min, that is one second per send. For
      a rate of 300/min, 0.2 seconds.

      A single worker is the simplest correct model.
      Multiple workers would need a distributed lock.
      For the volume this platform is built for
      (small and mid-size stores), one worker is
      enough.

    Cron entry (operator-installed):
      * * * * * php /path/to/bin/send-emails.php --batch=100

---

## Bounce handling

Bounces arrive in three ways:

  1. The sending SMTP provider rejects the message
     synchronously. EmailService::send() returns
     false. The worker records it as a failure.
  2. The sending SMTP provider accepts the message
     but later reports a bounce by email. Handling
     that requires parsing bounce emails from a
     configured bounce mailbox. Deferred to a
     follow-up sub-stage.
  3. The recipient marks the message as spam. The
     provider reports it by email. Same as (2).

This stage handles (1). The other two are logged as
deferred. The schema supports them: `commerce_email_events`
has `bounce_hard` and `bounce_soft` types ready.

Rules applied in this stage:

  Hard bounce (synchronous reject, e.g. "user unknown",
  "domain does not exist"):
    - Subscriber moves to 'suppressed'.
    - No further campaigns.

  Soft bounce (synchronous reject, e.g. "mailbox full",
  "try again later"):
    - subscriber.soft_bounce_count++.
    - At 3 consecutive soft bounces, subscriber moves
      to 'suppressed'.
    - A successful send resets the counter to zero.

---

## Compliance checklist

From COMPLIANCE-DE entries 12, 13, 14:

  Entry 12 - Double opt-in:
    [x] Consent timestamp captured.
    [x] Consent IP captured.
    [x] Consent source captured.
    [x] Confirmation token + confirmation timestamp.
    [x] Default on for German deployments.
    [x] Single opt-in configurable for other regions.
    Where: SubscriberService, commerce_subscribers.

  Entry 13 - Unsubscribe link:
    [x] Every campaign email includes a signed link.
    [x] Link hits /newsletter/unsubscribe/{token}.
    [x] Sets status to 'unsubscribed'.
    [x] Confirmation page rendered.
    [x] Opt back in via account preferences.
    Where: Campaign email template, NewsletterController.

  Entry 14 - No third-party transfer:
    [x] Subscriber list stays in the store's own DB.
    [x] Sending uses the operator's own SMTP.
    [x] No external marketing API.
    [x] No data leaves the deployment.
    Where: architecture; no code to build.

---

## Store settings

Four new settings under the 'newsletter' namespace:

    newsletter.double_opt_in            bool, default true
    newsletter.tracking_enabled         bool, default false
    newsletter.rate_limit_per_minute    int,  default 60
    newsletter.unsubscribe_token_days   int,  default 90

Defaults are store-level. German deployments get the
double opt-in default. Non-German stores can turn it
off; the setting is per store.

---

## Admin surfaces

    /commerce/ca-admin/subscribers
      list with filters (status, source, list, date),
      pagination, CSV export

    /commerce/ca-admin/subscribers/{id}
      detail: consent record, list memberships,
      event history

    /commerce/ca-admin/subscribers/lists
      CRUD for named lists

    /commerce/ca-admin/campaigns
      list with status filter

    /commerce/ca-admin/campaigns/create
      compose: subject, preheader, list picker,
      body editor

    /commerce/ca-admin/campaigns/{id}
      detail: preview, recipient count, send action,
      stats after send

Permissions (seeder 0015):

    newsletter.subscribers.view
    newsletter.subscribers.manage
    newsletter.campaigns.view
    newsletter.campaigns.manage

Sidebar:

    New 'Marketing' group between Sales and Settings.
      Subscribers  -> /commerce/ca-admin/subscribers
      Campaigns    -> /commerce/ca-admin/campaigns

---

## Storefront surfaces

    POST /newsletter/subscribe
      The form endpoint. Body: email, name (optional),
      list_id (optional). Creates a pending subscriber
      and sends the double opt-in email.

    GET  /newsletter/confirm/{token}
      Confirmation page. Verifies token, moves the
      subscriber to 'confirmed', records confirmed_at.

    GET  /newsletter/unsubscribe/{token}
      Renders a confirmation page with a POST form.
      GET does not unsubscribe (guards against
      mail-scanner auto-clicks).

    POST /newsletter/unsubscribe/{token}
      Performs the unsubscribe. Optional reason field.

    /account/messages/preferences (existing)
      Gains a "Newsletter and promotions" toggle. On
      enable: creates or reactivates the subscriber.
      On disable: unsubscribes.

Two existing forms wired to the endpoint:

  home.php line ~359     "Stay in Style" section
  footer.php line ~179   the footer subscribe form

Both currently have `onsubmit="return false;"` and
placeholder text. Both become real.

---

## Email templates

Three templates under resources/views/emails/newsletter/:

  opt-in-confirmation.php    Double opt-in confirm link
  unsubscribe-confirm.php    "You have been unsubscribed"
  campaign.php               The campaign wrapper with
                             the unsubscribe footer

Rendered by EmailService. Body HTML comes from the
campaign record. The wrapper adds the mandatory
footer with the signed unsubscribe link and the
operator's physical address.

---

## Open questions resolved

  1. A/B subject testing - deferred. Not essential.
  2. Segments - list-based only. Segments are Stage
     011+.
  3. Bounce handling - hard bounce suppress, soft
     bounce 3-strike. Async bounce parsing deferred.
  4. Open/click tracking - schema and endpoints in
     scope, store setting default off for German
     deployments.

---

## Deferred

  - Async bounce parsing (bounce mailbox).
  - Open and click tracking UI (the events table
    will be read by the campaign stats view; a
    future polish adds charts).
  - A/B subject testing.
  - Rule-based segments.
  - Subscriber import (CSV upload). The admin
    export is in scope; import is a separate
    feature.
  - Welcome email on confirmation. Nice to have.
  - A cron-registry integration so the operator
    does not install their own crontab entry.
    `core_scheduled_tasks` already has the schema;
    a future stage wires it.

---

## References

  - docs/ROADMAP.md 008I section.
  - docs/COMPLIANCE-DE.md entries 12, 13, 14.
  - app/Platform/Support/EmailService.php - the
    existing transport.
  - resources/views/storefront/partials/footer.php -
    the existing subscribe form.
