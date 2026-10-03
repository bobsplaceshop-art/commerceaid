<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Fonts;

use CorporateAid\Commerce\Support\Fonts\FontCatalog;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Support\Logger;

/**
 * Downloads WOFF2 files for catalog fonts from Fontsource.
 *
 * Fontsource is a pure redistribution project that serves
 * the fonts from cdn.jsdelivr.net. It does no tracking.
 * The download is a one-time server-side fetch. After the
 * download the files live on the store's own disk. No
 * visitor request ever reaches Google or any CDN.
 *
 * Storage layout:
 *   storage/uploads/fonts/{slug}/{slug}-{weight}.woff2
 *
 * Every downloaded file gets a core_files row via
 * FileRepository::create(). SHA-256 deduplication applies:
 * if two stores on the same install download Inter, the
 * second download finds the existing hash and reuses the
 * row instead of writing a duplicate.
 *
 * The downloader does NOT go through FileService. That
 * service is built for HTTP uploads and calls
 * is_uploaded_file() which fails on server-side fetches.
 * The pipeline here is purpose-built for a server-initiated
 * download.
 *
 * Every per-weight failure is captured in the return
 * array, not thrown. The caller decides what to do. A
 * partial failure still returns successfully with errors
 * listed.
 *
 * See docs/stages/008g.4c_font-manager-plan.md (to be
 * written at stage close).
 */
final class FontDownloader
{
    private const TIMEOUT_SECONDS = 15;
    private const WOFF2_MAGIC = 'wOF2';

    public function __construct(
        private FileRepository $files,
        private string $basePath
    ) {
    }

    /**
     * Absolute disk path to the public assets/fonts
     * directory. Fonts are public, immutable, versioned by
     * slug+weight. Apache serves them directly. No route,
     * no controller. Same pattern as the placeholder SVGs.
     */
    public function fontsRoot(): string
    {
        return rtrim($this->basePath, '/\\')
            . '/public/assets/fonts';
    }

    /**
     * Absolute disk path to a slug's directory.
     */
    public function dirFor(string $slug): string
    {
        return $this->fontsRoot() . '/' . $this->safeSlug($slug);
    }

    /**
     * Absolute disk path for a specific (slug, weight) file.
     */
    public function pathFor(string $slug, string $weight): string
    {
        $slug = $this->safeSlug($slug);
        $weight = $this->safeWeight($weight);
        return $this->dirFor($slug) . '/' . $slug . '-' . $weight . '.woff2';
    }

    /**
     * The public URL that serves a (slug, weight) file. The
     * storefront layout emits this in @font-face rules.
     *
     * Not called by this class. Provided so the storefront
     * and admin preview resolve URLs the same way.
     */
    public function urlFor(string $slug, string $weight): string
    {
        return '/assets/fonts/'
            . rawurlencode($this->safeSlug($slug))
            . '/'
            . rawurlencode($this->safeSlug($slug) . '-' . $this->safeWeight($weight) . '.woff2');
    }

    /**
     * True when every weight of a font is already on disk.
     * Used by the admin to skip a re-download.
     */
    public function isDownloaded(string $slug): bool
    {
        if (FontCatalog::find($slug) === null) {
            return false;
        }

        foreach (FontCatalog::weightsFor($slug) as $weight) {
            if (!is_file($this->pathFor($slug, $weight))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Download every weight of a font.
     *
     * Idempotent: existing files are skipped, not refetched.
     * Partial failures are captured.
     *
     * @return array{
     *   slug: string,
     *   downloaded: int,
     *   cached: int,
     *   errors: array<int,string>
     * }
     */
    public function download(string $slug): array
    {
        $result = [
            'slug'       => $slug,
            'downloaded' => 0,
            'cached'     => 0,
            'errors'     => [],
        ];

        $font = FontCatalog::find($slug);
        if ($font === null) {
            $result['errors'][] = "Font '{$slug}' is not in the catalog.";
            return $result;
        }

        $dir = $this->dirFor($slug);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $result['errors'][] = "Could not create directory: {$dir}";
            return $result;
        }

        foreach (FontCatalog::weightsFor($slug) as $weight) {
            $path = $this->pathFor($slug, $weight);

            // Already on disk. Count as cached, no fetch.
            if (is_file($path)) {
                // Ensure a core_files row exists. If the
                // file is on disk but the row was deleted
                // (cleanup?), recreate it.
                $hash = hash_file('sha256', $path);
                if ($hash !== false && $this->files->findByHash($hash) === null) {
                    $this->recordFile($slug, $weight, $path, $hash);
                }
                $result['cached']++;
                continue;
            }

            $url = FontCatalog::woff2Url($slug, $weight);
            $bytes = $this->fetch($url);
            if ($bytes === null) {
                $result['errors'][] = "Fetch failed: {$url}";
                continue;
            }

            // Sanity: verify the WOFF2 magic bytes before
            // writing. A Fontsource 404 or a proxy error
            // page would otherwise be written as a "font".
            if (substr($bytes, 0, 4) !== self::WOFF2_MAGIC) {
                $result['errors'][] = "Not a WOFF2 file: {$url}";
                continue;
            }

            if (!@file_put_contents($path, $bytes)) {
                $result['errors'][] = "Write failed: {$path}";
                continue;
            }

            $hash = hash('sha256', $bytes);
            if ($this->files->findByHash($hash) === null) {
                $this->recordFile($slug, $weight, $path, $hash);
            }

            $result['downloaded']++;
        }

        return $result;
    }

    /**
     * Fetch a URL and return the body, or null on any
     * failure. Uses curl when available, falls back to
     * file_get_contents.
     */
    private function fetch(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => 'CommerceAid/FontDownloader',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FAILONERROR    => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false || $code !== 200 || !is_string($body)) {
                return null;
            }
            return $body;
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => self::TIMEOUT_SECONDS,
                'user_agent' => 'CommerceAid/FontDownloader',
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : null;
    }

    /**
     * Write a core_files row for a downloaded file.
     * Failures are logged but not thrown - the file is on
     * disk and that is what matters for the storefront.
     */
    private function recordFile(string $slug, string $weight, string $path, string $hash): void
    {
        try {
            $size = (int) @filesize($path);
            $this->files->create(
                uuid: generate_uuid(),
                originalName: $slug . '-' . $weight . '.woff2',
                storedName: basename($path),
                storagePath: 'public/assets/fonts/' . $slug . '/' . basename($path),
                mimeType: 'font/woff2',
                extension: 'woff2',
                sizeBytes: $size,
                sha256: $hash,
                uploadedBy: null
            );
        } catch (\Throwable $e) {
            Logger::warning('Font file could not be recorded', [
                'slug'   => $slug,
                'weight' => $weight,
                'path'   => $path,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reject anything in a slug that could escape the fonts
     * directory. The catalog slugs are hand-written and
     * already safe, but the downloader is defensive about
     * every path it constructs.
     */
    private function safeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $slug)) {
            throw new \InvalidArgumentException("Invalid font slug: {$slug}");
        }
        return $slug;
    }

    private function safeWeight(string $weight): string
    {
        $weight = (string) $weight;
        if (!preg_match('/^[1-9]00$/', $weight)) {
            throw new \InvalidArgumentException("Invalid weight: {$weight}");
        }
        return $weight;
    }
}
