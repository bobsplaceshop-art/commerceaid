# Stage 023 — Page Builder Architecture

**Status:** Audit complete. Tier 0 (foundation) next.
**Opened:** 2026-10-24
**Audits against:** the master prompt's Phase 1 requirements.
**Does not implement:** the engine. Implements the audit and the
dependency order that makes the engine buildable.

---

## 1. What exists today

The page builder is a flat block list editor backed by two
tables and four files that matter.

### 1.1 The tables

`commerce_page_blocks` — the published blocks. Columns:
`id`, `store_id`, `page_slug`, `block_type`, `sort_order`,
`config_json`, `is_enabled`. Ordered by `sort_order`. Flat.
No parent, no children, no schema version.

`commerce_page_drafts` — the draft overlay. Columns:
`id`, `store_id`, `theme_slug`, `page_slug`, `block_id`,
`block_type`, `sort_order`, `config_json`, `status`. Status is
one of `pending_add`, `pending_update`, `pending_delete`.
`block_id` is NULL for `pending_add` rows; the row's own `id`
is the effective block id for those.

`commerce_store_pages` — page metadata (title, meta
description, status, noindex). One row per (store, slug).

### 1.2 The merge

`DraftManager::getMergedBlocks($pageSlug, $themeSlug)` reads
`commerce_page_blocks` (`listForPage()`) and overlays any
`commerce_page_drafts` rows for the same page. Draft rows in
`pending_update` override the published row's `sort_order` and
merge their `config_json` over the published config.
`pending_add` rows become new blocks with negative ids.
`pending_delete` rows remove blocks from the merged list.
The merge is sorted by `sort_order`, so the sidebar and the
preview always render the same order.

### 1.3 The publish

`DraftPublisher::publish($pageSlug, $themeSlug)` walks the
draft rows, applies them to `commerce_page_blocks`
(`pending_delete` → delete, `pending_update` → merge config
+ update sort_order, `pending_add` → insert as new published
row), then clears the drafts. The write is transactional.

### 1.4 The controller

`AdminPageController` is the only admin controller. Methods:
`index`, `show`, `create`, `store`, `addBlock`, `deleteBlock`,
`duplicateBlock`, `toggleBlock`, `saveBlockConfig`,
`configBlock`, `previewBlock`, `publish`, `discard`,
`reorderBlocks`, `pageSettings`, `updatePageSettings`.

The only public write path is `saveBlockConfig()`. It reads
`$_POST['config']`, walks the schema for the block type,
validates required fields, and writes the merged config to
`commerce_page_drafts` (as `pending_update`) or to the
matching `pending_add` row.

### 1.5 The schema

`BlockConfigRegistry::schema($blockType)` returns one entry
per config field. Each entry is:
`{label, type, default, hint?, options?}`. Types: `string`,
`text`, `int`, `boolean`, `select`, `category_select`, `file`.
`BlockConfigRegistry::defaults($blockType)` derives the
default map from the schema (used to seed a new block's
`config_json`).

### 1.6 The renderer

`resources/views/storefront/home.php` loops the merged blocks
and for each one calls
`view_partial('storefront/blocks/' . $block->blockType, $viewData)`.
`$viewData` carries `config` plus any block-specific data the
controller pre-loaded (products, categories). Each partial
renders itself. No shared layout dispatcher, no validation on
missing partials.

### 1.7 The editor

`resources/views/admin/pages/show.php` renders the two-pane
editor: the block list on the left, the live preview iframe
on the right. The iframe loads `/?customize_preview=1&v=<hash>`,
which triggers the preview branch of `HomeController`.

`page-builder-customizer-v3.js` wires the sidebar:
device toggle, drag reorder, settings panel, and the drop
handler that POSTs to `reorderBlocks`. The block config form
is rendered from `_config-field.php` into
`tpl-settings-<blockId>` templates, and
`bindLiveForm(form)` posts each change to `saveBlockConfig`
and to `previewBlock` (which returns the block's HTML for
in-place swap via postMessage to the iframe).

### 1.8 The full file set

**Controllers:** `AdminPageController.php`.
**Services:** `DraftManager.php`, `DraftPublisher.php`.
**Repositories:** `PageBlockRepository.php`,
`PageDraftRepository.php`, `StorePageRepository.php`.
**Models:** `PageBlock.php`.
**Support:** `BlockConfigRegistry.php`.
**Admin views:** `_block-config-modal.php`,
`_block-media-picker.php`, `_block-picker.php`,
`_config-field.php`, `block-config.php`, `create.php`,
`index.php`, `show.php`.
**Storefront block partials:** `banner.php`,
`category-grid.php`, `hero.php`, `newsletter.php`,
`product-grid.php`, `promo-panel.php`, `rich-text.php`,
`spacer.php`.
**Migrations:** `0350_create_page_blocks.sql`,
`0351_create_page_drafts.sql`, `0352_create_store_pages.sql`.
**JS:** `page-builder-customizer-v3.js`, `preview-bridge.js`,
`block-media-picker.js`, `page-builder.js`,
`page-builder-live.js`.
**CSS:** `app.css`, `main-v2.css` (admin);
`storefront-catalog.css`, `storefront.css` (storefront).

