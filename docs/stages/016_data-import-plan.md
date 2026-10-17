# Stage 016 - Data Import (CSV)

**Status:** Planning
**Opened:** 2026-10-17
**Depends on:** Stage 006 (catalog schema,
frozen), Stage 007A (customer groups), Stage
008E (checkout), Stage 008K.5 (order pricing
engine), Stage 010 (payments schema). All
complete.
**Blocks:** Nothing. Independent of every
pending sub-stage.

---

## Purpose

A merchant setting up a store needs to
bulk-load products, categories, brands,
attributes, variants, inventory, and customers
from a CSV. Today every record is entered by
hand through the admin UI. For a store with
200+ SKUs that is days of manual work; for a
store migrating from another platform it is
not feasible at all.

The export side already exists. Three admin
controllers have a CSV export:

  - `InventoryController::export()` and
    `::movementsExport()` use `fputcsv()` to a
    `php://temp` stream and return the bytes as
    a `Response` with `Content-Type: text/csv`.
    This is the cleanest shape in the codebase
    and the one the importer round-trips against.

  - `AdminFormController::submissionsExport()`
    assembles the CSV with an `$escapeCsv`
    closure and `implode(',', ...)`. Same
    intent, different assembly.

  - `AdminSubscriberController::export()`
    assembles the CSV with an anonymous
    function.

Three controllers, three implementations, one
idea. The importer's reader does not add a
fourth shape. It is one reader class, used by
every import path.

**What ships in this stage.**

A CSV upload and import flow for seven
entities, in dependency order:

  brands -> categories -> attributes -> products
  -> variants -> inventory -> customers

Each entity gets an import handler that
declares its natural key, its required fields,
its validation rules, and its upsert semantics.
The pipeline is:

  upload -> map -> validate -> preview -> commit
  -> failed-row export -> (optionally) rollback

Nothing is written during validate or preview.
The commit phase is transactional per chunk.
Every batch records what it created and what it
updated, so a batch can be reversed.

**What the ROADMAP already committed to.**

The ROADMAP's data-import entry names:

  - Upload: CSV first. XLSX later.
  - Column mapping UI.
  - Validation pass: per-row errors, nothing
    written.
  - Dry-run preview.
  - Commit: transactional per chunk, with a
    batch id.
  - Idempotency: match on natural key (SKU,
    slug, email).
  - Failed-row export.
  - Rollback.

