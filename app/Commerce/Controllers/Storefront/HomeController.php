<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Repositories\ProductRepository;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Storefront home page.
 *
 * Renders the landing page at GET /.
 *
 * Sections (all rendered by storefront/home.php):
 *   - Hero / brand campaign (static composition)
 *   - Shop by Category (top 7 categories)
 *   - Promotional collections (static composition)
 *   - New Arrivals (8 products, sort=newest)
 *   - Best Sellers (see note below)
 *   - Special Offers (in a future sub-stage)
 *   - Newsletter signup (static composition)
 *
 * The home page deliberately does not error on empty catalog.
 * A fresh install with no products still shows a working page:
 * sections render with empty states.
 *
 * Best Sellers:
 *   Real best-seller ranking requires order history, which
 *   arrives in Stage 008E. Until then, "Best Sellers" mirrors
 *   featured products. When orders exist, swap the query below
 *   for a real ranking without changing the view.
 *
 * Store builder contract:
 *   The sections and their data sources will eventually come
 *   from `commerce_homepage_sections`. For now they are
 *   hardcoded here. The view renders whatever it is given.
 */
final class HomeController extends StorefrontController
{
    private const SECTION_LIMIT = 8;
    private const CATEGORY_LIMIT = 7;

    /** @param array<string,string> $params */
    public function index(Request $request, array $params = []): Response
    {
        $products = $this->container->get(ProductRepository::class);
        $categories = $this->container->get(CategoryRepository::class);

        // ----- Featured products -----
        $featured = $products->paginatePublished(
            ['featured' => true, 'sort' => 'newest'],
            1,
            self::SECTION_LIMIT
        );

        // ----- New arrivals -----
        $newArrivals = $products->paginatePublished(
            ['sort' => 'newest'],
            1,
            self::SECTION_LIMIT
        );

        // ----- Best sellers -----
        // Until order history exists (008E), Best Sellers mirrors
        // Featured. When orders land, replace this with a real
        // ranking query.
        $bestSellers = $featured;

        // ----- Top-level categories -----
        $allCategories = $categories->listAll();
        $topCategories = [];
        foreach ($allCategories as $c) {
            if ($c->parentId === null) {
                $topCategories[] = $c;
                if (count($topCategories) >= self::CATEGORY_LIMIT) {
                    break;
                }
            }
        }

        return $this->render('storefront.home', [
            'title'         => '',
            'featured'      => $featured['items'],
            'newArrivals'   => $newArrivals['items'],
            'bestSellers'   => $bestSellers['items'],
            'topCategories' => $topCategories,
        ]);
    }
}