`page-builder.js` and `page-builder-live.js` are not loaded by
any current view. They are historical. The live editor is
`page-builder-customizer-v3.js`.

---

## 2. Master prompt requirement matrix

The master prompt's Phase 1 asks for a tick-list against the
whole spec. Below is that list, with status per requirement.

### 2.1 Architectural principles

| Requirement | Status | Notes |
|---|---|---|
| Single source of truth | Partial | Two tables, merged at read. Agree at read time; no document version. |
| Structured, versioned document | Missing | No `schema_version`, no root node |
| Schema-driven design | Partial | Per-block-type field schema; no shared property types |
| Configuration over hardcoding | Found | `BlockConfigRegistry`, `StoreSettingDefinitions` |

### 2.2 Document model (§5)

| Requirement | Status |
|---|---|
| Document ID | Found (implicit — one page per store+slug) |
| Schema version | Missing |
| Page title | Found (`commerce_store_pages.title`) |
| Page settings | Partial (title, meta, status, noindex) |
| Root node or ordered roots | Missing (flat list) |
| Node ID stable | Found (`id` on both tables) |
| Node type | Found (`block_type`) |
| Human label | Partial (`PageBlock::typeLabel()`) |
| Content properties | Found (`config_json`) |
| Layout properties | Missing |
| Style properties | Missing |
| Responsive overrides | Missing |
| Children | Missing |
| Schema migrations | Missing |

### 2.3 Layout engine (§6)

| Requirement | Status |
|---|---|
| Sections | Missing (each block is a section wrapper today) |
| Containers | Missing |
| Flexbox containers | Missing |
| CSS Grid containers | Missing |
| Full-width + boxed content | Partial (per-partial) |
| Layout presets | Missing |
| Nesting | Missing |
| Padding / margin controls | Missing |
| Width / height controls | Missing |

### 2.4 Property schema (§7)

| Requirement | Status |
|---|---|
| Per-type property schema | Found |
| Shared property types | Missing |
| Editor control registry | Partial (7 branches in `_config-field.php`) |
| Validation | Partial (required only) |
| Reset to default | Missing |

### 2.5 Responsive (§8)

| Requirement | Status |
|---|---|
| Device preview | Found (desktop / tablet / mobile iframe widths) |
| Per-property responsive overrides | Missing |
| Breakpoint config | Partial (hardcoded in JS and CSS) |
| Override inheritance | N/A (no overrides) |

### 2.6 Rendering (§9)

| Requirement | Status |
|---|---|
| Shared widget rendering | Partial (per-partial, no dispatcher) |
| Editor-only decoration | Found (only in `customize_preview`) |
| Semantic HTML | Found |
| CSS isolation | Partial (namespaced classes, no allowlist) |
| Published render | Found |

### 2.7 Editor state and commands (§10)

| Requirement | Status |
|---|---|
| Editor state separate from document | Partial (selection is DOM state) |
| Command layer | Missing |
| History / undo / redo | Missing |
| Dirty-state tracking | Found (UI) |
| Save failure recovery | Partial (`res.ok` only) |

### 2.8 Drag and drop (§11)

| Requirement | Status |
|---|---|
| Drag block list reorder | Found |
| Drag into canvas | Missing |
| Insertion indicator | Missing |
| Nested drop targets | Missing |

### 2.9 History (§12)

| Requirement | Status |
|---|---|
| Undo / redo | Missing |

### 2.10 Persistence (§13)

| Requirement | Status |
|---|---|
| Save | Found (`saveBlockConfig`) |
| Load | Found (`DraftManager::getMergedBlocks`) |
| Publish | Found (`DraftPublisher::publish`) |
| Version checks | Missing |
| Dirty-state | Found |
| Save failure recovery | Partial |

### 2.11 Migration (§14)

| Requirement | Status |
|---|---|
| Document schema version | Missing |
| Ordered migrations | Partial (schema migrations exist; document migrations do not) |
| Backup / recovery | Missing |

### 2.12 Widget registry (§15)

