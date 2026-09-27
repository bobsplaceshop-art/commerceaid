#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/verify-file.php
 *
 * Runs the standard post-write verification on a single file.
 * Replaces the ad-hoc grep chains we've been typing for every write.
 *
 * Usage:
 *   php bin/verify-file.php <relative-path> [--view] [--expect=<pattern>] [--no-lint]
 *
 * Checks performed (in order):
 *   1. File exists and prints size + line count
 *   2. Backtick (```) count is 0
 *   3. Mangled-char (Russian em dash corruption) count is 0
 *   4. If --view: confirms flash( is NOT called
 *   5. Any --expect=PATTERN occurrences are counted and reported
 *   6. Unless --no-lint: php bin/lint.php runs across app/ + routes/
 *
 * Exit codes:
 *   0 - all checks pass
 *   1 - a check failed (file missing, backticks found, etc.)
 */

$args = $argv;
array_shift($args);

if (empty($args)) {
    fwrite(STDERR, "Usage: php bin/verify-file.php <relative-path> [options]\n");
    exit(1);
}

$path = array_shift($args);
$isView = false;
$noLint = false;
$expects = [];

foreach ($args as $arg) {
    if ($arg === '--view') {
        $isView = true;
    } elseif ($arg === '--no-lint') {
        $noLint = true;
    } elseif (str_starts_with($arg, '--expect=')) {
        $expects[] = substr($arg, 9);
    }
}

$root = dirname(__DIR__);
$full = str_starts_with($path, '/') ? $path : $root . '/' . $path;

$failed = 0;
$warned = 0;

function line(string $s = ''): void {
    echo $s . "\n";
}

function ok(string $label, string $detail = ''): void {
    printf("  OK    %s%s\n", $label, $detail !== '' ? " -> {$detail}" : '');
}

function fail(string $label, string $detail = ''): void {
    printf("  FAIL  %s%s\n", $label, $detail !== '' ? " -> {$detail}" : '');
}

// =====================================================================
// 1. Existence
// =====================================================================
if (!is_file($full)) {
    fail('File exists', "not found at {$full}");
    exit(1);
}

$size = filesize($full);
$lines = count(file($full, FILE_IGNORE_NEW_LINES));
ok('File exists', "{$size} bytes, {$lines} lines");

$content = file_get_contents($full);
if ($content === false) {
    fail('File readable');
    exit(1);
}

// =====================================================================
// 2. Backtick fences (should never appear in our source)
// =====================================================================
$backticks = substr_count($content, '```');
if ($backticks === 0) {
    ok('No triple backticks');
} else {
    fail('No triple backticks', "found {$backticks}");
    $failed++;
}

// =====================================================================
// 3. Mangled em-dash (Russian "тАФ" from UTF-8 corruption)
// =====================================================================
$mangled = substr_count($content, "тАФ");
if ($mangled === 0) {
    ok('No mangled characters');
} else {
    fail('No mangled characters', "found {$mangled}");
    $failed++;
}

// =====================================================================
// 4. View hygiene - no flash() calls in views
// =====================================================================
if ($isView) {
    // Layouts and shell partials legitimately read flash - that is
    // their job. Only page views (things rendered inside the
    // layout's content slot) must be flash-free.
    //
    // A path is exempt if it is under resources/views/**/layouts/
    // or is a chrome partial (header, footer, utility-bar, sidebar,
    // topbar, user-dropdown, theme-dropdown). Everything else
    // flagged as --view is held to the strict rule.
    $normalized = str_replace('\\', '/', $full);
    $isExempt = (strpos($normalized, '/layouts/') !== false);
    if (!$isExempt) {
        $exemptPartials = [
            '/partials/header.php',
            '/partials/footer.php',
            '/partials/utility-bar.php',
            '/partials/sidebar.php',
            '/partials/topbar.php',
            '/partials/user-dropdown.php',
            '/partials/theme-dropdown.php',
        ];
        foreach ($exemptPartials as $p) {
            if (str_ends_with($normalized, $p)) {
                $isExempt = true;
                break;
            }
        }
    }

    if ($isExempt) {
        ok('Flash reads allowed (layout or shell partial)');
    } else {
        $flash = substr_count($content, 'flash(');
        if ($flash === 0) {
            ok('No flash() calls (view hygiene)');
        } else {
            fail('No flash() calls', "found {$flash}");
            $failed++;
        }
    }
}

// =====================================================================
// 5. Expected-pattern occurrences (ad-hoc checks)
// =====================================================================
foreach ($expects as $pattern) {
    $count = substr_count($content, $pattern);
    if ($count > 0) {
        ok("Contains: {$pattern}", "{$count} occurrence(s)");
    } else {
        fail("Contains: {$pattern}", 'NOT FOUND');
        $failed++;
    }
}

// =====================================================================
// 6. Full-project lint
// =====================================================================
if (!$noLint) {
    line();
    line('Running php bin/lint.php...');
    $lintOutput = [];
    $lintCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' bin/lint.php 2>&1', $lintOutput, $lintCode);
    foreach ($lintOutput as $l) {
        echo '  ' . $l . "\n";
    }
    if ($lintCode !== 0) {
        $failed++;
    }
}

line();
if ($failed === 0) {
    line('ALL CHECKS PASSED');
    exit(0);
}

line("FAILED: {$failed} check(s)");
exit(1);
