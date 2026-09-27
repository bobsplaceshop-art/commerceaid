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
