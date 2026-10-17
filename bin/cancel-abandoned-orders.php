#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Abandoned order cancellation.
 *
 * Finds orders stuck in 'pending_payment' with
 * payment_status = 'unpaid', older than the store's
 * threshold, and cancels them. The cancellation releases
 * each line's inventory reservation and fails any pending
 * M-Pesa request for the order.
 *
 * An order reaches 'pending_payment' the moment
 * CheckoutService::placeOrder() commits. If the customer
 * never completes payment - the Stripe Checkout page is
 * abandoned, the PayPal approval page is closed, the
 * M-Pesa PIN prompt is ignored - the order stays
 * 'pending_payment' forever. The inventory reservation
 * holds the stock. Without this cron, the stock stays
 * locked until the merchant cancels each order manually.
 *
 * Usage:
 *   php bin/cancel-abandoned-orders.php
 *   php bin/cancel-abandoned-orders.php --minutes=30
 *   php bin/cancel-abandoned-orders.php --store=1
 *   php bin/cancel-abandoned-orders.php --dry-run
 *
 * The threshold defaults to 60 minutes. The per-store
 * setting general.orders.abandoned_after_minutes
 * overrides the default. The --minutes flag overrides
 * both, for testing.
 *
 * The --dry-run flag reports what would be cancelled
 * without writing anything.
 *
 * In production, schedule the script to run every 15
 * minutes. A 15-minute tick with a 60-minute threshold
 * means an order is cancelled between 60 and 75 minutes
 * after it was placed.
 *
 *   php /path/to/bin/cancel-abandoned-orders.php
 *
 * See docs/ROADMAP.md for the abandoned-checkout
 * recovery entry.
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

// Parse flags.
$storeId = 0;
$minutes = 0;
$dryRun  = false;
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--store=')) {
        $storeId = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--minutes=')) {
        $minutes = (int) substr($arg, 10);
    } elseif ($arg === '--dry-run') {
        $dryRun = true;
    }
}

if ($minutes < 0) {
    $minutes = 0;
}
if ($minutes > 10080) {
    $minutes = 10080;
}

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

    $storeCtx     = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
    $ordersRepo   = $container->get(\CorporateAid\Commerce\Repositories\OrderRepository::class);
    $settingsRepo = $container->get(\CorporateAid\Commerce\Repositories\StoreSettingsRepository::class);
    $service      = $container->get(\CorporateAid\Commerce\Services\OrderCancellationService::class);

    $totalCancelled = 0;
    $totalScanned   = 0;

    foreach ($stores as $row) {
        $id   = (int) $row['id'];
        $slug = (string) ($row['slug'] ?? 'store');

        $storeCtx->set($id, $slug, 'cli');

        $threshold = $minutes;
        if ($threshold <= 0) {
            $threshold = $settingsRepo->int(
                'general.orders.abandoned_after_minutes',
                60
            );
            if ($threshold < 1) {
                $threshold = 60;
            }
        }

        $abandoned = $ordersRepo->findAbandoned($threshold);
        $totalScanned += count($abandoned);

        if ($abandoned === []) {
            echo "Store {$id} ({$slug}): 0 order(s) abandoned at {$threshold} minutes\n";
            continue;
        }

        $cancelled = 0;
        foreach ($abandoned as $order) {
            if ($dryRun) {
                $created = (string) ($order->createdAt ?? '');
                echo "  [dry-run] would cancel {$order->orderNumber}"
                   . ($created !== '' ? " (placed {$created})" : '')
                   . "\n";
                continue;
            }

            try {
                $service->cancel($order, 'Abandoned');
                $cancelled++;
            } catch (\Throwable $e) {
                fwrite(STDERR, "  Failed to cancel {$order->orderNumber}: "
                     . $e->getMessage() . "\n");
            }
        }

        $totalCancelled += $cancelled;
        echo "Store {$id} ({$slug}): {$cancelled} order(s) cancelled "
           . "at {$threshold} minutes\n";
    }

    if ($dryRun) {
        echo "Dry run: {$totalScanned} order(s) would be cancelled.\n";
    } else {
        echo "Total: {$totalCancelled} order(s) cancelled.\n";
    }
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Abandoned-order cancellation failed: " . $e->getMessage() . "\n");
    exit(1);
}