| Requirement | Status |
|---|---|
| Per-type schema lookup | Found |
| Per-type defaults | Found |
| Per-type renderer | Partial (string concat, no error on missing) |
| Per-type validation | Partial (generic) |
| Registration API | Missing (block list hardcoded in 3 places) |
| Per-type default seed | Partial (`defaults()` + `addBlock()`) |

### 2.13 Security (§16)

| Requirement | Status |
|---|---|
| Rich text sanitisation | Missing (`rich-text` stores raw HTML) |
| URL validation | Partial (`e()` escapes; no `javascript:` scheme block) |
| CSS value validation | Missing (N/A today — all CSS is hardcoded) |
| Node-type validation | Found |
| Depth / size limits | Missing (N/A — flat) |
| Access control | Found (middleware) |

### 2.14 Performance (§17)

| Requirement | Status |
|---|---|
| Baseline measurement | Unknown (builder path not in `bin/perf-check.php`) |
| Large-document tests | Missing |

### 2.15 Testing (§18)

| Requirement | Status |
|---|---|
| Unit tests | Missing |
| Integration tests | Missing |
| End-to-end | Partial (`composer check`; no browser harness) |
| Smoke cases | Partial (`bin/smoke.php`; not exercised for the builder) |

---

## 3. The gap, restated

The audit finds three failures that explain every row above.

### 3.1 No document model

A block is a row. There is no JSON document, no root node,
no schema version, no children array. Everything the master
prompt calls "the document" is split across two tables and
merged at read time. The merge works and agrees between
sidebar and preview, but a document is not a composition
of nodes. It is a list of rows.

**Consequence:** nesting, sections, containers, layout
properties, responsive overrides, and schema migrations
are each blocked on a document model that does not exist.

### 3.2 No shared property types

Every block type declares its own fields, each with a scalar
type. There is no `padding_box`, `typography`, `dimensions`,
`color`, or `alignment` type shared across block types. Adding
the tenth block type means adding ten fields to it that were
already added to the other nine.

**Consequence:** the tenth block is bigger than it should be,
and the eleventh bigger again. The system's per-block cost is
not converging.

### 3.3 No single render dispatch

`home.php` builds a view path by string concatenation and
calls `view_partial()`. If a block type exists in the schema
but no partial exists, the page 500s silently. If a partial
exists but no type is registered, the partial is dead code.
There is no source of truth for "what blocks exist".

**Consequence:** the block list is hardcoded in
`AdminPageController::addBlock()`, `_block-picker.php`, and
`BlockConfigRegistry`. Three places to update per block.

---

## 4. The dependency graph

The graph is strict. Nothing higher builds until what is
below it exists.

### Tier 0 — foundations

**0.1 `ConfigFieldRegistry`** — the shared property-type
registry. One class. Each field type is a definition
(label, control, validation, default). `_config-field.php`
becomes a consumer of the registry rather than an
if/elseif chain of seven branches.

**0.2 `BlockTypeRegistry`** — the block list. One class.
`register()`, `all()`, `has()`. `AdminPageController::addBlock()`
consults it. `_block-picker.php` iterates it.
`BlockConfigRegistry` stays as the schema source; the block
type list moves out.

**0.3 `BlockRenderer`** — the dispatcher. One class.
`render(PageBlock $block): string`. A `match` on
`$block->blockType` that maps to the exact partial, or
throws on missing. `home.php` calls it once.

### Tier 1 — document model

**1.1 `schema_version` column** on both block tables. Default 1.
Nothing reads it yet.

**1.2 `parent_id` nullable column** on both block tables.
Default NULL. Nothing uses it yet.

**1.3 `children()` helper** on `PageBlock` and
`DraftManager`. Returns root nodes vs children. The traversal
exists; nothing renders nested blocks yet.

**1.4 `responsive_json` nullable column** on both block tables.
Default NULL. Nothing reads it yet.

### Tier 2 — editor infrastructure

**2.1 command layer** in `page-builder-customizer-v3.js` — a
`Builder` object with `addNode`, `updateNode`, `moveNode`,
`deleteNode`. Each routes to the existing controller methods.
The drag reorder becomes a `moveNode` call.

**2.2 history buffer** in JS — an array of commands.
`undo()` and `redo()` send commands in reverse order. Cleared
on load. In-memory.

**2.3 per-field responsive tab** in `_config-field.php`.
Consumes `responsive_json`. Posts to `saveBlockConfig` with a
`device` key. Empty until a value is overridden.

### Tier 3 — feature work

**3.1 `section` and `container` block types.** Nestable via
`parent_id`. `BlockRenderer` supports children rendering.

**3.2 layout properties** on containers (flex-direction,
grid-template-columns, gap). Property registry provides
the controls. CSS consumed via `style=""`.

