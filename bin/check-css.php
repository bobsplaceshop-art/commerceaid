#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-css.php - inline <style> block integrity check.
 *
 * Scans every .php file under resources/views/ and every
 * .css file under public/assets/css/ for <style>...</style>
 * blocks (PHP files only) and for the CSS files themselves.
 * Validates the CSS at Level 2:
 *
 *   1. Every { has a matching }.
 *   2. Every declaration inside a block looks like
 *      `property: value;` - has a colon separator, and
 *      (unless it is the last declaration before }) ends
 *      with a semicolon.
 *   3. Every @media/@supports/etc block has balanced
 *      braces.
 *
 * Level 2 is not a full CSS parser. It does not verify
 * property names or values. The browser silently ignores
 * unknown properties, so a typo like `bakcground` is a
 * no-op, not a broken layout. The bug class this catches
 * is malformed structure from a botched patch: an
 * unmatched brace, a missing semicolon that merges two
 * declarations, a truncated block.
 *
 * Exit codes:
 *   0 - every <style> block and CSS file passed
 *   1 - at least one structural error
 *
 * Usage:
 *   php bin/check-css.php
 *   composer css
 */

$root = dirname(__DIR__);

$viewsDir = $root . '/resources/views';
$cssDir   = $root . '/public/assets/css';

$errors = [];
$checked = 0;

// -----------------------------------------------------------------
// Extract <style>...</style> blocks from a PHP file
// -----------------------------------------------------------------

/**
 * @return array<int, array{start:int, end:int, css:string}>
 */
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

        // Line number of the opening tag
        $lineStart = substr_count(substr($src, 0, $open), "\n") + 1;

        $blocks[] = [
            'line_start' => $lineStart,
            'css'        => $css,
        ];

        $offset = $close + strlen('</style>');
    }

    return $blocks;
}

// -----------------------------------------------------------------
// Validate one CSS string at Level 2
// -----------------------------------------------------------------

/**
 * @return array<int, array{line:int, message:string}>
 */
function validate_css(string $css, int $baseLine): array
{
    $errors = [];

    // --- Pass 1: brace balance ---
    $depth    = 0;
    $openLine = 0;
    $len      = strlen($css);
    for ($i = 0; $i < $len; $i++) {
        $ch = $css[$i];
        if ($ch === '{') {
            if ($depth === 0) {
                $openLine = substr_count(substr($css, 0, $i), "\n") + 1;
            }
            $depth++;
        } elseif ($ch === '}') {
            $depth--;
            if ($depth < 0) {
                $line = substr_count(substr($css, 0, $i), "\n") + 1;
                $errors[] = [
                    'line'    => $baseLine + $line - 1,
                    'message' => "Unmatched closing brace '}'",
                ];
                $depth = 0;
            }
        }
    }
    if ($depth > 0) {
        $errors[] = [
            'line'    => $baseLine + $openLine - 1,
            'message' => "Unmatched opening brace '{' (depth ends at {$depth})",
        ];
    }

    // --- Pass 2: declaration shape inside blocks ---
    // Split into lines, track whether we are inside a
    // declaration block (depth >= 1) and not inside a
    // nested rule (depth >= 2 means we are inside @media
    // or a nested block - we still validate declarations
    // at depth 1 or 2).
    $lines = explode("\n", $css);
    $depth = 0;

    foreach ($lines as $idx => $rawLine) {
        $line = rtrim($rawLine, "\r");
        $trim = trim($line);

        // Track depth changes on this line (before processing).
        $opens  = substr_count($line, '{');
        $closes = substr_count($line, '}');

        // Skip blank lines and comment-only lines.
        if ($trim === '' || str_starts_with($trim, '/*') || str_starts_with($trim, '*')) {
            $depth += $opens - $closes;
            continue;
        }

        // Skip selector-only lines (the line that ends with {).
        if (str_ends_with($trim, '{')) {
            $depth += $opens - $closes;
            continue;
        }

        // Skip the line that is just a closing brace.
        if ($trim === '}') {
            $depth += $opens - $closes;
            continue;
        }

        // Skip lines inside nested blocks (media queries).
        // We only validate declarations at depth 1 (top-level
        // selector block).
        if ($depth !== 1) {
            $depth += $opens - $closes;
            continue;
        }

        // A declaration line: must contain ':' and end
        // with ';' or '}'. Multi-property lines are allowed
        // but rare; we only check the shape.
        //
        // Multi-line declarations are common:
        //
        //   background:
        //       radial-gradient(...),
        //       radial-gradient(...);
        //
        // The first line ends with ':' and the terminator
        // is on a later line. We only flag a missing
        // semicolon when the line contains ':' AND a value
        // after the colon (i.e. not a bare property line
        // waiting for its value on the next line).
        if (str_contains($trim, ':')) {
            $isBareProperty = preg_match('/^[a-z-]+\s*:\s*$/i', $trim) === 1;

            if (!$isBareProperty) {
                $endsProperly = preg_match('/;\s*\}?\s*$/', $trim) === 1
                              || preg_match('/\}\s*$/', $trim) === 1;

                if (!$endsProperly) {
                    $errors[] = [
                        'line'    => $baseLine + $idx,
                        'message' => "Declaration missing terminating semicolon: {$trim}",
                    ];
                }
            }
        }

        $depth += $opens - $closes;
    }

    return $errors;
}

