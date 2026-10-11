# Stage 009 - Forms & Submissions

**Status:** Planning
**Opened:** 2026-10-12
**Depends on:** Stage 003 (core schema), 008I
(notification/webhook infrastructure)
**Blocks:** Nothing.

---

## Purpose

A native form builder. The merchant creates forms
without code, embeds them in the storefront, and manages
the submissions they receive.

The feature set targets the paid tier of the Shopify
marketplace apps (conditional logic, multi-step, file
uploads, submissions inbox) at no additional cost.
Unlike every Shopify form-builder app, submissions have
no cap, no per-month volume limit, and no per-form fee.
Submissions live in the merchant's own database and are
never transmitted to a third party.

---

## Industry reference

**Forminator (WordPress):** the reference
implementation. Its architecture is built around the
WordPress post/entry model, which this platform does not
have and does not need. What we borrow from Forminator
is the field type registry pattern, the conditional
logic model, and the webhook-as-escape-hatch approach.
What we deliberately do not borrow is the drag-and-drop
editor (matches nothing else in this admin panel) and
the payment fields (belong in Stage 010).

**Shopify native Forms:** minimal. Email/SMS capture
only. No conditional logic, no multi-step, no file
upload. 31 fields per form maximum, 25 forms per store
maximum. Not the benchmark.

**Shopify marketplace apps (Form Builder, Typeform,
Jotform, Powr, and the top 20 listings):** the benchmark
for this stage. Their paid tier ships drag-and-drop
editors, 17-19 field types, conditional logic,
multi-step forms, submissions inboxes with status
tracking, spam filtering, CSV export, and file uploads.
Pricing is $6-$26/month with submission caps and file
storage limits.

**Positioning:** ship the marketplace's paid-tier
feature set for free, with no submission caps and no
storage limits. This is a genuine differentiator.

---

## What we reuse from CustomFields

The `app/Platform/CustomFields/` engine already ships
the input type infrastructure Stage 009 needs. Reuse it
without modification:

  - `InputType` interface (8 methods).
  - The 12 concrete types: Text, Email, Phone, Number,
    Date, URL, LongText, Select, MultiSelect, Checkbox,
    YesNo, File.
  - `InputTypeRegistry` (reads the same static list).
  - The `validate()` and `render()` implementations
    inside each type.
  - The honeypot pattern from 008I.

What we do NOT reuse:

  - `CustomFieldRenderer` - coupled to the CustomField
    model and the entity_type/entity_id pattern.
  - `CustomFieldValueSaver` - writes to
    core_custom_field_values, which is the wrong shape
    for standalone form submissions.
  - `CustomFieldValueValidator` - the logic is fine but
    the orchestration is different (per-form field
    definition, not per-entity).

---

## Schema

Three tables. Migrations 0334, 0335, 0336.

### 0334_create_forms.sql

    commerce_forms
      id                  BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id            BIGINT UNSIGNED NOT NULL
      name                VARCHAR(120) NOT NULL
      slug                VARCHAR(120) NOT NULL
      description         VARCHAR(500) NULL
      status              ENUM('draft','published','archived')
                          NOT NULL DEFAULT 'draft'
      success_message     TEXT NULL
      notify_email        VARCHAR(190) NULL
      honeypot_enabled    TINYINT(1) NOT NULL DEFAULT 1
      requires_auth       TINYINT(1) NOT NULL DEFAULT 0
      created_by          INT UNSIGNED NULL
      created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                          ON UPDATE CURRENT_TIMESTAMP
      deleted_at          DATETIME NULL

      UNIQUE KEY uniq_com_forms_store_slug (store_id, slug)
      KEY idx_com_forms_store_status (store_id, status)
      FK store_id   -> commerce_stores(id) CASCADE
      FK created_by -> core_users(id) SET NULL

Notes:

  status 'published' means the form is reachable at
  /forms/{slug}. 'draft' means it is visible in the
  admin only. 'archived' means it is hidden everywhere
  and any public URL returns a 404.

  success_message  The text shown after a successful
                   submission. Defaults to a stock
                   message if null.
  notify_email     Optional. If set, a copy of the
                   submission is emailed to that address
                   in addition to firing the domain
                   event.
  honeypot_enabled Whether to render the off-screen
                   honeypot field. Default on.
  requires_auth    Whether the form is reachable only
                   to logged-in customers. Default off.

