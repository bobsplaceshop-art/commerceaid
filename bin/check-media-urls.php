#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-media-urls.php - media URL resolution contract.
 *
 * The media route is /media/{uuid}/{variant}.{format}
 * (routes/commerce.php lines 57-61). The route key is the
 * UUID, not the numeric commerce_files.id. Any code that
 * builds a URL from a numeric id produces a 404, and the
 * image silently fails to render.
 *
 * This contract was broken three times:
 *   - hero.php        fixed c39922f
 *   - banner.php      fixed in the same commit as promo-panel
 *   - promo-panel.php fixed in the same commit as banner
 *
 * The two canonical ways to produce a media URL:
 *
 *   1. A helper from app/Helpers/functions.php:
 *        store_logo_url()
 *        store_favicon_url()
 *        product_image_url()
 *        product_picture_url()
 *        category_image_url()
 *        category_picture_url()
 *
 *   2. MediaItem::variantUrl() (through MediaRepository)
 *
 * Plus, for page-builder blocks: the renderer resolves
 * image_file_id to a UUID URL and passes it as bg_url.
 * Partials use bg_url, never build a URL themselves.
 *
 * Rules:
 *
 *   1. No literal slash-media-slash concatenation in app/
 *      or resources/. The scanner looks for a string literal
 *      "media/" followed by a concatenation operator and a
 *      variable. Any match is a failure.
 *
 *   2. Any view file under resources/views that renders an
 *      image from a config field must use bg_url, never a
 *      numeric id.
 *
 * Exit codes:
 *   0 - no violations
 *   1 - at least one violation
 *
 * Usage:
 *   php bin/check-media-urls.php
 *   composer prevenger
 */

$root = dirname(__DIR__);

$scanDirs = [
    $root . '/app',
    $root . '/resources',
];

// The bug shape: a string literal containing '/media/',
// followed by a concatenation operator and a variable.
$pattern = '/["\']\/media\/["\']\s*\.\s*\$[a-zA-Z_]/';

$errors = [];
$checked = 0;

function scan_file(string $path, string $pattern): array
{
    $src = file_get_contents($path);
    if ($src === false) return [];

    $hits = [];
    if (preg_match_all($pattern, $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $match) {
            $line = substr_count(substr($src, 0, $match[1]), "\n") + 1;
            $hits[] = ['line' => $line, 'text' => trim($match[0])];
        }
    }
    return $hits;
}

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) continue;
        if (!preg_match('/\.(php|js)$/', $file->getFilename())) continue;

        $checked++;
        $path = $file->getPathname();
        $hits = scan_file($path, $pattern);

        if ($hits === []) continue;

        $rel = str_replace($root . '/', '', $path);
        foreach ($hits as $hit) {
            $errors[] = $rel . ':' . $hit['line'] . '  ' . $hit['text'];
        }
    }
}

echo "Checking media URL resolution...\n";
echo "  Files scanned: {$checked}\n";
echo "  Violations:    " . count($errors) . "\n\n";

if ($errors !== []) {
    foreach ($errors as $e) {
        echo "  {$e}\n";
    }
    echo "\nFAILED: " . count($errors) . " literal media URL(s) found.\n";
    echo "Use a helper (product_picture_url, etc.), bg_url, or\n";
    echo "MediaItem::variantUrl(). See docs/REMEMBER.md Pattern 46.\n";
    exit(1);
}

echo "OK - no literal slash-media-slash URL.\n";
exit(0);
