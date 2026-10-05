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

    private const VALID_SORTS = [
        'newest'     => 'Newest',
        'price_asc'  => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'name_asc'   => 'Name: A to Z',
        'featured'   => 'Featured first',
    ];

    // -----------------------------------------------------------------
    // GET /categories
    // -----------------------------------------------------------------

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $repo = $this->container->get(CategoryRepository::class);

        // Fetch visible categories (those with at least one
        // published product or a visible descendant), then
        // filter to top-level for the grid. Child categories
        // are shown on their parent's page.
        $all = $repo->listVisibleForStorefront();
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

        // ----- Read filter query params -----
        // Same pipeline as CatalogController::index(), minus the
        // category dropdown (the page IS the category).
        $brandRepo = $this->container->get(\CorporateAid\Commerce\Repositories\BrandRepository::class);

        $q           = trim((string) $request->query('q', ''));
        $brandSlug   = trim((string) $request->query('brand', ''));
        $inStockOnly = (bool) $request->query('in_stock', false);
        $priceMinRaw = (string) $request->query('price_min', '');
        $priceMaxRaw = (string) $request->query('price_max', '');
        $ratingRaw   = (string) $request->query('rating', '');
        $sortKey     = (string) $request->query('sort', 'newest');

        // Validate rating: 1-5 or empty.
        $rating = '';
        if ($ratingRaw !== '' && is_numeric($ratingRaw)) {
            $r = (int) $ratingRaw;
            if ($r >= 1 && $r <= 5) {
                $rating = (string) $r;
            }
        }

        // Validate sort against the same whitelist.
        if (!isset(self::VALID_SORTS[$sortKey])) {
            $sortKey = 'newest';
        }

        $brandId = null;
        if ($brandSlug !== '') {
            $resolvedBrand = $brandRepo->findBySlug($brandSlug);
            if ($resolvedBrand !== null) {
                $brandId = $resolvedBrand->id;
            }
        }

        // ----- Build the repository filters -----
        $filters = ['category_id' => $category->id, 'sort' => $sortKey];
        if ($q !== '')                          { $filters['q'] = $q; }
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

        $filters = array_merge($filters, $this->hideOutOfStockFilter());
        $result = $productRepo->paginatePublished(
            $filters,
            $page,
            self::PER_PAGE
        );

        // ----- Filter dropdown data -----
        $brandOptions = [];
        foreach ($brandRepo->listAll() as $b) {
            $brandOptions[] = ['slug' => $b->slug, 'name' => $b->name];
        }

        // ----- Current query state for the view -----
        $currentFilters = [
            'q'         => $q,
            'brand'     => $brandSlug,
            'in_stock'  => $inStockOnly ? '1' : '',
            'price_min' => $priceMinRaw,
            'price_max' => $priceMaxRaw,
            'rating'    => $rating,
            'sort'      => $sortKey,
        ];

        $hasFilters = $q !== ''
            || $brandSlug !== ''
            || $inStockOnly
            || $priceMinRaw !== ''
            || $priceMaxRaw !== ''
            || $sortKey !== 'newest';

        $sortOptions = self::VALID_SORTS;

        // ----- Active filter chips -----
        // Same shape as CatalogController. The URL base is
        // the current category page.
        $categoryBase = '/categories/' . rawurlencode($category->slug);

        // $currentFilters is array<string,string> here. Only
        // the empty-string check is meaningful — a non-empty
        // string is never null or 0.
        $clearFilterUrl = function (string $key) use ($currentFilters, $categoryBase): string {
            $q = array_filter($currentFilters, fn ($v) => $v !== '');
            unset($q[$key]);
            $qs = http_build_query($q);
            return $qs === '' ? $categoryBase : $categoryBase . '?' . $qs;
        };

        $activeChips = [];
        if ($q !== '') {
            $activeChips[] = ['key' => 'q', 'label' => 'Search: ' . $q];
        }
        if ($brandSlug !== '') {
            $activeChips[] = ['key' => 'brand', 'label' => 'Brand: ' . $brandSlug];
        }
        if ($inStockOnly) {
            $activeChips[] = ['key' => 'in_stock', 'label' => 'In stock only'];
        }
        if ($priceMinRaw !== '' || $priceMaxRaw !== '') {
            $min = $priceMinRaw !== '' ? $priceMinRaw : '0';
            $max = $priceMaxRaw !== '' ? $priceMaxRaw : 'max';
            $activeChips[] = ['key' => 'price_min', 'label' => 'Price: ' . $min . ' - ' . $max];
        }
        if ($rating !== '') {
            $activeChips[] = ['key' => 'rating', 'label' => (int) $rating . '★ & up'];
        }

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
            'title'          => $category->name,
            'category'       => $category,
            'parent'         => $parent,
            'children'       => $children,
            'result'         => $result,
            'currentFilters' => $currentFilters,
            'brandOptions'   => $brandOptions,
            'sortOptions'    => $sortOptions,
            'hasFilters'     => $hasFilters,
            'activeChips'    => $activeChips,
            'clearFilterUrl' => $clearFilterUrl,
        ]);
    }
}