### 0335_create_form_fields.sql

    commerce_form_fields
      id             BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id       BIGINT UNSIGNED NOT NULL
      form_id        BIGINT UNSIGNED NOT NULL
      field_key      VARCHAR(60) NOT NULL
      label          VARCHAR(190) NOT NULL
      input_type     VARCHAR(40) NOT NULL
      placeholder    VARCHAR(190) NULL
      help_text      VARCHAR(500) NULL
      is_required    TINYINT(1) NOT NULL DEFAULT 0
      options_json   JSON NULL
      constraints_json JSON NULL
      default_value  VARCHAR(500) NULL
      step           TINYINT UNSIGNED NOT NULL DEFAULT 1
      sort_order     INT UNSIGNED NOT NULL DEFAULT 0
      conditional_json JSON NULL
      is_active      TINYINT(1) NOT NULL DEFAULT 1
      created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP

      UNIQUE KEY uniq_com_fform_key (form_id, field_key)
      KEY idx_com_fform_step (form_id, step, sort_order)
      FK store_id -> commerce_stores(id) CASCADE
      FK form_id  -> commerce_forms(id) CASCADE

Notes:

  field_key       The submitted name. Lowercase,
                  underscores, stable across edits.
                  Used by the client and the server to
                  identify which field a value belongs
                  to. Never changes once the field is
                  saved (or the old submissions become
                  orphaned).
  input_type      One of the twelve keys from
                  InputTypeRegistry.
  options_json    For Select, MultiSelect, Checkbox
                  group. Array of {value,label} pairs.
  constraints_json Type-specific constraints
                  (min/max length, regex, min/max
                  number, date range). Shape matches
                  the CustomFields engine's
                  constraints column.
  step            Which step of a multi-step form
                  this field belongs to. Step 1 by
                  default. Fields with the same step
                  render together. A single-step form
                  has every field at step 1 and the
                  renderer suppresses step navigation.
  conditional_json A conditional rule object:
                    {
                      "field": "other_field_key",
                      "operator": "equals",
                      "value": "yes"
                    }
                  Null means always visible. When a
                  rule is present, the field is only
                  shown when the rule matches. The
                  same rule is applied on the server
                  to determine whether the field's
                  validation rules run.

  Cascade: fields move with the form. No soft delete;
  fields are hard-deleted (they have no separate audit
  value; the submissions live in their own table).

### 0336_create_form_submissions.sql

    commerce_form_submissions
      id             BIGINT UNSIGNED PK AUTO_INCREMENT
      store_id       BIGINT UNSIGNED NOT NULL
      form_id        BIGINT UNSIGNED NOT NULL
      customer_id    BIGINT UNSIGNED NULL
      submitter_email VARCHAR(190) NULL
      submitter_name VARCHAR(190) NULL
      submitter_ip   VARCHAR(45) NULL
      user_agent     VARCHAR(500) NULL
      payload_json   JSON NOT NULL
      status         ENUM('new','read','spam','archived')
                     NOT NULL DEFAULT 'new'
      notes          TEXT NULL
      created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
      updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP

      KEY idx_com_fsub_form_created (form_id, created_at)
      KEY idx_com_fsub_store_status (store_id, status)
      KEY idx_com_fsub_email (store_id, submitter_email)
      FK store_id    -> commerce_stores(id) CASCADE
      FK form_id     -> commerce_forms(id) CASCADE
      FK customer_id -> commerce_customers(id) SET NULL

Notes:

  payload_json    The submitted values keyed by
                  field_key. Example:
                    {
                      "name": "Jane Smith",
                      "email": "jane@example.com",
                      "message": "Hello"
                    }
                  One row per submission. No joins, no
                  schema change when a form adds a
                  field.

  submitter_email/name
                  Denormalised from the payload for the
                  common filter/export case. When the
                  form has a field with
                  input_type = 'email' and the label
                  contains "email" (case-insensitive),
                  its submitted value is copied here.
                  Same for a name field. This lets the
                  admin list filter by email without
                  querying JSON.

  status 'spam'   Set by the merchant manually, or
                  automatically if the honeypot tripped
                  but the request was not rejected for
                  some reason (belt and braces). Spam
                  submissions are hidden from the
                  default list view but preserved.

  notes           Internal, admin-only annotations on a
                  submission.

---

## Services

Four new services. All in `app/Commerce/Services/`.

### FormService

CRUD for forms and fields. Composes the admin editor.

    createForm(array $data, ?int $userId): int
    updateForm(int $formId, array $data): void
    publish(int $formId): void
    unpublish(int $formId): void
    archive(int $formId): void
    delete(int $formId): void

    addField(int $formId, array $data): int
    updateField(int $fieldId, array $data): void
    deleteField(int $fieldId): void
    reorderFields(int $formId, array $orderedIds): void

### FormRenderer

Composes the input types into a full form.

    render(Form $form, array $values = [], array $errors = []): string
    renderField(FormField $field, ?string $value, ?string $error): string

The renderer:

  - Loads the fields via FormFieldRepository.
  - Groups them by `step` if any field has step > 1.
  - Renders each field by calling the InputType's
    `render()` method with the field's config.
  - Wraps each field with a data-conditional attribute
    when `conditional_json` is set.
  - Emits the CSRF field, the honeypot field, and the
    submit button.
  - Renders step navigation when the form is
    multi-step.

