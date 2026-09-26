<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Storefront;

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Commerce\Support\StorefrontController;

/**
 * Storefront home page.
 *
 * Renders the landing page at GET /. For 008A this is a
 * placeholder that confirms the storefront shell works end to
 * end. Once 008B ships, the featured-products grid is populated
 * from real catalog data.
 *
 * The page deliberately does not error on empty catalog. A fresh
 * install with no products still shows a working home page.
 */
final class HomeController extends StorefrontController
{
    public function index(Request $request, array $params = []): Response
    {
        return $this->render('storefront.home', [
            'title' => 'Home',
        ]);
    }
}