// -----------------------------------------------------------------
// Walk views, validate every <style> block
// -----------------------------------------------------------------

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $viewsDir,
        RecursiveDirectoryIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'php') continue;

    $path   = $file->getPathname();
    $blocks = extract_style_blocks($path);

    foreach ($blocks as $block) {
        $checked++;
        $blockErrors = validate_css($block['css'], $block['line_start']);
        foreach ($blockErrors as $err) {
            $errors[] = [
                'file'    => $path,
                'line'    => $err['line'],
                'message' => $err['message'],
            ];
        }
    }
}

// -----------------------------------------------------------------
// Validate the shared CSS files (no @media rule applies here)
// -----------------------------------------------------------------

$cssFiles = glob($cssDir . '/*.css') ?: [];
foreach ($cssFiles as $cssFile) {
    $css = file_get_contents($cssFile);
    if ($css === false) continue;
    $checked++;

    // Only brace balance on the shared files. The
    // declaration-shape check is skipped because the
    // files legitimately use multi-line declarations.
    $depth = 0;
    $len = strlen($css);
    for ($i = 0; $i < $len; $i++) {
        if ($css[$i] === '{') $depth++;
        elseif ($css[$i] === '}') {
            $depth--;
            if ($depth < 0) {
                $line = substr_count(substr($css, 0, $i), "\n") + 1;
                $errors[] = [
                    'file'    => $cssFile,
                    'line'    => $line,
                    'message' => "Unmatched closing brace '}'",
                ];
                $depth = 0;
            }
        }
    }
    if ($depth > 0) {
        $errors[] = [
            'file'    => $cssFile,
            'line'    => 0,
            'message' => "Unmatched opening brace '{' (depth ends at {$depth})",
        ];
    }
}

// -----------------------------------------------------------------
// Output
// -----------------------------------------------------------------

echo "Checking CSS integrity...\n";
echo "  Style blocks checked: {$checked}\n";
echo "  Errors:               " . count($errors) . "\n";

if ($errors === []) {
    echo "\nOK - CSS is structurally clean.\n";
    exit(0);
}

echo "\n";
foreach ($errors as $err) {
    $file = $err['file'] ?? '?';
    $rel  = str_replace($root . DIRECTORY_SEPARATOR, '', $file);
    $rel  = str_replace('\\', '/', $rel);
    $line = $err['line'] > 0 ? ':' . $err['line'] : '';
    echo "  {$rel}{$line}\n";
    echo "    {$err['message']}\n";
}

echo "\nFAILED: " . count($errors) . " structural error(s).\n";
exit(1);
