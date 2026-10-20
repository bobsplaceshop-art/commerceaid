<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Repositories\PageBlockRepository;

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

        $pages = [];
        foreach ($pageSlugs as $slug) {
            $pages[] = [
                'slug'       => $slug,
                'label'      => $this->labelFor($slug),
                'blockCount' => $repo->countForPage($slug),
                'isHome'     => $slug === 'home',
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

        return $this->render('admin.pages.show', [
            'title'  => $this->labelFor($slug) . ' — Editor',
            'slug'   => $slug,
            'label'  => $this->labelFor($slug),
            'blocks' => $blocks,
        ]);
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
