#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Pesapal pending-request cleanup.
 *
 * Deletes commerce_pesapal_requests rows that are still
 * in the 'pending' state and older than 24 hours. Rows
 * in 'completed' or 'failed' state stay; they are the
 * audit trail for what Pesapal reported.
 *
 * Usage:
 *   php bin/cleanup-pesapal-requests.php
 *   php bin/cleanup-pesapal-requests.php --hours=48
 *   php bin/cleanup-pesapal-requests.php --store=1
 *
 * A pending request older than 24 hours is a Pesapal
 * order the customer never completed, or an IPN that
 * never arrived. Either way, the request will never
 * complete. The row is stale and deletable.
 *
 * In production, schedule the script to run every 30
 * minutes:
 *
 *   php /path/to/bin/cleanup-pesapal-requests.php
 *
 * See docs/stages/010.2_pesapal-plan.md.
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

$storeId = 0;
$hours   = 24;
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--store=')) {
        $storeId = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--hours=')) {
        $hours = (int) substr($arg, 8);
    }
}

if ($hours < 1)   $hours = 1;
if ($hours > 720) $hours = 720;

try {
    $app = \CorporateAid\Core\App::boot($basePath);
    $container = $app->container();

    $db = $container->get(\CorporateAid\Core\Database::class);

    if ($storeId > 0) {
        $stores = $db->select(
            "SELECT `id`, `slug` FROM `commerce_stores` WHERE `id` = ?",
            [$storeId]
        );
    } else {
        $stores = $db->select(
            "SELECT `id`, `slug` FROM `commerce_stores` ORDER BY `id` ASC"
        );
    }

    if ($stores === []) {
        fwrite(STDERR, "No stores found. Nothing to do.\n");
        exit(0);
    }

    $storeCtx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $repo     = $container->get(\CorporateAid\Commerce\Repositories\PesapalRequestRepository::class);

    $totalDeleted = 0;

    foreach ($stores as $row) {
        $id   = (int) $row['id'];
        $slug = (string) ($row['slug'] ?? 'store');

        $storeCtx->set($id, $slug, 'cli');

        $deleted = $repo->deleteStale($hours);
        $totalDeleted += $deleted;

        echo "Store {$id} ({$slug}): {$deleted} row(s) deleted\n";
    }

    echo "Total: {$totalDeleted} stale pending request(s) deleted.\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Cleanup failed: " . $e->getMessage() . "\n");
    exit(1);
}
