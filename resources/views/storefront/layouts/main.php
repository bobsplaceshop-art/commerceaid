<?php
/**
 * Storefront layout.
 *
 * The public-facing shell. Distinct from layouts/app.php which
 * serves the admin. Same View pipeline, different chrome.
 *
 * Context (from StorefrontController):
 *   $store    ?Store  the resolved store, or null
 *   $title    string  page title
 *   $content  string  the rendered view
 *
 * Context (added by this layout):
 *   $brandName     string  display name
 *   $flashSuccess  ?string
 *   $flashError    ?string
 *
 * Flash handling: same pattern as the admin layout. Layout reads
 * flash via the global flash() helper, which consumes on read.
 * Views never touch flash themselves.
 *
 * Typography:
 *   Display serif  - Playfair Display
 *   Interface sans - Inter
 *   Script accent  - Dancing Script
 *   Loaded from Google Fonts with preconnect for faster first paint.
 *
 * Stylesheets (loaded in this order):
 *   1. storefront.css          core: variables, header, footer, hero
 *   2. storefront-home.css     homepage sections
 *   3. storefront-catalog.css  product grid, filters, product card
 *   4. storefront-product.css  product detail page
 *
 * Theme: the storefront is always light. No data-theme attribute.
 * The admin dark mode toggle does not affect the public site.
 */

$store     = $store     ?? null;
$title     = $title     ?? '';
$cartCount = $cartCount ?? 0;
$content = $content ?? '';

$flashSuccess = function_exists('flash') ? flash('success') : null;
$flashError   = function_exists('flash') ? flash('error')   : null;
$hasFlash     = ($flashSuccess !== null || $flashError !== null);

$branding   = function_exists('config') ? (array) config('branding', []) : [];
$configName = $branding['product_name'] ?? 'Anaya Global';

$brandName = ($store !== null && !empty($store->name))
    ? (string) $store->name
    : $configName;

$pageTitle = $title !== '' ? ($title . ' - ' . $brandName) : $brandName;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Dancing+Script:wght@400;600&display=swap">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <link href="<?= versioned_asset('css/storefront.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-home.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-catalog.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-product.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-auth.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-cart.css') ?>" rel="stylesheet">
    <link href="<?= versioned_asset('css/storefront-checkout.css') ?>" rel="stylesheet">
