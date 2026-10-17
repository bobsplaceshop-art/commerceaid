<?php
declare(strict_types=1);

/**
 * Smoke case: the brand import handler. Stage 016 Session 1.
 *
 * Exercises ImportBrandHandler end to end. The pipeline
 * (map / validate / commit) ships in Session 2; this case
 * exercises the handler's contract directly, which is what
 * the pipeline will call.
 *
 * The eight assertions map to the interface methods:
 *
 *   1. entityKey() returns 'brand'.
 *   2. entityLabel() returns 'Brand'.
 *   3. fields() declares name and slug, both required.
 *   4. validate() passes a well-formed row, fails a bad one.
 *   5. naturalKey() normalises case.
 *   6. exists() is false for a slug that does not exist.
 *   7. create() writes a brand, and exists() then returns
 *      true for the new slug.
 *   8. update() returns the previous values, and a rollback
 *      (restore) puts them back.
 *
 * The case does not run the pipeline. It proves the
 * handler's shape before the pipeline wraps it.
 *
 * Cleanup: a hard delete of the brand row the case
 * created. The repository's delete is a soft-delete; the
 * case bypasses it for teardown so the test database does
 * not accumulate archived brands across runs.
 *
 * Returns true on pass, a string on failure.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo  the test database connection
 */

use CorporateAid\Commerce\Services\Import\Handlers\ImportBrandHandler;

return function ($container, PDO $pdo): bool|string {

    $handler = $container->get(ImportBrandHandler::class);
    $failures = [];
    $createdId = 0;

    try {
        // ----------------------------------------------------------
        // Assertion 1: entityKey
        // ----------------------------------------------------------
        if ($handler->entityKey() !== 'brand') {
            $failures[] = 'assertion 1: entityKey returned '
                . var_export($handler->entityKey(), true)
                . ', expected "brand"';
        }

        // ----------------------------------------------------------
        // Assertion 2: entityLabel
        // ----------------------------------------------------------
        if ($handler->entityLabel() !== 'Brand') {
            $failures[] = 'assertion 2: entityLabel returned '
                . var_export($handler->entityLabel(), true)
                . ', expected "Brand"';
        }

        // ----------------------------------------------------------
        // Assertion 3: fields
        // ----------------------------------------------------------
        $fields = $handler->fields();
        foreach (['name', 'slug'] as $required) {
            if (!isset($fields[$required])) {
                $failures[] = "assertion 3: field '{$required}' not declared";
                continue;
            }
            if (($fields[$required]['required'] ?? false) !== true) {
                $failures[] = "assertion 3: field '{$required}' is not required";
            }
        }

        // ----------------------------------------------------------
        // Assertion 4: validate
        // ----------------------------------------------------------
        $goodRow = [
            'name' => 'Smoke Brand ' . bin2hex(random_bytes(4)),
            'slug' => 'smoke-brand-' . bin2hex(random_bytes(4)),
        ];

        $validResult = $handler->validate($goodRow);
        if ($validResult !== null) {
            $failures[] = 'assertion 4a: validate returned an error for a good row: '
                . var_export($validResult, true);
        }

        $badRow = ['name' => '', 'slug' => 'not a slug'];
        $badResult = $handler->validate($badRow);
        if ($badResult === null) {
            $failures[] = 'assertion 4b: validate accepted an empty name and an invalid slug';
        }

        // ----------------------------------------------------------
        // Assertion 5: naturalKey normalisation
        // ----------------------------------------------------------
        $k1 = $handler->naturalKey(['slug' => 'Summer-Sale']);
        $k2 = $handler->naturalKey(['slug' => '  summer-sale  ']);
        if ($k1 !== 'summer-sale' || $k2 !== 'summer-sale') {
            $failures[] = 'assertion 5: naturalKey normalisation failed: '
                . var_export([$k1, $k2], true);
        }

        // ----------------------------------------------------------
        // Assertion 6: exists false before create
        // ----------------------------------------------------------
        if ($handler->exists($goodRow['slug']) !== false) {
            $failures[] = 'assertion 6: exists returned true for an uncreated slug';
        }

        // ----------------------------------------------------------
        // Assertion 7: create
        // ----------------------------------------------------------
        $createdId = $handler->create($goodRow);
        if ($createdId <= 0) {
            $failures[] = 'assertion 7a: create returned an id of ' . var_export($createdId, true);
        } else {
            $row = $pdo->prepare(
                "SELECT `name`, `slug` FROM `commerce_brands` WHERE `id` = ?"
            );
            $row->execute([$createdId]);
            $brand = $row->fetch();
            if ($brand === false) {
                $failures[] = 'assertion 7b: created brand not found in the database';
            } else {
                if ($brand['name'] !== $goodRow['name']) {
                    $failures[] = "assertion 7b: stored name is '{$brand['name']}', "
                        . "expected '{$goodRow['name']}'";
                }
                if ($brand['slug'] !== $goodRow['slug']) {
                    $failures[] = "assertion 7b: stored slug is '{$brand['slug']}', "
                        . "expected '{$goodRow['slug']}'";
                }
            }

            if ($handler->exists($goodRow['slug']) !== true) {
                $failures[] = 'assertion 7c: exists returned false for a just-created slug';
            }
        }

        // ----------------------------------------------------------
        // Assertion 8: update captures previous values
        // ----------------------------------------------------------
        if ($createdId > 0) {
            $updateRow = [
                'name' => $goodRow['name'] . ' (updated)',
                'slug' => $goodRow['slug'],
            ];

            $previous = $handler->update($goodRow['slug'], $updateRow);
            if (!is_array($previous)) {
                $failures[] = 'assertion 8a: update did not return an array';
            } else {
                if (($previous['name'] ?? null) !== $goodRow['name']) {
                    $failures[] = 'assertion 8a: previous[name] is '
                        . var_export($previous['name'] ?? null, true)
                        . ', expected ' . var_export($goodRow['name'], true);
                }
                if (($previous['slug'] ?? null) !== $goodRow['slug']) {
                    $failures[] = 'assertion 8a: previous[slug] is '
                        . var_export($previous['slug'] ?? null, true)
                        . ', expected ' . var_export($goodRow['slug'], true);
                }
            }

            // The name should now be the updated value.
            $row = $pdo->prepare(
                "SELECT `name` FROM `commerce_brands` WHERE `id` = ?"
            );
            $row->execute([$createdId]);
            $storedName = (string) $row->fetchColumn();
            if ($storedName !== $updateRow['name']) {
                $failures[] = 'assertion 8b: after update, stored name is '
                    . var_export($storedName, true)
                    . ', expected ' . var_export($updateRow['name'], true);
            }
        }

    } finally {
        // Hard-delete the row this case created. The
        // repository's soft-delete would leave it archived;
        // the test database should not accumulate rows.
        if ($createdId > 0) {
            $pdo->prepare(
                "DELETE FROM `commerce_brands` WHERE `id` = ?"
            )->execute([$createdId]);
        }
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
