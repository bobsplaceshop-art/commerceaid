#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-responsive.php - responsive layout contract check.
 *
 * Stage 014 established the responsive layout system:
 * four breakpoints, two component treatments for tables,
 * a card-based list view at mobile. This checker enforces
 * the parts of that system that are statically detectable.
 *
 * Three rules:
 *
 *   1. Inline <style> blocks in views must not contain
 *      @media queries. Media queries belong in the shared
 *      stylesheets (main-v2.css, setup.css, storefront*.css).
 *      Moved from bin/check-css.php Pass 3.
 *
 *   2. Views must not hardcode pixel widths above 375px on
 *      inline style attributes. A fixed width above the
 *      platform's smallest target prevents the element from
 *      shrinking below the viewport.
 *
 *   3. <table> elements in admin views must sit inside a
 *      wrapper that carries data-responsive="scroll" or
 *      data-responsive="cards". The attribute is the
 *      declarative treatment. Moved from bin/check-html.php
 *      W1, now a hard error rather than a warning.
 *
 * The storefront is not subject to rule 3. Storefront tables
 * use per-table styling rather than the treatment system.
 *
 * Exit codes:
 *   0 - no violations
 *   1 - at least one violation
 *
 * Usage:
 *   php bin/check-responsive.php
 *   composer responsive
 */

$root = dirname(__DIR__);
$viewsDir = $root . '/resources/views';

$errors = [];
$checked = 0;

function extract_style_blocks(string $path): array
{
    $src = file_get_contents($path);
    if ($src === false) return [];

    $blocks = [];
    $offset = 0;

    while (true) {
        $open = stripos($src, '<style', $offset);
        if ($open === false) break;

        $gt = strpos($src, '>', $open);
        if ($gt === false) break;

        $close = stripos($src, '</style>', $gt);
        if ($close === false) break;

        $css = substr($src, $gt + 1, $close - ($gt + 1));
        $lineStart = substr_count(substr($src, 0, $open), "\n") + 1;

        $blocks[] = ['line_start' => $lineStart, 'css' => $css];
        $offset = $close + strlen('</style>');
    }

    return $blocks;
}

function table_has_responsive_wrapper(string $src, int $offset): bool
{
    $lookbackStart = max(0, $offset - 400);
    $lookback = substr($src, $lookbackStart, $offset - $lookbackStart);

    if (preg_match_all('/<div\b[^>]*>/i', $lookback, $divMatches)) {
        $lastDiv = end($divMatches[0]);
        if (str_contains($lastDiv, 'data-responsive')) {
            return true;
        }
    }

    return false;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($viewsDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'php') continue;

    $path = $file->getPathname();
    $src = file_get_contents($path);
    if ($src === false) continue;
    $checked++;

    $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
    $rel = str_replace('\\', '/', $rel);

    // --- Rule 1 ---
    $blocks = extract_style_blocks($path);
    foreach ($blocks as $block) {
        if (preg_match('/@media\b/', $block['css'])) {
            $errors[] = [
                'file'    => $rel,
                'line'    => $block['line_start'],
                'message' => "Inline <style> contains @media. Media queries belong in a shared stylesheet.",
            ];
        }
    }

    // --- Rule 2 ---
    if (preg_match_all('/style="([^"]*)"/', $src, $styleMatches, PREG_OFFSET_CAPTURE)) {
        foreach ($styleMatches[0] as $i => $match) {
            $style = $styleMatches[1][$i][0];
            $offset = $match[1];
            $line = substr_count(substr($src, 0, $offset), "\n") + 1;

            if (preg_match_all('/(?<![a-z-])(min-)?width\s*:\s*([0-9.]+)\s*px/i', $style, $widthMatches, PREG_SET_ORDER)) {
                foreach ($widthMatches as $wm) {
                    $value = (float) $wm[2];
                    if ($value > 375) {
                        $errors[] = [
                            'file'    => $rel,
                            'line'    => $line,
                            'message' => "Inline style sets width: {$value}px. Above 375px, use max-width or a percentage.",
                        ];
                    }
                }
            }
        }
    }

    // --- Rule 3 ---
    if (str_starts_with($rel, 'resources/views/admin/')) {
        if (preg_match_all('/<table\b[^>]*>/i', $src, $tableMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($tableMatches[0] as $match) {
                $offset = $match[1];
                if (table_has_responsive_wrapper($src, $offset)) continue;

                $line = substr_count(substr($src, 0, $offset), "\n") + 1;
                $errors[] = [
                    'file'    => $rel,
                    'line'    => $line,
                    'message' => "<table> is not inside a data-responsive wrapper. Add data-responsive=\"scroll\" or data-responsive=\"cards\".",
                ];
            }
        }
    }
}

echo "Checking responsive contract...\n";
echo "  Views checked: {$checked}\n";
echo "  Errors:        " . count($errors) . "\n";

if ($errors === []) {
    echo "\nOK - responsive contract is clean.\n";
    exit(0);
}

echo "\n";
foreach ($errors as $err) {
    echo "  {$err['file']}:{$err['line']}\n";
    echo "    {$err['message']}\n";
}

echo "\nFAILED: " . count($errors) . " error(s).\n";
exit(1);
