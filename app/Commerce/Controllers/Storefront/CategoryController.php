<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Public category browsing.
 *
 * Routes:
 *   GET /categories             index of top-level categories
 *   GET /categories/{slug}      product listing filtered by category
 *
 * The index shows top-level categories as cards. The detail page
 * shows the category's products and, if it has children, a chip
 * strip of subcategories above the grid.
 *
 * The detail page reuses paginatePublished() with a category_id
 * filter. No separate query method is needed.
 */
final class CategoryController extends StorefrontController
{
    private const PER_PAGE = 24;

    // -----------------------------------------------------------------
    // GET /categories
    // -----------------------------------------------------------------

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CategoryRepository::class);

        // Fetch all active categories, then filter to top-level for
        // the top-level grid. Child categories are shown on their
        // parent's page.
        $all = $repo->listAll();
        $topLevel = [];
        foreach ($all as $c) {
            if ($c->parentId === null) {
                $topLevel[] = $c;
            }
        }

        return $this->render('storefront.category.index', [
            'title'      => 'Shop by category',
            'categories' => $topLevel,
            'totalCount' => count($all),
        ]);
    }

    // -----------------------------------------------------------------
    // GET /categories/{slug}
    // -----------------------------------------------------------------

    /** @param array<string,string> $params */
    public function show(Request $request, array $params = []): Response
    {
        $categoryRepo = $this->container->get(CategoryRepository::class);
        $productRepo = $this->container->get(ProductRepository::class);

        $slug = (string) ($params['slug'] ?? '');
        if ($slug === '') {
            return Response::redirect('/categories');
        }

        $category = $categoryRepo->findBySlug($slug);
        if ($category === null || $category->isArchived() || $category->isDeleted()) {
            // 404 for missing or hidden categories.
            return Response::redirect('/categories');
        }

        $page = max(1, (int) $request->query('page', 1));

        // Product listing scoped to this category.
        $result = $productRepo->paginatePublished(
            ['category_id' => $category->id, 'sort' => 'newest'],
            $page,
            self::PER_PAGE
        );

        // Child categories for the subcategory chip strip.
        $children = [];
        foreach ($categoryRepo->listAll() as $c) {
            if ($c->parentId === $category->id) {
                $children[] = $c;
            }
        }

        // Parent breadcrumb. Only one level up - if we ever allow
        // deeper nesting, expand this into a loop.
        $parent = null;
        if ($category->parentId !== null) {
            $parent = $categoryRepo->findById($category->parentId);
        }

        return $this->render('storefront.category.show', [
            'title'    => $category->name,
            'category' => $category,
            'parent'   => $parent,
            'children' => $children,
            'result'   => $result,
        ]);
    }
}
