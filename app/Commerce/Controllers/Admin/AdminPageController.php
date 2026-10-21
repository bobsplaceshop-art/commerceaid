<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\PageBlockRepository;
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
        $repo = $this->container->get(PageBlockRepository::class);

        $pageSlugs = $repo->pageSlugs();

        // Always show 'home' even before it has any blocks.
        // The page list is the entry point for the builder.
        if (!in_array('home', $pageSlugs, true)) {
            array_unshift($pageSlugs, 'home');
        }

        // Detect which pages have an unpublished draft under the
        // store's active theme. Each draft is a Changeset stored
        // in the session; the Pages list shows a "Draft" badge so
        // the merchant knows to open the page and either Publish
        // or Discard.
        $themeSlug = $this->activeThemeSlug();
        $allDrafts = $_SESSION['builder_draft'][$themeSlug] ?? [];

        $pages = [];
        foreach ($pageSlugs as $slug) {
            $hasDraft = !empty($allDrafts[$slug]);
            $pages[] = [
                'slug'       => $slug,
                'label'      => $this->labelFor($slug),
                'blockCount' => $repo->countForPage($slug),
                'isHome'     => $slug === 'home',
                'hasDraft'   => $hasDraft,
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

        // Persist the page by creating a marker block? No —
        // pages are identified by the block rows that reference
        // them. An empty page is a valid state, so we create
        // a placeholder 'rich-text' block with the page title
        // so the page_slug appears in pageSlugs() and the
        // merchant sees it in the list. This block is editable
        // and deletable. When Session 3 adds the builder, the
        // merchant can replace it with real content.
        $repo = $this->container->get(PageBlockRepository::class);
        $repo->create(
            $slug,
            'rich-text',
            [
                'html'      => '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1>',
                'max_width' => 'narrow',
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

        $repo = $this->container->get(PageBlockRepository::class);
        $blocks = $repo->listForPage($slug, false);

        // Merge the theme-scoped session draft into each block so
        // the sidebar form reflects unsaved changes. Without this,
        // typing "8" in a field saves to the session but the page
        // reload re-renders the DB value "4".
        $themeSlug = $this->activeThemeSlug();
        $draft     = $_SESSION['builder_draft'][$themeSlug][$slug] ?? [];
        foreach ($blocks as $block) {
            if (isset($draft[$block->id])) {
                $block->config = array_merge($block->config, $draft[$block->id]);
            }
        }

        // The block config modals render inline in show.php.
        // They need the category list (for category_select
        // fields) and the media library (for file fields).
        $categories   = $this->container->get(CategoryRepository::class)->listAll(false);
        $mediaLibrary = $this->mediaLibraryItems();
        
        // Render the customizer content, then wrap it in its own
        // layout (no admin shell, no sidebar).
        $view = $this->container->get(\CorporateAid\Core\View::class);
        // Whether a session draft exists for this page + theme.
        // Drives the Save Draft and Discard buttons: they only
        // appear when there is something to act on.
        $themeSlugForView = $this->activeThemeSlug();
        $hasDraft = !empty($_SESSION['builder_draft'][$themeSlugForView][$slug]);

        $inner = $view->render('admin.pages.show', [
            'title'          => $this->labelFor($slug) . ' — Editor',
            'slug'           => $slug,
            'label'          => $this->labelFor($slug),
            'blocks'         => $blocks,
            'categories'     => $categories,
            'mediaLibrary'   => $mediaLibrary,
            'hasDraft'       => $hasDraft,
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

        $allowedTypes = [
            'hero', 'product-grid', 'category-grid',
            'promo-panel', 'banner', 'rich-text', 'newsletter',
        ];
        if (!in_array($type, $allowedTypes, true)) {
            $session->flash('error', 'Unknown block type.');
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        $repo = $this->container->get(PageBlockRepository::class);
        $repo->create($slug, $type, []);

        $session->flash('success', 'Block added.');
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

        if ($slug === '' || $id <= 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $repo = $this->container->get(PageBlockRepository::class);
        $repo->delete($id);

        $session->flash('success', 'Block removed.');
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

        $ids = $request->input('ids', []);
        if (!is_array($ids) || $ids === []) {
            return Response::json(['ok' => false, 'error' => 'No block ids provided.'], 400);
        }

        $cleanIds = array_values(array_filter(
            array_map('intval', $ids),
            static fn (int $v): bool => $v > 0
        ));

        if ($cleanIds === []) {
            return Response::json(['ok' => false, 'error' => 'No valid block ids.'], 400);
        }

        $repo = $this->container->get(PageBlockRepository::class);
        $repo->reorder($slug, $cleanIds);

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

        if ($slug === '' || $id <= 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $repo  = $this->container->get(PageBlockRepository::class);
        $block = $repo->findById($id);
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

        if ($slug === '' || $id <= 0) {
            return Response::redirect('/commerce/ca-admin/pages');
        }

        $repo  = $this->container->get(PageBlockRepository::class);
        $block = $repo->findById($id);
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

            // Checkbox absent = unchecked. Everything else:
            // missing = use the default.
            if ($type === 'boolean') {
                $config[$name] = ($raw === '1' || $raw === 'on' || $raw === true);
                continue;
            }

            if ($raw === null || $raw === '') {
                if ($required) {
                    $errors[] = ($meta['label'] ?? $name) . ' is required.';
                }
                $config[$name] = $meta['default'] ?? null;
                continue;
            }

            $config[$name] = match ($type) {
                'int'   => (int) $raw,
                'file'  => is_numeric($raw) ? (int) $raw : null,
                default => trim((string) $raw),
            };
        }

        if ($errors !== []) {
            $session->flash('error', implode(' ', $errors));
            return Response::redirect('/commerce/ca-admin/pages/' . $slug);
        }

        // Save to the "Changeset" (Session draft), scoped by
        // the store's active theme, so switching themes keeps
        // each theme's draft separate.
        $themeSlug = $this->activeThemeSlug();
        $draft = $_SESSION['builder_draft'][$themeSlug][$slug][$id] ?? [];
        $_SESSION['builder_draft'][$themeSlug][$slug][$id] = array_merge($draft, $config);

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
        $result = $media->paginateForStore($ctx->id(), [], 1, 200);
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
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            return Response::json(['error' => 'Invalid block'], 400);
        }

        $repo  = $this->container->get(PageBlockRepository::class);
        $block = $repo->findById($id);
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

        $html = $this->renderBlockPartial($block->blockType, $config);

        return Response::json(['html' => $html]);
    }

    /**
     * @param array<string,mixed> $config
     */
    private function renderBlockPartial(string $type, array $config): string
    {
        $viewData = ['config' => $config];

        // Fetch the data the block needs to render
        if ($type === 'product-grid') {
            $productRepo = $this->container->get(ProductRepository::class);
            // Simplified fetch for preview: 8 newest products
            $result = $productRepo->paginatePublished([], 1, 8);
            $viewData['products'] = $result['items'];
        } elseif ($type === 'category-grid') {
            $catRepo = $this->container->get(CategoryRepository::class);
            $viewData['categories'] = $catRepo->listAll(false);
        }

        // Render the partial into a string
        try {
            return view_partial('storefront/blocks/' . $type, $viewData);
        } catch (\Throwable $e) {
            return '<div style="padding:2rem;text-align:center;color:red;">Error rendering block: ' . e($e->getMessage()) . '</div>';
        }
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

        $themeSlug = $this->activeThemeSlug();
        $hasDraft  = !empty($_SESSION['builder_draft'][$themeSlug][$slug]);

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
        unset($_SESSION['builder_draft'][$themeSlug][$slug]);

        $session = $this->container->get(Session::class);
        $session->flash('success', 'Draft discarded. The live site was never changed.');
        return Response::redirect('/commerce/ca-admin/pages/' . $slug);
    }

    /** @param array<string,string> $params */
    public function publish(Request $request, array $params = []): Response
    {
        $slug      = (string) ($params['slug'] ?? '');
        $themeSlug = $this->activeThemeSlug();
        $draft     = $_SESSION['builder_draft'][$themeSlug][$slug] ?? [];
        $repo      = $this->container->get(PageBlockRepository::class);

        foreach ($draft as $blockId => $blockDraft) {
            // Merge the draft onto the block's existing config so
            // we never overwrite fields the user did not touch.
            // update() replaces config_json wholesale; a draft
            // containing only ['columns' => 8] would otherwise
            // wipe out title, source, limit, etc.
            $existing = $repo->findById($blockId);
            if ($existing === null) {
                continue;
            }
            $merged = array_merge($existing->config, $blockDraft);
            $repo->update($blockId, $merged);
        }

        // Clear the draft for this theme + page only
        unset($_SESSION['builder_draft'][$themeSlug][$slug]);

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
            $html .= $this->renderBlockPartial($block->blockType, $block->config);
        }

        $html .= '<script src="/assets/js/preview-bridge.js"></script></body></html>';

        return Response::html($html);
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
        if ($slug === 'home') {
            return 'Homepage';
        }
        $words = preg_split('/[-_]+/', $slug) ?: [$slug];
        return implode(' ', array_map('ucfirst', $words));
    }
}
