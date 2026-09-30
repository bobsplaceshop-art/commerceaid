<?php
declare(strict_types=1);

/**
 * Anaya Global - global helper functions.
 * Loaded automatically by Composer's autoloader.
 */

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return \CorporateAid\Core\App::instance()->container()->get('config')->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $suffix = ''): string
    {
        return \CorporateAid\Core\App::instance()->basePath($suffix);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('public_path')) {
    /**
     * Absolute filesystem path to the public directory, or a file
     * within it. Used by versioned_asset() to stat the file for
     * its modification time.
     */
    function public_path(string $suffix = ''): string
    {
        $base = \CorporateAid\Core\App::instance()->basePath('public');
        return $suffix !== ''
            ? $base . '/' . ltrim($suffix, '/')
            : $base;
    }
}

if (!function_exists('versioned_asset')) {
    /**
     * Same as asset(), but appends a ?v=<mtime> query string so
     * browsers refetch when the file changes on disk.
     *
     * Without this, CSS and JS files are cached aggressively and
     * browser hard-refresh is unreliable (Chrome's ETag cache can
     * serve stale content even after Ctrl+Shift+R). With it, every
     * file modification changes the URL, and the browser has no
     * choice but to fetch.
     *
     * Falls back to the plain asset URL if the file does not
     * exist (so a typo in a view doesn't fatal - it just renders
     * a 404 for that asset, which the dev overlay would catch).
     */
    function versioned_asset(string $path): string
    {
        $url = asset($path);
        try {
            $file = public_path('assets/' . ltrim($path, '/'));
            if (is_file($file)) {
                $url .= '?v=' . filemtime($file);
            }
        } catch (\Throwable) {
            // App not booted (rare). Fall through with unversioned URL.
        }
        return $url;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Csrf::class)
                ->token();
        } catch (\Throwable) {
            return '';
        }
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('recently_viewed_product_ids')) {
    /**
     * The current viewer's recently-viewed product IDs.
     *
     * Resolved once per request and cached in a static,
     * mirroring wishlist_product_ids(). Used by views that
     * want to render a "recently viewed" strip or highlight
     * already-viewed products.
     *
     * Guests read from their customer session history.
     * Logged-in customers read from commerce_recently_viewed.
     *
     * Returns an empty array on any failure.
     *
     * @return array<int, int>
     */
    function recently_viewed_product_ids(): array
    {
        /** @var array<int, int>|null $cache */
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $svc = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Commerce\Services\RecentViewService::class);
            $cache = $svc->productIds();
        } catch (\Throwable) {
            $cache = [];
        }

        return $cache;
    }
}

if (!function_exists('customer_recent_messages')) {
    /**
     * Recent unread messages for the current customer, newest
     * first. Used by the header bell dropdown to show a quick
     * snapshot without leaving the current page.
     *
     * Returns an empty array for guests, and an empty array
     * when the customer has no unread messages.
     *
     * Static-cached per request. The header renders on every
     * storefront page.
     *
     * Each item is a small associative array:
     *   [
     *     'id'        => int,
     *     'title'     => string,
     *     'category'  => 'system' | 'order' | 'promotion',
     *     'icon'      => string   Bootstrap icon class,
     *     'time'      => string   relative time,
     *     'link'      => ?string  optional URL,
     *   ]
     *
     * Using a plain array rather than the full
     * CustomerNotification model keeps the header light and
     * avoids coupling the view to the model.
     *
     * @return array<int, array{id:int,title:string,category:string,icon:string,time:string,link:?string}>
     */
    function customer_recent_messages(int $limit = 5): array
    {
        /** @var array<int, array<string,mixed>>|null $cache */
        static $cache = null;
        static $resolved = false;
        static $cachedLimit = 5;

        // If the limit changes between calls on the same
        // request, do not reuse a stale cache.
        if ($resolved && $cachedLimit === $limit) {
            /** @var array<int, array{id:int,title:string,category:string,icon:string,time:string,link:?string}> $c */
            $c = $cache ?? [];
            return $c;
        }

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $customerAuth = $container->get(
                \CorporateAid\Commerce\Services\CustomerAuthService::class
            );
            $customer = $customerAuth->user();
            if ($customer === null) {
                $cache = [];
                $resolved = true;
                $cachedLimit = $limit;
                return [];
            }

            $svc = $container->get(
                \CorporateAid\Commerce\Services\CustomerNotificationService::class
            );
            $list = $svc->unreadFor($customer->id, $limit);

            $out = [];
            foreach ($list as $n) {
                /** @var array{id:int,title:string,category:string,icon:string,time:string,link:?string} $row */
                $row = [
                    'id'       => (int) $n->id,
                    'title'    => (string) $n->title,
                    'category' => (string) $n->category,
                    'icon'     => (string) $n->icon(),
                    'time'     => (string) $n->relativeTime(),
                    'link'     => $n->link !== null ? (string) $n->link : null,
                ];
                $out[] = $row;
            }

            /** @var array<int, array{id:int,title:string,category:string,icon:string,time:string,link:?string}> $out */
            $cache = $out;
            $resolved = true;
            $cachedLimit = $limit;
            return $out;
        } catch (\Throwable) {
            $cache = [];
            $resolved = true;
            $cachedLimit = $limit;
            return [];
        }
    }
}

