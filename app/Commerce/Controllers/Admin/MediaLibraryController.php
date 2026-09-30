<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;

/**
 * Media library page.
 *
 * The browse and manage surface for uploaded files. Files
 * are scoped to the current store via core_entity_files.
 *
 * Routes:
 *   GET  /commerce/ca-admin/media              index
 *   POST /commerce/ca-admin/media/{id}/delete  delete
 *   POST /commerce/ca-admin/media/{id}/meta    updateMeta
 *
 * The delete action checks usage first. A file referenced
 * by any entity cannot be deleted here. The operator must
 * unlink it from the referencing entity first.
 *
 * See docs/stages/008g.4a.1_media-library-plan.md.
 */
final class MediaLibraryController extends AdminController
{
    /**
     * Filters and pagination from the query string.
     *
     * @var array<int, string>
     */
    private const SORT_OPTIONS = ['newest', 'oldest', 'name', 'size'];

    // =================================================================
    // Show
    // =================================================================

    /**
     * The grid page.
     *
     * @param array<string,string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        $storeContext = $this->container->get(StoreContext::class);
        if (!$storeContext->has()) {
            return Response::redirect('/commerce/ca-admin');
        }
        $storeId = $storeContext->id();

        $filters = [
            'q'        => trim((string) $request->query('q', '')),
            'mime'     => trim((string) $request->query('mime', '')),
            'category' => trim((string) $request->query('category', '')),
            'sort'     => (string) $request->query('sort', 'newest'),
        ];
        if (!in_array($filters['sort'], self::SORT_OPTIONS, true)) {
            $filters['sort'] = 'newest';
        }

        $page = max(1, (int) $request->query('page', 1));

        $media   = $this->container->get(MediaRepository::class);
        $session = $this->container->get(Session::class);

        $result       = $media->paginateForStore($storeId, $filters, $page, 24);
        $categories   = $media->categoriesForStore($storeId);

        return $this->render('admin.media.index', [
            'title'      => 'Media Library',
            'result'     => $result,
            'filters'    => $filters,
            'categories' => $categories,
            'sortOptions' => self::SORT_OPTIONS,
            'flashOk'    => $session->getFlash('media_success'),
            'flashErr'   => $session->getFlash('media_error'),
        ]);
    }

    // =================================================================
    // Mutations
    // =================================================================

    /**
     * Soft-delete a file, guarded by usage.
     *
     * @param array<string,string> $params
     */
    public function delete(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $back    = $this->safeBackUrl($request);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $session->flash('media_error', 'Missing file ID.');
            return Response::redirect($back);
        }

        $media = $this->container->get(MediaRepository::class);

        // Usage check. A file referenced by any entity is not
        // deletable here.
        $usage = $media->usageCountForFile($id);
        if ($usage > 0) {
            $refs = $media->findUsageForFile($id);
            $parts = [];
            foreach ($refs as $ref) {
                $parts[] = $ref['entity_type'] . '#' . $ref['entity_id']
                    . ' (' . $ref['file_category'] . ')';
            }
            $session->flash(
                'media_error',
                'This file is in use: ' . implode(', ', $parts)
                . '. Unlink it from those places first.'
            );
            return Response::redirect($back);
        }

        $media->softDelete($id);
        $session->flash('media_success', 'File deleted.');
        return Response::redirect($back);
    }

    /**
     * Update alt text and credit on a file.
     *
     * @param array<string,string> $params
     */
    public function updateMeta(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $back    = $this->safeBackUrl($request);

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $session->flash('media_error', 'Missing file ID.');
            return Response::redirect($back);
        }

        $altText = trim((string) $request->input('alt_text', ''));
        $credit  = trim((string) $request->input('credit', ''));

        $media = $this->container->get(MediaRepository::class);

        // The file must belong to the current store.
        $item = $media->findById($id);
        if ($item === null) {
            $session->flash('media_error', 'File not found.');
            return Response::redirect($back);
        }

        $media->updateMetaText(
            $id,
            $altText !== '' ? $altText : null,
            $credit !== ''  ? $credit  : null
        );

        $session->flash('media_success', 'File metadata updated.');
        return Response::redirect($back);
    }

    // =================================================================
    // Internals
    // =================================================================

    /**
     * Redirect back to the media page, preserving the current
     * filter query string. Falls back to the bare page.
     */
    private function safeBackUrl(Request $request): string
    {
        $referer = (string) $request->header('Referer', '');
        if ($referer !== '') {
            $path = parse_url($referer, PHP_URL_PATH);
            if (is_string($path) && str_starts_with($path, '/commerce/ca-admin/media')) {
                $query = parse_url($referer, PHP_URL_QUERY);
                return $path . ($query !== null && $query !== '' ? '?' . $query : '');
            }
        }
        return '/commerce/ca-admin/media';
    }
}
