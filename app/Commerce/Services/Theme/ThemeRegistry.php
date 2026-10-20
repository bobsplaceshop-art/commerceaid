<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Theme;

/**
 * Discovers themes from the themes/ directory.
 * A theme is a folder with a theme.json manifest.
 */
final class ThemeRegistry
{
    /** @var array<int, array<string, mixed>> */
    private array $themes = [];
    private bool $loaded = false;

    public function __construct(private string $themesDir)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $this->load();
        return $this->themes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        $this->load();
        foreach ($this->themes as $theme) {
            if (($theme['slug'] ?? '') === $slug) {
                return $theme;
            }
        }
        return null;
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        if (!is_dir($this->themesDir)) {
            return;
        }

        $folders = scandir($this->themesDir) ?: [];
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..' || $folder === '') {
                continue;
            }
            $manifest = $this->themesDir . '/' . $folder . '/theme.json';
            if (!is_file($manifest)) {
                continue;
            }
            $json = file_get_contents($manifest);
            if ($json === false) {
                continue;
            }
            $data = json_decode($json, true);
            if (!is_array($data)) {
                continue;
            }
            // Ensure slug defaults to folder name if not in manifest
            if (empty($data['slug'])) {
                $data['slug'] = $folder;
            }
            $this->themes[] = $data;
        }

        // Sort alphabetically by name
        usort($this->themes, fn($a, $b) => strcmp($a['name'] ?? '', $b['name'] ?? ''));
    }
}
