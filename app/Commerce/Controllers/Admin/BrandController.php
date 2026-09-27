<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Session;
use CorporateAid\Commerce\Models\Brand;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Support\Validator;

/**
 * Brand admin CRUD.
 *
 * Routes (all under /commerce/ca-admin/brands):
 *   GET    /                    index
 *   GET    /create              show create form
 *   POST   /                    store new brand
 *   GET    /{id}/edit           show edit form
 *   POST   /{id}                update existing
 *   POST   /{id}/archive        mark archived
 *   POST   /{id}/restore        restore to active
 *   POST   /{id}/delete         soft-delete
 *   GET    /{id}/delete-info    JSON for confirm modal
 */
final class BrandController extends AdminController
{
    // =================================================================
    // Reads
    // =================================================================

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(BrandRepository::class);

        $includeArchived = (bool) $request->query('archived', false);
        $brands          = $repo->listAll($includeArchived);
        $totalActive     = $repo->count();

        return $this->render('admin.brands.index', [
            'title'           => 'Brands',
            'brands'          => $brands,
            'includeArchived' => $includeArchived,
            'totalActive'     => $totalActive,
        ]);
    }

    /** @param array<string,string> $params */
    public function showCreate(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);

        return $this->render('admin.brands.edit', [
            'title'  => 'New brand',
            'brand'  => null,
            'errors' => $session->getFlash('brand_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
            'mode'   => 'create',
        ]);
    }


    /** @param array<string,string> $params */
    public function showEdit(Request $request, array $params = []): Response
    {
        $repo  = $this->container->get(BrandRepository::class);
        $id    = (int) ($params['id'] ?? 0);
        $brand = $repo->findById($id);

        if ($brand === null) {
            return Response::redirect('/commerce/ca-admin/brands');
        }

        $session = $this->container->get(Session::class);

        return $this->render('admin.brands.edit', [
            'title'  => 'Edit: ' . $brand->name,
            'brand'  => $brand,
            'errors' => $session->getFlash('brand_errors', []),
            'old'    => $_SESSION['_old'] ?? [],
            'mode'   => 'edit',
        ]);
    }

    // =================================================================
    // Writes
    // =================================================================

    /** @param array<string,string> $params */
    public function store(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(BrandRepository::class);

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, null, $repo);

        if ($errors !== []) {
            $session->flash('brand_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/brands/create');
        }

        $brand = new Brand();
        $brand->slug           = $data['slug'];
        $brand->name           = $data['name'];
        $brand->description    = $data['description'];
        $brand->websiteUrl     = $data['website_url'];
        $brand->sortOrder      = $data['sort_order'];
        $brand->seoTitle       = $data['seo_title'];
        $brand->seoDescription = $data['seo_description'];
        $brand->seoKeywords    = $data['seo_keywords'];
        $brand->status         = 'active';

        $repo->create($brand);

        $session->flash('success', 'Brand created.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/brands');
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params = []): Response
    {
        $session = $this->container->get(Session::class);
        $repo    = $this->container->get(BrandRepository::class);
        $id      = (int) ($params['id'] ?? 0);

        $brand = $repo->findById($id);
        if ($brand === null) {
            return Response::redirect('/commerce/ca-admin/brands');
        }

        $data   = $this->collectInput($request);
        $errors = $this->validate($data, $id, $repo);

        if ($errors !== []) {
            $session->flash('brand_errors', $errors);
            $session->flashOldInput($data);
            return Response::redirect('/commerce/ca-admin/brands/' . $id . '/edit');
        }

        $brand->slug           = $data['slug'];
        $brand->name           = $data['name'];
        $brand->description    = $data['description'];
        $brand->websiteUrl     = $data['website_url'];
        $brand->sortOrder      = $data['sort_order'];
        $brand->seoTitle       = $data['seo_title'];
        $brand->seoDescription = $data['seo_description'];
        $brand->seoKeywords    = $data['seo_keywords'];

        $repo->update($brand);

        $session->flash('success', 'Brand updated.');
        $session->clearOldInput();
        return Response::redirect('/commerce/ca-admin/brands');
    }

    /** @param array<string,string> $params */
    public function archive(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(BrandRepository::class);
        $repo->archive((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Brand archived.');
        return Response::redirect('/commerce/ca-admin/brands');
    }

    /** @param array<string,string> $params */
    public function restore(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(BrandRepository::class);
        $repo->restore((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Brand restored.');
        return Response::redirect('/commerce/ca-admin/brands?archived=1');
    }

    /** @param array<string,string> $params */
    public function destroy(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(BrandRepository::class);
        $repo->softDelete((int) $params['id']);
        $this->container->get(Session::class)->flash('success', 'Brand deleted.');
        return Response::redirect('/commerce/ca-admin/brands');
    }

    /**
     * JSON endpoint for the delete-confirm modal.
     */
    /** @param array<string,string> $params */
    public function deleteInfo(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(BrandRepository::class);
        $id   = (int) $params['id'];

        $brand = $repo->findById($id);
        if ($brand === null) {
            return Response::json(['error' => 'Brand not found'], 404);
        }

        return Response::json([
            'name'          => $brand->name,
            'product_count' => $repo->productCount($id),
            'has_logo'      => $brand->hasLogo(),
        ]);
    }

    // =================================================================
    // Helpers
    // =================================================================

    /** @return array<string,mixed> */
    private function collectInput(Request $request): array
    {
        $slug = (string) $request->input('slug', '');
        if ($slug === '') {
            $slug = $this->slugify((string) $request->input('name', ''));
        }

        $website = trim((string) $request->input('website_url', ''));

        return [
            'name'            => trim((string) $request->input('name', '')),
            'slug'            => strtolower(trim($slug)),
            'description'     => trim((string) $request->input('description', '')) ?: null,
            'website_url'     => $website !== '' ? $website : null,
            'sort_order'      => max(0, (int) $request->input('sort_order', 0)),
            'seo_title'       => trim((string) $request->input('seo_title', '')) ?: null,
            'seo_description' => trim((string) $request->input('seo_description', '')) ?: null,
            'seo_keywords'    => trim((string) $request->input('seo_keywords', '')) ?: null,
        ];
    }

    /** @return array<string,string> */
    /**
     * @param array<string,mixed> $data
     * @return array<string,string>
     */
    private function validate(array $data, ?int $excludeId, BrandRepository $repo): array
    {
        $errors = [];

        if ($e = Validator::required($data['name'], 'Name')) {
            $errors['name'] = $e;
        } elseif ($e = Validator::maxLength($data['name'], 200, 'Name')) {
            $errors['name'] = $e;
        }

        if ($data['slug'] === '') {
            $errors['slug'] = 'Slug is required.';
        } elseif ($e = Validator::slug($data['slug'], 120, 'Slug')) {
            $errors['slug'] = $e;
        } elseif ($repo->slugExists($data['slug'], $excludeId)) {
            $errors['slug'] = 'This slug is already used.';
        }

        if ($data['website_url'] !== null) {
            if ($e = Validator::url($data['website_url'], 'Website')) {
                $errors['website_url'] = $e;
            } elseif ($e = Validator::maxLength($data['website_url'], 500, 'Website')) {
                $errors['website_url'] = $e;
            }
        }

        if ($data['description'] !== null
            && ($e = Validator::maxLength($data['description'], 5000, 'Description'))) {
            $errors['description'] = $e;
        }

        if ($data['seo_title'] !== null
            && ($e = Validator::maxLength($data['seo_title'], 200, 'SEO title'))) {
            $errors['seo_title'] = $e;
        }

        if ($data['seo_description'] !== null
            && ($e = Validator::maxLength($data['seo_description'], 300, 'Meta description'))) {
            $errors['seo_description'] = $e;
        }

        if ($data['seo_keywords'] !== null
            && ($e = Validator::maxLength($data['seo_keywords'], 300, 'Keywords'))) {
            $errors['seo_keywords'] = $e;
        }

        return $errors;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}