</head>
<body class="sf-body">

    <?= view_partial('storefront/partials/utility-bar', [
        'store' => $store,
    ]) ?>

    <?= view_partial('storefront/partials/header', [
        'store'     => $store,
        'brandName' => $brandName,
        'cartCount' => $cartCount,
    ]) ?>

    <?php if ($hasFlash): ?>
        <div class="sf-flash-wrap">
            <?php if ($flashSuccess !== null): ?>
                <div class="sf-alert sf-alert-success"><?= e((string) $flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError !== null): ?>
                <div class="sf-alert sf-alert-error"><?= e((string) $flashError) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <main class="sf-main">
        <?= $content ?>
    </main>

    <?= view_partial('storefront/partials/footer', [
        'store'     => $store,
        'brandName' => $brandName,
    ]) ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    /* ================================================================
     * Mini-cart JS
     * ================================================================
     * Progressive enhancement for the storefront cart. If this
     * script fails to load, the cart forms still work as plain
     * POSTs. Nothing here is required for functionality.
     *
     * Behavior:
     *   - Intercepts form submissions on [data-sf-add-to-cart]
     *   - POSTs via fetch with Accept: application/json
     *   - Updates the header badge from the JSON response
     *   - Shows a small toast notification
     *   - Dispatches a 'cart:updated' CustomEvent for any other
     *     script that wants to react
     *   - On page load, fetches /cart/count to correct a stale
     *     server-rendered badge (e.g., after the customer edits
     *     the cart in another tab)
     *
     * The server may return either a JSON response (when Accept
     * is application/json) or an HTML redirect (when it isn't).
     * This script always sends the JSON Accept header, so it
     * always gets JSON back.
     * ================================================================ */
    (function () {
        'use strict';

        // ---------- Badge update ----------
        function updateCartBadge(count) {
            var badges = document.querySelectorAll('[data-sf-cart-count]');
            for (var i = 0; i < badges.length; i++) {
                var el = badges[i];
                var prev = parseInt(el.getAttribute('data-sf-cart-count'), 10) || 0;

                el.textContent = count;
                el.setAttribute('data-sf-cart-count', String(count));

                if (count > 0) {
                    el.classList.remove('is-empty');
                    el.hidden = false;
                } else {
                    el.classList.add('is-empty');
                    el.hidden = true;
                }

                // Pulse animation when count increases
                if (count > prev) {
                    el.classList.add('is-pulse');
                    setTimeout(function () {
                        el.classList.remove('is-pulse');
                    }, 600);
                }
            }
        }

        // ---------- Toast ----------
        var toastTimer = null;
        function showToast(message, kind) {
            kind = kind || 'success';
            var existing = document.getElementById('sf-cart-toast');
            if (existing) existing.remove();
            if (toastTimer) clearTimeout(toastTimer);

            var toast = document.createElement('div');
            toast.id = 'sf-cart-toast';
            toast.className = 'sf-cart-toast sf-cart-toast-' + kind;
            toast.textContent = message;
            document.body.appendChild(toast);

            // Trigger the entrance animation on the next frame
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    toast.classList.add('is-visible');
                });
            });

            toastTimer = setTimeout(function () {
                toast.classList.remove('is-visible');
                setTimeout(function () {
                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                }, 300);
            }, 2200);
        }

        // ---------- Custom event ----------
        function dispatchCartUpdated(count) {
            try {
                document.dispatchEvent(new CustomEvent('cart:updated', {
                    detail: { count: count }
                }));
            } catch (e) {
                // CustomEvent not supported (very old browsers). Silent.
            }
        }

        // ---------- Form interception ----------
        function interceptForm(form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                var url = form.getAttribute('action') || '/cart/add';
                var formData = new FormData(form);

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                .then(function (r) {
                    if (!r.ok) throw new Error('Request failed: ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (data && typeof data.count === 'number') {
                        updateCartBadge(data.count);
                        dispatchCartUpdated(data.count);
                    }
                    if (data && data.ok) {
                        showToast(data.message || 'Added to cart', 'success');
                    } else {
                        showToast((data && data.message) || 'Something went wrong', 'error');
                    }
                })
                .catch(function () {
                    showToast('Could not update cart. Try refreshing.', 'error');
                });
            });
        }

        // ---------- Wire up ----------
        function init() {
            var forms = document.querySelectorAll('[data-sf-add-to-cart]');
            for (var i = 0; i < forms.length; i++) {
                interceptForm(forms[i]);
            }

            // Refresh the badge on page load to catch drift
            // (customer edited cart in another tab, etc.)
            fetch('/cart/count', {
                method: 'GET',
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && typeof data.count === 'number') {
                    updateCartBadge(data.count);
                }
            })
            .catch(function () {
                // Server unavailable or endpoint down. Silent -
                // the server-rendered badge stays as-is.
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>

    <?php if (function_exists('config') && config('app.debug')): ?>
    <script>
    /* Dev-mode error overlay. Only renders when app.debug is on.
       Catches uncaught errors, unhandled promise rejections, and
       explicit console.error calls. Displays them in a dismissible
       corner overlay so client-side issues surface without opening
       DevTools. Zero cost when debug is off. */
    (function () {
        'use strict';
        var MAX_ITEMS = 5;
        var TTL_MS = 8000;
        var container = null;

        function ensureContainer() {
            if (container) return container;
            container = document.createElement('div');
            container.id = 'ra-debug-overlay';
            container.setAttribute('style',
                'position:fixed;top:10px;right:10px;z-index:2147483647;' +
                'max-width:420px;font-family:monospace;font-size:12px;'
            );
            document.body.appendChild(container);
            return container;
        }

        function show(kind, message, detail) {
            var c = ensureContainer();
            var item = document.createElement('div');
            item.setAttribute('style',
                'background:' + (kind === 'error' ? '#7f1d1d' : '#78350f') + ';' +
                'color:#fff;padding:8px 12px;margin-bottom:6px;border-radius:4px;' +
                'box-shadow:0 2px 8px rgba(0,0,0,0.4);word-break:break-word;'
            );
            item.textContent = '[' + kind + '] ' + message +
                (detail ? ' - ' + detail : '');
            c.appendChild(item);

            while (c.children.length > MAX_ITEMS) {
                c.removeChild(c.firstChild);
            }

            setTimeout(function () {
                if (item.parentNode) item.parentNode.removeChild(item);
            }, TTL_MS);
        }

        window.addEventListener('error', function (e) {
            show('error', e.message || 'unknown',
                (e.filename || '') + ':' + (e.lineno || ''));
        });

        window.addEventListener('unhandledrejection', function (e) {
            show('error', 'Unhandled promise rejection',
                e.reason ? String(e.reason) : '');
        });

        var origError = console.error;
        console.error = function () {
            try {
                show('error', Array.prototype.map.call(arguments, String).join(' '), '');
            } catch (ignore) {}
            return origError.apply(console, arguments);
        };
    })();
    </script>
    <?php endif; ?>
<?php if (!empty($cookieBannerEnabled)): ?>
    <?= view_partial('storefront.partials._cookie-banner', ['store' => $store]) ?>
    <link href="<?= versioned_asset('css/storefront-cookie-banner.css') ?>" rel="stylesheet">
    <script src="<?= versioned_asset('js/cookie-consent.js') ?>" defer></script>
<?php endif; ?>

</body>
</html>
