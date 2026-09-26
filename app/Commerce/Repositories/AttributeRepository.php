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
}
