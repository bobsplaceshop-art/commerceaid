<?php
declare(strict_types=1);

use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\AuthController;
use CorporateAid\Commerce\Controllers\AccountController;

/** @var Router $router */

// Authentication
$router->get('/login',   [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login',  [AuthController::class, 'login'],     ['guest', 'csrf', 'ratelimit:login,5,15']);
$router->post('/logout', [AuthController::class, 'logout'],    ['auth', 'csrf']);

// Post-logout confirmation page. No middleware. The user is
// already signed out at this point, so 'guest' would pass
// anyway, but the page reads nothing from session state and
// does not need the guard. Kept out of the /login flow so the
// destination after logout is unambiguous.
$router->get('/logout/done', [AuthController::class, 'showLogoutDone'], []);

// Password reset (admin). Public flows gated by guest so a
// logged-in user is redirected to the dashboard if they land
// here.
$router->get('/password/forgot',
    [AuthController::class, 'showForgot'],
    ['guest']);

$router->post('/password/forgot',
    [AuthController::class, 'sendForgot'],
    ['guest', 'csrf', 'ratelimit:password_forgot,3,15']);

// The token arrives in the URL. showReset validates it and either
// renders the form or the "link expired" page. The form itself
// POSTs to /password/reset with the token in a hidden field.
//
// No 'guest' middleware. The token is the credential. A
// logged-in admin who triggers a reset for themselves, for a
// user, or who clicks a forwarded reset link, must be able to
// reach the page. GuestMiddleware would redirect them to '/'.
$router->get('/login/reset/{token}',
    [AuthController::class, 'showReset'],
    []);

$router->post('/password/reset',
    [AuthController::class, 'consumeReset'],
    ['csrf']);

// Account preferences (AJAX)
$router->post('/account/theme', [AccountController::class, 'setTheme'], ['auth', 'csrf']);
