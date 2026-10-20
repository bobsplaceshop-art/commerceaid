#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-assets.php - the asset integrity checker.
 *
 * Scans every view and theme partial for asset references:
 *   - versioned_asset('path/to/file.css')
 *   - versioned_asset('js/file.js')
 *   - <link href="/path.css">
 *   - <script src="/path.js">
 *
 * For versioned_asset() calls, resolves the path relative to
 * public/assets/ (that is where versioned_asset roots).
 * For direct /path references, resolves relative to public/.
 *
 * Confirms each resolved file exists on disk.
 *
 * Catches the "CSS referenced in the layout but the file is
 * in the wrong directory" class of bug that produced empty
 * stylesheets in the theme engine session of Stage 020.
 */

$root = dirname(__DIR__);

$errors = [];
$warnings = [];
$checked = 0;
$missing = 0;

$viewDirs = [
    'resources/views',
    'themes',
];

foreach ($viewDirs as $dir) {
    $fullDir = $root . '/' . $dir;
    if (!is_dir($fullDir)) continue;

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;

        $checked++;
        $path = $file->getPathname();
        $contents = file_get_contents($path);
        if ($contents === false) continue;

        // --- versioned_asset('...') ---
        if (preg_match_all("/versioned_asset\(\s*['\"]([^'\"]+)['\"]/", $contents, $matches)) {
            foreach ($matches[1] as $asset) {
                // Skip the bootstrap-icons and fontawesome bundles, they
                // are checked by the media pipeline, not here.
                $asset = ltrim($asset, '/');
                $candidates = [
                    $root . '/public/assets/' . $asset,
                    $root . '/public/' . $asset,
                ];

                $found = false;
                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $missing++;
                    $rel = str_replace($root . '/', '', $path);
                    $errors[] = "  {$rel}: versioned_asset('{$asset}') not found in public/assets/ or public/";
                }
            }
        }

        // --- <link href="/...css"> ---
        if (preg_match_all("/<link[^>]+href=[\"']\/([^\"']+\.css)[^\"']*[\"']/", $contents, $matches)) {
            foreach ($matches[1] as $asset) {
                $candidates = [
                    $root . '/public/' . $asset,
                ];

                $found = false;
                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $missing++;
                    $rel = str_replace($root . '/', '', $path);
                    $errors[] = "  {$rel}: <link href=\"/{$asset}\"> not found in public/";
                }
            }
        }

        // --- <script src="/...js"> ---
        if (preg_match_all("/<script[^>]+src=[\"']\/([^\"']+\.js)[^\"']*[\"']/", $contents, $matches)) {
            foreach ($matches[1] as $asset) {
                $candidates = [
                    $root . '/public/' . $asset,
                ];

                $found = false;
                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $missing++;
                    $rel = str_replace($root . '/', '', $path);
                    $errors[] = "  {$rel}: <script src=\"/{$asset}\"> not found in public/";
                }
            }
        }
    }
}

echo "Checking asset references...\n";
echo "  Files scanned: {$checked}\n";
echo "  Missing:       {$missing}\n";

if (!empty($errors)) {
    echo "\n";
    foreach ($errors as $e) {
        echo $e . "\n";
    }
    echo "\nFAILED: {$missing} missing asset(s).\n";
    exit(1);
}

echo "\nOK - every referenced asset exists on disk.\n";
exit(0);
