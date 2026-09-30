<?php
/**
 * Temporary verification for Session 4's
 * ProductVariantRepository::swatchAxesFor() and
 * combinationsFor().
 *
 * Run from Laragon terminal:
 *   php bin\test_swatch_axes.php
 *
 * Delete after Session 4 is verified.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line.\n");
    exit(1);
}

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

// Boot the app the same way public/index.php does.
$app = \CorporateAid\Core\App::boot($basePath);
$container = $app->container();

// Resolve the store context for a CLI run. Same pattern
// used by any CLI script that needs a store.
$ctx = $container->get(\CorporateAid\Commerce\Context\StoreContext::class);
$ctx->set(1, 'anaya', 'cli');

$variantRepo = $container->get(
    \CorporateAid\Commerce\Repositories\ProductVariantRepository::class
);

// Find the first variable product in the store.
$db = $container->get(\CorporateAid\Core\Database::class);
$row = $db->selectOne(
    "SELECT `id`, `name` FROM `commerce_products`
      WHERE `store_id` = ? AND `product_type` = 'variable'
        AND `deleted_at` IS NULL
      ORDER BY `id` ASC
      LIMIT 1",
    [1]
);

if ($row === null) {
    echo "No variable product in the store.\n";
    exit(0);
}

$productId = (int) $row['id'];
echo "Testing product: {$row['name']} (id {$productId})\n";

echo "\n========================================\n";
echo "swatchAxesFor({$productId})\n";
echo "========================================\n";
$axes = $variantRepo->swatchAxesFor($productId);
if ($axes === []) {
    echo "(empty - no variant axes for this product)\n";
} else {
    echo json_encode($axes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n";
}

echo "\n========================================\n";
echo "combinationsFor({$productId})\n";
echo "========================================\n";
$combos = $variantRepo->combinationsFor($productId);
if ($combos === []) {
    echo "(empty - no combinations)\n";
} else {
    echo json_encode($combos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo "\n";
}

echo "\n=== Summary ===\n";
echo "Axes: " . count($axes) . "\n";
echo "Combinations: " . count($combos) . "\n";
