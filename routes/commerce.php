<?php
declare(strict_types=1);

use CorporateAid\Core\Router;
use CorporateAid\Commerce\Controllers\Pwa\ManifestController;
use CorporateAid\Commerce\Controllers\Admin\ImportController;
use CorporateAid\Commerce\Controllers\Admin\MediaController;
use CorporateAid\Commerce\Controllers\Admin\MediaLibraryController;
use CorporateAid\Commerce\Controllers\MediaServeController;
use CorporateAid\Commerce\Controllers\Admin\DashboardController;
use CorporateAid\Commerce\Controllers\Admin\SettingsController;
use CorporateAid\Commerce\Controllers\Admin\LegalPageController;
use CorporateAid\Commerce\Controllers\Admin\SetupController;
use CorporateAid\Commerce\Controllers\Admin\CategoryController;
use CorporateAid\Commerce\Controllers\Admin\BrandController;
use CorporateAid\Commerce\Controllers\Admin\ReviewController;
use CorporateAid\Commerce\Controllers\Admin\ProductController;
use CorporateAid\Commerce\Controllers\Admin\CustomerApprovalController;
use CorporateAid\Commerce\Controllers\Admin\AdminOrderController;
use CorporateAid\Commerce\Controllers\Admin\AdminCustomerController;
use CorporateAid\Commerce\Controllers\Admin\AdminUserController;
use CorporateAid\Commerce\Controllers\Admin\AdminRoleController;
use CorporateAid\Commerce\Controllers\Admin\AdminCouponController;
use CorporateAid\Commerce\Controllers\Admin\AdminTaxController;
use CorporateAid\Commerce\Controllers\Admin\AdminShippingController;
use CorporateAid\Commerce\Controllers\Admin\AdminReturnController;
use CorporateAid\Commerce\Controllers\Admin\AdminSubscriberController;
use CorporateAid\Commerce\Controllers\Admin\AdminSubscriberListController;
use CorporateAid\Commerce\Controllers\Admin\AdminCampaignController;
use CorporateAid\Commerce\Controllers\Admin\AdminFormController;
use CorporateAid\Commerce\Controllers\Admin\DebugController;
use CorporateAid\Commerce\Controllers\Admin\LogController;
use CorporateAid\Commerce\Controllers\WebhookController;

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

