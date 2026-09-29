<?php
/**
 * Account shell. Wraps every /account/* page.
 *
 * The controller renders this view (not the inner view). This
 * view renders the sidebar and then delegates to the inner view
 * via view_partial().
 *
 * Context:
 *   $_account_inner_view  string  dot-notation name of the inner view
 *   $customer             ?Customer
 *   $claimableCount       int     unread claimable orders (badge)
 *   ... plus whatever the inner view needs
 *
 * The inner view receives $data unchanged. The shell adds
 * sidebar context ($currentPath) only.
 */
$innerView = $_account_inner_view ?? null;
$customer  = $customer ?? null;

if ($innerView === null || $innerView === '') {
    echo '<div class="sf-container"><p>Account view not found.</p></div>';
    return;
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$claimableCount = (int) ($claimableCount ?? 0);

// Unread message count for the sidebar badge. Resolved once
// per request. Defensive: a broken notification read returns
// 0 rather than crashing the account page.
$unreadMessageCount = 0;
try {
    $__container = \CorporateAid\Core\App::instance()->container();
    $__customerAuth = $__container->get(
        \CorporateAid\Commerce\Services\CustomerAuthService::class
    );
    $__customer = $__customerAuth->user();
    if ($__customer !== null) {
        $__unreadSvc = $__container->get(
            \CorporateAid\Commerce\Services\CustomerNotificationService::class
        );
        $unreadMessageCount = $__unreadSvc->unreadCount($__customer->id);
    }
} catch (\Throwable) {
    $unreadMessageCount = 0;
}

// ---------------------------------------------------------------------
// Breadcrumbs
// ---------------------------------------------------------------------
// Build the trail from the current path. The first crumb is
// always "Account" (links to /account). Subsequent crumbs
// follow the URL segments with human-readable labels.
$crumbLabels = [
    'account'          => 'Account',
    'orders'           => 'My Orders',
    'claims'           => 'Claims',
    'addresses'        => 'Addresses',
    'create'           => 'New address',
    'edit'             => 'Edit',
    'password'         => 'Change password',
];

$segments = array_values(array_filter(explode('/', trim($currentPath, '/'))));

$crumbs = [];  // [ [label, href|null], ... ]
$acc = '';
foreach ($segments as $i => $seg) {
    $acc .= '/' . $seg;
    // Skip the base 'account' segment for the first crumb -
    // the trail starts with it explicitly.
    if ($seg === 'account' && $i === 0) {
        $crumbs[] = ['Account', '/account'];
        continue;
    }
    // Skip numeric segments (order id, address id). They are
    // represented by their parent crumb.
    if (ctype_digit($seg)) {
        continue;
    }
    $label = $crumbLabels[$seg] ?? ucfirst(str_replace('-', ' ', $seg));
    // The last crumb is the current page and is not linked.
    $href = ($i === count($segments) - 1) ? null : $acc;
    $crumbs[] = [$label, $href];
}

// Drop the final crumb if it duplicates the previous one
// (e.g. /account/account). Edge case for the dashboard.
if (count($crumbs) > 1) {
    $last = $crumbs[count($crumbs) - 1];
    $prev = $crumbs[count($crumbs) - 2];
    if ($last[0] === $prev[0]) {
        array_pop($crumbs);
        // The last remaining is the current page - unlink it.
        $crumbs[count($crumbs) - 1][1] = null;
    }
}
?>
<div class="sf-account-shell" data-sf-page="account">

    <div class="sf-account-shell-container">

        <aside class="sf-account-sidebar">
            <?= view_partial('storefront.account._sidebar', [
                'currentPath'        => $currentPath,
                'claimableCount'     => $claimableCount,
                'unreadMessageCount' => $unreadMessageCount,
            ]) ?>
        </aside>

        <main class="sf-account-main">
            <?php if (count($crumbs) > 1): ?>
                <nav class="sf-account-breadcrumbs" aria-label="Breadcrumb">
                    <?php foreach ($crumbs as $idx => [$label, $href]): ?>
                        <?php if ($idx > 0): ?>
                            <span class="sf-account-breadcrumbs-sep" aria-hidden="true">&rsaquo;</span>
                        <?php endif; ?>
                        <?php if ($href !== null && $idx < count($crumbs) - 1): ?>
                            <a href="<?= e($href) ?>"><?= e($label) ?></a>
                        <?php else: ?>
                            <span aria-current="page"><?= e($label) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?= view_partial($innerView, get_defined_vars()) ?>
        </main>

    </div>

</div>
