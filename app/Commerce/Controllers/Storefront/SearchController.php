<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Public storefront search.
 *
 * Route:
 *   GET /search?q=<query>
 *
 * The query runs through ProductRepository::paginatePublished
 * with the 'q' filter. The existing buildPublishedFilters()
 * matches on name, SKU, and short_description. Full-text
 * indexing is deferred; LIKE-based is fine for a first pass.
 *
 * An empty query redirects to /products - no point showing
 * an empty search page.
 *
 * See docs/stages/008g_storefront-polish-plan.md.
 */
final class SearchController extends StorefrontController
{
    private const PER_PAGE = 24;

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return Response::redirect('/products');
        }

        $products = $this->container->get(ProductRepository::class);
        $categories = $this->container->get(CategoryRepository::class);

        $page   = max(1, (int) $request->query('page', 1));
        $result = $products->paginatePublished(
            array_merge(['q' => $query], $this->hideOutOfStockFilter()),
            $page,
            self::PER_PAGE
        );

        // Top-level categories for the empty-state suggestion.
        // tree() already returns root nodes only; unwrap the
        // 'category' key from each.
        $topCategories = array_map(
            fn ($node) => $node['category'],
            $categories->tree()
        );

        return $this->render('storefront.search.index', [
            'title'      => 'Search: ' . $query,
            'query'      => $query,
            'result'     => $result,
            'categories' => $topCategories,
        ]);
    }
}
