#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * bin/smoke.php - logic check harness for critical flows.
 *
 * Boots the app against a separate test database, runs one
 * or more named cases, and reports pass/fail per case. The
 * test database is created on first run, migrated, and
 * seeded. Subsequent runs reuse it.
 *
 * The dev database is never touched. The harness connects
 * to the test database only.
 *
 * Exit codes:
 *   0 - every case passed
 *   1 - at least one case failed, or the harness could not run
 *
 * Usage:
 *   php bin/smoke.php                    run every case
 *   php bin/smoke.php refund             run one case by name
 *   php bin/smoke.php --list             list every case
 *   php bin/smoke.php --fresh            drop and recreate the test database first
 *   php bin/smoke.php --db=name          use a different test database name
 *
 * Test database name defaults to anaya_commerce_test. The
 * connection credentials are read from config/local.php
 * (the same file the web app uses); only the database name
 * changes.
 *
 * See docs/ROADMAP.md for the smoke harness entry, and
 * docs/sessions/ for the case list.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);

$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run: composer install\n");
    exit(1);
}
require $autoload;

// Buffer every byte the harness writes to STDOUT before
// App::boot(). App::loadRoutes() calls Session->start(),
// which calls session_name(). PHP refuses session_name()
// after headers have been sent. On CLI, "headers sent"
// means any byte already written to STDOUT. Buffering the
// phase 1-3 progress keeps STDOUT empty until after boot.
ob_start();

// ---------------------------------------------------------------------
// Flags
// ---------------------------------------------------------------------
$caseName    = null;
$listOnly    = false;
$fresh       = false;
$testDbName  = 'anaya_commerce_test';

foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if ($arg === '--list') {
        $listOnly = true;
    } elseif ($arg === '--fresh') {
        $fresh = true;
    } elseif (str_starts_with($arg, '--db=')) {
        $testDbName = substr($arg, 5);
    } elseif (!str_starts_with($arg, '--')) {
        $caseName = $arg;
    }
}

// ---------------------------------------------------------------------
// Phase 1 - the test database
// ---------------------------------------------------------------------
$local = $basePath . '/config/local.php';
if (!is_file($local)) {
    fwrite(STDERR, "Missing config/local.php. Copy config/local.php.example and edit it.\n");
    exit(1);
}
$localConfig = require $local;
$dbConfig    = $localConfig['database'] ?? null;
if (!is_array($dbConfig)) {
    fwrite(STDERR, "config/local.php has no 'database' key.\n");
    exit(1);
}

$host = $dbConfig['host']     ?? 'localhost';
$port = $dbConfig['port']     ?? 3306;
$user = $dbConfig['username'] ?? 'root';
$pass = $dbConfig['password'] ?? '';

