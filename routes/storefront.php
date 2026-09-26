<?php
declare(strict_types=1);

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\Storefront\HomeController;

/** @var Router $router */

// =====================================================================
// Public storefront routes
// =====================================================================
//
// These are the customer-facing pages. Distinct from the admin
// routes in routes/commerce.php. Loaded together by
// App::loadRoutes(). No shared state between the two files.
//
// Route prefix convention: flat URLs. No /shop or /store prefix.
// The storefront lives at the root of the domain:
//
//   /                    home
//   /products            product listing (008B)
//   /products/{slug}     product detail (008B)
//   /categories          category index (008B)
//   /categories/{slug}   category listing (008B)
//   /cart                cart (008D)
//   /checkout            checkout (008E)
//   /account             customer dashboard (008C)
//   /account/login       customer login (008C)
//   /account/register    customer registration (008C)
//
// Admin login stays at /login (existing). Customer login lives at
// /account/login to avoid the collision. See
// docs/stages/008_storefront-plan.md for the reasoning.
// =====================================================================

// ---------------------------------------------------------------------
// Home
// ---------------------------------------------------------------------
$router->get('/', [HomeController::class, 'index'], ['store']);

// ---------------------------------------------------------------------
// Offline fallback page
// ---------------------------------------------------------------------
//
// Rendered by the service worker when the network is unavailable.
// Served from the storefront because that's where the PWA lives.
$router->get('/offline', function (Request $req, array $params): Response {
    return Response::html(
        '<!doctype html><html><head><meta charset="utf-8">'
        . '<title>Offline</title>'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font-family:system-ui;padding:4rem;max-width:520px;'
        . 'margin:0 auto;color:#1c1a16;text-align:center}h1{margin:0 0 .5rem}'
        . 'p{color:#57534e;line-height:1.6}</style></head><body>'
        . '<h1>You are offline</h1>'
        . '<p>Reconnect to continue shopping.</p>'
        . '</body></html>'
    );
});
