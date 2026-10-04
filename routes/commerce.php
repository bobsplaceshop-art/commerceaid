<?php
declare(strict_types=1);

use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\Pwa\ManifestController;
use CorporateAid\Commerce\Controllers\Admin\MediaController;
use CorporateAid\Commerce\Controllers\Admin\MediaLibraryController;
use CorporateAid\Commerce\Controllers\MediaServeController;
use CorporateAid\Commerce\Controllers\Admin\DashboardController;
use CorporateAid\Commerce\Controllers\Admin\SettingsController;
use CorporateAid\Commerce\Controllers\Admin\LegalPageController;
use CorporateAid\Commerce\Controllers\Admin\SetupController;
use CorporateAid\Commerce\Controllers\Admin\CategoryController;
use CorporateAid\Commerce\Controllers\Admin\BrandController;
use CorporateAid\Commerce\Controllers\Admin\ProductController;
use CorporateAid\Commerce\Controllers\Admin\CustomerApprovalController;

/** @var Router $router */

// =====================================================================
// PWA - manifest + offline fallback
// =====================================================================
$router->get('/manifest.json', [ManifestController::class, '__invoke']);

// =====================================================================
// Public - media serving
// =====================================================================
//
// The UUID is the public reference. Numeric file IDs are
// never exposed in URLs. No authentication - uploaded media
// (store logos, product images) is publicly visible by
// design. The UUID scheme prevents enumeration, not access.
//
//   GET /media/{uuid}                      source file
//   GET /media/{uuid}/{variant}.{format}   size variant
//
// The route for the variant uses a dot in {variant} so
// /media/{uuid}/card.webp parses as variant="card" and
// format="webp". The pattern below matches both forms.
$router->get('/media/{uuid}',
    [MediaServeController::class, 'serve'],
    ['store']);

$router->get('/media/{uuid}/{variant}.{format}',
    [MediaServeController::class, 'serve'],
    ['store']);

// =====================================================================
// Admin - dashboard
// =====================================================================
$router->get('/commerce/ca-admin',
    [DashboardController::class, 'index'],
    ['store', 'auth']);

// =====================================================================
// Admin - media library
// =====================================================================
//
//   GET  /commerce/ca-admin/media              grid page
//   POST /commerce/ca-admin/media/upload       JSON upload (AJAX)
//   POST /commerce/ca-admin/media/{id}/delete  delete with usage guard
//   POST /commerce/ca-admin/media/{id}/meta    update alt text and credit
//
// The /delete route from 008G.4a (POST /commerce/ca-admin/media/delete)
// was retired. It was unused - no view posted to it. The
// {id}/delete form is the single delete path.
$router->get('/commerce/ca-admin/media',
    [MediaLibraryController::class, 'index'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/media/upload',
    [MediaController::class, 'upload'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/media/{id}/delete',
    [MediaLibraryController::class, 'delete'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/media/{id}/meta',
    [MediaLibraryController::class, 'updateMeta'],
    ['store', 'auth', 'csrf']);

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
// Admin - Settings (Stage 008E.1)
$router->get('/commerce/ca-admin/settings',
    [SettingsController::class, 'index'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/settings',
    [SettingsController::class, 'save'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/settings/advanced',
    [SettingsController::class, 'addCustom'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/settings/advanced/delete',
    [SettingsController::class, 'deleteCustom'],
    ['store', 'auth', 'csrf']);

// Admin - Legal Pages (Stage 008E.1)
$router->get('/commerce/ca-admin/legal-pages',
    [LegalPageController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/legal-pages/{slug}/edit',
    [LegalPageController::class, 'showEdit'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/legal-pages/{slug}',
    [LegalPageController::class, 'update'],
    ['store', 'auth', 'csrf']);

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

// Per-product inventory detail page (Stage 008D.2).
// Path starts with the literal segment "item" so it cannot
// collide with the {productId}/adjust-info route above.
$router->get('/commerce/ca-admin/inventory/item/{productId}',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryItemController::class, 'show'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/inventory/item/{productId}',
    [\CorporateAid\Commerce\Controllers\Admin\InventoryItemController::class, 'save'],
    ['store', 'auth', 'csrf']);

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
