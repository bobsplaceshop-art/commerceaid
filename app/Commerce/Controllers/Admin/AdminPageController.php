<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Models\PageBlock;
use CorporateAid\Commerce\Repositories\PageBlockRepository;
use CorporateAid\Commerce\Repositories\PageDraftRepository;
use CorporateAid\Commerce\Repositories\StorePageRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry;

/**
 * Page builder admin.
 *
 * Session 2 (Stage 023): the page list and the "create page"
 * flow. The builder canvas is a placeholder until Session 3
 * adds the drag-and-drop block editor.
 *
 * Pages are identified by their page_slug. 'home' is the
 * homepage. Anything else is a custom page reachable at
 * /pages/{slug} on the storefront.
 */
final class AdminPageController extends AdminController
{
    /**
     * GET /commerce/ca-admin/pages
     *
     * List every page in use at this store. The 'home' page
     * is always present. Custom pages appear when the merchant
     * has created them.
     *
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $pageRepo   = $this->container->get(StorePageRepository::class);
        $blockRepo  = $this->container->get(PageBlockRepository::class);

        // Ensure the homepage row exists so the merchant can
        // edit its title and meta description without first
        // opening the customizer.
        $pageRepo->ensure('home', 'Homepage');

        $rows = $pageRepo->list();

        // Detect which pages have an unpublished draft.
        $themeSlug    = $this->activeThemeSlug();
        $draftManager = $this->container->get(\CorporateAid\Commerce\Services\Builder\DraftManager::class);

        $pages = [];
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            $pages[] = [
                'slug'       => $slug,
                'label'      => (string) $row['title'],
                'blockCount' => $blockRepo->countForPage($slug),
                'isHome'     => $slug === 'home',
                'hasDraft'   => $draftManager->hasDrafts($slug, $themeSlug),
            ];
        }

        return $this->render('admin.pages.index', [
            'title' => 'Pages',
            'pages' => $pages,
        ]);
    }

    /**
     * GET /commerce/ca-admin/pages/create
     *
     * @param array<string,string> $params
     */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.pages.create', [
            'title'  => 'New page',
            'errors' => $session->getFlash('page_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
        ]);
    }

    /**
     * POST /commerce/ca-admin/pages
     *
     * @param array<string,string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        $title = trim((string) $request->input('title', ''));
        $slug  = trim((string) $request->input('slug', ''));

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        }
        if ($slug === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9-]{0,118}[a-z0-9]$/', $slug)) {
            $errors['slug'] = 'Slug must be lowercase letters, numbers, and hyphens.';
        } elseif ($slug === 'home') {
            $errors['slug'] = 'The homepage cannot be re-created.';
        }

        if ($errors !== []) {
            $session->flash('page_errors', $errors);
            $session->flashOldInput(['title' => $title, 'slug' => $slug]);
            return Response::redirect('/commerce/ca-admin/pages/create');
        }

        // Insert the page metadata row first. This is the
        // authoritative record of the page. Status starts as
        // 'draft' — the merchant publishes via the page
        // settings modal or the Publish button in the builder.
        $pageRepo = $this->container->get(StorePageRepository::class);
        if ($pageRepo->exists($slug)) {
            $session->flash('error', 'A page with that slug already exists.');
            $session->flashOldInput(['title' => $title, 'slug' => $slug]);
            return Response::redirect('/commerce/ca-admin/pages/create');
        }
        $pageRepo->create($slug, $title, null, 'draft');

        // Insert the placeholder block so the builder opens
        // with something visible. The merchant can delete it,
        // replace it, or reorder it — it is a real block.
        $blockRepo = $this->container->get(PageBlockRepository::class);
        $blockRepo->create(
            $slug,
            'rich-text',
            [
                'content' => '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1>',
            ]
        );

        $session->flash('success', 'Page "' . $title . '" created.');

        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * GET /commerce/ca-admin/pages/{slug}
     *
     * Session 2: placeholder. Shows the page slug and a
     * "builder coming soon" note. Session 3 replaces this
     * with the drag-and-drop canvas.
     *
     * @param array<string,string> $params
     */
    public function show(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $themeSlug = $this->activeThemeSlug();
        $draftManager = $this->container->get(\CorporateAid\Commerce\Services\Builder\DraftManager::class);
        // getMergedTree() returns roots with $children
        // populated. The sidebar iterates roots; a Section's
        // children render inside the Section on the
        // storefront via BlockRenderer's recursion. A future
        // commit (Tier 0.8 or the sidebar-tree UI) indents
        // children under their parent in the sidebar.
        $blocks = $draftManager->getMergedTree($slug, $themeSlug);

        // The block config modals render inline in show.php.
        // They need the category list (for category_select
        // fields) and the media library (for file fields).
        $categories   = $this->container->get(CategoryRepository::class)->listAll(false);
        $mediaLibrary = $this->mediaLibraryItems();
        
        // Render the customizer content, then wrap it in its own
        // layout (no admin shell, no sidebar).
        $view = $this->container->get(\CorporateAid\Core\View::class);
        // Whether any draft exists for this page + theme.
        // Drives the Save Draft and Discard buttons: they
        // only appear when there is something to act on.
        $hasDraft = $draftManager->hasDrafts($slug, $themeSlug);

        // When the merchant clicks a Section's "+" button, the
        // sidebar row links to ?add_under=<section_id>. That
        // query param opens the block picker with the section
        // pre-selected as the parent. Null means the picker
        // adds a root block.
        $addUnder = $request->query('add_under');
        $addUnder = is_string($addUnder) && $addUnder !== ''
            ? (int) $addUnder
            : null;

        // Load (or auto-create) the store_pages metadata row.
        // A legacy block-only page opened for the first time
        // gets a row so the merchant can edit its metadata.
        $pageRepo = $this->container->get(StorePageRepository::class);
        $pageRepo->ensure($slug, $this->labelFor($slug));
        $page = $pageRepo->find($slug);

        $inner = $view->render('admin.pages.show', [
            'title'          => $this->labelFor($slug) . ' — Editor',
            'slug'           => $slug,
            'label'          => $this->labelFor($slug),
            'blocks'         => $blocks,
            'categories'     => $categories,
            'mediaLibrary'   => $mediaLibrary,
            'hasDraft'       => $hasDraft,
            'addUnder'       => $addUnder,
            'page'           => $page,
        ]);
        $html = $view->render('layouts.customizer', [
            'title'   => $this->labelFor($slug) . ' — Editor',
            'content' => $inner,
        ]);
        return new Response($html, 200);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/delete
     *
     * Deletes every block on a page. The page disappears
     * from pageSlugs() and stops being reachable.
     *
     * @param array<string,string> $params
     */
    public function destroy(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');

        if ($slug === '' || $slug === 'home') {
            $session->flash('error', 'The homepage cannot be deleted.');
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $repo  = $this->container->get(PageBlockRepository::class);
        $blocks = $repo->listForPage($slug, false);
        foreach ($blocks as $block) {
            $repo->delete($block->id);
        }

        $session->flash('success', 'Page deleted.');
        return Response::redirect('/commerce/ca-admin/pages');
    }

    // -----------------------------------------------------------------

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks
     *
     * Add a new block to the page. The block type is the only
     * required field. The config is initialized to an empty
     * array; the merchant fills it in via the block editor
     * (Sessions 2-3).
     *
     * @param array<string,string> $params
     */
    public function addBlock(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');
        $type    = trim((string) $request->input('block_type', ''));

        if ($slug === '') {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        if (!\CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry::has($type)) {
            $session->flash('error', 'Unknown block type.');
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $themeSlug = $this->activeThemeSlug();
        $draftRepo = $this->container->get(\CorporateAid\Commerce\Repositories\PageDraftRepository::class);

        // Read the optional parent_id from POST. Empty string,
        // missing key, and null all mean 'add as root.' A real
        // id (positive for a published block, negative for a
        // pending_add draft) means 'add as child of that block.'
        $parentIdRaw = $request->input('parent_id');
        $parentId = is_string($parentIdRaw) && $parentIdRaw !== ''
            ? (int) $parentIdRaw
            : null;

        if ($parentId !== null) {
            $parent = $this->findBlockOrDraft($parentId, $slug);
            if ($parent === null) {
                $session->flash('error', 'Parent block not found.');
                return Response::redirect('/commerce/ca-admin/pages/' . $slug);
            }
            if (!\CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry::acceptsChildren($parent->blockType)) {
                $session->flash('error', 'That block does not accept children.');
                return Response::redirect('/commerce/ca-admin/pages/' . $slug);
            }
        }

        // Compute the sort within the destination parent's
        // children (roots have their own sort space). The
        // draft-aware next-sort counts pending_add siblings,
        // which the old 'count(published) + 1' approach did not.
        $nextSort = $draftRepo->nextDraftSortOrder($slug, $themeSlug, $parentId);

        $draftRepo->addDraftBlockWithConfig($slug, $themeSlug, $type, $nextSort, [], $parentId);

        $session->flash('success', 'Block added (draft). Click Publish to go live.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks/{id}/duplicate
     *
     * Insert a copy of the block immediately below (well, at the
     * bottom — sort order is bumped to the end). Works for both
     * published blocks and pending drafts.
     *
     * @param array<string,string> $params
     */
    public function duplicateBlock(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');
        $id      = (int) ($params['id'] ?? 0);

        if ($slug === '' || $id === 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $themeSlug    = $this->activeThemeSlug();
        $draftManager = $this->container->get(\CorporateAid\Commerce\Services\Builder\DraftManager::class);
        $draftManager->duplicateBlock($id, $slug, $themeSlug);

        $session->flash('success', 'Block duplicated (draft). Click Publish to go live.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks/{id}/delete
     *
     * Remove one block from the page. The block row is
     * hard-deleted. The page_slug survives as long as any
     * other block references it, or disappears from the
     * page list if this was the last one.
     *
     * @param array<string,string> $params
     */
    public function deleteBlock(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');
        $id      = (int) ($params['id'] ?? 0);

        if ($slug === '' || $id === 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $themeSlug = $this->activeThemeSlug();
        $draftRepo = $this->container->get(\CorporateAid\Commerce\Repositories\PageDraftRepository::class);
        
        if ($id < 0) {
            // Deleting a pending addition: just remove the draft row entirely
            $draftRepo->removePendingAdd(abs($id));
        } else {
            // Deleting an existing block: mark it for deletion
            $draftRepo->markDelete($id, $slug, $themeSlug);
        }

        $session->flash('success', 'Block removed (draft). Click Publish to go live.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks/reorder
     *
     * Rewrite the sort_order of every block on the page
     * according to the posted id list. The request body
     * carries `ids` as an array of block IDs in their new
     * order. The repository walks the list and assigns
     * sort_order 1, 2, 3, ...
     *
     * Returns JSON so the JS drag handler gets an immediate
     * confirmation without a page reload.
     *
     * @param array<string,string> $params
     */
    public function reorderBlocks(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::json(['ok' => false, 'error' => 'Missing page slug.'], 400);
        }

        // Phase 3: accept a tree (map of id => {parent_id, sort_order})
        // from the client. The tree includes parent-child relationships
        // and per-node sort_order. When present, saveTree() writes
        // both parent_id and sort_order.
        $tree = $request->input('tree');
        if (is_array($tree) && $tree !== []) {
            $cleanTree = [];
            foreach ($tree as $id => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $cleanTree[(int) $id] = [
                    'parent_id'  => (int) ($row['parent_id']  ?? 0),
                    'sort_order' => (int) ($row['sort_order'] ?? 1),
                ];
            }
            if ($cleanTree === []) {
                return Response::json(['ok' => false, 'error' => 'Empty tree.'], 400);
            }
            $draftRepo = $this->container->get(PageDraftRepository::class);
            $draftRepo->saveTree($slug, $this->activeThemeSlug(), $cleanTree);
            return Response::json(['ok' => true, 'count' => count($cleanTree)]);
        }

        // Fall back to the legacy flat `ids` payload. Preserved for
        // backward compatibility with older page-builder JS.
        $ids = $request->input('ids', []);
        if (!is_array($ids) || $ids === []) {
            return Response::json(['ok' => false, 'error' => 'No block ids provided.'], 400);
        }

        // Do not filter out negative IDs. A negative ID is a
        // pending_add draft row. PageDraftRepository::saveReorder()
        // knows how to resolve it (by `id` instead of `block_id`).
        // Filtering them out here meant a drag involving a newly
        // added block silently dropped that block from the order.
        $cleanIds = array_values(array_map('intval', $ids));

        if ($cleanIds === []) {
            return Response::json(['ok' => false, 'error' => 'No block ids provided.'], 400);
        }

        // Reordering is a draft edit, same as add, delete, and
        // config save. Write to the draft layer, not the published
        // table. DraftManager::getMergedBlocks() reads published
        // and overlays the draft; a reorder written only to
        // commerce_page_blocks would be overridden by any
        // existing pending_update draft row's stale sort_order,
        // which is why the preview showed the pre-reorder order
        // after a successful drag. saveReorder() handles both
        // pending_add rows (negative id) and pending_update rows
        // (creates the row if it does not exist yet). The published
        // table gets its sort_order at Publish time, when the
        // draft manager applies the drafts. Discard reverts the
        // reorder with the rest of the drafts.
        $draftRepo = $this->container->get(PageDraftRepository::class);
        $draftRepo->saveReorder($slug, $this->activeThemeSlug(), $cleanIds);

        return Response::json(['ok' => true, 'count' => count($cleanIds)]);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks/{id}/toggle
     *
     * Flip the enabled state of one block. Disabled blocks
     * keep their sort position, so re-enabling restores it.
     *
     * @param array<string,string> $params
     */
    public function toggleBlock(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');
        $id      = (int) ($params['id'] ?? 0);

        if ($slug === '' || $id <= 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $repo  = $this->container->get(PageBlockRepository::class);
        $block = $repo->findById($id);
        if ($block === null) {
            $session->flash('error', 'Block not found.');
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $repo->setEnabled($id, !$block->isEnabled);

        $session->flash('success', 'Block ' . ($block->isEnabled ? 'disabled' : 'enabled') . '.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * GET /commerce/ca-admin/pages/{slug}/blocks/{id}/config
     *
     * Render the config form for one block. Renders only the
     * fields declared in BlockConfigRegistry::schema() for the
     * block's type. Returns the modal markup directly (the
     * caller includes it via view_partial), but this action
     * exists as a fallback full-page route in case the modal
     * needs to be linked directly.
     *
     * @param array<string,string> $params
     */
    public function configBlock(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $id   = (int) ($params['id'] ?? 0);

        if ($slug === '' || $id === 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $block = $this->findBlockOrDraft($id, $slug);
        if ($block === null) {
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $categories   = $this->container->get(CategoryRepository::class)->listAll(false);
        $mediaLibrary = $this->mediaLibraryItems();

        return $this->render('admin.pages.block-config', [
            'title'       => 'Configure ' . $block->typeLabel(),
            'slug'        => $slug,
            'block'       => $block,
            'categories'  => $categories,
            'mediaLibrary' => $mediaLibrary,
        ]);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks/{id}/config
     *
     * Validate the posted fields against the block type's
     * schema, coerce each to its declared type, and save to
     * config_json via PageBlockRepository::update().
     *
     * Fields not declared in the schema are ignored. Required
     * fields missing from the POST flash an error and redirect
     * back to the page editor.
     *
     * @param array<string,string> $params
     */
    public function saveBlockConfig(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');
        $id      = (int) ($params['id'] ?? 0);

        if ($slug === '' || $id === 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $block = $this->findBlockOrDraft($id, $slug);
        if ($block === null) {
            $session->flash('error', 'Block not found.');
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $schema = BlockConfigRegistry::schema($block->blockType);
        if ($schema === []) {
            $session->flash('error', 'This block type has no configuration fields.');
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $config = [];
        $errors = [];
        $posted = $request->input('config');
        if (!is_array($posted)) {
            $posted = [];
        }

        foreach ($schema as $name => $meta) {
            $type    = (string) ($meta['type'] ?? 'string');
            $required = !empty($meta['required']);
            $raw     = $posted[$name] ?? null;

            // Boolean and category_multi_select short-circuit
            // before the required/empty check. A boolean checkbox
            // posted unchecked is null; the field must resolve to
            // false, not the schema default. An empty multi-select
            // must resolve to [], not the default. Every other
            // type falls through to the required/empty check.
            //
            // The sanitisation operation itself lives in
            // ConfigFieldRegistry. The two short-circuits stay
            // here because their ORDER (before the required/empty
            // check) is the contract. The registry reproduces the
            // operation, this loop reproduces the order.
            $definition = \CorporateAid\Commerce\Support\Blocks\ConfigFieldRegistry::get($type);

            if ($type === 'boolean' || $type === 'category_multi_select') {
                if ($definition !== null) {
                    $config[$name] = ($definition['sanitize'])($raw, $meta);
                }
                continue;
            }

            if ($raw === null || $raw === '') {
                if ($required) {
                    $errors[] = ($meta['label'] ?? $name) . ' is required.';
                }
                $config[$name] = $meta['default'] ?? null;
                continue;
            }

            if ($definition !== null) {
                $config[$name] = ($definition['sanitize'])($raw, $meta);
                continue;
            }

            // Unknown type: preserve raw. This should not happen
            // for a schema-declared field, but the fail-safe
            // matches the prior default arm.
            $config[$name] = $raw;
        }

        if ($errors !== []) {
            $session->flash('error', implode(' ', $errors));
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        // Save to the database draft table.
        $themeSlug = $this->activeThemeSlug();
        $draftRepo = $this->container->get(\CorporateAid\Commerce\Repositories\PageDraftRepository::class);
        
        if ($id < 0) {
            // This is a pending_add block. Update its config directly.
            $draftRepo->updatePendingAdd(abs($id), $config);
        } else {
            // This is an existing published block. Mark for update.
            $draftRepo->saveUpdate($id, $slug, $themeSlug, $config);
        }

        if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
            return Response::json(['ok' => true]);
        }

        $session->flash('success', 'Settings updated (Draft). Click Publish to go live.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * Resolve the store's media library into MediaItem instances
     * for the block config modal's image picker. Capped at 200
     * files - the picker is a grid; 200 tiles is enough to find
     * most images without a search UI. A future pass can add
     * pagination.
     *
     * @return array<int, \CorporateAid\Commerce\Models\MediaItem>
     */
    private function mediaLibraryItems(): array
    {
        $ctx = $this->container->get(\CorporateAid\Commerce\Context\StoreContext::class);
        $media = $this->container->get(\CorporateAid\Commerce\Repositories\MediaRepository::class);
        // 40 recent images is enough for the picker. The
        // "Upload new image" button inside the modal covers
        // anything older. paginateForStore caps at 100, so 200
        // silently became 100 — which fired 100 thumbnail
        // requests on every admin page load.
        $result = $media->paginateForStore($ctx->id(), [], 1, 40);
        return $result['items'];
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/blocks/{id}/preview
     *
     * Renders a single block's HTML given its current config.
     * Used by the live editor to update the right-hand pane
     * without reloading the whole page.
     *
     * @param array<string,string> $params
     */
    public function previewBlock(Request $request, array $params = []): Response
    {
        $id   = (int) ($params['id'] ?? 0);
        $slug = (string) ($params['slug'] ?? '');

        if ($id === 0) {
            return Response::json(['error' => 'Invalid block'], 400);
        }

        $block = $this->findBlockOrDraft($id, $slug);
        if ($block === null) {
            return Response::json(['error' => 'Block not found'], 404);
        }

        // Build the config the preview should render:
        //   1. Start with the DB config.
        //   2. Overlay any session draft for this block.
        //   3. Overlay any live-typed fields from the current POST.
        // That order means the preview matches what Publish would
        // produce if the merchant hit the button right now.
        $slug      = (string) ($params['slug'] ?? '');
        $themeSlug = $this->activeThemeSlug();
        $config    = $block->config;

        if (isset($_SESSION['builder_draft'][$themeSlug][$slug][$id])) {
            $config = array_merge($config, $_SESSION['builder_draft'][$themeSlug][$slug][$id]);
        }

        $postedConfig = $request->input('config');
        if (is_array($postedConfig)) {
            $config = array_merge($config, $postedConfig);
        }

        $block->config = $config;
        $html = $this->renderBlockPartial($block);

        return Response::json(['html' => $html]);
    }

    private function renderBlockPartial(PageBlock $block): string
    {
        return $this->container->get(
            \CorporateAid\Commerce\Services\Builder\BlockRenderer::class
        )->render($block);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/publish
     *
     * Applies the session draft to the database.
     */
    /**
     * POST /commerce/ca-admin/pages/{slug}/save-draft
     *
     * The session draft is already being kept up to date by the
     * JS on every keystroke (saveBlockConfig). This action exists
     * purely to give the merchant explicit visual confirmation
     * that their work is safe without publishing to the live site.
     *
     * @param array<string,string> $params
     */
    public function saveDraft(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $themeSlug    = $this->activeThemeSlug();
        $draftManager = $this->container->get(\CorporateAid\Commerce\Services\Builder\DraftManager::class);
        $hasDraft     = $draftManager->hasDrafts($slug, $themeSlug);

        $session = $this->container->get(Session::class);
        if ($hasDraft) {
            $session->flash('success', 'Draft saved. You can safely close this page.');
        } else {
            $session->flash('warning', 'No changes to save yet. Edit a block first.');
        }
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/discard-draft
     *
     * Drops the session draft for this page + theme. The live
     * storefront is unchanged. The next time the page loads in
     * the customizer, the form shows the saved DB values.
     *
     * @param array<string,string> $params
     */
    public function discardDraft(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $themeSlug = $this->activeThemeSlug();
        $draftRepo = $this->container->get(\CorporateAid\Commerce\Repositories\PageDraftRepository::class);
        $draftRepo->clearDrafts($slug, $themeSlug);

        $session = $this->container->get(Session::class);
        $session->flash('success', 'Draft discarded. The live site was never changed.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /** @param array<string,string> $params */
    public function publish(Request $request, array $params = []): Response
    {
        $slug      = (string) ($params['slug'] ?? '');
        $themeSlug = $this->activeThemeSlug();

        $publisher = $this->container->get(\CorporateAid\Commerce\Services\Builder\DraftPublisher::class);
        $publisher->publish($slug, $themeSlug);

        $session = $this->container->get(Session::class);
        $session->flash('success', 'Page published successfully.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * GET /commerce/ca-admin/pages/{slug}/preview
     *
     * Renders the full page inside an iframe using draft data.
     */
    /** @param array<string,string> $params */
    public function previewIframe(Request $request, array $params = []): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::html('Not found', 404);
        }

        $themeSlug = $this->activeThemeSlug();
        $repo   = $this->container->get(PageBlockRepository::class);
        $blocks = $repo->listForPage($slug, false);
        $draft  = $_SESSION['builder_draft'][$themeSlug][$slug] ?? [];

        // Apply draft configs to the blocks
        foreach ($blocks as $block) {
            if (isset($draft[$block->id])) {
                $block->config = array_merge($block->config, $draft[$block->id]);
            }
        }

        // Render a barebones HTML shell for the iframe
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<link rel="stylesheet" href="/assets/css/main.css">';
        $html .= '<style>body { margin: 0; padding: 0; } .sf-header { display: none; }</style>';
        $html .= '</head><body>';

        foreach ($blocks as $block) {
            $html .= $this->renderBlockPartial($block);
        }

        $html .= '<script src="/assets/js/preview-bridge.js"></script></body></html>';

        return Response::html($html);
    }

    /**
     * Fetch a block by ID, transparently handling both
     * published blocks (positive IDs, from commerce_page_blocks)
     * and pending draft additions (negative IDs, from
     * commerce_page_drafts). Used by saveBlockConfig,
     * configBlock, and previewBlock so the negative-ID
     * branch doesn't have to be repeated in three places.
     */
    private function findBlockOrDraft(int $id, string $slug): ?\CorporateAid\Commerce\Models\PageBlock
    {
        if ($id === 0) {
            return null;
        }

        if ($id < 0) {
            $draftRepo = $this->container->get(\CorporateAid\Commerce\Repositories\PageDraftRepository::class);
            $drafts    = $draftRepo->getDraftsForPage($slug, $this->activeThemeSlug());
            foreach ($drafts as $draft) {
                if ((int) $draft['id'] === abs($id) && $draft['status'] === 'pending_add') {
                    $block = new \CorporateAid\Commerce\Models\PageBlock();
                    $block->id        = $id;
                    $block->pageSlug  = $slug;
                    $block->blockType = (string) $draft['block_type'];
                    $block->config    = json_decode((string) $draft['config_json'], true) ?: [];
                    $block->sortOrder = (int) $draft['sort_order'];
                    $block->isEnabled = true;
                    return $block;
                }
            }
            return null;
        }

        return $this->container->get(PageBlockRepository::class)->findById($id);
    }

    /**
     * The store's active theme slug, with 'modern' as the
     * fallback. Used to namespace session drafts so switching
     * themes doesn't collide drafts from a different theme.
     */
    private function activeThemeSlug(): string
    {
        $ctx = $this->container->get(\CorporateAid\Commerce\Context\StoreContext::class);
        $storeRepo = $this->container->get(\CorporateAid\Commerce\Repositories\StoreRepository::class);
        $store = $storeRepo->findById($ctx->id());
        return $store !== null && $store->themeSlug !== ''
            ? $store->themeSlug
            : 'modern';
    }

    /**
     * A human label for a page_slug. 'home' becomes 'Homepage'.
     * Other slugs are title-cased with hyphens replaced.
     */
    private function labelFor(string $slug): string
    {
        $pageRepo = $this->container->get(StorePageRepository::class);
        $page = $pageRepo->find($slug);
        if ($page !== null && !empty($page['title'])) {
            return (string) $page['title'];
        }
        if ($slug === 'home') {
            return 'Homepage';
        }
        $words = preg_split('/[-_]+/', $slug) ?: [$slug];
        return implode(' ', array_map('ucfirst', $words));
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/settings
     *
     * Save page-level metadata (title, meta description, OG
     * image, noindex, status). The blocks are saved separately.
     *
     * @param array<string,string> $params
     */
    public function savePageSettings(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');

        if ($slug === '') {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $pageRepo = $this->container->get(StorePageRepository::class);
        if (!$pageRepo->exists($slug)) {
            $session->flash('error', 'Page not found.');
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $session->flash('error', 'Title is required.');
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $metaDescription = trim((string) $request->input('meta_description', ''));
        $ogImageId       = $request->input('og_image_file_id', null);
        $noindex         = $request->input('noindex', null) !== null;
        $status          = (string) $request->input('status', 'draft');

        if (!in_array($status, ['draft', 'published', 'archived'], true)) {
            $status = 'draft';
        }

        $pageRepo->update($slug, [
            'title'            => $title,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'og_image_file_id' => $ogImageId !== null && $ogImageId !== '' ? (int) $ogImageId : null,
            'noindex'          => $noindex ? 1 : 0,
            'status'           => $status,
        ]);

        $session->flash('success', 'Page settings saved.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /**
     * POST /commerce/ca-admin/pages/{slug}/delete-page
     *
     * Hard delete: removes the metadata row and every block on
     * the page. The homepage is protected.
     *
     * @param array<string,string> $params
     */
    public function deletePage(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $slug    = (string) ($params['slug'] ?? '');

        if ($slug === '' || $slug === 'home') {
            $session->flash('error', 'The homepage cannot be deleted.');
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $pageRepo  = $this->container->get(StorePageRepository::class);
        $blockRepo = $this->container->get(PageBlockRepository::class);

        // Delete blocks first (no FK cascade from blocks to
        // pages in this schema), then the page row.
        $blockRepo->deleteAllForPage($slug);
        $pageRepo->delete($slug);

        $session->flash('success', 'Page deleted.');
        return Response::redirect('/commerce/ca-admin/pages');
    }
}
