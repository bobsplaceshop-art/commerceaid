<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use RuntimeException;

/**
 * Renders PHP templates from resources/views/.
 * Views are plain PHP files. Layouts wrap views; partials are fragments.
 *
 * Theme support: when themePath is set (from App.php), views
 * starting with 'themes/' resolve from the themes/ directory.
 * Storefront partials (header, footer, utility-bar) resolve from
 * themes/{activeTheme}/partials/ first, then fall back to the
 * shared resources/views/storefront/partials/.
 */
final class View
{
    private string $viewPath;
    private ?string $themePath = null;
    private ?string $activeTheme = null;
    private Config $config;
    /** @var array<string,mixed> */
    private array $shared = [];

    public function __construct(string $viewPath, Config $config, ?string $themePath = null)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->config = $config;
        $this->themePath = $themePath !== null ? rtrim($themePath, '/\\') : null;
    }

    /**
     * Set the active theme slug so theme-overridable partials
     * (header, footer, utility-bar) resolve from themes/{slug}/partials/
     * before falling back to resources/views/storefront/partials/.
     */
    public function setActiveTheme(?string $themeSlug): void
    {
        $this->activeTheme = $themeSlug;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $values */
    public function shareMany(array $values): void
    {
        foreach ($values as $k => $v) {
            $this->shared[$k] = $v;
        }
    }

    /**
     * Render a view. If $layout is given, the view's output is passed
     * into the layout as $content.
     */
    /** @param array<string,mixed> $data */
    public function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = $this->renderFile($view, $data);

        if ($layout !== null) {
            return $this->renderFile($layout, array_merge($data, ['content' => $content]));
        }

        return $content;
    }

    /**
     * Render a single view file.
     *
     * @param array<string,mixed> $viewData
     */
    private function renderFile(string $viewName, array $viewData): string
    {
        $file = $this->resolvePath($viewName);

        $vars = array_merge($this->shared, $viewData);
        extract($vars, EXTR_SKIP);

        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Turn 'auth/login' into 'resources/views/auth/login.php'.
     * Accepts either 'auth/login' or 'auth/login.php'.
     */
    private function resolvePath(string $name): string
    {
        // Convert dot notation to slashes: 'dashboard.index' → 'dashboard/index'
        $name = str_replace('.', '/', $name);

        // Strip a trailing '.php' properly (if present).
        if (str_ends_with($name, '.php')) {
            $name = substr($name, 0, -4);
        }

        // Theme-aware partial resolution. If the view name is
        // 'storefront/partials/header', 'storefront/partials/footer',
        // or 'storefront/partials/utility-bar', check the theme's
        // partials/ directory first. Falls back to resources/views/
        // when the theme doesn't ship its own version.
        if ($this->themePath !== null && $this->activeTheme !== null) {
            $themeOverridablePartials = [
                'storefront/partials/header',
                'storefront/partials/footer',
                'storefront/partials/utility-bar',
            ];
            if (in_array($name, $themeOverridablePartials, true)) {
                $base = basename($name);
                $themeFile = $this->themePath . '/' . $this->activeTheme . '/partials/' . $base . '.php';
                if (is_file($themeFile)) {
                    return $themeFile;
                }
            }
        }

        // If the view name starts with 'themes/', look in the theme
        // root first. Falls through to the default viewPath so
        // partials inside a theme can still resolve normally.
        if ($this->themePath !== null && str_starts_with($name, 'themes/')) {
            $themeName = substr($name, strlen('themes/'));
            $themeFile = $this->themePath . '/' . $themeName . '.php';
            if (is_file($themeFile)) {
                return $themeFile;
            }
        }

        $file = $this->viewPath . '/' . $name . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$name} (looked in {$file})");
        }

        return $file;
    }
}
