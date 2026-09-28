<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Models\LegalPage;
use CorporateAid\Commerce\Repositories\LegalPageRepository;

/**
 * Legal pages admin.
 *
 * Routes (all under /commerce/ca-admin/legal-pages):
 *   GET  /                 list
 *   GET  /{slug}/edit      edit form
 *   POST /{slug}           update
 *
 * The four slugs (impressum, agb, widerruf, datenschutz) are
 * fixed by the checkout compliance gate. This controller does
 * not create or delete pages - it only edits the ones that
 * already exist (from the 0007 seeder) plus any custom pages a
 * future stage adds.
 *
 * See docs/stages/008e.1_german-compliance-plan.md.
 */
final class LegalPageController extends AdminController
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(LegalPageRepository::class);
        $pages = $repo->listAll();

        return $this->render('admin.legal_pages.index', [
            'title' => 'Legal Pages',
            'pages' => $pages,
        ]);
    }

    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(LegalPageRepository::class);
        $slug = (string) ($params['slug'] ?? '');

        $page = $repo->findBySlugIncludingUnpublished($slug);
        if ($page === null) {
            return Response::redirect('/commerce/ca-admin/legal-pages');
        }

        return $this->render('admin.legal_pages.edit', [
            'title' => 'Edit ' . $page->title,
            'page'  => $page,
        ]);
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $repo    = $this->container->get(LegalPageRepository::class);
        $auth    = $this->container->get(Auth::class);
        $session = $this->container->get(Session::class);

        $slug = (string) ($params['slug'] ?? '');

        $page = $repo->findBySlugIncludingUnpublished($slug);
        if ($page === null) {
            $session->flash('error', 'Legal page not found.');
            return Response::redirect('/commerce/ca-admin/legal-pages');
        }

        $title = trim((string) $request->input('title', ''));
        $body  = (string) $request->input('body', '');
        $isPublished = (bool) $request->input('is_published', false);

        if ($title === '') {
            $session->flash('error', 'Title is required.');
            return Response::redirect('/commerce/ca-admin/legal-pages/' . urlencode($slug) . '/edit');
        }

        $userId = $auth->user()?->id ?? null;

        $repo->updateContent(
            $slug,
            $title,
            $body,
            $isPublished,
            $userId
        );

        $session->flash('success', 'Legal page updated.');
        return Response::redirect('/commerce/ca-admin/legal-pages');
    }
}
