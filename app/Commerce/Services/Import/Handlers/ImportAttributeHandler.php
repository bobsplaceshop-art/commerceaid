<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Repositories\AttributeRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

/**
 * Import handler for commerce_attribute_definitions. Stage 016.
 *
 * Four fields: name (required), slug (required),
 * input_type (optional, default 'select'),
 * is_variant_axis (optional, default false).
 *
 * Natural key: the slug, trimmed and lowercased.
 * Attribute definitions are a store-independent
 * vocabulary - the same Color is used across every
 * store - so the natural key is global, not
 * store-scoped. This handler's exists() check resolves
 * against the whole table.
 *
 * Update semantics: name, slug, input_type,
 * is_variant_axis are the fields the CSV touches.
 * sort_order is not in the CSV and stays as-is.
 *
 * Rollback: a create is hard-deleted (the table has no
 * archive column; a newly-created attribute referenced
 * only by this batch has no reason to survive). An
 * update restores the previous name, slug, input_type,
 * and is_variant_axis.
 *
 * The is_variant_axis field accepts the standard CSV
 * boolean forms: 1, true, yes, on (case-insensitive)
 * mean true; everything else means false. An empty
 * cell means false.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportAttributeHandler implements ImportHandler
{
    public function __construct(private AttributeRepository $attributes)
    {
    }

    public function entityKey(): string
    {
        return 'attribute';
    }

    public function entityLabel(): string
    {
        return 'Attribute';
    }

    /**
     * @return array<string, array{label: string, required: bool, hint: string}>
     */
    public function fields(): array
    {
        return [
            'name' => [
                'label'    => 'Name',
                'required' => true,
                'hint'     => 'The attribute name (Color, Size, Material).',
            ],
            'slug' => [
                'label'    => 'Slug',
                'required' => true,
                'hint'     => 'Lowercase, letters, digits, and hyphens only. Used in URLs and as the natural key.',
            ],
            'input_type' => [
                'label'    => 'Input type',
                'required' => false,
                'hint'     => "One of: select, color, text, number. Defaults to 'select'.",
            ],
            'is_variant_axis' => [
                'label'    => 'Variant axis',
                'required' => false,
                'hint'     => '1, true, yes, or on means this attribute generates product variants. Empty means no.',
            ],
        ];
    }

    /**
     * @param array<string, string> $row
     */
    public function validate(array $row): ?string
    {
        $name = trim((string) ($row['name'] ?? ''));
        $slug = trim((string) ($row['slug'] ?? ''));
        $type = trim((string) ($row['input_type'] ?? 'select'));

        if ($name === '') {
            return 'Name is required.';
        }
        if (mb_strlen($name) > 100) {
            return 'Name must be 100 characters or fewer.';
        }
        if ($slug === '') {
            return 'Slug is required.';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Slug must be lowercase letters, digits, and hyphens, '
                . 'and cannot start or end with a hyphen.';
        }
        if (mb_strlen($slug) > 60) {
            return 'Slug must be 60 characters or fewer.';
        }

        if ($type === '') {
            $type = 'select';
        }
        if (!in_array($type, ['select', 'color', 'text', 'number'], true)) {
            return "Input type must be one of: select, color, text, number.";
        }

        return null;
    }

    /**
     * @param array<string, string> $row
     */
    public function naturalKey(array $row): string
    {
        return strtolower(trim((string) ($row['slug'] ?? '')));
    }

    public function exists(string $naturalKey): bool
    {
        return $this->attributes->findBySlug($naturalKey) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        return $this->attributes->create([
            'slug'            => strtolower(trim((string) ($row['slug'] ?? ''))),
            'name'            => trim((string) ($row['name'] ?? '')),
            'input_type'      => $this->normalizeInputType($row['input_type'] ?? ''),
            'is_variant_axis' => $this->parseBoolean($row['is_variant_axis'] ?? ''),
            'sort_order'      => 0,
        ]);
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        $existing = $this->attributes->findBySlug($naturalKey);
        if ($existing === null) {
            return [];
        }

        $previous = [
            'name'            => (string) $existing['name'],
            'slug'            => (string) $existing['slug'],
            'input_type'      => (string) ($existing['input_type'] ?? 'select'),
            'is_variant_axis' => (int) ($existing['is_variant_axis'] ?? 0) === 1,
        ];

        $this->attributes->update((int) $existing['id'], [
            'slug'            => strtolower(trim((string) ($row['slug'] ?? $existing['slug']))),
            'name'            => trim((string) ($row['name'] ?? $existing['name'])),
            'input_type'      => $this->normalizeInputType(
                $row['input_type'] ?? (string) $existing['input_type']
            ),
            'is_variant_axis' => array_key_exists('is_variant_axis', $row)
                ? $this->parseBoolean($row['is_variant_axis'])
                : ((int) ($existing['is_variant_axis'] ?? 0) === 1),
            'sort_order'      => (int) ($existing['sort_order'] ?? 0),
        ]);

        return $previous;
    }

    public function delete(int $entityId): void
    {
        $this->attributes->softDelete($entityId);
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        // The pipeline passes the entity id it recorded
        // at create or update time. The id is the row
        // that the batch wrote; the restore overwrites
        // its mutable fields with the previous values.
        // No existence check: if the row is gone, the
        // rollback has nothing to do (a later step
        // records the missing row as an error).
        $this->attributes->update($entityId, [
            'slug'            => strtolower((string) ($previousValues['slug'] ?? '')),
            'name'            => (string) ($previousValues['name'] ?? ''),
            'input_type'      => (string) ($previousValues['input_type'] ?? 'select'),
            'is_variant_axis' => !empty($previousValues['is_variant_axis']),
            'sort_order'      => 0,
        ]);
    }

    public function findIdByNaturalKey(string $naturalKey): ?int
    {
        $row = $this->attributes->findBySlug($naturalKey);
        return $row !== null ? (int) $row['id'] : null;
    }

    // -----------------------------------------------------------------

    private function normalizeInputType(string $type): string
    {
        $type = strtolower(trim($type));
        if ($type === '') {
            return 'select';
        }
        if (!in_array($type, ['select', 'color', 'text', 'number'], true)) {
            return 'select';
        }
        return $type;
    }

    private function parseBoolean(string $value): bool
    {
        $value = strtolower(trim($value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