### FormSubmissionService

Handles the public submit path.

    submit(Form $form, array $posted, array $files, array $context): array

Where `$context` is `{ ip, user_agent, customer_id }`.

The method:

  1. Honeypot check. If tripped, return a silent
     success (same behaviour as the newsletter).
  2. Server-side conditional logic: evaluate each
     field's `conditional_json` against the submitted
     values. Fields whose condition is not met are
     skipped entirely — not validated, not stored.
  3. Required-field check on the visible fields.
  4. Validate each visible field via its InputType's
     `validate()` method.
  5. If any errors: return `{ok: false, errors: [...]}`.
  6. Insert the submission row with the payload JSON.
  7. Fire a `form.submitted` domain event.
  8. If the form has `notify_email`, send a plain
     notification email to that address.
  9. Return `{ok: true, submission_id: N}`.

### FormService (public surface)

Already listed above. The public submit path is not a
separate service.

---

## Conditional logic

A single rule per field. Six operators:

    equals          Strict string comparison.
    not_equals      Negation.
    contains        Substring match on string values,
                    membership test on array values.
    is_empty        The referenced field has no value.
    is_not_empty    The referenced field has a value.
    greater_than    Numeric comparison.
    less_than       Numeric comparison.

The rule is stored in `conditional_json` as
`{field, operator, value}`. On the server the evaluation
is a small class method. On the client a JS module reads
`data-conditional` attributes and toggles the field's
wrapper visibility on input events.

The JS module is loaded only on pages that render a
form. It is a small, dependency-free script, matching
the pattern of the storefront's existing JS.

---

## Multi-step forms

A form is multi-step when any field has `step > 1`.

In the renderer:

  - Fields are grouped by `step`.
  - Each step renders in its own `<div
    data-form-step="N">`.
  - Steps after the first are hidden by default
    (CSS display: none).
  - A step indicator shows "Step N of M".
  - Next / Back buttons move between steps. Client-side
    only; the whole form submits at once.
  - The final step shows the Submit button.

On the server the whole form is validated as one unit.
The step structure is presentation only. This is the
correct model: multi-step is a UX affordance, not a
security boundary.

---

## Admin surface

    /commerce/ca-admin/forms
      List. Filters by status, search by name/slug.
      Row actions: Edit, Duplicate, Archive, Delete.

    /commerce/ca-admin/forms/create
      Create form. Fields: name, slug, description,
      success message, notify email, honeypot toggle,
      auth-required toggle.

    /commerce/ca-admin/forms/{id}/edit
      The field editor. Table-based:
        - Columns: #, Label, Key, Type, Required, Step,
          Actions.
        - Add field button opens a modal with type-aware
          config (options textarea for Select, min/max
          for Number, conditional rule builder for all
          types).
        - Reorder via numbered position column or
          up/down buttons. No drag-and-drop.
        - Step assignment via a number input on each
          field row.

    /commerce/ca-admin/forms/{id}/preview
      Full-page preview of the form on a public-like
      shell.

    /commerce/ca-admin/forms/{id}/submissions
      Submissions list. Filters: status (new, read,
      spam, archived), date range, email search.
      Bulk actions: mark read, mark spam, archive,
      delete. CSV export of the current filtered set.

    /commerce/ca-admin/forms/{id}/submissions/{sid}
      Submission detail. Payload rendered as a
      key-value list. Status dropdown. Notes textarea.

    POST /commerce/ca-admin/forms/{id}/duplicate
      Clone a form and its fields. The slug is
      suffixed with "-copy".

Permissions (seeder 0017):

    forms.view
    forms.manage
    forms.submissions.view
    forms.submissions.manage

Sidebar: a new "Forms" item under a new "Marketing"
group, or under the existing "Catalog" group. Decide at
Session 2 open. Marketing feels correct (forms are a
customer acquisition surface).

---

## Public surface

    GET  /forms/{slug}
      Render the form. 404 if status is not
      'published', or the slug is unknown, or the
      form requires auth and there is no session.

    POST /forms/{slug}
      Handle the submission. Middleware:
      ['store', 'csrf', 'ratelimit:form_submit,10,60'].
      On success: re-render the success message in
      place, or redirect to the same URL with a flash.
      On failure: re-render the form with the posted
      values and per-field error messages.

Both routes live in `routes/storefront.php`.

The public route is deliberately anonymous. Forms are
customer-facing by nature. A per-form `requires_auth`
flag allows the merchant to gate a form if needed
(quote requests, B2B onboarding).

---

## Webhook and notification

A successful submission fires `form.submitted` through
the existing domain event system. The event carries:

    - form id, slug, name
    - submission id
    - submitted_at
    - fields: {field_key: value}

