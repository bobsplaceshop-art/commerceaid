<?php
declare(strict_types=1);

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\Storefront\HomeController;
use CorporateAid\Commerce\Controllers\Storefront\CatalogController;
use CorporateAid\Commerce\Controllers\Storefront\CategoryController;
use CorporateAid\Commerce\Controllers\Storefront\ProductController;
use CorporateAid\Commerce\Controllers\Storefront\AuthController;
use CorporateAid\Commerce\Controllers\Storefront\AccountController;
use CorporateAid\Commerce\Controllers\Storefront\CartController;
use CorporateAid\Commerce\Controllers\Storefront\CheckoutController;

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

// ---------------------------------------------------------------------
// Catalog - product listing and detail
// ---------------------------------------------------------------------
// Specific route (/products) must precede the wildcard
// (/products/{slug}) so /products matches the listing and not
// a product named "products".
$router->get('/products',        [CatalogController::class,  'index'], ['store']);
$router->get('/products/{slug}', [ProductController::class,  'show'],  ['store']);

// ---------------------------------------------------------------------
// Categories - index and detail
// ---------------------------------------------------------------------
// Same ordering rule: specific before wildcard.
$router->get('/categories',        [CategoryController::class, 'index'], ['store']);
$router->get('/categories/{slug}', [CategoryController::class, 'show'],  ['store']);

// ---------------------------------------------------------------------
// Customer authentication (Stage 008C)
// ---------------------------------------------------------------------
//
// Auth routes are PUBLIC - they must be reachable when the customer
// is not logged in. They use 'store' for store resolution but not
// 'customer.auth'.
//
// Rate limiting is applied at the route level via the 'ratelimit'
// middleware. Format: ratelimit:action,max_attempts,window_minutes.
//
// Customer login lives at /account/login (NOT /login). The admin
// login owns /login.
$router->get('/account/register',          [AuthController::class, 'showRegister'],       ['store']);
$router->post('/account/register',         [AuthController::class, 'register'],           ['store', 'csrf', 'ratelimit:register,3,60']);
$router->get('/account/login',             [AuthController::class, 'showLogin'],          ['store']);
$router->post('/account/login',            [AuthController::class, 'login'],              ['store', 'csrf', 'ratelimit:login,5,15']);
$router->post('/account/logout',           [AuthController::class, 'logout'],             ['store', 'csrf']);
$router->get('/account/forgot-password',   [AuthController::class, 'showForgotPassword'], ['store']);
$router->post('/account/forgot-password',  [AuthController::class, 'sendResetLink'],      ['store', 'csrf', 'ratelimit:forgot,3,60']);
$router->get('/account/reset-password',    [AuthController::class, 'showResetPassword'],  ['store']);
$router->post('/account/reset-password',   [AuthController::class, 'resetPassword'],      ['store', 'csrf', 'ratelimit:reset,5,60']);

// ---------------------------------------------------------------------
// Customer account (guarded by customer.auth)
// ---------------------------------------------------------------------
//
// These routes require a logged-in customer. The 'customer.auth'
// alias maps to CustomerAuthMiddleware, which redirects to
// /account/login?intended=... on failure.
$router->get('/account',                         [AccountController::class, 'dashboard'],         ['store', 'customer.auth']);
$router->get('/account/orders',                  [AccountController::class, 'orders'],            ['store', 'customer.auth']);

$router->get('/account/addresses',               [AccountController::class, 'addresses'],         ['store', 'customer.auth']);
$router->get('/account/addresses/create',        [AccountController::class, 'showCreateAddress'], ['store', 'customer.auth']);
$router->post('/account/addresses',              [AccountController::class, 'createAddress'],     ['store', 'customer.auth', 'csrf']);
$router->get('/account/addresses/{id}/edit',     [AccountController::class, 'showEditAddress'],   ['store', 'customer.auth']);
$router->post('/account/addresses/{id}',         [AccountController::class, 'updateAddress'],     ['store', 'customer.auth', 'csrf']);
$router->post('/account/addresses/{id}/delete',  [AccountController::class, 'deleteAddress'],     ['store', 'customer.auth', 'csrf']);
$router->post('/account/addresses/{id}/default', [AccountController::class, 'setDefaultAddress'], ['store', 'customer.auth', 'csrf']);

$router->get('/account/password',                [AccountController::class, 'showPassword'],      ['store', 'customer.auth']);
$router->post('/account/password',               [AccountController::class, 'changePassword'],    ['store', 'customer.auth', 'csrf']);

// ---------------------------------------------------------------------
// Cart (Stage 008D)
// ---------------------------------------------------------------------
//
// Public routes. No auth required — guests can use the cart.
// CSRF on every POST.
//
// Every mutation route accepts both form POST and AJAX POST
// (based on the Accept header). Form POST returns a redirect;
// AJAX returns JSON. Same handler, different response format.
//
// The /cart/count route is JSON-only and powers the mini-cart
// badge in the header.
$router->get('/cart',        [CartController::class, 'index'],  ['store']);
$router->post('/cart/add',   [CartController::class, 'add'],    ['store', 'csrf']);
$router->post('/cart/update',[CartController::class, 'update'], ['store', 'csrf']);
$router->post('/cart/remove',[CartController::class, 'remove'], ['store', 'csrf']);
$router->post('/cart/clear', [CartController::class, 'clear'],  ['store', 'csrf']);
$router->get('/cart/count',  [CartController::class, 'count'],  ['store']);

// Checkout (Stage 008E)
$router->get('/checkout',                      [CheckoutController::class, 'review'],       ['store']);
$router->get('/checkout/info',                 [CheckoutController::class, 'info'],         ['store']);
$router->post('/checkout/info',                [CheckoutController::class, 'saveInfo'],     ['store', 'csrf']);
$router->get('/checkout/shipping',             [CheckoutController::class, 'shipping'],     ['store']);
$router->post('/checkout/shipping',            [CheckoutController::class, 'saveShipping'], ['store', 'csrf']);
$router->get('/checkout/payment',              [CheckoutController::class, 'payment'],      ['store']);
$router->post('/checkout/payment',             [CheckoutController::class, 'savePayment'],  ['store', 'csrf']);
$router->get('/checkout/confirm',              [CheckoutController::class, 'confirm'],      ['store']);
$router->post('/checkout/place',               [CheckoutController::class, 'place'],        ['store', 'csrf']);
$router->get('/checkout/confirmation/{orderNumber}', [CheckoutController::class, 'confirmation'], ['store']);

// Legal pages (public, one route for all four slugs)
$router->get('/legal/{slug}', [CheckoutController::class, 'legalPage'], ['store']);