This plan honors every commitment. It defers
XLSX explicitly (see "What is not in this
stage"). It adds the specifics: the reader's
encoding, the entity handlers' shape, the
schema for the batch records.

---

## The CSV reader

`app/Commerce/Services/Import/CsvReader.php`.
One class, no dependencies.

    final class CsvReader
    {
        /**
         * @return array{0: array<int,string>,
         *               1: array<int, array<string,string>>}
         *   header row, then associative rows
         */
        public function read(string $path): array;
    }

**Rules:**

  - **UTF-8 only.** A file with an invalid
    UTF-8 byte fails the whole read, before any
    row is parsed. The error names the byte
    position and the line.

  - **BOM stripping.** A leading UTF-8 BOM
    (`EF BB BF`) is stripped from the first
    cell of the header row.

  - **Comma delimiter only.** Semicolon and
    tab are future enhancements.

  - **`fgetcsv()` for the parse.** The reader
    does not implement CSV parsing; `fgetcsv`
    handles quoted fields, embedded commas,
    embedded newlines, and escaped quotes
    correctly.

  - **Associative rows.** The header row keys
    each subsequent row. A duplicate header
    name is an error at read time (not a silent
    last-wins). A missing header name is an
    error.

  - **Empty rows are skipped.** A row of all
    empty strings is not a row.

  - **The header is preserved verbatim.** The
    reader does not normalise header names. The
    column mapping UI maps the merchant's
    header names (which may be "Product Name",
    "product_name", or "Item" — all fine) to
    the handler's expected fields.

**Why `fgetcsv` and not a regex.** CSV has
edge cases that regex handles badly: quoted
values containing commas, quoted values
containing newlines, escaped quotes inside
quoted values. `fgetcsv` is well-tested. The
reader is 30 lines on top of it.

---

## The entity handlers

One handler per entity. Each declares:

  - **The table.** The entity's table name.
  - **The natural key.** The column(s) that
    identify a row for idempotency. A re-upload
    of the same file updates existing rows
    rather than creating duplicates.
  - **The required fields.** What the handler
    will not accept a row without.
  - **The validation rules.** Per-field,
    matching the admin form's validation.
  - **The upsert semantics.** What "create"
    means (INSERT) and what "update" means
    (UPDATE by natural key). The update path
    touches only the fields the CSV provided.
  - **The foreign key order.** Which entities
    must have been imported before this one.

Handlers live at
`app/Commerce/Services/Import/Handlers/`.

### `ImportBrandHandler`

  - Table: `commerce_brands`
  - Natural key: `slug` (per store)
  - Required: `name`, `slug`
  - Validation: name ≤ 120, slug matches
    `[a-z0-9-]+`, slug unique per store
  - Create: INSERT
  - Update: by slug; name and optional URL are
    overwritten
  - Depends on: nothing

### `ImportCategoryHandler`

  - Table: `commerce_categories`
  - Natural key: `slug` (per store)
  - Required: `name`, `slug`
  - Optional: `parent_slug` (resolved against
    the already-imported categories)
  - Validation: parent must resolve to a
    category imported earlier in this batch
  - Create: INSERT
  - Update: by slug
  - Depends on: nothing (parents are resolved
    within the same import)

### `ImportAttributeHandler`

  - Table: `commerce_attribute_definitions`
  - Natural key: `slug`
  - Required: `name`, `slug`, `input_type`
  - Optional: `is_variant_axis`
  - Create: INSERT
  - Update: by slug
  - Depends on: nothing

### `ImportProductHandler`

  - Table: `commerce_products`
  - Natural key: `sku` (per store)
  - Required: `name`, `sku`, `slug`, `price`
  - Optional: `brand_slug` (resolved against
    imported brands), `category_slugs`
    (comma-separated; resolved against imported
    categories), `tax_class_id`,
    `short_description`, `long_description`
  - Validation: sku unique per store, slug
    unique per store, price ≥ 0
  - Create: INSERT + product_categories rows
  - Update: by sku; the listed fields are
    overwritten; category associations are
    added (not removed) unless the CSV
    explicitly clears them
  - Depends on: brands, categories

### `ImportVariantHandler`

  - Table: `commerce_product_variants`
  - Natural key: `sku`
  - Required: `product_sku`, `sku`,
    `attribute_values` (e.g. "Color:Red|Size:M")
  - Validation: product_sku resolves to an
    imported product, attribute_values parse
    into value ids
  - Create: INSERT + variant_attribute_values
  - Update: by sku
  - Depends on: products, attributes

### `ImportInventoryHandler`

  - Table: `commerce_inventory`
  - Natural key: the SKU, resolved to the
    `(store_id, product_id, variant_id)` tuple.
    `variant_id = 0` for simple products (the
    sentinel the codebase uses).
  - Required: `sku`, `quantity`
  - Validation: SKU resolves to a simple product,
    quantity is a non-negative integer
  - Create: `InventoryRepository::adjust()` with
    movement type `'opening'`
  - Update: `InventoryRepository::adjust()` with
    movement type `'stocktake'`
  - Depends on: products
  - **Variant-SKU inventory is deferred to
    016.4.** The `ProductVariantRepository` has
    no global SKU lookup - `findBySku()` takes
    the parent product id. A CSV that carries
    only a variant SKU cannot resolve without
    that lookup. 016.4 adds `findBySkuGlobal()`
    to the repository and extends this handler.
    Session 2's inventory handler covers the
    simple-product case.
  - **The movement type is `'stocktake'`, not
    `'import'`.** The repository's valid-type
    list is closed and `'import'` is not in it.
    `'stocktake'` is the semantically correct
    choice: a full correction to a known value.
    The `reason` option carries
    "Imported from CSV", and `reference_type`
    is `'import_batch'`.

### `ImportCustomerHandler`

  - Table: `commerce_customers`
  - Natural key: `email` (per store)
  - Required: `email`, `first_name`,
    `last_name`
  - Optional: `phone`, `company`,
    `tax_id`, `group_slug`,
    `accepts_marketing`
  - Validation: email well-formed and unique
    per store, group_slug resolves to an
    existing group
  - Create: INSERT with a generated random
    password (the customer resets it via the
    forgot-password flow)
  - Update: by email; the listed fields are
    overwritten
  - Depends on: nothing (groups already exist
    per store from the seeders)

### What is not an entity handler

**Customer groups and price lists** are named
in the ROADMAP but are not in this stage. The
schema for both needs a separate read; the
handler shape (multiple price tiers per group)
does not fit the one-row-per-record model the
other handlers use. Deferred to 016.1.

**Suppliers** are blocked on Stage 007C
(Procurement). 007C is not shipped. Deferred
to 016.2 after 007C closes.

---

## The pipeline

Six phases. Each phase has its own output.
Each phase is a UI step in the admin.

### 1. Upload

`GET /commerce/ca-admin/import`
`POST /commerce/ca-admin/import/upload`

The merchant picks an entity (one of the
seven) and uploads a CSV. The upload creates
a `commerce_import_batches` row with
`status = 'uploaded'` and stores the file at
`storage/imports/{batch_id}.csv`.

Validation at upload:

  - File size ≤ 10 MB (a 100,000-row CSV is
    ~10 MB for most entity shapes).
  - File extension is `.csv`.
  - The reader's `read()` succeeds. If it
    fails, the batch is deleted and the
    merchant sees the error.

### 2. Map

`GET /commerce/ca-admin/import/{id}/map`
`POST /commerce/ca-admin/import/{id}/map`

The merchant maps the uploaded CSV's header
names to the entity handler's expected fields.

The screen shows, for each expected field:

  - The field name.
  - Its required badge.
  - A select populated with the CSV's header
    names.
  - A "Do not import" option (empty columns
    the merchant does not need).

The mapping is stored in the batch row's
`mapping_json` column.

### 3. Validate

`POST /commerce/ca-admin/import/{id}/validate`

The batch's rows are parsed, mapped, and
validated. No database write. The result is
one `commerce_import_rows` row per CSV row
with `status = 'valid'` or `status = 'invalid'`
and the specific error for the invalid rows.

The batch status moves to `'validated'`.
`rows_total`, `rows_created`, `rows_updated`,
and `rows_failed` are computed and stored on
the batch row (though `rows_created` and
`rows_updated` are the "would create" and
"would update" counts — nothing is committed
yet).

**Idempotency check during validate.** Each
valid row is looked up by its natural key. If
the row exists, it is marked `would_update`;
otherwise `would_create`. The dry-run
preview shows both counts.

### 4. Preview

`GET /commerce/ca-admin/import/{id}/preview`

A read-only screen. Two tables:

  - **Would create** — the rows that will
    INSERT.
  - **Would update** — the rows that will
    UPDATE, with a diff view showing old vs new
    for each column.
  - **Invalid** — the rows that failed
    validation, with the error per row.

A "Download failed rows" button exports the
invalid rows as a CSV with a `_error` column
appended.

The batch stays `'validated'`.

### 5. Commit

`POST /commerce/ca-admin/import/{id}/commit`

The batch is committed. The commit is chunked:
every 500 rows is one transaction. If any row
in a chunk fails (e.g. a uniqueness conflict
that validation missed because a concurrent
admin created the same SKU), the whole chunk
rolls back, and the batch moves to
`'partially_committed'`.

**The two commits that succeed, in one unit.**

For each valid row, the handler is called. The
handler writes the row to its entity table,
then records what it did:

  - A `commerce_import_rows` row is updated
    from `'valid'` to `'committed'` with the
    created or updated entity id.
  - For an updated row, the previous values
    are recorded in a JSON column on the row
    (for rollback).

After the last chunk, the batch moves to
`'committed'`. The failed-row count on the
batch is the number of `'invalid'` rows plus
any rows that failed in a chunk.

### 6. Failed-row export

`GET /commerce/ca-admin/import/{id}/failed.csv`

The invalid rows, re-serialised as a CSV with
the original columns plus a `_error` column.
The merchant fixes those rows in the original
file, re-uploads, and the flow runs again.
The natural key makes the re-upload idempotent.

### 7. Rollback (optional)

`POST /commerce/ca-admin/import/{id}/rollback`

The batch is reversed. For each `'committed'`
row, in reverse dependency order:

  - If the row was created, it is deleted.
  - If the row was updated, the previous values
    are restored from the row's JSON column.

Rollback is transactional per chunk. A
partially-committed batch can be rolled back
up to the last successful chunk.

The batch moves to `'rolled_back'`.

---

## The schema

Two new tables. Migrations 0346 and 0347.

### `commerce_import_batches`

    id                BIGINT UNSIGNED PK AUTO_INCREMENT
    store_id          BIGINT UNSIGNED NOT NULL
    entity            VARCHAR(60) NOT NULL
                      -- 'brand', 'category', 'attribute',
                      -- 'product', 'variant', 'inventory',
                      -- 'customer'
    filename          VARCHAR(255) NOT NULL
    status            ENUM('uploaded','mapped','validated',
                           'committing','committed',
                           'partially_committed','rolled_back',
                           'cancelled')
                      NOT NULL DEFAULT 'uploaded'
    mapping_json      JSON NULL
    rows_total        INT UNSIGNED NOT NULL DEFAULT 0
    rows_valid        INT UNSIGNED NOT NULL DEFAULT 0
    rows_invalid      INT UNSIGNED NOT NULL DEFAULT 0
    rows_created      INT UNSIGNED NOT NULL DEFAULT 0
    rows_updated      INT UNSIGNED NOT NULL DEFAULT 0
    rows_failed_commit INT UNSIGNED NOT NULL DEFAULT 0
    created_by        INT UNSIGNED NULL
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    committed_at      DATETIME NULL
    rolled_back_at    DATETIME NULL

    KEY idx_com_imp_batches_store_created
      (store_id, created_at)
    KEY idx_com_imp_batches_store_status
      (store_id, status)
    FK store_id -> commerce_stores(id) CASCADE
    FK created_by -> core_users(id) SET NULL

### `commerce_import_rows`

    id           BIGINT UNSIGNED PK AUTO_INCREMENT
    store_id     BIGINT UNSIGNED NOT NULL
    batch_id     BIGINT UNSIGNED NOT NULL
    row_number   INT UNSIGNED NOT NULL
    natural_key  VARCHAR(190) NOT NULL
    payload_json JSON NOT NULL
    previous_values_json JSON NULL
    entity_id    BIGINT UNSIGNED NULL
    status       ENUM('pending','valid','invalid',
                      'committed','rolled_back')
                 NOT NULL DEFAULT 'pending'
    error        VARCHAR(500) NULL
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP

    KEY idx_com_imp_rows_batch_row
      (batch_id, row_number)
    KEY idx_com_imp_rows_batch_status
      (batch_id, status)
    FK store_id -> commerce_stores(id) CASCADE
    FK batch_id -> commerce_import_batches(id) CASCADE

Both tables use the same InnoDB, utf8mb4,
CASCADE pattern the codebase uses. The
`commerce_import_rows` row is the per-row
record: one row per CSV row, its status,
its validation or commit error, the entity id
it created or updated, and the previous values
(for rollback).

**Why two tables and not one.**

The batch table answers "what was uploaded and
what happened." The row table answers "what
happened to row 47." A failed-row export needs
the row table. A rollback needs the row table.
The batch's aggregate counters are cheap
derived values, not stored twice.

---

## The admin UI

Five screens, one nav entry.

**Nav.** A single "Import" entry under the
Catalog group, gated by a new
`catalog.import` permission.

**List.** `GET /commerce/ca-admin/import` — a
list of batches, newest first. Columns: entity,
filename, status, created_at, rows (total / valid
/ invalid / created / updated). Filters: entity,
status. Row actions: view, resume (if not yet
committed), cancel.

**Upload.** `GET/POST /commerce/ca-admin/import/upload`
— entity select + file input + submit.

**Map.** `GET/POST /commerce/ca-admin/import/{id}/map`
— the mapping screen, described above.

**Preview.** `GET /commerce/ca-admin/import/{id}/preview`
— the read-only preview.

**Detail.** `GET /commerce/ca-admin/import/{id}` —
the batch detail with per-row status, the
failed-row export button, the commit button
(when validated), and the rollback button
(when committed).

**CSS.** The batch list uses the existing
`data-responsive="cards"` treatment (Stage 014
established it). The preview tables use
`data-responsive="scroll"` — they are wider
than 8 columns and the row shape matters more
than the summary.

The render harness (Stage 015) covers every
one of these screens. The import screens will
be navigated at the four viewports after the
stage's Session 2.

---

## Permissions

One new slug, seeded in migration 0348:

    catalog.import  view and manage imports

Granted to `super-admin`, `store-admin`, and
`catalog-manager` by default. A merchant can
create a role with or without it.

---

## Sessions

Four sessions. Each ends with a commit. The
plan originally estimated three; the split
of Session 2 into 2a and 2b happened at
Session 2a's open, when the six remaining
handlers plus the full pipeline proved too
much for a single verifiable step.

### Session 1 - plan + schema + reader

  [shipped 2026-10-17, commit ec2d6d9]

  - This plan document.
  - Migrations 0346 (batches) and 0347 (rows).
  - Permission seeder 0019.
  - The `CsvReader` class.
  - The `ImportHandler` interface.
  - The `ImportBatchStatus` constants class.
  - The first handler, `ImportBrandHandler`.
  - `ImportBatchRepository`,
    `ImportRowRepository`.
  - The admin controller skeleton with the list
    and upload screens.
  - The batch detail placeholder.
  - Smoke case `import-brand`.

### Session 2a - the six handlers + validate pipeline

  - The six remaining handlers: category,
    attribute, product, variant, inventory,
    customer.
  - The `AttributeRepository` extension
    (findBySlug, create, update, softDelete,
    findValueIdBySlug, findValueIdByValue).
  - `ImportController::handlerRegistry()`
    gains the six new entries.
  - `ImportValidator`: parse the batch CSV,
    apply the mapping, walk rows, call
    `$handler->validate()`, write per-row
    status. Updates batch counters. Moves the
    batch to `validated`.
  - The map screen and its routes.
  - The validate route.
  - The batch detail screen (replaces the
    Session 1 placeholder with the real
    status and the map / validate buttons).
  - Smoke case `import-product`.

  At the end of Session 2a, a batch can be
  uploaded, mapped, and validated. Nothing
  commits yet. The preview and commit screens
  do not exist. The batch detail shows the
  per-row status counts and the "coming in
  Session 2b" note.

### Session 2b - the committer + the pipeline tail

  - `ImportCommitter`: chunked transactional
    commit. Per chunk: call `handler->create()`
    or `handler->update()`, record the entity id
    and previous values on the row, commit.
    Per-row failures mark the row invalid and
    continue.
  - `ImportRollback`: reverse a committed batch
    in dependency order. Creates → `delete()`.
    Updates → `restore()`. Chunked.
  - The preview screen (would-create,
    would-update, invalid).
  - The commit, rollback, and failed-export
    routes.
  - The failed-row export.
  - Smoke case `import-customer`.

  At the end of Session 2b, the pipeline is
  complete: upload, map, validate, preview,
  commit, export, rollback.

### Session 3 - the browser test + close

  - The browser test: upload a real CSV for
    each of the seven entities, walk through
    map → validate → preview → commit, verify
    the rows exist in the database, verify the
    failed-row export for a deliberately broken
    row, verify the rollback.
  - `composer render` on the import screens at
    four viewports.
  - `composer check` on every file touched.
  - Session file, STATUS, ROADMAP, COMPLIANCE-DE
    (the customer import touches DSGVO: the
    handler records the import source on the
    consent record, same as the newsletter).
  - Stage close.

---

## Test approach

Two smoke cases carry the pipeline:

  - **`import-brand`** — a synthetic two-row
    CSV, validated and committed in-memory.
    Asserts two brands, their natural keys, the
    batch's counters, the row statuses, and
    the rollback. The smallest entity: no
    foreign keys, no variant resolution, no
    attribute parsing. Proves the pipeline
    shape.

  - **`import-product`** — a CSV with a product
    that references an already-imported brand
    and category. Proves the foreign key
    resolution, the category association, and
    the update-on-natural-key semantics.

  - **`import-customer`** — a CSV with a new
    customer and a duplicate email. Asserts
    the create, the duplicate error, and the
    group assignment. Proves the validation
    error path.

The browser test in Session 3 covers the
end-to-end flow with real files: every entity
gets a file, every screen gets a click, every
error message renders.

---

## What is not in this stage

  - **XLSX.** CSV only. XLSX is deferred to
    016.3. The reader accepts CSV; the upload
    form validates the extension; anything else
    is rejected with a clear message.

  - **Automatic column mapping.** The merchant
    maps columns manually. Automatic detection
    (fuzzy header matching) is a future
    enhancement. The mapping screen is the
    right place to add it when it is needed.

  - **Customer groups and price lists.**
    Deferred to 016.1. The schema needs a
    separate read.

  - **Suppliers.** Deferred to 016.2. Blocked
    on Stage 007C.

  - **Multi-currency price import.** The
    handler reads the store's default currency.
    A CSV with prices in another currency is
    rejected during validate. Multi-currency
    import (with conversion or with a
    currency_code column) is a future
    enhancement.

  - **Rollback of a customer's deletion.** If
    the merchant deletes a customer between
    commit and rollback, the rollback restores
    the row and the FK cascades to any
    dependent rows. The rollback is a
    best-effort restoration; it does not
    resurrect deleted dependents. The batch
    detail warns.

  - **Live progress.** The commit runs
    synchronously. A 100,000-row commit takes
    ~30 seconds on a local MySQL. Live progress
    (chunked async) is a future enhancement.
    The batch detail shows "committing" during
    the request; the browser waits.

---

## Open questions

  1. **Where do the uploaded CSV files live?**
     `storage/imports/{batch_id}.csv` is the
     proposal. Outside the web root (so the
     file is not web-accessible). The file is
     deleted after commit and after rollback,
     once the failed-row export is no longer
     needed. A future cleanup cron removes
     abandoned batches older than 7 days.

  2. **What happens if the merchant deletes a
     batch?** The batch row and its rows are
     deleted (CASCADE). The imported entity
     rows are NOT deleted — the batch was a
     record of the import, not a container of
     the data. A batch that was committed
     remains as a record; deleting it removes
     only the record. Rollback must happen
     before delete if the merchant wants the
     data reverted.

  3. **What about the customer password?** The
     handler generates a random 32-byte hex
     string, hashes it with the same Hasher the
     registration flow uses, and stores it. The
     customer does not know the password; the
     forgot-password flow is the first login.
     The batch detail warns the merchant that
     imported customers must reset their
     password.

  4. **What about the customer's email
     consent?** A customer imported from a CSV
     has not given DSGVO-compliant consent to
     receive marketing. The handler sets
     `accepts_marketing = 0` regardless of what
     the CSV says, unless the merchant
     explicitly confirms in a checkbox on the
     upload screen ("I confirm every customer
     in this file has consented to marketing").
     The default is no consent. The checkbox is
     the merchant's legal responsibility; the
     batch records which value it carried.

  5. **What about the customer's group?** A
     `group_slug` in the CSV is resolved
     against the store's groups. A group that
     does not exist is a validation error per
     row. A customer with no group is assigned
     to the store's default group (the seeder
     creates one).

These are resolved in the sections above.
Reviewed at Session 1 open.

---

## See also

  - `docs/ROADMAP.md` "Data Import (CSV /
    Excel)" — the backlog entry this plan
    expands.
  - `app/Commerce/Controllers/Admin/InventoryController.php`
    `::csvResponse()` — the CSV export shape
    the reader round-trips against.
  - `app/Commerce/Controllers/Admin/AdminFormController.php`
    `::submissionsExport()` — the failed-row
    export's closest sibling.
  - `database/migrations/0332_create_email_queue.sql`
    — the batch + rows + status + per-record
    table shape the plan's two tables follow.
  - `database/migrations/0336_create_form_submissions.sql`
    — the per-record payload + status shape.
  - `docs/stages/014_responsive-layout-plan.md`
    — the `data-responsive` treatment the
    import screens use.
  - `docs/stages/015_render-harness-plan.md`
    — the harness that runs against the import
    screens after Session 2.
