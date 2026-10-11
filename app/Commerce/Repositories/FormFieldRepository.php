<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\FormField;

/**
 * Form fields. Stage 009.
 *
 * Every read filters store_id. Fields are hard-deleted;
 * they have no separate audit value (the submissions
 * live in their own table and are keyed by field_key,
 * not by field id).
 *
 * See docs/stages/009_forms-plan.md.
 */
final class FormFieldRepository extends CommerceRepository
{
    public function findById(int $id): ?FormField
    {
        if ($id <= 0) return null;

        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `id` = ? LIMIT 1",
            [$this->storeId(), $id]
        );

        return $row !== null ? FormField::fromRow($row) : null;
    }

    /**
     * All fields of a form, ordered by step then position.
     * Includes inactive fields; the admin needs to see them
     * so they can be reactivated.
     *
     * @return array<int,FormField>
     */
    public function listForForm(int $formId): array
    {
        if ($formId <= 0) return [];

        $rows = $this->db->select(
            "SELECT * FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `form_id` = ?
              ORDER BY `step` ASC, `sort_order` ASC, `id` ASC",
            [$this->storeId(), $formId]
        );

        return array_map(fn ($r) => FormField::fromRow($r), $rows);
    }

    /**
     * Only active fields, ordered the same way. Used by
     * the public renderer and the submission service.
     *
     * @return array<int,FormField>
     */
    public function listActiveForForm(int $formId): array
    {
        if ($formId <= 0) return [];

        $rows = $this->db->select(
            "SELECT * FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `form_id` = ? AND `is_active` = 1
              ORDER BY `step` ASC, `sort_order` ASC, `id` ASC",
            [$this->storeId(), $formId]
        );

        return array_map(fn ($r) => FormField::fromRow($r), $rows);
    }

    /**
     * Active fields grouped by step.
     *
     * @return array<int,array<int,FormField>>  step => fields
     */
    public function listGroupedByStep(int $formId): array
    {
        $fields = $this->listActiveForForm($formId);

        $grouped = [];
        foreach ($fields as $field) {
            $grouped[$field->step][] = $field;
        }
        ksort($grouped);

        return $grouped;
    }

    /**
     * A field key must be unique within a form. Case
     * insensitive to avoid submission lookup collisions.
     */
    public function fieldKeyExists(int $formId, string $fieldKey, ?int $excludeId = null): bool
    {
        if ($formId <= 0 || $fieldKey === '') return false;

        $sql    = "SELECT COUNT(*) FROM `commerce_form_fields`
                    WHERE `store_id` = ? AND `form_id` = ? AND `field_key` = ?";
        $params = [$this->storeId(), $formId, $fieldKey];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND `id` != ?";
            $params[] = $excludeId;
        }

        return ((int) $this->db->scalar($sql, $params)) > 0;
    }

    /**
     * Next free sort_order within a step. Used by create()
     * when the caller does not specify one.
     */
    public function nextSortOrder(int $formId, int $step): int
    {
        if ($formId <= 0) return 0;

        $max = $this->db->scalar(
            "SELECT MAX(`sort_order`) FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `form_id` = ? AND `step` = ?",
            [$this->storeId(), $formId, $step]
        );

        return $max === null ? 0 : ((int) $max) + 10;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        $data = array_merge(['store_id' => $this->storeId()], $data);

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList   = '`' . implode('`, `', $columns) . '`';

        return (int) $this->db->insert(
            "INSERT INTO `commerce_form_fields` ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        if ($id <= 0 || $data === []) return;

        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]   = "`{$col}` = ?";
            $params[] = $val;
        }
        $params[] = $this->storeId();
        $params[] = $id;

        $this->db->execute(
            "UPDATE `commerce_form_fields`
                SET " . implode(', ', $sets) . "
              WHERE `store_id` = ? AND `id` = ?",
            $params
        );
    }

    public function delete(int $id): void
    {
        if ($id <= 0) return;

        $this->db->execute(
            "DELETE FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
    }

    public function deleteForForm(int $formId): void
    {
        if ($formId <= 0) return;

        $this->db->execute(
            "DELETE FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `form_id` = ?",
            [$this->storeId(), $formId]
        );
    }

    /**
     * Write sort_order values from an ordered list of ids.
     * Called after a drag, an up/down move, or an explicit
     * reorder in the editor.
     *
     * @param array<int,int> $orderedIds
     */
    public function reorder(int $formId, array $orderedIds): void
    {
        if ($formId <= 0 || $orderedIds === []) return;

        $position = 0;
        foreach ($orderedIds as $fieldId) {
            $fieldId = (int) $fieldId;
            if ($fieldId <= 0) continue;

            $this->db->execute(
                "UPDATE `commerce_form_fields`
                    SET `sort_order` = ?
                  WHERE `store_id` = ? AND `form_id` = ? AND `id` = ?",
                [$position, $this->storeId(), $formId, $fieldId]
            );
            $position += 10;
        }
    }

    public function countForForm(int $formId): int
    {
        if ($formId <= 0) return 0;

        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `form_id` = ?",
            [$this->storeId(), $formId]
        );
    }

    /**
     * The highest step number used by any active field on
     * the form. 1 means a single-step form.
     */
    public function maxStepForForm(int $formId): int
    {
        if ($formId <= 0) return 1;

        $max = $this->db->scalar(
            "SELECT MAX(`step`) FROM `commerce_form_fields`
              WHERE `store_id` = ? AND `form_id` = ? AND `is_active` = 1",
            [$this->storeId(), $formId]
        );

        return $max === null ? 1 : max(1, (int) $max);
    }
}
