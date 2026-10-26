<?php
declare(strict_types=1);

/**
 * Cancels unpaid orders older than the configured window.
 *
 * Usage (from Laragon Terminal or cron):
 *   php bin/cleanup-abandoned-orders.php
 *   php bin/cleanup-abandoned-orders.php --minutes=120
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

use CorporateAid\Core\App;
use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Services\AbandonedOrderService;

$minutes = 60;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--minutes=(\d+)$/', $arg, $m)) {
        $minutes = max(1, (int) $m[1]);
    }
}

$app = App::boot(dirname(__DIR__));
$container = $app->container();
$container->get(StoreContext::class)->set(1, 'anaya', 'cli');

$service = $container->get(AbandonedOrderService::class);

echo "Cleaning up unpaid orders older than {$minutes} minutes...\n";
$result = $service->cancelStale($minutes);

echo "  Cancelled: {$result['cancelled']} order(s).\n";
echo "  Released:  {$result['released']} line item(s).\n";
if (!empty($result['order_ids'])) {
    echo "  Order IDs: " . implode(', ', $result['order_ids']) . "\n";
}
echo "Done.\n";
