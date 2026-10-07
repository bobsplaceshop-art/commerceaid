<?php
declare(strict_types=1);

namespace CorporateAid\Core;

use RuntimeException;

/**
 * Renders PHP templates from resources/views/.
 * Views are plain PHP files. Layouts wrap views; partials are fragments.
 */
final class View
{
    private string $viewPath;
    private Config $config;
    /** @var array<string,mixed> */
    private array $shared = [];

    public function __construct(string $viewPath, Config $config)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->config = $config;
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
     * The parameter names here ($viewName, $viewData) deliberately do
     * not match common view-variable names. Before this rename, a
     * view that read `$name` from its render data received the
     * view's dot-notation name instead, because `extract()` uses
     * EXTR_SKIP and would not overwrite the parameter `$name`
     * already in scope. Renaming the parameter removes that
     * collision class entirely.
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

        $file = $this->viewPath . '/' . $name . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$name} (looked in {$file})");
        }

        return $file;
    }
}
