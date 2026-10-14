#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Refund retry worker.
 *
 * Selects failed refunds with fewer than 5 attempts from
 * commerce_payment_refunds, re-dispatches each through
 * PaymentRefundService::retry(), and reports the outcome.
 *
 * A refund row enters the failed state when a provider call
 * returns an error. The row is not deleted and its
 * attempts counter is not reset. Once attempts reaches 5,
 * the row is left for manual intervention and no longer
 * picked up by this worker.
 *
 * A refund whose provider class does not exist yet (Stripe
 * and PayPal ship in Sessions 3 and 4) also lands here.
 * retry() throws PaymentRefundException; the worker counts
 * the row as "skipped" and does not increment attempts.
 *
 * Usage:
 *   php bin/retry-refunds.php
 *   php bin/retry-refunds.php --store=1 --batch=50
 *
 * The store flag is required when the platform has more
 * than one store. With a single-store deployment it
 * defaults to that store.
 *
 * In production, install a crontab entry:
 *   */5 * * * * php /path/to/bin/retry-refunds.php --batch=50
 *
 * See docs/stages/010_payments-deep-plan.md.
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
$batch   = 50;
foreach ($argv as $i => $arg) {
    if ($i === 0) continue;
    if (str_starts_with($arg, '--store=')) {
        $storeId = (int) substr($arg, 8);
    } elseif (str_starts_with($arg, '--batch=')) {
        $batch = (int) substr($arg, 8);
    }
}

if ($batch < 1) $batch = 1;
if ($batch > 500) $batch = 500;

try {
    // Boot the app so the container, providers, config,
    // and routes are all wired.
    $app = \CorporateAid\Core\App::boot($basePath);
    $container = $app->container();

    // Resolve the store to drain.
    $db = $container->get(\CorporateAid\Core\Database::class);

    if ($storeId <= 0) {
        $row = $db->selectOne(
            "SELECT `id`, `slug`, `name` FROM `commerce_stores`
              ORDER BY `id` ASC LIMIT 1"
        );
        if ($row === null) {
            fwrite(STDERR, "No stores found. Aborting.\n");
            exit(1);
        }
        $storeId = (int) $row['id'];
        $slug    = (string) ($row['slug'] ?? 'store');
    } else {
        $row = $db->selectOne(
            "SELECT `id`, `slug` FROM `commerce_stores` WHERE `id` = ? LIMIT 1",
            [$storeId]
        );
        if ($row === null) {
            fwrite(STDERR, "Store {$storeId} not found. Aborting.\n");
            exit(1);
        }
        $slug = (string) ($row['slug'] ?? 'store');
    }

    // Set the store context for the CLI process.
    $container->get(\CorporateAid\Commerce\Context\StoreContext::class)
        ->set($storeId, $slug, 'cli');

    // Read the pending-retry rows.
    $refunds = $container->get(
        \CorporateAid\Commerce\Repositories\PaymentRefundRepository::class
    );
    $rows = $refunds->listPendingForRetry($batch);

    $attempted = 0;
    $succeeded = 0;
    $failed    = 0;
    $skipped   = 0;

    $service = $container->get(
        \CorporateAid\Commerce\Services\Payment\PaymentRefundService::class
    );

    foreach ($rows as $row) {
        $refundId = (int) ($row['id'] ?? 0);
        if ($refundId <= 0) {
            continue;
        }
        $attempted++;

        try {
            $result = $service->retry($refundId);
            if ($result->status === 'succeeded') {
                $succeeded++;
            } else {
                $failed++;
            }
        } catch (\CorporateAid\Commerce\Exceptions\PaymentRefundException $e) {
            // The provider class is not available, or the
            // order is gone, or the row's status changed
            // between the SELECT and the retry. Do not
            // increment attempts; leave the row alone.
            $skipped++;
            fwrite(STDERR, sprintf(
                "  [skip] refund %d: %s\n",
                $refundId,
                $e->getMessage()
            ));
        }
    }

    fwrite(STDOUT, sprintf(
        "Store %d: attempted %d, succeeded %d, failed %d, skipped %d\n",
        $storeId,
        $attempted,
        $succeeded,
        $failed,
        $skipped
    ));

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal: " . $e->getMessage() . "\n");
    if (method_exists($app ?? null, 'container')) {
        try {
            $container->get(\CorporateAid\Platform\Support\Logger::class)
                ->error('retry-refunds CLI fatal', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
        } catch (\Throwable) {
            // best effort
        }
    }
    exit(1);
}
