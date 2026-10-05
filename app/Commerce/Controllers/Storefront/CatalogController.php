<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Public product listing at /products.
 *
 * Accepts filter query params (all optional):
 *   q          text search across name, descriptions, sku
 *   category   category slug
 *   brand      brand slug
 *   in_stock   1 to show only in-stock products
 *   price_min  numeric lower bound
 *   price_max  numeric upper bound
 *   sort       'newest' | 'price_asc' | 'price_desc' | 'name_asc' | 'featured'
 *   page       page number (1-based)
 *
 * Storefront URLs use slugs, not IDs, for readability. The
 * controller resolves slugs to IDs before hitting the repository.
 *
 * The sort key is validated here against a fixed whitelist so the
 * sort dropdown in the view always reflects the actual applied sort.
 */
final class CatalogController extends StorefrontController
{
    private const PER_PAGE = 24;

    private const VALID_SORTS = [
        'newest'     => 'Newest',
        'price_asc'  => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name_asc'   => 'Name: A to Z',
        'featured'   => 'Featured first',
    ];

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $products = $this->container->get(ProductRepository::class);
        $categories = $this->container->get(CategoryRepository::class);
        $brands = $this->container->get(BrandRepository::class);

        // ----- Read raw query params -----
        $q           = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));
        $brandSlug   = trim((string) $request->query('brand', ''));
        $inStockOnly = (bool) $request->query('in_stock', false);
        $priceMinRaw = (string) $request->query('price_min', '');
        $priceMaxRaw = (string) $request->query('price_max', '');
        $ratingRaw   = (string) $request->query('rating', '');
        $sortKey     = (string) $request->query('sort', 'newest');
        $page        = max(1, (int) $request->query('page', 1));

        // Validate rating: 1-5 or empty.
        $rating = '';
        if ($ratingRaw !== '' && is_numeric($ratingRaw)) {
            $r = (int) $ratingRaw;
            if ($r >= 1 && $r <= 5) {
                $rating = (string) $r;
            }
        }

        // ----- Validate sort against whitelist -----
        if (!isset(self::VALID_SORTS[$sortKey])) {
            $sortKey = 'newest';
        }

        // ----- Resolve slug filters to IDs -----
        $categoryId = null;
        $resolvedCategory = null;
        if ($categorySlug !== '') {
            $resolvedCategory = $categories->findBySlug($categorySlug);
            if ($resolvedCategory !== null) {
                $categoryId = $resolvedCategory->id;
            }
            // If the slug doesn't resolve, we keep the filter empty but
            // preserve the slug in the URL so the view can show "no
            // results for that category" with the filter still visible.
        }

        $brandId = null;
        $resolvedBrand = null;
        if ($brandSlug !== '') {
            $resolvedBrand = $brands->findBySlug($brandSlug);
            if ($resolvedBrand !== null) {
                $brandId = $resolvedBrand->id;
            }
        }

        // ----- Build the repository filters -----
        $filters = [];
        if ($q !== '')                          { $filters['q'] = $q; }
        if ($categoryId !== null)               { $filters['category_id'] = $categoryId; }
        if ($brandId !== null)                  { $filters['brand_id'] = $brandId; }
        if ($inStockOnly)                       { $filters['in_stock_only'] = true; }
        if ($priceMinRaw !== '' && is_numeric($priceMinRaw)) {
            $filters['price_min'] = (float) $priceMinRaw;
        }
        if ($priceMaxRaw !== '' && is_numeric($priceMaxRaw)) {
            $filters['price_max'] = (float) $priceMaxRaw;
        }
        if ($rating !== '') {
            $filters['rating_min'] = (int) $rating;
        }
        $filters['sort'] = $sortKey;

        // ----- Query -----
        $filters = array_merge($filters, $this->hideOutOfStockFilter());
        $result = $products->paginatePublished($filters, $page, self::PER_PAGE);

        // ----- Filter dropdown data -----
        $categoryOptions = [];
        foreach ($categories->listAll() as $c) {
            $categoryOptions[] = ['slug' => $c->slug, 'name' => $c->name];
        }

        $brandOptions = [];
        foreach ($brands->listAll() as $b) {
            $brandOptions[] = ['slug' => $b->slug, 'name' => $b->name];
        }

        // ----- Current query state for the view (for form preselect + pagination) -----
        $currentFilters = [
            'q'         => $q,
            'category'  => $categorySlug,
            'brand'     => $brandSlug,
            'in_stock'  => $inStockOnly ? '1' : '',
            'price_min' => $priceMinRaw,
            'price_max' => $priceMaxRaw,
            'rating'    => $rating,
            'sort'      => $sortKey,
        ];

        // Title copy varies based on what filters are applied.
        $title = 'Shop all products';
        if ($resolvedCategory !== null) {
            $title = $resolvedCategory->name;
        } elseif ($resolvedBrand !== null) {
            $title = $resolvedBrand->name;
        } elseif ($q !== '') {
            $title = 'Search: ' . $q;
        }

        return $this->render('storefront.catalog.index', [
            'title'            => $title,
            'result'           => $result,
            'currentFilters'   => $currentFilters,
            'sortOptions'      => self::VALID_SORTS,
            'categoryOptions'  => $categoryOptions,
            'brandOptions'     => $brandOptions,
            'resolvedCategory' => $resolvedCategory,
            'resolvedBrand'    => $resolvedBrand,
        ]);
    }
}
