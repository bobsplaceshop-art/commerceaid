#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/check.php - the prevenger aggregator.
 *
 * Runs every structural checker in the prevenger series
 * and prints a unified summary. Each checker is a
 * standalone PHP script that reads a specific surface
 * (theme variables, CSS structure, HTML structure) and
 * exits non-zero on any violation.
 *
 * The aggregator exists so that composer check has one
 * prevenger entry point, not three. Adding a fourth
 * checker is one line in this file, not two edits in
 * composer.json.
 *
 * Checkers run sequentially in this order:
 *
 *   check-theme.php  theme variable contract
 *   check-css.php    inline <style> structural integrity
 *   check-html.php   nested forms, modal includes,
 *                    data-* escaping
 *
 * Each checker is executed as a subprocess. They are not
 * required directly, because they each declare their own
 * top-level variables ($errors, $warnings, $checked) and
 * requiring two of them into the same process would
 * collide on those names.
 *
 * The aggregator does not stop on the first failure. A
 * developer running composer check wants the full
 * picture, not the first error. Every checker runs, its
 * output is captured, and the summary at the end reports
 * which checks passed and which failed.
 *
 * Exit codes:
 *   0 - every checker returned 0
 *   1 - at least one checker returned non-zero
 *
 * Usage:
 *   php bin/check.php
 *   composer prevenger
 *   composer check
 */

$root = dirname(__DIR__);

$checkers = [
    'theme' => [
        'label' => 'Theme variable contract',
        'script' => 'bin/check-theme.php',
        'description' => 'every --ra-X: definition has a var(--ra-X) reference, and vice versa',
    ],
    'css' => [
        'label' => 'CSS structural integrity',
        'script' => 'bin/check-css.php',
        'description' => 'brace balance, declaration shape',
    ],
    'html' => [
        'label' => 'HTML structural integrity',
        'script' => 'bin/check-html.php',
        'description' => 'nested forms, modal-in-form includes, data-* escaping',
    ],
    'responsive' => [
        'label' => 'Responsive layout contract',
        'script' => 'bin/check-responsive.php',
        'description' => 'inline @media in views, hardcoded widths, unmarked tables',
    ],
    'media-urls' => [
        'label' => 'Media URL resolution contract',
        'script' => 'bin/check-media-urls.php',
        'description' => "no literal '/media/{id}' URLs; use a helper, \$bg_url, or MediaItem::variantUrl()",
    ],
];

/**
 * Run a checker as a subprocess and return its exit code
 * and output. Uses proc_open so stderr and stdout are
 * captured separately and the caller can decide how to
 * present them.
 *
 * @return array{exit:int, stdout:string, stderr:string}
 */
function run_checker(string $root, string $relative): array
{
    $script = $root . '/' . $relative;
    if (!is_file($script)) {
        return [
            'exit'   => 127,
            'stdout' => '',
            'stderr' => "checker not found: {$relative}",
        ];
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);

    $process = proc_open($cmd, $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        return [
            'exit'   => 127,
            'stdout' => '',
            'stderr' => "failed to start subprocess: {$relative}",
        ];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exit = proc_close($process);

    return [
        'exit'   => $exit,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}

$results = [];

echo "Running prevenger checks...\n";
echo "\n";

foreach ($checkers as $key => $meta) {
    echo "================================================================\n";
    echo "  {$meta['label']}\n";
    echo "  ({$meta['description']})\n";
    echo "================================================================\n";
    echo "\n";

    $start = microtime(true);
    $result = run_checker($root, $meta['script']);
    $elapsed = microtime(true) - $start;

    // Print the checker's own stdout verbatim.
    echo $result['stdout'];
    if ($result['stderr'] !== '') {
        echo "\n[stderr]\n";
        echo $result['stderr'];
    }
    echo "\n";

    $results[$key] = [
        'meta'    => $meta,
        'exit'    => $result['exit'],
        'elapsed' => $elapsed,
    ];
}

// -----------------------------------------------------------------
// Summary
// -----------------------------------------------------------------

echo "================================================================\n";
echo "  Summary\n";
echo "================================================================\n";
echo "\n";

$allPassed = true;
foreach ($results as $key => $r) {
    $status = $r['exit'] === 0 ? 'OK  ' : 'FAIL';
    $time   = number_format($r['elapsed'], 2, '.', '');
    echo "  [{$status}]  {$r['meta']['label']}  ({$time}s)\n";
    if ($r['exit'] !== 0) {
        $allPassed = false;
    }
}

echo "\n";

if ($allPassed) {
    echo "All prevenger checks passed.\n";
    exit(0);
}

$failed = 0;
foreach ($results as $r) {
    if ($r['exit'] !== 0) $failed++;
}
echo "FAILED: {$failed} of " . count($results) . " prevenger check(s).\n";
exit(1);
