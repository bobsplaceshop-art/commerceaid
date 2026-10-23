<?php
declare(strict_types=1);

/**
 * Deploy script.
 *
 * Usage (from Laragon Terminal, project root):
 *   php bin/deploy.php
 *   php bin/deploy.php --branch=main
 *   php bin/deploy.php --dry-run
 *
 * Order of operations:
 *   1. Verify environment (git repo, clean tree, correct branch).
 *   2. Fetch remote. If no new commits, exit.
 *   3. Set system.store_status = 'maintenance' (storefront
 *      goes to the 503 maintenance page for customers).
 *   4. Report pending migrations before the pull.
 *   5. git pull --ff-only.
 *   6. Re-check pending migrations (the pull may have added some).
 *   7. Apply migrations. On failure, LEAVE maintenance mode ON
 *      so the storefront does not serve a broken app. The
 *      merchant fixes the migration and re-runs.
 *   8. On success: clear OPcache, clear storage cache, restore
 *      the previous store_status value.
 *   9. Report.
 *
 * Exit codes:
 *   0  success
 *   1  user error (dirty tree, wrong branch, migration failure)
 *   2  environment error (missing autoload, git not on PATH, DB down)
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(2);
}

$basePath = dirname(__DIR__);

$autoload = $basePath . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing vendor/autoload.php. Run: composer install\n");
    exit(2);
}
require $autoload;

if (!is_dir($basePath . '/.git')) {
    fwrite(STDERR, "Not a git repository: {$basePath}\n");
    exit(2);
}

$branch = 'main';
$dryRun = false;
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--branch=')) $branch = substr($arg, 9);
    if ($arg === '--dry-run')             $dryRun = true;
}

function git(string $cmd, string $cwd): array {
    $full = 'git ' . $cmd . ' 2>&1';
    exec('cd ' . escapeshellarg($cwd) . ' && ' . $full, $out, $code);
    return ['code' => $code, 'text' => trim(implode("\n", $out))];
}

echo "Deploy script\n";
echo "=============\n\n";

// 1. Branch check
$cur = git('rev-parse --abbrev-ref HEAD', $basePath);
if ($cur['code'] !== 0) {
    fwrite(STDERR, "Cannot determine current branch: {$cur['text']}\n");
    exit(2);
}
if (trim($cur['text']) !== $branch) {
    fwrite(STDERR, "Current branch is '{$cur['text']}', expected '{$branch}'. Refusing.\n");
    exit(1);
}
echo "Branch: {$branch}\n";

// 2. Clean tree check
$st = git('status --porcelain', $basePath);
if ($st['code'] !== 0) {
    fwrite(STDERR, "Cannot read git status: {$st['text']}\n");
    exit(2);
}
if (trim($st['text']) !== '') {
    fwrite(STDERR, "Working tree is dirty. Commit or stash first.\n");
    fwrite(STDERR, $st['text'] . "\n");
    exit(1);
}
echo "Working tree: clean\n\n";

// 3. Fetch
echo "Fetching origin/{$branch}...\n";
$f = git('fetch origin ' . $branch, $basePath);
if ($f['code'] !== 0) {
    fwrite(STDERR, "git fetch failed: {$f['text']}\n");
    exit(2);
}

$cmp = git('log HEAD..origin/' . $branch . ' --oneline', $basePath);
$newCommits = array_filter(explode("\n", $cmp['text']));
if (empty($newCommits)) {
    echo "Already up to date. Nothing to deploy.\n";
    exit(0);
}
echo "New commits: " . count($newCommits) . "\n";
foreach ($newCommits as $line) {
    echo "  " . $line . "\n";
}
echo "\n";

// 4. Connect to DB, resolve migrator
try {
    $cliConfig = require $basePath . '/config/cli.php';
    $db = $cliConfig['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'], $db['port'], $db['database']
    );
    $pdo = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (\Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(2);
}
$migrator = new \CorporateAid\Core\Migrator($pdo, $basePath . '/database/migrations');

// 5. Pre-pull migration report
$prePending = $migrator->getPendingMigrations();
echo "Migrations pending before pull: " . count($prePending) . "\n";
foreach ($prePending as $file) echo "  - {$file}\n";
echo "\n";

if ($dryRun) {
    echo "Dry run. Stopping before pull and before maintenance mode.\n";
    exit(0);
}

// 6. Maintenance mode ON. Read the current store_status,
//    save it, and switch to 'maintenance'. The MaintenanceMiddleware
//    gates on system.store_status, not system.maintenance_mode.
$stmt = $pdo->prepare(
    "SELECT `value` FROM `commerce_store_settings`
     WHERE `store_id` = 1 AND `key_name` = 'system.store_status'"
);
$stmt->execute();
$prevStatusRow = $stmt->fetchColumn();
$prevStatus = $prevStatusRow !== false ? (string) $prevStatusRow : 'live';

function setStoreStatus(PDO $pdo, string $value): void {
    $stmt = $pdo->prepare(
        "INSERT INTO `commerce_store_settings` (`store_id`, `key_name`, `value`, `updated_by`)
         VALUES (1, 'system.store_status', ?, NULL)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
    );
    $stmt->execute([$value]);
}

echo "Previous store_status: {$prevStatus}\n";
echo "Setting store_status to 'maintenance'...\n";
setStoreStatus($pdo, 'maintenance');
echo "Maintenance mode: ON\n\n";

// 7. Pull
echo "Pulling...\n";
$pull = git('pull --ff-only origin ' . $branch, $basePath);
if ($pull['code'] !== 0) {
    fwrite(STDERR, "git pull failed: {$pull['text']}\n");
    fwrite(STDERR, "Maintenance mode remains ON. Investigate, then set store_status back to '{$prevStatus}' manually.\n");
    exit(1);
}
echo "Pulled.\n\n";

// 8. Post-pull migration apply
$postPending = $migrator->getPendingMigrations();
echo "Migrations pending after pull: " . count($postPending) . "\n";
foreach ($postPending as $file) echo "  - {$file}\n";
echo "\n";

$ran = [];
if (empty($postPending)) {
    echo "No migrations to apply.\n";
} else {
    echo "Applying migrations...\n";
    try {
        $ran = $migrator->run();
        foreach ($ran as $file) echo "  [applied] {$file}\n";
        echo "Done. " . count($ran) . " migration(s) applied.\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "MIGRATION FAILED: " . $e->getMessage() . "\n");
        fwrite(STDERR, "\nMaintenance mode remains ON.\n");
        fwrite(STDERR, "The storefront serves the 503 maintenance page.\n");
        fwrite(STDERR, "Fix the migration file, then re-run bin/deploy.php.\n");
        fwrite(STDERR, "Do NOT set store_status back to '{$prevStatus}' until the migration applies.\n");
        exit(1);
    }
}
echo "\n";

// 9. Clear caches
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache: reset (CLI)\n";
}
$cacheDir = $basePath . '/storage/cache';
if (is_dir($cacheDir)) {
    $count = 0;
    foreach (glob($cacheDir . '/*') ?: [] as $file) {
        if (is_file($file)) { @unlink($file); $count++; }
    }
    echo "Storage cache: cleared {$count} file(s)\n";
}

// 10. Restore store_status
echo "\nRestoring store_status to '{$prevStatus}'...\n";
setStoreStatus($pdo, $prevStatus);
echo "Maintenance mode: OFF\n\n";

echo "Deploy complete.\n";
echo "=============\n";
echo "Commits pulled: " . count($newCommits) . "\n";
echo "Migrations applied: " . count($ran) . "\n\n";
echo "Next step: restart Apache from Laragon to pick up the new code.\n";
exit(0);