// Connect without a database name first, so we can create it.
$rootDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
try {
    $rootPdo = new PDO($rootDsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Test DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

if ($fresh) {
    $rootPdo->exec("DROP DATABASE IF EXISTS `{$testDbName}`");
}

$rootPdo->exec(
    "CREATE DATABASE IF NOT EXISTS `{$testDbName}`
     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
);

// Now connect to the test database.
$testDsn = "mysql:host={$host};port={$port};dbname={$testDbName};charset=utf8mb4";
try {
    $testPdo = new PDO($testDsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Test DB connect failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Smoke harness\n";
echo "  test DB: {$testDbName}\n";
echo "  host:    {$host}:{$port}\n";
echo "\n";

// ---------------------------------------------------------------------
// Phase 2 - migrate the test database
// ---------------------------------------------------------------------
$migrator = new \CorporateAid\Core\Migrator($testPdo, $basePath . '/database/migrations');
$migrator->ensureMigrationsTable();
$applied = $migrator->run();
if ($applied !== []) {
    echo "  [migrated] " . count($applied) . " migration(s) applied\n";
} else {
    echo "  [migrated] schema up to date\n";
}

// ---------------------------------------------------------------------
// Phase 3 - seed the test database
// ---------------------------------------------------------------------
$seedersDir = $basePath . '/database/seeders';
$seederFiles = glob($seedersDir . '/*.sql') ?: [];
sort($seederFiles, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($seederFiles as $path) {
    $filename = basename($path);
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Unable to read seeder: {$filename}\n");
        exit(1);
    }

    // Same splitter as bin/seed.php.
    $statements = [];
    $buffer = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inString) {
            if ($c === '\\') {
                $buffer .= $c;
                if ($i + 1 < $len) { $buffer .= $sql[++$i]; }
                continue;
            }
            if ($c === $stringChar) { $inString = false; }
            $buffer .= $c;
            continue;
        }
        if ($c === "'" || $c === '"') {
            $inString = true;
            $stringChar = $c;
            $buffer .= $c;
            continue;
        }
        if ($c === '-' && ($i + 1 < $len) && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            $buffer .= "\n";
            continue;
        }
        if ($c === ';') {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $c;
    }
    if (trim($buffer) !== '') { $statements[] = $buffer; }

    try {
        foreach ($statements as $stmt) {
            $trimmed = trim($stmt);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $testPdo->exec($trimmed);
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "  [FAILED]  {$filename}: " . $e->getMessage() . "\n");
        exit(1);
    }
}
echo "  [seeded]   " . count($seederFiles) . " seeder(s) applied\n";

// ---------------------------------------------------------------------
// Phase 4 - boot the app and override the database name
// ---------------------------------------------------------------------
//
// App::boot() registers every binding but resolves nothing
// eagerly - every Database::class reference is inside a
// closure. So before anything calls $container->get(),
// overriding the config value points the whole app at the
// test database.
//
$app = \CorporateAid\Core\App::boot($basePath);
$container = $app->container();

$config = $container->get('config');
$config->set('database.database', $testDbName);

// Boot is complete and the config override is applied.
// Nothing about the boot path will call session_name()
// again. Flush everything the harness buffered during
// phases 1 through 3.
ob_end_flush();

// Set the store context to the seeded store so repositories
// that scope by store_id can run. Same shape as bin/send-emails.php.
$storeRow = $testPdo->query(
    "SELECT `id`, `slug` FROM `commerce_stores` ORDER BY `id` ASC LIMIT 1"
)->fetch();
if ($storeRow === false) {
    fwrite(STDERR, "Test database has no store. Seeders did not run correctly.\n");
    exit(1);
}
$container->get(\CorporateAid\Commerce\Context\StoreContext::class)
    ->set((int) $storeRow['id'], (string) $storeRow['slug'], 'cli');

echo "\n";

// ---------------------------------------------------------------------
// Phase 5 - discover and run cases
// ---------------------------------------------------------------------
$casesDir = __DIR__ . '/smoke-cases';
$caseFiles = glob($casesDir . '/*.php') ?: [];
sort($caseFiles, SORT_NATURAL | SORT_FLAG_CASE);

if ($caseFiles === []) {
    echo "No cases found in bin/smoke-cases/.\n";
    exit(0);
}

$available = [];
foreach ($caseFiles as $file) {
    $base = basename($file, '.php');
    // Files whose name starts with an underscore are
    // shared fixtures, not cases. Skip them.
    if (str_starts_with($base, '_')) {
        continue;
    }
    $available[$base] = $file;
}

if ($listOnly) {
    echo "Available cases:\n";
    foreach (array_keys($available) as $name) {
        echo "  {$name}\n";
    }
    exit(0);
}

if ($caseName !== null) {
    if (!isset($available[$caseName])) {
        fwrite(STDERR, "Unknown case: {$caseName}\n");
        exit(1);
    }
    $available = [$caseName => $available[$caseName]];
}

$passed = 0;
$failed = 0;
$results = [];

foreach ($available as $name => $file) {
    echo "Running case: {$name}\n";
    $start = microtime(true);

    $caseFn = require $file;
    if (!is_callable($caseFn)) {
        echo "  [FAIL] the case file did not return a callable\n";
        $failed++;
        $results[] = [$name, false, 0.0, 'case file did not return a callable'];
        continue;
    }

    try {
        $result = $caseFn($container, $testPdo);
    } catch (Throwable $e) {
        $elapsed = microtime(true) - $start;
        echo "  [FAIL] " . $e->getMessage() . "\n";
        $failed++;
        $results[] = [$name, false, $elapsed, $e->getMessage()];
        continue;
    }

    $elapsed = microtime(true) - $start;

    if ($result === true) {
        echo "  [OK]   " . sprintf('(%.2fs)', $elapsed) . "\n";
        $passed++;
        $results[] = [$name, true, $elapsed, ''];
    } else {
        $message = is_string($result) ? $result : 'case returned a non-true value';
        echo "  [FAIL] {$message}\n";
        $failed++;
        $results[] = [$name, false, $elapsed, $message];
    }
}

// ---------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------
echo "\n";
echo "================================================================\n";
echo "  Summary\n";
echo "================================================================\n\n";

foreach ($results as [$name, $ok, $elapsed, $message]) {
    $tag = $ok ? '[OK  ]' : '[FAIL]';
    $line = sprintf('  %s  %-30s (%.2fs)', $tag, $name, $elapsed);
    if (!$ok && $message !== '') {
        $line .= '  - ' . $message;
    }
    echo $line . "\n";
}

echo "\n";
if ($failed > 0) {
    echo "FAILED: {$failed} of " . ($passed + $failed) . " case(s).\n";
    exit(1);
}

echo "All " . $passed . " case(s) passed.\n";
exit(0);
