<?php
declare(strict_types=1);

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\Storefront\HomeController;
use CorporateAid\Commerce\Controllers\Storefront\CatalogController;
use CorporateAid\Commerce\Controllers\Storefront\CategoryController;
use CorporateAid\Commerce\Controllers\Storefront\ProductController;
use CorporateAid\Commerce\Controllers\Storefront\ProductReviewController;
use CorporateAid\Commerce\Controllers\Storefront\SearchController;
use CorporateAid\Commerce\Controllers\Storefront\AuthController;
use CorporateAid\Commerce\Controllers\Storefront\AccountController;
use CorporateAid\Commerce\Controllers\Storefront\CustomerClaimController;
use CorporateAid\Commerce\Controllers\Storefront\CartController;
use CorporateAid\Commerce\Controllers\Storefront\WishlistController;
use CorporateAid\Commerce\Controllers\Storefront\RecentViewController;
use CorporateAid\Commerce\Controllers\Storefront\MessageController;
use CorporateAid\Commerce\Controllers\Storefront\ReturnController;
use CorporateAid\Commerce\Controllers\Storefront\CheckoutController;
use CorporateAid\Commerce\Controllers\Storefront\NewsletterController;

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
//
// The view at resources/views/errors/offline.php is a
// self-contained branded page (navy/gold palette, truck icon,
// Try Again button). It does not use the storefront layout
// because it has to render during a failed navigation, when
// the request cycle is unreliable.
$router->get('/offline', function (Request $req, array $params): Response {
    ob_start();
    require __DIR__ . '/../resources/views/errors/offline.php';
    $html = ob_get_clean();
    // ob_get_clean() returns string|false. The buffer is
    // always started in this closure, so false is not
    // reachable in practice - but PHPStan is right to
    // require the guard.
    return Response::html(is_string($html) ? $html : '');
});

// ---------------------------------------------------------------------
// Catalog - product listing and detail
// ---------------------------------------------------------------------
// Specific route (/products) must precede the wildcard
// (/products/{slug}) so /products matches the listing and not
// a product named "products".
$router->get('/products',        [CatalogController::class,  'index'], ['store']);
// ---------------------------------------------------------------------
// Product reviews
// ---------------------------------------------------------------------
//
// Submission requires login. The controller checks the
// customer session and redirects to /account/login when
// missing. CSRF is enforced.
//
// Pagination is public. Returns JSON.
$router->post('/products/{slug}/review',  [ProductReviewController::class, 'submit'],   ['store', 'csrf']);
$router->get('/products/{slug}/reviews',  [ProductReviewController::class, 'paginate'], ['store']);

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
$router->get('/account/orders/{orderNumber}',      [AccountController::class, 'orderDetail'],       ['store', 'customer.auth']);

$router->get('/account/addresses',               [AccountController::class, 'addresses'],         ['store', 'customer.auth']);
$router->get('/account/addresses/create',        [AccountController::class, 'showCreateAddress'], ['store', 'customer.auth']);
$router->post('/account/addresses',              [AccountController::class, 'createAddress'],     ['store', 'customer.auth', 'csrf']);
$router->get('/account/addresses/{id}/edit',     [AccountController::class, 'showEditAddress'],   ['store', 'customer.auth']);
$router->post('/account/addresses/{id}',         [AccountController::class, 'updateAddress'],     ['store', 'customer.auth', 'csrf']);
$router->post('/account/addresses/{id}/delete',  [AccountController::class, 'deleteAddress'],     ['store', 'customer.auth', 'csrf']);
$router->post('/account/addresses/{id}/default', [AccountController::class, 'setDefaultAddress'], ['store', 'customer.auth', 'csrf']);

$router->get('/account/password',                [AccountController::class, 'showPassword'],      ['store', 'customer.auth']);
$router->post('/account/password',               [AccountController::class, 'changePassword'],    ['store', 'customer.auth', 'csrf']);

// Guest order claim (Stage 008E.2)
$router->get('/account/claims',           [CustomerClaimController::class, 'list'],    ['store', 'customer.auth']);
$router->post('/account/claims/link',     [CustomerClaimController::class, 'link'],    ['store', 'customer.auth', 'csrf']);
$router->post('/account/claims/dismiss',  [CustomerClaimController::class, 'dismiss'], ['store', 'customer.auth', 'csrf']);

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
$router->post('/cart/add',           [CartController::class, 'add'],          ['store', 'csrf']);
$router->post('/cart/update',        [CartController::class, 'update'],       ['store', 'csrf']);
$router->post('/cart/remove',        [CartController::class, 'remove'],       ['store', 'csrf']);
$router->post('/cart/clear',         [CartController::class, 'clear'],        ['store', 'csrf']);
$router->post('/cart/apply-coupon',  [CartController::class, 'applyCoupon'],  ['store', 'csrf']);
$router->post('/cart/remove-coupon', [CartController::class, 'removeCoupon'], ['store', 'csrf']);
$router->get('/cart/count',          [CartController::class, 'count'],        ['store']);

