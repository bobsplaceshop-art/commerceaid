<?php
declare(strict_types=1);

/**
 * Smoke case: the product import handler (validation only).
 * Stage 016 Session 2a.
 *
 * Exercises ImportProductHandler::validate() and the
 * naturalKey normalisation. The create path needs the
 * full pipeline; this case proves the handler contract
 * before the pipeline wraps it.
 *
 * Six assertions:
 *
 *   1. entityKey returns 'product'.
 *   2. entityLabel returns 'Product'.
 *   3. fields declares the eight fields; name, sku,
 *      slug, price are required.
 *   4. validate() fails on an empty name.
 *   5. validate() fails on a non-numeric price.
 *   6. validate() fails on an unknown brand_slug.
 *   7. validate() passes on a well-formed row (with a
 *      real brand from this case's fixture).
 *   8. naturalKey uppercases the SKU.
 *
 * The case does NOT call $handler->create(). That path
 * needs the full pipeline's committer and belongs to
 * Session 2b. The brand fixture is created and cleaned
 * up here so the "passes validation" path has a real
 * brand slug to resolve.
 *
 * Returns true on pass, a string on failure.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo
 */

use CorporateAid\Commerce\Models\Brand;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Services\Import\Handlers\ImportProductHandler;

return function ($container, PDO $pdo): bool|string {

    $handler = $container->get(ImportProductHandler::class);
    $brandRepo = $container->get(BrandRepository::class);
    $failures = [];
    $brandId = 0;

    try {
        // ----------------------------------------------------------
        // Assertion 1: entityKey
        // ----------------------------------------------------------
        if ($handler->entityKey() !== 'product') {
            $failures[] = 'assertion 1: entityKey returned '
                . var_export($handler->entityKey(), true)
                . ', expected "product"';
        }

        // ----------------------------------------------------------
        // Assertion 2: entityLabel
        // ----------------------------------------------------------
        if ($handler->entityLabel() !== 'Product') {
            $failures[] = 'assertion 2: entityLabel returned '
                . var_export($handler->entityLabel(), true)
                . ', expected "Product"';
        }

        // ----------------------------------------------------------
        // Assertion 3: fields
        // ----------------------------------------------------------
        $fields = $handler->fields();
        foreach (['name', 'sku', 'slug', 'price'] as $required) {
            if (!isset($fields[$required])) {
                $failures[] = "assertion 3: field '{$required}' not declared";
                continue;
            }
            if (($fields[$required]['required'] ?? false) !== true) {
                $failures[] = "assertion 3: field '{$required}' is not required";
            }
        }

        // ----------------------------------------------------------
        // Assertion 4: fails on empty name
        // ----------------------------------------------------------
        $bad1 = [
            'name'  => '',
            'sku'   => 'SMOKE-1',
            'slug'  => 'smoke-1',
            'price' => '9.99',
        ];
        if ($handler->validate($bad1) === null) {
            $failures[] = 'assertion 4: validate accepted an empty name';
        }

        // ----------------------------------------------------------
        // Assertion 5: fails on non-numeric price
        // ----------------------------------------------------------
        $bad2 = [
            'name'  => 'Smoke Product',
            'sku'   => 'SMOKE-2',
            'slug'  => 'smoke-2',
            'price' => 'not-a-number',
        ];
        if ($handler->validate($bad2) === null) {
            $failures[] = 'assertion 5: validate accepted a non-numeric price';
        }

        // ----------------------------------------------------------
        // Assertion 6: fails on unknown brand slug
        // ----------------------------------------------------------
        $bad3 = [
            'name'       => 'Smoke Product',
            'sku'        => 'SMOKE-3',
            'slug'       => 'smoke-3',
            'price'      => '9.99',
            'brand_slug' => 'nonexistent-brand-' . bin2hex(random_bytes(3)),
        ];
        if ($handler->validate($bad3) === null) {
            $failures[] = 'assertion 6: validate accepted an unknown brand slug';
        }

        // ----------------------------------------------------------
        // Fixture: a real brand so the good row passes
        // ----------------------------------------------------------
        $brand = new Brand();
        $brand->slug = 'smoke-brand-' . bin2hex(random_bytes(4));
        $brand->name = 'Smoke Brand';
        $brand->status = 'active';
        $brandId = $brandRepo->create($brand);
        if ($brandId <= 0) {
            $failures[] = 'fixture: could not create a brand';
        }

        // ----------------------------------------------------------
        // Assertion 7: passes on a well-formed row
        // ----------------------------------------------------------
        if ($brandId > 0) {
            $good = [
                'name'           => 'Smoke Product',
                'sku'            => 'SMOKE-' . strtoupper(bin2hex(random_bytes(4))),
                'slug'           => 'smoke-' . bin2hex(random_bytes(4)),
                'price'          => '9.99',
                'brand_slug'     => $brand->slug,
                'category_slugs' => '',
                'description'    => 'A smoke-test product.',
            ];
            $goodResult = $handler->validate($good);
            if ($goodResult !== null) {
                $failures[] = 'assertion 7: validate rejected a good row: '
                    . var_export($goodResult, true);
            }

            // ----------------------------------------------------------
            // Assertion 8: naturalKey uppercases the SKU
            // ----------------------------------------------------------
            $k1 = $handler->naturalKey(['sku' => 'smoke-xyz']);
            $k2 = $handler->naturalKey(['sku' => '  SMOKE-XYZ  ']);
            if ($k1 !== 'SMOKE-XYZ' || $k2 !== 'SMOKE-XYZ') {
                $failures[] = 'assertion 8: naturalKey normalisation failed: '
                    . var_export([$k1, $k2], true);
            }
        }

    } finally {
        if ($brandId > 0) {
            $pdo->prepare("DELETE FROM `commerce_brands` WHERE `id` = ?")
                ->execute([$brandId]);
        }
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
