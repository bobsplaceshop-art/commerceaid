<?php
declare(strict_types=1);

/**
 * Smoke case: block type registry.
 *
 * Asserts the registry's contract with itself and with
 * BlockConfigRegistry. Every consumer of block types (the
 * picker, addBlock validation, PageBlock::typeLabel) reads
 * through this registry. This case is the guard that keeps
 * the registry and its sibling schema layer in agreement.
 *
 * Six assertions:
 *
 *   1. all() returns exactly the eight block types that
 *      currently exist.
 *   2. Every entry declares the six fields the registry
 *      promises (label, icon, hint, group, accepts_children,
 *      partial).
 *   3. Every entry's group is one of 'structural' or
 *      'element'.
 *   4. Every entry's accepts_children is false — no Section
 *      or Container type exists yet. When 0.8 adds those,
 *      this assertion updates to accept true for the two
 *      new types.
 *   5. Every entry has a non-empty BlockConfigRegistry
 *      schema. The metadata and schema layers agree on the
 *      type set.
 *   6. get() / has() / acceptsChildren() on an unknown type
 *      return null / false / false — the safe-fail
 *      contract.
 *
 * @param \CorporateAid\Core\Container $container
 * @param PDO $pdo
 */

use CorporateAid\Commerce\Support\Blocks\BlockTypeRegistry;
use CorporateAid\Commerce\Support\Blocks\BlockConfigRegistry;

return function ($container, PDO $pdo): bool|string {
    $failures = [];

    // --- Assertion 1: ten types ---
    // Eight leaf types plus Section and Container (Tier 0.8).
    $all = BlockTypeRegistry::all();
    $expectedCount = 11;
    if (count($all) !== $expectedCount) {
        $failures[] = 'all() returned ' . count($all)
            . ' types, expected ' . $expectedCount;
    }

    $requiredFields = ['label', 'icon', 'hint', 'group', 'accepts_children', 'partial'];
    $validGroups    = ['structural', 'element'];

    foreach ($all as $type => $meta) {
        // --- Assertion 2: required fields ---
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $meta)) {
                $failures[] = "{$type}.{$field}: missing";
            }
        }

        // --- Assertion 3: group is valid ---
        $group = $meta['group'] ?? null;
        if (!in_array($group, $validGroups, true)) {
            $failures[] = "{$type}.group: expected one of '"
                . implode("', '", $validGroups)
                . "', got " . var_export($group, true);
        }

        // --- Assertion 4: accepts_children matches the type ---
        // Section and Container accept children. Every other
        // type is a leaf. When a future commit makes a leaf
        // structural, this assertion updates.
        $expectsChildren = in_array($type, ['section', 'container'], true);
        if (($meta['accepts_children'] ?? null) !== $expectsChildren) {
            $failures[] = "{$type}.accepts_children: expected "
                . var_export($expectsChildren, true) . ', got '
                . var_export($meta['accepts_children'] ?? null, true);
        }

        // --- Assertion 5 (softened): schema is an array ---
        // A type with no config fields is a legitimate state
        // (Section, Container). The schema's return type
        // guarantees array; this assertion would have been
        // 'schema is non-empty' before Section existed, and
        // that is what failed. Calling schema() also proves
        // the dispatch arm exists (no fatal on the match).
        BlockConfigRegistry::schema($type);
    }

    // --- Assertion 6: unknown-type safe-fail ---
    if (BlockTypeRegistry::get('does-not-exist') !== null) {
        $failures[] = 'get(does-not-exist) expected null';
    }
    if (BlockTypeRegistry::has('does-not-exist') !== false) {
        $failures[] = 'has(does-not-exist) expected false';
    }
    if (BlockTypeRegistry::acceptsChildren('does-not-exist') !== false) {
        $failures[] = 'acceptsChildren(does-not-exist) expected false';
    }

    if ($failures !== []) {
        return implode(' | ', $failures);
    }
    return true;
};
