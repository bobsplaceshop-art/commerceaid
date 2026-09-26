<?php
declare(strict_types=1);

use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\Pwa\ManifestController;
use CorporateAid\Commerce\Controllers\Admin\DashboardController;
use CorporateAid\Commerce\Controllers\Admin\SetupController;
use CorporateAid\Commerce\Controllers\Admin\CategoryController;
use CorporateAid\Commerce\Controllers\Admin\BrandController;
use CorporateAid\Commerce\Controllers\Admin\ProductController;
use CorporateAid\Commerce\Controllers\Admin\CustomerApprovalController;

/** @var Router $router */

// =====================================================================
// PWA
// =====================================================================
$router->get('/manifest.json', [ManifestController::class, '__invoke']);

$router->get('/offline', function (Request $req, array $params): Response {
    return Response::html(
        '<!doctype html><html><head><meta charset="utf-8">'
        . '<title>Offline - Anaya Global</title>'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>body{font-family:system-ui;padding:4rem;max-width:520px;'
        . 'margin:0 auto;color:#0f172a;text-align:center}h1{margin:0 0 .5rem}'
        . 'p{color:#475569;line-height:1.6}</style></head><body>'
        . '<h1>You are offline</h1>'
        . '<p>Reconnect to continue shopping.</p>'
        . '</body></html>'
    );
});

// =====================================================================
// Storefront (placeholder - Phase 8 expands this)
// =====================================================================
$router->get('/', function (Request $req, array $params): Response {
    return Response::html(
        '<!doctype html><html><head><meta charset="utf-8">'
        . '<title>Anaya Global</title>'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>'
        . 'body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . 'background:#f4f6fb;color:#0f172a;padding:4rem 1rem;margin:0}'
        . '.wrap{max-width:720px;margin:0 auto}'
        . 'h1{font-size:2.25rem;margin:0 0 .5rem;letter-spacing:-.02em}'
        . '.tag{color:#64748b;font-size:1.05rem;margin:0 0 2rem}'
        . 'a{color:#2563eb;text-decoration:none;font-weight:500}'
        . 'a:hover{text-decoration:underline}'
        . '.card{background:#fff;border:1px solid #e5e9f2;border-radius:14px;'
        . 'padding:1.5rem 1.75rem;margin:1rem 0;box-shadow:0 1px 2px rgba(15,23,42,.04)}'
        . '</style></head><body><div class="wrap">'
        . '<h1>Anaya Global</h1>'
        . '<p class="tag">Commerce platform - storefront is booting up.</p>'
        . '<div class="card">'
        . '<p><strong>Health check</strong></p>'
        . '<p><a href="/manifest.json">Manifest</a> &middot; '
        . '<a href="/login">Admin login</a> &middot; '
        . '<a href="/commerce/ca-admin">Admin panel</a></p>'
        . '</div>'
        . '<p style="color:#94a3b8;font-size:.85rem;margin-top:2rem">'
        . 'If you can read this, the framework is running.</p>'
        . '</div></body></html>'
    );
});

// =====================================================================
// Admin - dashboard
// =====================================================================
$router->get('/commerce/ca-admin',
    [DashboardController::class, 'index'],
    ['store', 'auth']);

// =====================================================================
// Admin - setup / settings hub
// =====================================================================
$router->get('/commerce/ca-admin/setup',
    [SetupController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/setup/skip',
    [SetupController::class, 'skip'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/setup/{tab}',
    [SetupController::class, 'show'],
    ['store', 'auth', 'setup.gate']);

$router->post('/commerce/ca-admin/setup/{tab}',
    [SetupController::class, 'save'],
    ['store', 'auth', 'setup.gate', 'csrf']);

// =====================================================================
// Admin - Categories
// =====================================================================
$router->get('/commerce/ca-admin/categories',
    [CategoryController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/categories/create',
    [CategoryController::class, 'showCreate'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/categories',
    [CategoryController::class, 'store'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/categories/{id}/edit',
    [CategoryController::class, 'showEdit'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/categories/{id}',
    [CategoryController::class, 'update'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/categories/{id}/archive',
    [CategoryController::class, 'archive'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/categories/{id}/restore',
    [CategoryController::class, 'restore'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/categories/{id}/delete',
    [CategoryController::class, 'destroy'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/categories/{id}/delete-info',
    [CategoryController::class, 'deleteInfo'],
    ['store', 'auth']);

// =====================================================================
// Admin - Brands
// =====================================================================
$router->get('/commerce/ca-admin/brands',
    [BrandController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/brands/create',
    [BrandController::class, 'showCreate'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/brands',
    [BrandController::class, 'store'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/brands/{id}/edit',
    [BrandController::class, 'showEdit'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/brands/{id}',
    [BrandController::class, 'update'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/brands/{id}/archive',
    [BrandController::class, 'archive'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/brands/{id}/restore',
    [BrandController::class, 'restore'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/brands/{id}/delete',
    [BrandController::class, 'destroy'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/brands/{id}/delete-info',
    [BrandController::class, 'deleteInfo'],
    ['store', 'auth']);

// =====================================================================
// Admin - Products (Stage 006E)
// =====================================================================
$router->get('/commerce/ca-admin/products',
    [ProductController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/products/create',
    [ProductController::class, 'showCreate'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/products',
    [ProductController::class, 'store'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/products/{id}/edit',
    [ProductController::class, 'showEdit'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/products/{id}',
    [ProductController::class, 'update'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/products/{id}/publish',
    [ProductController::class, 'publish'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/products/{id}/unpublish',
    [ProductController::class, 'unpublish'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/products/{id}/archive',
    [ProductController::class, 'archive'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/products/{id}/restore',
    [ProductController::class, 'restore'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/products/{id}/delete',
    [ProductController::class, 'destroy'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/products/{id}/delete-info',
    [ProductController::class, 'deleteInfo'],
    ['store', 'auth']);

// =====================================================================
// Admin - Attributes (Stage 006F)
// =====================================================================
$router->get('/commerce/ca-admin/attributes',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/attributes/create',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'showCreate'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/attributes',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'store'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/attributes/{id}/edit',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'showEdit'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/attributes/{id}',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'update'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/attributes/{id}/values',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'addValue'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/attributes/{id}/values/{valueId}/delete',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'removeValue'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/attributes/{id}/delete',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'destroy'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/attributes/{id}/delete-info',
    [\CorporateAid\Commerce\Controllers\Admin\AttributeController::class, 'deleteInfo'],
    ['store', 'auth']);

// =====================================================================
// Admin - Product variants (Stage 006F)
// =====================================================================
$router->get('/commerce/ca-admin/products/{id}/variants',
    [\CorporateAid\Commerce\Controllers\Admin\ProductController::class, 'variants'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/products/{id}/variants/generate',
    [\CorporateAid\Commerce\Controllers\Admin\ProductController::class, 'generateVariants'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/products/{id}/variants/save',
    [\CorporateAid\Commerce\Controllers\Admin\ProductController::class, 'saveVariants'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/products/{id}/variants/{vid}/delete',
    [\CorporateAid\Commerce\Controllers\Admin\ProductController::class, 'deleteVariant'],
    ['store', 'auth', 'csrf']);

// =====================================================================
// Admin - Category <-> Attribute mapping (Stage 006F)
// =====================================================================
$router->post('/commerce/ca-admin/categories/{id}/attributes',
    [\CorporateAid\Commerce\Controllers\Admin\CategoryController::class, 'attachAttribute'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/categories/{id}/attributes/{attrId}/detach',
    [\CorporateAid\Commerce\Controllers\Admin\CategoryController::class, 'detachAttribute'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/categories/{id}/attributes/{attrId}/usage',
    [\CorporateAid\Commerce\Controllers\Admin\CategoryController::class, 'attributeUsageInfo'],
    ['store', 'auth']);

// Product variants - regenerate (wipes and rebuilds)
$router->post('/commerce/ca-admin/products/{id}/variants/regenerate',
    [\CorporateAid\Commerce\Controllers\Admin\ProductController::class, 'regenerateVariants'],
    ['store', 'auth', 'csrf']);

// =====================================================================
// Admin - Inventory (Stage 006G)
// =====================================================================
$router->get('/commerce/ca-admin/inventory',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/inventory/movements',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryController::class, 'movements'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/inventory/export.csv',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryController::class, 'exportCsv'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/inventory/movements/export.csv',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryController::class, 'exportMovementsCsv'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/inventory/{productId}/adjust-info',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryController::class, 'adjustInfo'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/inventory/adjust',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryController::class, 'adjust'],
    ['store', 'auth', 'csrf']);

// =====================================================================
// Admin - Customer Groups (Stage 007A)
// =====================================================================
$router->get('/commerce/ca-admin/customer-groups',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/customer-groups/create',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'showCreate'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/customer-groups',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'store'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/customer-groups/{id}/edit',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'showEdit'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/customer-groups/{id}',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'update'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/customer-groups/{id}/archive',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'archive'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/customer-groups/{id}/restore',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'restore'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/customer-groups/{id}/prices',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'prices'],
    ['store', 'auth']);
$router->post('/commerce/ca-admin/customer-groups/{id}/prices',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'addPrice'],
    ['store', 'auth', 'csrf']);
$router->post('/commerce/ca-admin/customer-groups/{id}/prices/{pid}/delete',
    [\CorporateAid\Commerce\Controllers\Admin\CustomerGroupController::class, 'deletePrice'],
    ['store', 'auth', 'csrf']);

// =====================================================================
// Admin - Customer Approvals (Stage 007B)
// =====================================================================
$router->get('/commerce/ca-admin/customer-approvals',
    [CustomerApprovalController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/customer-approvals/{id}/history',
    [CustomerApprovalController::class, 'history'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/customer-approvals/{id}/approve',
    [CustomerApprovalController::class, 'approve'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/customer-approvals/{id}/reject',
    [CustomerApprovalController::class, 'reject'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/customer-approvals/{id}/reset',
    [CustomerApprovalController::class, 'reset'],
    ['store', 'auth', 'csrf']);
