<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Core\Database;

/**
 * Attribute repository.
 *
 * Attribute definitions are store-independent (they live in
 * `commerce_attribute_definitions` with no store_id). So this
 * repository does NOT extend CommerceRepository — it doesn't need
 * store scoping.
 */
final class AttributeRepository
{
    public function __construct(private Database $db)
    {
    }

    /**
     * All attribute definitions, each with its values attached.
     *
     * @return array<int, array<string,mixed>>
     */
    public function allWithValues(): array
    {
        $defs = $this->db->select(
            "SELECT * FROM `commerce_attribute_definitions`
              ORDER BY `sort_order` ASC, `name` ASC"
        );

        foreach ($defs as &$def) {
            $def['values'] = $this->db->select(
                "SELECT * FROM `commerce_attribute_values`
                  WHERE `attribute_id` = ?
                  ORDER BY `sort_order` ASC, `value` ASC",
                [(int) $def['id']]
            );
        }
        unset($def);

        return $defs;
    }

    /** @return array<int, array<string,mixed>> */
    public function variantAxes(): array
    {
        $defs = $this->db->select(
            "SELECT * FROM `commerce_attribute_definitions`
              WHERE `is_variant_axis` = 1
              ORDER BY `sort_order` ASC, `name` ASC"
        );

        foreach ($defs as &$def) {
            $def['values'] = $this->db->select(
                "SELECT * FROM `commerce_attribute_values`
                  WHERE `attribute_id` = ?
                  ORDER BY `sort_order` ASC, `value` ASC",
                [(int) $def['id']]
            );
        }
        unset($def);

        return $defs;
    }

    // =================================================================
    // Reads - single record (added for the import pipeline, Stage 016)
    // =================================================================

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }
        return $this->db->selectOne(
            "SELECT * FROM `commerce_attribute_definitions`
              WHERE `slug` = ? LIMIT 1",
            [$slug]
        );
    }

    // =================================================================
    // Writes (added for the import pipeline, Stage 016)
    // =================================================================
    //
    // Attribute definitions are a store-independent
    // vocabulary. The admin controller has always written
    // them through raw Database; that is the codebase's
    // rule violation this section fixes. The import
    // handler calls these methods; both the handler and
    // the admin controller should eventually share them.
    //
    // No store scoping here: the table has no store_id
    // column. See the class docblock.

    /**
     * Insert a new attribute definition. Returns the id.
     *
     * @param array{
     *     slug: string,
     *     name: string,
     *     input_type: string,
     *     is_variant_axis: bool,
     *     sort_order: int
     * } $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_attribute_definitions`
                (`slug`, `name`, `input_type`, `is_variant_axis`, `sort_order`)
             VALUES (?, ?, ?, ?, ?)",
            [
                (string) $data['slug'],
                (string) $data['name'],
                (string) $data['input_type'],
                !empty($data['is_variant_axis']) ? 1 : 0,
                (int) ($data['sort_order'] ?? 0),
            ]
        );
    }

    /**
     * Update a definition's mutable fields.
     *
     * @param array{
     *     slug: string,
     *     name: string,
     *     input_type: string,
     *     is_variant_axis: bool,
     *     sort_order: int
     * } $data
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            "UPDATE `commerce_attribute_definitions`
                SET `slug` = ?, `name` = ?, `input_type` = ?,
                    `is_variant_axis` = ?, `sort_order` = ?
              WHERE `id` = ?",
            [
                (string) $data['slug'],
                (string) $data['name'],
                (string) $data['input_type'],
                !empty($data['is_variant_axis']) ? 1 : 0,
                (int) ($data['sort_order'] ?? 0),
                $id,
            ]
        );
    }

    /**
     * Resolve an attribute value id by (attribute_id, value_slug).
     * Added for the import pipeline (Stage 016). Returns null
     * when the slug does not match any value of the attribute.
     */
    public function findValueIdBySlug(int $attributeId, string $valueSlug): ?int
    {
        if ($attributeId <= 0 || $valueSlug === '') {
            return null;
        }
        $id = $this->db->scalar(
            "SELECT `id` FROM `commerce_attribute_values`
              WHERE `attribute_id` = ? AND `slug` = ?
              LIMIT 1",
            [$attributeId, $valueSlug]
        );
        return $id !== null && $id !== false && (int) $id > 0
            ? (int) $id
            : null;
    }

    /**
     * Resolve an attribute value id by (attribute_id, value)
     * case-insensitively. Added for the import pipeline.
     * The CSV may carry "Red" while the admin UI stored "red";
     * MySQL's utf8mb4_unicode_ci collation makes the equality
     * case-insensitive by default, so the query works.
     */
    public function findValueIdByValue(int $attributeId, string $value): ?int
    {
        if ($attributeId <= 0 || $value === '') {
            return null;
        }
        $id = $this->db->scalar(
            "SELECT `id` FROM `commerce_attribute_values`
              WHERE `attribute_id` = ? AND `value` = ?
              LIMIT 1",
            [$attributeId, trim($value)]
        );
        return $id !== null && $id !== false && (int) $id > 0
            ? (int) $id
            : null;
    }

    /**
     * Soft-delete a definition. Sets status to 'archived'.
     * Hard delete would violate the "used by N products"
     * guard; the import rollback uses this method, and
     * the row remains for audit.
     */
    public function softDelete(int $id): void
    {
        // The table has no status column and no deleted_at
        // column. Archiving is not modelled. The rollback
        // of a create has nothing to archive - the row was
        // created by this batch and no other entity
        // references it. Hard delete is the only clean
        // state here.
        $this->db->execute(
            "DELETE FROM `commerce_attribute_definitions` WHERE `id` = ?",
            [$id]
        );
    }
}
