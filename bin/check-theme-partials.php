#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-theme-partials.php - the theme partial renderer.
 *
 * For each themes/{slug}/partials/*.php file, include it in
 * an isolated buffer with a stub $data array and confirm the
 * rendered output is non-empty (after trimming whitespace and
 * PHP-only comments).
 *
 * Catches the "theme partial renders blank" bug that produced
 * an empty header during Stage 020.
 *
 * PHP-only partials (a file that is only `<?php // comment`)
 * legitimately render as whitespace. Those are exempted.
 */

/**
 * Render a PHP file in an isolated buffer with the given data.
 */
function renderPartial(string $path, array $data): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    try {
        require $path;
    } catch (\Throwable $e) {
        ob_end_clean();
        return "RENDER ERROR: " . $e->getMessage();
    }
    return (string) ob_get_clean();
}

/**
 * Detect whether a PHP file has non-PHP content (real HTML
 * or anything that produces output when included).
 */
function hasOutputContent(string $contents): bool
{
    // Remove opening-and-closing PHP blocks first (a matched
    // pair renders nothing). Then strip any trailing unclosed
    // PHP block that runs to end-of-file.
    $stripped = preg_replace('/<\?php.*?\?>/s', '', $contents);
    $stripped = preg_replace('/<\?php.*$/s', '', $stripped);
    return trim((string) $stripped) !== '';
}

$root = dirname(__DIR__);
$themesDir = $root . '/themes';

if (!is_dir($themesDir)) {
    echo "No themes/ directory found.\n";
    exit(0);
}

$errors = [];
$checked = 0;
$empty = 0;

$stubData = [
    'store'               => null,
    'storeName'           => 'Test Store',
    'brandName'           => 'Test Store',
    'cartCount'           => 0,
    'wishlistCount'       => 0,
    'navCategories'       => [],
    'flashSuccess'        => null,
    'flashError'          => null,
    'content'             => '',
    'title'               => 'Test',
    'storeNotice'         => '',
    'cookieBannerEnabled' => false,
    'wishlistProductIds'  => [],
];

$themes = scandir($themesDir);
if ($themes === false) {
    echo "Unable to read themes/ directory.\n";
    exit(1);
}

foreach ($themes as $theme) {
    if ($theme === '.' || $theme === '..') continue;
    if (!is_dir($themesDir . '/' . $theme)) continue;

    $partialsDir = $themesDir . '/' . $theme . '/partials';
    if (!is_dir($partialsDir)) continue;

    $files = scandir($partialsDir);
    if ($files === false) continue;

    foreach ($files as $file) {
        if (!str_ends_with($file, '.php')) continue;

        $checked++;
        $path = $partialsDir . '/' . $file;

        $contents = file_get_contents($path);
        if ($contents === false) continue;

        // Skip PHP-only partials (no output-producing HTML).
        if (!hasOutputContent($contents)) {
            continue;
        }

        $output  = renderPartial($path, $stubData);
        $trimmed = trim($output);

        if ($trimmed === '') {
            $empty++;
            $errors[] = "  themes/{$theme}/partials/{$file} rendered empty (expected HTML output)";
        }
    }
}

echo "Checking theme partials render non-empty...\n";
echo "  Partials checked: {$checked}\n";
echo "  Empty:            {$empty}\n";

if (!empty($errors)) {
    echo "\n";
    foreach ($errors as $e) {
        echo $e . "\n";
    }
    echo "\nFAILED: {$empty} empty partial(s).\n";
    exit(1);
}

echo "\nOK - every theme partial renders non-empty.\n";
exit(0);