// Wishlist (Stage 008G.1)
//
// The heart button on product cards uses /account/wishlist/toggle
// (JSON only, called via AJAX). The wishlist page's own remove
// links use /account/wishlist/remove (form POST, redirect). The
// /account/wishlist page requires login; the mutation endpoints
// work for guests via the session wishlist.
$router->get('/account/wishlist',         [WishlistController::class, 'index'],  ['store']);
$router->post('/account/wishlist/add',    [WishlistController::class, 'add'],    ['store', 'csrf']);
$router->post('/account/wishlist/remove', [WishlistController::class, 'remove'], ['store', 'csrf']);
$router->post('/account/wishlist/toggle', [WishlistController::class, 'toggle'], ['store', 'csrf']);
$router->get('/account/wishlist/ids',     [WishlistController::class, 'ids'],    ['store']);

// Recently viewed (Stage 008G.2)
//
// Recording happens inside ProductController::show(), not via
// a route. These two routes serve the account page and the
// clear button.
$router->get('/account/recent',        [RecentViewController::class, 'index'], ['store']);
$router->post('/account/recent/clear', [RecentViewController::class, 'clear'], ['store', 'csrf']);

// Message center (Stage 008G.3)
//
// Every route requires login. The controller gates on
// CustomerAuthService::user(). Same shape as the wishlist
// and recent-view routes.
$router->get('/account/messages',                [MessageController::class, 'index'],           ['store']);
$router->post('/account/messages/mark-read',     [MessageController::class, 'markRead'],        ['store', 'csrf']);
$router->post('/account/messages/mark-all-read', [MessageController::class, 'markAllRead'],     ['store', 'csrf']);
$router->get('/account/messages/preferences',    [MessageController::class, 'preferences'],     ['store']);
$router->post('/account/messages/preferences',   [MessageController::class, 'savePreferences'], ['store', 'csrf']);

// Returns (Stage 008H)
//
// Every route requires login. The controller gates on
// CustomerAuthService::user() and redirects to /account/login
// when the customer is not logged in. Same shape as the
// message-center, wishlist, and recent-view routes.
//
// Route order matters: /new/{orderNumber} must precede
// /{requestNumber} so the literal "new" is not captured as a
// request number.
$router->get('/account/returns',                    [ReturnController::class, 'index'],  ['store']);
$router->get('/account/returns/new/{orderNumber}',  [ReturnController::class, 'create'], ['store']);
$router->post('/account/returns',                   [ReturnController::class, 'store'],  ['store', 'csrf']);
$router->get('/account/returns/{requestNumber}',    [ReturnController::class, 'show'],   ['store']);
$router->post('/account/returns/{id}/cancel',       [ReturnController::class, 'cancel'], ['store', 'csrf']);

// Search (Stage 008G)
$router->get('/search', [SearchController::class, 'index'], ['store']);

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

// ---------------------------------------------------------------------
// Newsletter (public, no auth)
// ---------------------------------------------------------------------
//
// The double opt-in flow, driven by signed tokens. Every
// route here is reachable without a customer session; the
// token in the URL is the authentication.
//
//   POST /newsletter/subscribe             footer + home forms
//   GET  /newsletter/confirm/{token}       click from the opt-in email
//   GET  /newsletter/unsubscribe/{token}   renders the confirm form
//   POST /newsletter/unsubscribe/{token}   performs the unsubscribe
//
// The GET on unsubscribe exists so a two-step flow separates
// "the customer clicked a link" from "the customer confirmed".
// Mail scanners auto-click every link in every email; if GET
// unsubscribed directly, every scanned email would silently
// remove the recipient.
//
// Rate limit on the subscribe POST is per IP. A second layer,
// per email address, lives inside
// SubscriberService::sendConfirmation(). Together they stop a
// subscription bomb: the IP limit stops a single source, the
// email limit stops a distributed botnet.

$router->post('/newsletter/subscribe',
    [NewsletterController::class, 'subscribe'],
    ['store', 'csrf', 'ratelimit:newsletter_subscribe,5,60']);

$router->get('/newsletter/confirm/{token}',
    [NewsletterController::class, 'showConfirm'],
    ['store']);

$router->get('/newsletter/unsubscribe/{token}',
    [NewsletterController::class, 'showUnsubscribe'],
    ['store']);

$router->post('/newsletter/unsubscribe/{token}',
    [NewsletterController::class, 'performUnsubscribe'],
    ['store', 'csrf']);