**3.3 drag-into-canvas** via `postMessage` from the iframe.
Insertion indicator. The drop fires `addNode` with a
`parent_id` and `after_id`.

**3.4 per-property responsive UI** — the device toggle in
the block settings panel. One tab per field.

**3.5 document schema migration engine** —
`DocumentMigrator` in Services/Builder + `bin/migrate-documents.php`.

### Tier 4 — engine

**4.1 the JSON document.** A `commerce_store_pages.document_json`
column, replacing the merge-at-read-time with a
document-at-read-time. This is the biggest single migration
in the plan. It rewrites `DraftManager`, `DraftPublisher`, and
every consumer of `getMergedBlocks()`.

**4.2 layout presets** — one and two and three column layouts
that create real section/container nodes.

**4.3 widget registration API** — a `register()` method on
`BlockTypeRegistry` that any future widget calls, plus a
documented developer guide.

---

## 5. The build order

The graph's tiers are the build order. Within each tier, the
items in order.

### Session A — Tier 0.1

- Create `app/Commerce/Support/Blocks/ConfigFieldRegistry.php`.
- Refactor `_config-field.php` to consume it. Eight existing
  types port to registry entries: string, text, int, boolean,
  select, category_select, category_multi_select, file. One
  new type is added: color.
- Refactor `AdminPageController::saveBlockConfig()` to call
  the registry's `sanitize` closure instead of its current
  match. The boolean and category_multi_select short-circuits
  stay in `saveBlockConfig` — they run before the required/
  empty check, and that order must survive the refactor.
- Commit. `composer analyse` green. `composer smoke` must
  still pass byte-identical (the shaper case, captured
  at 0f061f7, is the acceptance criterion).

**Amendment (2026-10-24):** the original wording said "seven
existing types" and named `category_multi_select` as new.
That was written before the category grid controls shipped;
`category_multi_select` landed at ae8dc3e. The count is
eight existing types, one new. Corrected here so the plan
matches reality.

### Amendment — Tier 0 is not complete (2026-10-24)

The audit's tier list under-weighted the document model. It
put `parent_id`, `children()`, and tree rendering in Tier 1,
behind three registry/dispatcher tasks. That sequencing was
wrong.

Sections and containers — the master prompt's Phase 3 — are
impossible without `parent_id` and a tree traversal. The
registries (ConfigFieldRegistry, BlockTypeRegistry,
BlockRenderer) are useful, and they shipped, but they are
the schema layer. The document model is the frame. Without
the frame, no amount of schema work makes a Section possible.

**The corrected Tier 0, in build order, with shipped status:**

  0.1  ConfigFieldRegistry      — shipped 58df9c0
  0.2  BlockTypeRegistry        — next
  0.3  BlockRenderer            — after 0.2
  0.4  parent_id on blocks + drafts       — shipped dc9f74d
  0.5  children() traversal on PageBlock
       and DraftManager
         5a  PageBlock::$parentId/$children
                                  — shipped 850817c
         5b  repository parent-aware
                                  — shipped 32677a7
         5c  DraftManager::getMergedTree()
                                  — after 0.2, 0.3
  0.6  DraftPublisher two-pass publish
       (parents before children)  — after 5c
  0.7  tree-aware BlockRenderer
       (Section/Container accept children)
                                  — after 0.3, 5c
  0.8  section and container block types
                                  — after 0.7

**Status at 2026-10-24 session close:** 0.1, 0.4, 5a,
5b shipped. 0.2 and 0.3 are open. 5c, 0.6, 0.7, 0.8
remain. The tree is at 5b: the schema and model carry
parent_id, but nothing reads children yet, and home.php
still renders a flat list. The next session opens with
0.2 (BlockTypeRegistry), not 5c — the tree traversal is
only useful once BlockRenderer knows what block types
exist and can dispatch to a tree walker.

0.1-0.3 were built first because the schema layer is small
and self-contained. 0.4-0.8 are the document model. Tier 1
becomes "editor commands and history"; Tier 2 "sections,
containers, layout"; Tier 3 "drag-into-canvas, responsive
overrides, migration engine"; Tier 4 "the JSON document";
Tier 5 "menu/footer builder + Customisations sidebar tab".

The Session A block above is now complete for 0.1. Session B
opens with 0.2 (BlockTypeRegistry). Session C opens with
0.4 (parent_id). Section and Container arrive at 0.8, and
Divider/Spacer become draggable elements at that point —
they are leaf element types the merchant drops into a
Section, not block-level controls.

The correction is on the record so that Session C does not
open with the same plan that produced three registries and
no tree.

