<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require dirname(__DIR__) . '/vendor/autoload.php';

$app = CorporateAid\Core\App::boot(dirname(__DIR__));
$container = $app->container();

// 1. Resolve store context (cron runs for store 1 by default)
$storeContext = $container->get(CorporateAid\Commerce\Context\StoreContext::class);
$storeContext->set(1, 'default', 'cli');

// 2. Read the schedule
$settings = $container->get(CorporateAid\Commerce\Repositories\StoreSettingsRepository::class);
$scheduled = $settings->string('system.update.scheduled_at', '');

if ($scheduled === '') {
    echo "No scheduled update found.\n";
    exit(0);
}

$ts = strtotime($scheduled);
if ($ts === false || $ts > time()) {
    echo "Scheduled update is not due yet.\n";
    exit(0);
}

// 3. Time is due. Lock the storefront via system.store_status
//    (the key MaintenanceMiddleware actually reads). Preserve
//    the previous value so we can restore it after the run.
echo "Maintenance window reached. Locking storefront...\n";
$prevStatus = $settings->string('system.store_status', 'live');
$settings->set('system.store_status', 'maintenance');

// 4. Run the migrations
echo "Running pending migrations...\n";
$migrator = $container->get(CorporateAid\Core\Migrator::class);

try {
    $ran = $migrator->run();
    echo "Applied " . count($ran) . " migration(s).\n";
} catch (\Throwable $e) {
    echo "ERROR: Migration failed - " . $e->getMessage() . "\n";
    // Leave maintenance mode on so the admin can investigate
    exit(1);
}

// 5. Cleanup: clear schedule, restore previous store status
echo "Update finished. Unlocking storefront...\n";
$settings->set('system.update.scheduled_at', '');
$settings->set('system.store_status', $prevStatus);

echo "Done.\n";
exit(0);
