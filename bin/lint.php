#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/lint.php - PHP syntax check across the codebase.
 *
 * Runs `php -l` on every .php file under app/ and routes/. Uses the
 * PHP_BINARY constant so it always uses the same PHP interpreter that
 * is executing this script (i.e. the one Composer used).
 *
 * Exit codes:
 *   0 - all files passed syntax check
 *   1 - at least one file failed, or PHP binary not resolvable
 *
 * Usage:
 *   php bin/lint.php
 *   composer lint
 */

$root = dirname(__DIR__);
$php  = PHP_BINARY;

if ($php === '' || !is_file($php)) {
    fwrite(STDERR, "ERROR: PHP_BINARY is not a valid path.\n");
    fwrite(STDERR, "       Cannot self-invoke `php -l`.\n");
    exit(1);
}

$dirs = ['app', 'routes'];

$checked   = 0;
$failed    = 0;
$failures  = [];

foreach ($dirs as $dir) {
    $base = $root . DIRECTORY_SEPARATOR . $dir;
    if (!is_dir($base)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $base,
            RecursiveDirectoryIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (strtolower($file->getExtension()) !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $checked++;

        $command = escapeshellarg($php)
                 . ' -l '
                 . escapeshellarg($path)
                 . ' 2>&1';

        $output = [];
        $code   = 0;
        exec($command, $output, $code);

        if ($code !== 0) {
            $failed++;
            $failures[] = [
                'file'   => $path,
                'output' => implode("\n", $output),
            ];
        }
    }
}

echo "Linting PHP files...\n";
echo "Checked {$checked} file(s).\n";

if ($failed > 0) {
    echo "\n";
    foreach ($failures as $f) {
        echo "FAIL: {$f['file']}\n";
        echo $f['output'] . "\n\n";
    }
    echo "FAILED: {$failed} file(s) did not pass syntax check.\n";
    exit(1);
}

echo "All files passed syntax check.\n";
exit(0);