Consumers:

  - `NotificationRuleService` - the merchant's
    configured rules can dispatch an in-app
    notification or an email to a role or user.
  - `core_webhooks` - the merchant's configured webhook
    endpoint receives a POST with the event payload.
    Same shape as every other domain webhook this
    platform fires.

The form's own `notify_email` column is a shortcut for
the simple case: "email this address on every submit."

---

## Sessions

Four sessions. Each ends with a commit.

### Session 1 - schema, models, repositories (backend only)

  - Migrations 0334, 0335, 0336.
  - Seeder 0017 (permissions).
  - Form, FormField, FormSubmission models.
  - FormRepository, FormFieldRepository,
    FormSubmissionRepository.
  - Provider bindings.
  - No UI, no services yet. The database is ready.

### Session 2 - FormService, FormRenderer, admin editor

  - FormService (CRUD for forms and fields).
  - FormRenderer.
  - AdminFormController with index, create, store,
    edit, update, publish, unpublish, archive,
    duplicate, preview.
  - Admin views: index, create, edit (the field
    editor), _field-modal.
  - Sidebar entry, breadcrumbs, permissions.
  - Browser test: create a form, add fields, publish,
    preview.

### Session 3 - public surface, submission processing

  - FormSubmissionService.
  - Public FormController with show and submit.
  - Two routes in routes/storefront.php.
  - Public view: forms/show.php.
  - Conditional logic JS module.
  - Multi-step rendering.
  - Browser test: submit a form, verify honeypot,
    verify conditional logic, verify multi-step.

### Session 4 - submissions admin, webhooks, close

  - AdminFormController::submissions and
    submissions/{sid} methods.
  - Submissions list view with filters, bulk actions,
    CSV export.
  - Submission detail view.
  - Domain event firing.
  - NotificationRuleService integration.
  - Webhook integration test.
  - Browser test: receive a submission, filter,
    export, mark spam, view detail.
  - Stage close: session file, STATUS, ROADMAP,
    ADMIN-GUIDE update.

---

## What we deliberately do not ship in Stage 009

These are market-standard features in the paid tier of
the Shopify form-builder apps. Each is deferred to a
later sub-stage with its own scope.

  **009.1 - Drag-and-drop field editor.** The industry
  norm. The functional equivalent (numbered sort order
  plus up/down buttons) covers the use case with no
  JavaScript library, matching the admin's existing
  patterns. A future sub-stage can add drag-and-drop
  without a data model change.

  **009.2 - Field calculations and scoring.** Number
  fields with formulas, and conditional logic on the
  calculated value. Forminator and Formidable both
  ship this. It is a subsystem of its own.

  **009.3 - Payment fields.** Stripe and PayPal fields
  directly in the form. Belongs in Stage 010 (Payments
  Deep), which already wires the payment provider
  registry.

  **009.4 - Form templates.** A gallery of pre-built
  forms (contact, quote, newsletter). The "duplicate
  form" action in Session 2 covers the merchant's
  primary need.

  **009.5 - Analytics.** Views, submissions,
  completion rate, average fill time, abandon rate.
  Adobe AEM Forms tracks all four; the marketplace
  apps track the first two or three. This is its own
  reporting subsystem.

  **009.6 - PDF export of submissions.** Forminator
  Pro feature. Useful for quotes and invoices, but it
  is scope creep for the base stage.

---

## Open questions resolved at Session 1 open

  1. Should the form editor live under Catalog or
     Marketing in the sidebar? Recommendation:
     Marketing. Forms are a customer-facing surface,
     same as the newsletter.

  2. Should submissions be queryable by JSON field
     contents? Recommendation: no. The two denormalised
     columns (submitter_email, submitter_name) cover
     the common filter cases. Arbitrary JSON filters
     are a reporting feature, deferred to 009.5.

  3. Should the honeypot be per-form or global?
     Recommendation: per-form, default on. Some forms
     (B2B quote requests) may want it disabled in
     favour of a stricter captcha.

  4. Should the webhook fire before or after the
     submission is saved? Recommendation: after. The
     webhook carries the submission id, which means
     the row must exist first.

---

## References

  - Forminator (WordPress) - the conceptual model for
    the field type registry and the conditional logic
    operators. Do not copy the WordPress post/entry
    data model.
  - Shopify native Forms - not the benchmark. Email
    and SMS capture only. 31 fields, 25 forms.
  - Shopify marketplace apps (Form Builder, Typeform,
    Jotform, Powr) - the paid-tier feature set this
    stage targets.
  - `app/Platform/CustomFields/` - the input type
    engine reused by this stage.
  - `docs/ROADMAP.md` Stage 009 entry.
  - `docs/stages/008i_newsletter-plan.md` - the sibling
    stage; reuses the honeypot and rate-limit patterns.
