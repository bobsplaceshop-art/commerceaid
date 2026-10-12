#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check-theme.php - theme contract enforcement.
 *
 * The admin and storefront stylesheets expose a set of
 * CSS custom properties prefixed `--ra-`. Views consume
 * them via var(--ra-X). When a view references a
 * variable that is not defined, the fallback fires and
 * the render is wrong in one or more themes. This has
 * been the source of several visual regressions.
 *
 * This script:
 *   1. Reads every --ra-X: definition from every CSS
 *      file under public/assets/css/.
 *   2. Reads every var(--ra-X) reference from every
 *      PHP view under resources/views/ and from every
 *      CSS file under public/assets/css/.
 *   3. Reports:
 *        - Orphan references: var(--ra-X) with no
 *          matching --ra-X: definition.
 *        - Unused definitions: --ra-X: with no
 *          matching var(--ra-X) reference anywhere.
 *
 * Exit codes:
 *   0 - no orphans, no unused definitions
 *   1 - at least one orphan or unused definition
 *
 * Usage:
 *   php bin/check-theme.php
 *   composer theme
 */

$root = dirname(__DIR__);

// -----------------------------------------------------------------
// Collect definitions and references
// -----------------------------------------------------------------

$cssDir   = $root . '/public/assets/css';
$viewsDir = $root . '/resources/views';

$defined   = []; // var name => list of [file, line]
$referenced = []; // var name => list of [file, line]

// --- Definitions from CSS files ---
$cssFiles = glob($cssDir . '/*.css') ?: [];
foreach ($cssFiles as $cssFile) {
    $lines = file($cssFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) continue;

    foreach ($lines as $i => $line) {
        if (preg_match_all('/--ra-[a-z][a-z0-9-]*/', $line, $m)) {
            // Only count when this is a definition (followed by ':')
            foreach ($m[0] as $var) {
                // Find the position of the var
                $pos = strpos($line, $var);
                if ($pos === false) continue;
                // Check that the next non-whitespace char is ':'
                $after = substr($line, $pos + strlen($var));
                if (preg_match('/^\s*:/', $after)) {
                    $defined[$var][] = [
                        'file' => $cssFile,
                        'line' => $i + 1,
                    ];
                }
            }
        }
    }
}

// --- References from CSS files ---
foreach ($cssFiles as $cssFile) {
    $lines = file($cssFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) continue;

    foreach ($lines as $i => $line) {
        if (preg_match_all('/var\(\s*(--ra-[a-z][a-z0-9-]*)/', $line, $m)) {
            foreach ($m[1] as $var) {
                $referenced[$var][] = [
                    'file' => $cssFile,
                    'line' => $i + 1,
                ];
            }
        }
    }
}

// --- References from PHP views ---
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $viewsDir,
        RecursiveDirectoryIterator::SKIP_DOTS
    )
);

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'php') continue;

    $path = $file->getPathname();
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) continue;

    foreach ($lines as $i => $line) {
        if (preg_match_all('/var\(\s*(--ra-[a-z][a-z0-9-]*)/', $line, $m)) {
            foreach ($m[1] as $var) {
                $referenced[$var][] = [
                    'file' => $path,
                    'line' => $i + 1,
                ];
            }
        }
    }
}

// -----------------------------------------------------------------
// Report orphans and unused
// -----------------------------------------------------------------

$orphans = [];   // referenced but not defined
$unused  = [];   // defined but not referenced

foreach ($referenced as $var => $refs) {
    if (!isset($defined[$var])) {
        $orphans[$var] = $refs;
    }
}

foreach ($defined as $var => $defs) {
    if (!isset($referenced[$var])) {
        $unused[$var] = $defs;
    }
}

// -----------------------------------------------------------------
// Output
// -----------------------------------------------------------------

$orphanCount = count($orphans);
$unusedCount = count($unused);

echo "Checking theme contract...\n";
echo "  Variables defined:    " . count($defined) . "\n";
echo "  Variables referenced: " . count($referenced) . "\n";
echo "  Orphan references:    {$orphanCount}\n";
echo "  Unused definitions:   {$unusedCount}\n";

if ($orphanCount > 0) {
    echo "\n";
    echo "ORPHAN REFERENCES (var(--X) with no --X: definition):\n";
    foreach ($orphans as $var => $refs) {
        echo "\n  {$var}\n";
        foreach ($refs as $r) {
            $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $r['file']);
            $rel = str_replace('\\', '/', $rel);
            echo "    {$rel}:{$r['line']}\n";
        }
    }
}

if ($unusedCount > 0) {
    echo "\n";
    echo "UNUSED DEFINITIONS (--X: defined but never referenced):\n";
    foreach ($unused as $var => $defs) {
        $first = $defs[0];
        $rel   = str_replace($root . DIRECTORY_SEPARATOR, '', $first['file']);
        $rel   = str_replace('\\', '/', $rel);
        echo "  {$var}  ({$rel}:{$first['line']}, "
            . count($defs) . " definition(s))\n";
    }
}

if ($orphanCount > 0 || $unusedCount > 0) {
    echo "\n";
    echo "FAILED: {$orphanCount} orphan(s), {$unusedCount} unused.\n";
    exit(1);
}

echo "\nOK - theme contract is clean.\n";
exit(0);