### Session B — Tier 0.2

- Create `app/Commerce/Support/Blocks/BlockTypeRegistry.php`.
- Refactor the three hardcoded block lists to consume it.
- `_block-picker.php` iterates. `AdminPageController::addBlock`
  validates against. `BlockConfigRegistry` no longer owns the
  list.
- Commit. `composer analyse` green.

### Session C — Tier 0.3

- Create `app/Commerce/Services/Builder/BlockRenderer.php`.
- `home.php` calls it once.
- Throw on missing partial or missing type.
- Commit. `composer analyse` green.

### Sessions D-H — Tier 1 and Tier 2

Each item in Tier 1 is one migration + one model update + one
test. Each item in Tier 2 is one JS refactor + one UI change.
Five sessions.

### Sessions I-M — Tier 3

Sections, containers, layout properties, drag-into-canvas,
responsive UI, migration engine. Five sessions.

### Sessions N-P — Tier 4

The document. Presets. Registration API. Three sessions.

**Total: 14-16 sessions.** The audit's purpose is not to
shorten the estimate. It is to make the estimate buildable —
one Tier 0 item at a time, each verifiable, none requiring a
rewrite of the previous.

---

## 6. What this stage does NOT do

The audit is documentation. It does not:

- Create `ConfigFieldRegistry`.
- Move the block list into `BlockTypeRegistry`.
- Dispatch through `BlockRenderer`.
- Add `schema_version`, `parent_id`, or `responsive_json`.
- Add sections, containers, or flex/grid.
- Add history, undo/redo, or drag-into-canvas.
- Add responsive per-property overrides.
- Add the document model.

Every one of those is its own session.

---

## 7. What the audit changes

Before the audit, the category grid's nine controls were
"the next commit". After the audit, they are Tier 3 work
that depends on Tier 0 that does not exist.

The controls still ship. But they ship after
`ConfigFieldRegistry` so that every block benefits from the
shared property types, not just the category grid. The
audit is what moved the controls from "now" to
"after the foundation". That is the cost of getting the
foundation right once instead of redoing it eight times.

---

## 8. How to use this document

**To answer "what does the page builder do today?"** —
section 1.

**To answer "what does the master prompt want that we don't
have?"** — section 2.

**To answer "what does a specific session build?"** —
section 5.

**To answer "why are we building `ConfigFieldRegistry` when
the category grid is what I actually want?"** — section 3
and section 7.

**To answer "what should the next session open with?"** —
Session A in section 5.

The document is the reference. The sessions are the
deliverables.

---

## 10. Menu/footer builder and Customisations tab

The build closes with three additions that the master prompt
implies but does not name explicitly:

**Menu builder** — a new admin surface for editing the header
and footer navigation menus. Each menu is a list of items,
each item has a label, a URL (internal slug or external),
an optional parent for one level of nesting, and an order.
The items render through the same BlockRenderer-style
dispatch the blocks use, so the header nav and the footer
nav consume the same data.

  - Tables: `commerce_navigation_menus`,
    `commerce_navigation_items`.
  - Admin: `/commerce/ca-admin/customisations/menus`.
  - Storefront: `sf-header` and `sf-footer` partials read
    the menus instead of the hardcoded nav list.

**Footer builder** — a configurable footer that uses the
same widget vocabulary as the page builder. Columns, a
newsletter block, social links, a copyright line. Not a
full page builder, but a small set of blocks with the same
schema/config/render contract.

  - Config: `commerce_store_settings` under a `footer.*`
    namespace, or its own `commerce_footer_blocks` table
    if the footer needs the same sort/order/edit path the
    page blocks do. Decision at Session C.

**Customisations sidebar tab** — one new sidebar group
collecting Menus, Footer, Theme (font/colour), and Global
Styles. The group is a UI shell; the underlying features
ship across Sessions C through I and the tab becomes
complete when the last of them lands.

**Estimated effort:** three sessions after the document
model (0.4-0.8). Two for the builder surfaces, one for the
sidebar tab and the storefront consumer changes.

**Tier assignment:** the menu/footer builder is Tier 3.5.
The Customisations tab is Tier 5 (closing). Sections,
containers, and layout are Tier 2. The document model is
Tier 0.4-0.8.

## 9. See also

- `docs/ROADMAP.md` — where Stage 023 sits in the sequence.
- `docs/REMEMBER.md` — Patterns 23 (cascade check), 40
  (four-direction cascade), and 42 (middleware short-circuit).
- `docs/BASELINE.md` — the conventions every session
  follows.
- The master prompts (Phase 1 audit / architecture) — the
  requirements this audit is checked against.