$router->get('/commerce/ca-admin/reports',
    [\CorporateAid\Commerce\Controllers\Admin\AdminReportController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/reports/download',
    [\CorporateAid\Commerce\Controllers\Admin\AdminReportController::class, 'downloadPdf'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/reports/print',
    [\CorporateAid\Commerce\Controllers\Admin\AdminReportController::class, 'print'],
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
// Update System (Stage 017)
$router->get('/commerce/ca-admin/update',
    [\CorporateAid\Commerce\Controllers\Admin\AdminUpdateController::class, 'index'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/update/run',
    [\CorporateAid\Commerce\Controllers\Admin\AdminUpdateController::class, 'run'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/update/status',
    [\CorporateAid\Commerce\Controllers\Admin\AdminUpdateController::class, 'saveStatus'],
    ['store', 'auth', 'csrf']);

$router->post('/commerce/ca-admin/update/schedule',
    [\CorporateAid\Commerce\Controllers\Admin\AdminUpdateController::class, 'schedule'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/search',
    [\CorporateAid\Commerce\Controllers\Admin\AdminSearchController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/search/live',
    [\CorporateAid\Commerce\Controllers\Admin\AdminSearchController::class, 'live'],
    ['store', 'auth']);

// Page builder (Stage 023 Session 2)
$router->get('/commerce/ca-admin/pages',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'index'],
    ['store', 'auth', 'permission:core.settings.manage']);

$router->get('/commerce/ca-admin/pages/create',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'showCreate'],
    ['store', 'auth', 'permission:core.settings.manage']);

$router->post('/commerce/ca-admin/pages',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'store'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->get('/commerce/ca-admin/pages/{slug}',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'show'],
    ['store', 'auth', 'permission:core.settings.manage']);

$router->post('/commerce/ca-admin/pages/{slug}/blocks',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'addBlock'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->post('/commerce/ca-admin/pages/{slug}/blocks/reorder',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'reorderBlocks'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->get('/commerce/ca-admin/pages/{slug}/blocks/{id}/config',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'configBlock'],
    ['store', 'auth', 'permission:core.settings.manage']);

$router->post('/commerce/ca-admin/pages/{slug}/blocks/{id}/config',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'saveBlockConfig'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->post('/commerce/ca-admin/pages/{slug}/blocks/{id}/preview',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'previewBlock'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->post('/commerce/ca-admin/pages/{slug}/publish',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'publish'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->get('/commerce/ca-admin/pages/{slug}/preview',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'previewIframe'],
    ['store', 'auth', 'permission:core.settings.manage']);

$router->post('/commerce/ca-admin/pages/{slug}/blocks/{id}/delete',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'deleteBlock'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->post('/commerce/ca-admin/pages/{slug}/blocks/{id}/toggle',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'toggleBlock'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

$router->post('/commerce/ca-admin/pages/{slug}/delete',
    [\CorporateAid\Commerce\Controllers\Admin\AdminPageController::class, 'destroy'],
    ['store', 'auth', 'permission:core.settings.manage', 'csrf']);

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


// =====================================================================
// Admin - Reviews
// =====================================================================
// All four routes gated by the review.moderate permission.
// The moderator needs review.moderate (assigned to super-admin
// by the seed). The GET shows a filtered list; the three POSTs
// mutate one row and redirect back to the same filter.
$router->get('/commerce/ca-admin/reviews',
    [ReviewController::class, 'index'],
    ['store', 'auth', 'permission:review.moderate']);

$router->post('/commerce/ca-admin/reviews/{id}/approve',
    [ReviewController::class, 'approve'],
    ['store', 'auth', 'csrf', 'permission:review.moderate']);

$router->post('/commerce/ca-admin/reviews/{id}/reject',
    [ReviewController::class, 'reject'],
    ['store', 'auth', 'csrf', 'permission:review.moderate']);

$router->post('/commerce/ca-admin/reviews/{id}/delete',
    [ReviewController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:review.moderate']);

// =====================================================================
// Admin - Orders (Stage 008K.1)
// =====================================================================
// GETs gated by orders.view. POSTs gated by orders.manage.
// The list shows status tabs; the detail shows line items,
// status history, internal notes, and the transition bar.
$router->get('/commerce/ca-admin/orders',
    [AdminOrderController::class, 'index'],
    ['store', 'auth', 'permission:orders.view']);

$router->get('/commerce/ca-admin/orders/{id}',
    [AdminOrderController::class, 'show'],
    ['store', 'auth', 'permission:orders.view']);

$router->post('/commerce/ca-admin/orders/{id}/status',
    [AdminOrderController::class, 'updateStatus'],
    ['store', 'auth', 'csrf', 'permission:orders.manage']);

$router->post('/commerce/ca-admin/orders/{id}/note',
    [AdminOrderController::class, 'addNote'],
    ['store', 'auth', 'csrf', 'permission:orders.manage']);

// =====================================================================
// Admin - Customers (Stage 008K.1, read-only detail)
// =====================================================================
// The list page lands in 008K.2. This detail page is the
// target of the "View customer profile" link on the order
// detail page.
// =====================================================================
// Admin - Customers (Stage 008K.2)
// =====================================================================
// List gated by customers.view. Profile edit, group change, and
// account state change gated by customers.manage.
//
// The detail route (show) was added in 008K.1 and is preserved
// below. It now passes $groupOptions to the view for the group
// change modal.
//
// Approval actions (approve, reject, reset) are NOT declared here.
// They live in the CustomerApprovalController block and are reused
// by the detail page's action bar.
$router->get('/commerce/ca-admin/customers',
    [AdminCustomerController::class, 'index'],
    ['store', 'auth', 'permission:customers.view']);

$router->get('/commerce/ca-admin/customers/{id}/edit',
    [AdminCustomerController::class, 'edit'],
    ['store', 'auth', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}',
    [AdminCustomerController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}/group',
    [AdminCustomerController::class, 'changeGroup'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}/status',
    [AdminCustomerController::class, 'changeStatus'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

$router->get('/commerce/ca-admin/customers/{id}',
    [AdminCustomerController::class, 'show'],
    ['store', 'auth', 'permission:customers.view']);

// =====================================================================
// Admin - Customer addresses (Stage 008K.2 session 2)
// =====================================================================
// Address writes mirror the storefront's AccountController shape.
// Every route is gated by customers.manage; view-only users see
// the addresses on the detail page but cannot change them.
$router->get('/commerce/ca-admin/customers/{id}/addresses/create',
    [AdminCustomerController::class, 'addressCreate'],
    ['store', 'auth', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}/addresses',
    [AdminCustomerController::class, 'addressStore'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

$router->get('/commerce/ca-admin/customers/{id}/addresses/{aid}/edit',
    [AdminCustomerController::class, 'addressEdit'],
    ['store', 'auth', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}/addresses/{aid}',
    [AdminCustomerController::class, 'addressUpdate'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}/addresses/{aid}/delete',
    [AdminCustomerController::class, 'addressDelete'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

$router->post('/commerce/ca-admin/customers/{id}/addresses/{aid}/default',
    [AdminCustomerController::class, 'addressDefault'],
    ['store', 'auth', 'csrf', 'permission:customers.manage']);

// =====================================================================
// Admin - Users (Stage 008K.3)
// =====================================================================
// Granular permissions:
//   core.users.view    - list
//   core.users.create  - invite
//   core.users.edit    - edit, reactivate, password reset
//   core.users.delete  - deactivate
//
// Specific routes (invite, accept-invite) must precede the {id}
// wildcard routes. See Pattern 7.
$router->get('/commerce/ca-admin/users',
    [AdminUserController::class, 'index'],
    ['store', 'auth', 'permission:core.users.view']);

$router->get('/commerce/ca-admin/users/invite',
    [AdminUserController::class, 'showInvite'],
    ['store', 'auth', 'permission:core.users.create']);

$router->post('/commerce/ca-admin/users/invite',
    [AdminUserController::class, 'invite'],
    ['store', 'auth', 'csrf', 'permission:core.users.create']);

// Public: the invitee is not logged in. The token is the proof.
//
// No 'guest' middleware. GuestMiddleware redirects any authenticated
// user to '/'. That is correct for /login and /register, where a
// logged-in user has no business. It is wrong for invite acceptance:
// the invitee is defined by the token, not by session state. A
// logged-in admin clicking their own invite link (or a forwarded
// one) should see the page. Removing 'guest' allows that.
//
// No 'store' middleware either - the invite is not store-scoped.
$router->get('/commerce/ca-admin/users/accept-invite/{token}',
    [AdminUserController::class, 'acceptInvite'],
    []);

$router->post('/commerce/ca-admin/users/accept-invite/{token}',
    [AdminUserController::class, 'completeInvite'],
    ['csrf']);

$router->get('/commerce/ca-admin/users/{id}/edit',
    [AdminUserController::class, 'showEdit'],
    ['store', 'auth', 'permission:core.users.edit']);

$router->post('/commerce/ca-admin/users/{id}',
    [AdminUserController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:core.users.edit']);

$router->post('/commerce/ca-admin/users/{id}/deactivate',
    [AdminUserController::class, 'deactivate'],
    ['store', 'auth', 'csrf', 'permission:core.users.delete']);

$router->post('/commerce/ca-admin/users/{id}/reactivate',
    [AdminUserController::class, 'reactivate'],
    ['store', 'auth', 'csrf', 'permission:core.users.edit']);

$router->post('/commerce/ca-admin/users/{id}/send-password-reset',
    [AdminUserController::class, 'sendPasswordReset'],
    ['store', 'auth', 'csrf', 'permission:core.users.edit']);

// =====================================================================
// Admin - Roles (Stage 008K.4)
// =====================================================================
// Granular permissions:
//   core.roles.view    - list, edit view
//   core.roles.create  - new, create
//   core.roles.edit    - update
//   core.roles.delete  - delete, delete-info
//
// Specific routes (create, delete-info) must precede the {id}
// wildcard routes. See Pattern 7.
$router->get('/commerce/ca-admin/roles',
    [AdminRoleController::class, 'index'],
    ['store', 'auth', 'permission:core.roles.view']);

$router->get('/commerce/ca-admin/roles/create',
    [AdminRoleController::class, 'showCreate'],
    ['store', 'auth', 'permission:core.roles.create']);

$router->post('/commerce/ca-admin/roles',
    [AdminRoleController::class, 'store'],
    ['store', 'auth', 'csrf', 'permission:core.roles.create']);

$router->get('/commerce/ca-admin/roles/{id}/edit',
    [AdminRoleController::class, 'showEdit'],
    ['store', 'auth', 'permission:core.roles.view']);

$router->get('/commerce/ca-admin/roles/{id}/delete-info',
    [AdminRoleController::class, 'deleteInfo'],
    ['store', 'auth', 'permission:core.roles.delete']);

$router->post('/commerce/ca-admin/roles/{id}/delete',
    [AdminRoleController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:core.roles.delete']);

$router->post('/commerce/ca-admin/roles/{id}',
    [AdminRoleController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:core.roles.edit']);

// =====================================================================
// Admin - Coupons (Stage 008K.5)
// =====================================================================
// Granular permissions:
//   coupons.view    - list, edit view
//   coupons.create  - new, create
//   coupons.edit    - update
//   coupons.delete  - delete, delete-info
//
// Specific routes (create, delete-info) must precede the {id}
// wildcard routes. See Pattern 7.
$router->get('/commerce/ca-admin/coupons',
    [AdminCouponController::class, 'index'],
    ['store', 'auth', 'permission:coupons.view']);

$router->get('/commerce/ca-admin/coupons/create',
    [AdminCouponController::class, 'showCreate'],
    ['store', 'auth', 'permission:coupons.create']);

$router->post('/commerce/ca-admin/coupons',
    [AdminCouponController::class, 'store'],
    ['store', 'auth', 'csrf', 'permission:coupons.create']);

$router->get('/commerce/ca-admin/coupons/{id}/edit',
    [AdminCouponController::class, 'showEdit'],
    ['store', 'auth', 'permission:coupons.view']);

$router->get('/commerce/ca-admin/coupons/{id}/delete-info',
    [AdminCouponController::class, 'deleteInfo'],
    ['store', 'auth', 'permission:coupons.delete']);

$router->post('/commerce/ca-admin/coupons/{id}/delete',
    [AdminCouponController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:coupons.delete']);

$router->post('/commerce/ca-admin/coupons/{id}',
    [AdminCouponController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:coupons.edit']);

// =====================================================================
// Admin - Tax (Stage 008K.5)
// =====================================================================
// Granular permissions:
//   tax.view    - list, edit views
//   tax.manage  - create, update, delete
//
// The specific routes (classes, rates/create, rates/{id}/edit)
// must precede the wildcard {type}/{id} routes. See Pattern 7.
$router->get('/commerce/ca-admin/tax',
    [AdminTaxController::class, 'index'],
    ['store', 'auth', 'permission:tax.view']);

$router->post('/commerce/ca-admin/tax/classes',
    [AdminTaxController::class, 'storeClass'],
    ['store', 'auth', 'csrf', 'permission:tax.manage']);

$router->post('/commerce/ca-admin/tax/classes/{id}',
    [AdminTaxController::class, 'updateClass'],
    ['store', 'auth', 'csrf', 'permission:tax.manage']);

$router->get('/commerce/ca-admin/tax/rates/create',
    [AdminTaxController::class, 'showCreateRate'],
    ['store', 'auth', 'permission:tax.view']);

$router->post('/commerce/ca-admin/tax/rates',
    [AdminTaxController::class, 'storeRate'],
    ['store', 'auth', 'csrf', 'permission:tax.manage']);

$router->get('/commerce/ca-admin/tax/rates/{id}/edit',
    [AdminTaxController::class, 'showEditRate'],
    ['store', 'auth', 'permission:tax.view']);

$router->post('/commerce/ca-admin/tax/rates/{id}',
    [AdminTaxController::class, 'updateRate'],
    ['store', 'auth', 'csrf', 'permission:tax.manage']);

// Wildcard routes last.
$router->get('/commerce/ca-admin/tax/{type}/{id}/delete-info',
    [AdminTaxController::class, 'deleteInfo'],
    ['store', 'auth', 'permission:tax.manage']);

$router->post('/commerce/ca-admin/tax/{type}/{id}/delete',
    [AdminTaxController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:tax.manage']);

// =====================================================================
// Admin - Shipping (Stage 008K.5)
// =====================================================================
// Granular permissions:
//   shipping.view    - list, edit views
//   shipping.manage  - create, update, delete
//
// The specific routes (methods/create, methods/{id}/edit,
// zones/create, zones/{id}/edit) must precede the wildcard
// {type}/{id} routes. See Pattern 7.
$router->get('/commerce/ca-admin/shipping',
    [AdminShippingController::class, 'index'],
    ['store', 'auth', 'permission:shipping.view']);

$router->get('/commerce/ca-admin/shipping/methods/create',
    [AdminShippingController::class, 'showCreateMethod'],
    ['store', 'auth', 'permission:shipping.view']);

$router->post('/commerce/ca-admin/shipping/methods',
    [AdminShippingController::class, 'storeMethod'],
    ['store', 'auth', 'csrf', 'permission:shipping.manage']);

$router->get('/commerce/ca-admin/shipping/methods/{id}/edit',
    [AdminShippingController::class, 'showEditMethod'],
    ['store', 'auth', 'permission:shipping.view']);

$router->post('/commerce/ca-admin/shipping/methods/{id}',
    [AdminShippingController::class, 'updateMethod'],
    ['store', 'auth', 'csrf', 'permission:shipping.manage']);

$router->get('/commerce/ca-admin/shipping/zones/create',
    [AdminShippingController::class, 'showCreateZone'],
    ['store', 'auth', 'permission:shipping.view']);

$router->post('/commerce/ca-admin/shipping/zones',
    [AdminShippingController::class, 'storeZone'],
    ['store', 'auth', 'csrf', 'permission:shipping.manage']);

$router->get('/commerce/ca-admin/shipping/zones/{id}/edit',
    [AdminShippingController::class, 'showEditZone'],
    ['store', 'auth', 'permission:shipping.view']);

$router->post('/commerce/ca-admin/shipping/zones/{id}',
    [AdminShippingController::class, 'updateZone'],
    ['store', 'auth', 'csrf', 'permission:shipping.manage']);

// Wildcard routes last.
$router->get('/commerce/ca-admin/shipping/{type}/{id}/delete-info',
    [AdminShippingController::class, 'deleteInfo'],
    ['store', 'auth', 'permission:shipping.manage']);

$router->post('/commerce/ca-admin/shipping/{type}/{id}/delete',
    [AdminShippingController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:shipping.manage']);

$router->get('/commerce/ca-admin/brands/{id}/delete-info',
    [BrandController::class, 'deleteInfo'],
    ['store', 'auth']);

// =====================================================================
// Admin - Products (Stage 006E)
// =====================================================================
$router->get('/commerce/ca-admin/products',
    [ProductController::class, 'index'],
    ['store', 'auth']);

$router->get('/commerce/ca-admin/products/bulk-publish',
    [ProductController::class, 'bulkPublish'],
    ['store', 'auth']);

$router->post('/commerce/ca-admin/products/bulk-publish',
    [ProductController::class, 'processBulkPublish'],
    ['store', 'auth', 'csrf']);

$router->get('/commerce/ca-admin/products/create',
    [ProductController::class, 'showCreate'],
    ['store', 'auth']);

// CSV import. The upload creates a batch row and stores
// the file. The map / validate / preview / commit /
// rollback screens ship in Stage 016 Session 2. The
// index and upload screens are live now.
$router->get('/commerce/ca-admin/import',
    [ImportController::class, 'index'],
    ['store', 'auth', 'permission:catalog.import']);

$router->get('/commerce/ca-admin/import/upload',
    [ImportController::class, 'showUpload'],
    ['store', 'auth', 'permission:catalog.import']);

$router->post('/commerce/ca-admin/import/upload',
    [ImportController::class, 'upload'],
    ['store', 'auth', 'csrf', 'permission:catalog.import']);

$router->get('/commerce/ca-admin/import/template/{entity}',
    [ImportController::class, 'template'],
    ['store', 'auth', 'permission:catalog.import']);

$router->get('/commerce/ca-admin/import/{id}',
    [ImportController::class, 'show'],
    ['store', 'auth', 'permission:catalog.import']);

$router->get('/commerce/ca-admin/import/{id}/map',
    [ImportController::class, 'showMap'],
    ['store', 'auth', 'permission:catalog.import']);

$router->post('/commerce/ca-admin/import/{id}/map',
    [ImportController::class, 'saveMap'],
    ['store', 'auth', 'csrf', 'permission:catalog.import']);

$router->post('/commerce/ca-admin/import/{id}/validate',
    [ImportController::class, 'runValidation'],
    ['store', 'auth', 'csrf', 'permission:catalog.import']);

$router->get('/commerce/ca-admin/import/{id}/preview',
    [ImportController::class, 'preview'],
    ['store', 'auth', 'permission:catalog.import']);

$router->post('/commerce/ca-admin/import/{id}/commit',
    [ImportController::class, 'commit'],
    ['store', 'auth', 'csrf', 'permission:catalog.import']);

$router->post('/commerce/ca-admin/import/{id}/rollback',
    [ImportController::class, 'rollback'],
    ['store', 'auth', 'csrf', 'permission:catalog.import']);

$router->get('/commerce/ca-admin/import/{id}/failed.csv',
    [ImportController::class, 'failedExport'],
    ['store', 'auth', 'permission:catalog.import']);
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

// Payment provider connection test. Reads the provider
// from the registry and reports whether its credentials
// are present. No outbound network call.
$router->post('/commerce/ca-admin/settings/payments/test',
    [SettingsController::class, 'testPaymentProvider'],
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

// =====================================================================
// Admin returns queue (Stage 008H Session 3)
//
// The GET list and detail are gated by returns.view. The POST
// actions (approve, reject, receive, refund) by returns.moderate.
// =====================================================================

$router->get('/commerce/ca-admin/returns',
    [AdminReturnController::class, 'index'],
    ['store', 'auth', 'permission:returns.view']);

$router->get('/commerce/ca-admin/returns/{id}',
    [AdminReturnController::class, 'show'],
    ['store', 'auth', 'permission:returns.view']);

$router->post('/commerce/ca-admin/returns/{id}/approve',
    [AdminReturnController::class, 'approve'],
    ['store', 'auth', 'csrf', 'permission:returns.moderate']);

$router->post('/commerce/ca-admin/returns/{id}/reject',
    [AdminReturnController::class, 'reject'],
    ['store', 'auth', 'csrf', 'permission:returns.moderate']);

$router->post('/commerce/ca-admin/returns/{id}/receive',
    [AdminReturnController::class, 'markReceived'],
    ['store', 'auth', 'csrf', 'permission:returns.moderate']);

$router->post('/commerce/ca-admin/returns/{id}/refund',
    [AdminReturnController::class, 'refund'],
    ['store', 'auth', 'csrf', 'permission:returns.moderate']);

// =====================================================================
// Admin newsletter: subscribers and lists (Stage 008I)
// =====================================================================

$router->get('/commerce/ca-admin/subscribers',
    [AdminSubscriberController::class, 'index'],
    ['store', 'auth', 'permission:newsletter.subscribers.view']);

$router->get('/commerce/ca-admin/subscribers/export',
    [AdminSubscriberController::class, 'exportCsv'],
    ['store', 'auth', 'permission:newsletter.subscribers.view']);

$router->get('/commerce/ca-admin/subscribers/{id}',
    [AdminSubscriberController::class, 'show'],
    ['store', 'auth', 'permission:newsletter.subscribers.view']);

$router->post('/commerce/ca-admin/subscribers/{id}/unsubscribe',
    [AdminSubscriberController::class, 'unsubscribe'],
    ['store', 'auth', 'csrf', 'permission:newsletter.subscribers.manage']);

$router->get('/commerce/ca-admin/subscriber-lists',
    [AdminSubscriberListController::class, 'index'],
    ['store', 'auth', 'permission:newsletter.subscribers.view']);

$router->get('/commerce/ca-admin/subscriber-lists/create',
    [AdminSubscriberListController::class, 'showCreate'],
    ['store', 'auth', 'permission:newsletter.subscribers.manage']);

$router->post('/commerce/ca-admin/subscriber-lists',
    [AdminSubscriberListController::class, 'store'],
    ['store', 'auth', 'csrf', 'permission:newsletter.subscribers.manage']);

$router->get('/commerce/ca-admin/subscriber-lists/{id}/delete-info',
    [AdminSubscriberListController::class, 'deleteInfo'],
    ['store', 'auth', 'permission:newsletter.subscribers.manage']);

$router->get('/commerce/ca-admin/subscriber-lists/{id}/edit',
    [AdminSubscriberListController::class, 'showEdit'],
    ['store', 'auth', 'permission:newsletter.subscribers.manage']);

$router->post('/commerce/ca-admin/subscriber-lists/{id}/delete',
    [AdminSubscriberListController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:newsletter.subscribers.manage']);

$router->post('/commerce/ca-admin/subscriber-lists/{id}',
    [AdminSubscriberListController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:newsletter.subscribers.manage']);

// =====================================================================
// Admin newsletter: campaigns (Stage 008I)
// =====================================================================

$router->get('/commerce/ca-admin/campaigns',
    [AdminCampaignController::class, 'index'],
    ['store', 'auth', 'permission:newsletter.campaigns.view']);

$router->get('/commerce/ca-admin/campaigns/create',
    [AdminCampaignController::class, 'showCreate'],
    ['store', 'auth', 'permission:newsletter.campaigns.manage']);

$router->post('/commerce/ca-admin/campaigns',
    [AdminCampaignController::class, 'store'],
    ['store', 'auth', 'csrf', 'permission:newsletter.campaigns.manage']);

$router->get('/commerce/ca-admin/campaigns/{id}/edit',
    [AdminCampaignController::class, 'showEdit'],
    ['store', 'auth', 'permission:newsletter.campaigns.view']);

$router->get('/commerce/ca-admin/campaigns/{id}/preview',
    [AdminCampaignController::class, 'preview'],
    ['store', 'auth', 'permission:newsletter.campaigns.view']);

$router->post('/commerce/ca-admin/campaigns/{id}/send',
    [AdminCampaignController::class, 'send'],
    ['store', 'auth', 'csrf', 'permission:newsletter.campaigns.manage']);

$router->post('/commerce/ca-admin/campaigns/{id}/delete',
    [AdminCampaignController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:newsletter.campaigns.manage']);

$router->get('/commerce/ca-admin/campaigns/{id}',
    [AdminCampaignController::class, 'show'],
    ['store', 'auth', 'permission:newsletter.campaigns.view']);

$router->post('/commerce/ca-admin/campaigns/{id}',
    [AdminCampaignController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:newsletter.campaigns.manage']);

// =====================================================================
// Admin forms (Stage 009)
// =====================================================================

$router->get('/commerce/ca-admin/forms',
    [AdminFormController::class, 'index'],
    ['store', 'auth', 'permission:forms.view']);

$router->get('/commerce/ca-admin/forms/create',
    [AdminFormController::class, 'showCreate'],
    ['store', 'auth', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms',
    [AdminFormController::class, 'store'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->get('/commerce/ca-admin/forms/{id}/edit',
    [AdminFormController::class, 'showEdit'],
    ['store', 'auth', 'permission:forms.view']);

$router->get('/commerce/ca-admin/forms/{id}/delete-info',
    [AdminFormController::class, 'deleteInfo'],
    ['store', 'auth', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/publish',
    [AdminFormController::class, 'publish'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/unpublish',
    [AdminFormController::class, 'unpublish'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/archive',
    [AdminFormController::class, 'archive'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/duplicate',
    [AdminFormController::class, 'duplicate'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/delete',
    [AdminFormController::class, 'destroy'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}',
    [AdminFormController::class, 'update'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

// ---------------------------------------------------------------------
// Admin forms: field operations (Stage 009 Session 2b)
// ---------------------------------------------------------------------

$router->post('/commerce/ca-admin/forms/{id}/fields',
    [AdminFormController::class, 'fieldStore'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/fields/{fieldId}/delete',
    [AdminFormController::class, 'fieldDelete'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/fields/{fieldId}/move',
    [AdminFormController::class, 'fieldMove'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

$router->post('/commerce/ca-admin/forms/{id}/fields/{fieldId}',
    [AdminFormController::class, 'fieldUpdate'],
    ['store', 'auth', 'csrf', 'permission:forms.manage']);

// ---------------------------------------------------------------------
// Admin forms: submissions (Stage 009 Session 4)
// ---------------------------------------------------------------------
//
// Route ordering matters here. The export.csv path ends in a
// literal segment that a {sid} wildcard would otherwise
// capture. Registering export before the detail route means
// /submissions/export.csv reaches the export handler and does
// not get routed to submissionDetail with sid=0.
//
// Same reasoning applies to the bulk path, though the bulk
// path is POST-only so it does not collide with the GET
// detail route. The order below groups them by method for
// readability.

$router->get('/commerce/ca-admin/forms/{id}/submissions/export.csv',
    [AdminFormController::class, 'submissionsExport'],
    ['store', 'auth', 'permission:forms.submissions.view']);

$router->get('/commerce/ca-admin/forms/{id}/submissions',
    [AdminFormController::class, 'submissions'],
    ['store', 'auth', 'permission:forms.submissions.view']);

$router->post('/commerce/ca-admin/forms/{id}/submissions/bulk',
    [AdminFormController::class, 'submissionsBulk'],
    ['store', 'auth', 'csrf', 'permission:forms.submissions.manage']);

$router->post('/commerce/ca-admin/forms/{id}/submissions/{sid}/status',
    [AdminFormController::class, 'submissionStatus'],
    ['store', 'auth', 'csrf', 'permission:forms.submissions.manage']);

$router->post('/commerce/ca-admin/forms/{id}/submissions/{sid}/delete',
    [AdminFormController::class, 'submissionDelete'],
    ['store', 'auth', 'csrf', 'permission:forms.submissions.manage']);

$router->get('/commerce/ca-admin/forms/{id}/submissions/{sid}',
    [AdminFormController::class, 'submissionDetail'],
    ['store', 'auth', 'permission:forms.submissions.view']);

// =====================================================================
// Developer debug endpoints (pipeline commit 5/5)
// =====================================================================
//
// The controller itself 404s when app.debug is false, so the
// route is safe to register in every environment. The overlay
// in the admin layout fetches it after page load and displays
// any recent server-side exceptions.
//
// The permission gate reuses core.audit.view: the audience
// that reads the audit log is the audience that reads the
// exception log.
$router->get('/commerce/ca-admin/_debug/recent-errors',
    [DebugController::class, 'recentErrors'],
    ['store', 'auth', 'permission:core.audit.view']);

// =====================================================================
// Logs hub (Stage 014)
// =====================================================================
//
// One screen, four tabs. The merchant reads the Activity Log
// tab (core_activities). The developer reads System Logs
// (storage/logs/app.log) and Audit Trail (core_audit_logs).
//
// Two routes: /logs (defaults to Overview) and
// /logs/{tab}. Both go to the same action. Route order
// matters - the specific /logs path is registered first so
// the router does not try to match an empty {tab}.
$router->get('/commerce/ca-admin/logs',
    [LogController::class, 'index'],
    ['store', 'auth', 'permission:core.audit.view']);

$router->get('/commerce/ca-admin/logs/{tab}',
    [LogController::class, 'index'],
    ['store', 'auth', 'permission:core.audit.view']);

// =====================================================================
// Webhooks - payment providers
// =====================================================================
//
//   POST /webhooks/stripe
//   POST /webhooks/paypal
//
// No auth middleware. No CSRF. The signature header is the
// only security boundary; WebhookController::handle() calls
// the provider's verifyWebhook() and rejects the request
// with 400 on any mismatch.
//
// The 'store' middleware resolves the store from the request
// hostname (commerce_store_domains) or a ?store= query
// parameter. Every repository the handler touches scopes by
// store_id, so the store must be resolved before the
// controller runs.
//
// The Kernel attaches the audit middleware to every POST
// automatically. Every webhook delivery lands in
// core_audit_logs without the route declaring it.
$router->post('/webhooks/stripe',
    [WebhookController::class, 'stripe'],
    ['store']);

$router->post('/webhooks/paypal',
    [WebhookController::class, 'paypal'],
    ['store']);

$router->post('/webhooks/mpesa',
    [WebhookController::class, 'mpesa'],
    ['store']);

$router->post('/webhooks/pesapal',
    [WebhookController::class, 'pesapal'],
    ['store']);

$router->post('/webhooks/flutterwave',
    [WebhookController::class, 'flutterwave'],
    ['store']);
