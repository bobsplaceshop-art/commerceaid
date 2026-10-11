<?php
declare(strict_types=1);

/**
 * Newsletter queue worker.
 *
 * Drains up to --batch pending rows from
 * commerce_email_queue for a single store, sends each one
 * through EmailService, and applies the retry, bounce, and
 * suppression rules from the plan doc.
 *
 * Usage:
 *   php bin/send-emails.php --store=1 --batch=100
 *
 * The store flag is required when the platform has more than
 * one store; it identifies which store's queue to drain.
 * With a single-store deployment it defaults to that store.
 *
 * In production, install a crontab entry:
 *   * * * * * php /path/to/bin/send-emails.php --batch=100
 *
 * See docs/stages/008i_newsletter-plan.md.
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
$batch   = 100;
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
    // Boot the application so the container, providers,
    // config, and routes are all wired. App::boot()
    // returns the fully-bootstrapped instance.
    $app = \CorporateAid\Core\App::boot($basePath);
    $container = $app->container();

    // Resolve the store to drain.
    $db = $container->get(\CorporateAid\Core\Database::class);

    if ($storeId <= 0) {
        // Default: the first active store.
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

    // Resolve and drain.
    $svc = $container->get(\CorporateAid\Commerce\Services\QueueService::class);
    $result = $svc->drain($batch);

    fwrite(STDOUT, sprintf(
        "Store %d: claimed %d, sent %d, retried %d, failed %d, suppressed %d\n",
        $storeId,
        $result['claimed'],
        $result['sent'],
        $result['retried'],
        $result['failed'],
        $result['suppressed']
    ));

    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Fatal: " . $e->getMessage() . "\n");
    if (method_exists($app ?? null, 'container')) {
        try {
            $container->get(\CorporateAid\Platform\Support\Logger::class)
                ->error('send-emails CLI fatal', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
        } catch (\Throwable) {
            // best effort
        }
    }
    exit(1);
}
