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
?>
<div class="sf-account-shell" data-sf-page="account">

    <div class="sf-account-shell-container">

        <aside class="sf-account-sidebar">
            <?= view_partial('storefront.account._sidebar', [
                'currentPath'    => $currentPath,
                'claimableCount' => $claimableCount,
            ]) ?>
        </aside>

        <main class="sf-account-main">
            <?= view_partial($innerView, get_defined_vars()) ?>
        </main>

    </div>

</div>