if (!function_exists('customer_unread_message_count')) {
    /**
     * Unread message count for the currently logged-in customer.
     *
     * Returns null when no customer is logged in. Returns an
     * int (possibly 0) when a customer is logged in.
     *
     * The null-vs-int distinction matters for the header bell:
     * a logged-in customer with zero unread messages shows the
     * bell with no badge; a guest shows no bell at all. Both
     * would return 0 if we did not distinguish.
     *
     * Static-cached per request. The header renders on every
     * storefront page, so this helper is called on every page.
     * Caching avoids a redundant container resolve and DB
     * query if anything else on the same page also needs the
     * count.
     *
     * Defensive: any failure returns null. A broken read hides
     * the bell rather than crashing the page.
     */
    function customer_unread_message_count(): ?int
    {
        static $cache = null;
        static $resolved = false;
        if ($resolved) {
            return $cache;
        }
        $resolved = true;

        try {
            $container = \CorporateAid\Core\App::instance()->container();
            $customerAuth = $container->get(
                \CorporateAid\Commerce\Services\CustomerAuthService::class
            );
            $customer = $customerAuth->user();
            if ($customer === null) {
                $cache = null;
                return null;
            }

            $svc = $container->get(
                \CorporateAid\Commerce\Services\CustomerNotificationService::class
            );
            $cache = $svc->unreadCount($customer->id);
            return $cache;
        } catch (\Throwable) {
            $cache = null;
            return null;
        }
    }
}

if (!function_exists('generate_uuid')) {
    /**
     * RFC 4122 v4 UUID.
     *
     * Pulled out of FileService so other services can produce
     * UUIDs without duplicating the generator. Used for file
     * storage names, and will be used for other identifiers
     * (import batch ids, API keys) as the codebase grows.
     *
     * Returns a canonical 8-4-4-4-12 string.
     */
    function generate_uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }
}

if (!function_exists('wishlist_product_ids')) {
    /**
     * The current viewer's wishlist product IDs.
     *
     * Resolved once per request and cached in a static. Views
     * that render many product cards (a 24-item catalog grid,
     * a home page with three product sections) call this once
     * per card - the static cache means the container resolve
     * and the DB query happen once total, not once per card.
     *
     * Guests read from their customer session wishlist.
     * Logged-in customers read from commerce_wishlists.
     *
     * Returns an empty array on any failure. A broken wishlist
     * read must not break a storefront page.
     *
     * @return array<int, int>
     */
    function wishlist_product_ids(): array
    {
        /** @var array<int, int>|null $cache */
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $svc = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Commerce\Services\WishlistService::class);
            $cache = $svc->productIds();
        } catch (\Throwable) {
            $cache = [];
        }

        return $cache;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Session::class)
                ->getFlash($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?\CorporateAid\Platform\Models\User
    {
        try {
            return \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\Auth::class)
                ->user();
        } catch (\Throwable) {
            return null;
        }
    }
}

if (!function_exists('view_partial')) {
    /**
     * Render a partial template. Returns HTML string.
     * Partials are expected to be self-contained (no layout).
     *
     * @param array<string,mixed> $data
     */
    function view_partial(string $name, array $data = []): string
    {
        try {
            /** @var \CorporateAid\Core\View $view */
            $view = \CorporateAid\Core\App::instance()
                ->container()
                ->get(\CorporateAid\Core\View::class);
            return $view->render($name, $data);
        } catch (\Throwable $e) {
            \CorporateAid\Platform\Support\Logger::error('view_partial(' . $name . ') failed: ' . $e->getMessage());
            return '';
        }
    }
}
