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

// Account preferences (AJAX)
$router->post('/account/theme', [AccountController::class, 'setTheme'], ['auth', 'csrf']);
