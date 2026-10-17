<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Models\Brand;
use CorporateAid\Commerce\Repositories\BrandRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

/**
 * Import handler for commerce_brands. Stage 016.
 *
 * The smallest entity handler: two fields (name, slug),
 * one natural key (slug), no foreign keys. It is the
 * first handler built because its shape is the simplest
 * and the pipeline's correctness can be proved against it
 * before the six remaining handlers are added.
 *
 * Natural key: the slug, trimmed and lowercased. A CSV
 * with "Summer" and "summer" resolves to the same brand;
 * the second row updates the first.
 *
 * Update semantics: name and slug only. The CSV does not
 * carry description, logo, website_url, sort_order, or
 * the SEO fields. A batch that re-imports an existing
 * brand changes only the fields it supplied. That is the
 * "the CSV touches what it names" rule; a column the CSV
 * does not name is left alone.
 *
 * Rollback: a create is deleted; an update restores the
 * previous name and slug. The previous_values_json on the
 * import row carries exactly those two fields. The
 * rollback of an update cannot resurrect a description
 * the update did not touch - it was never changed.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportBrandHandler implements ImportHandler
{
    public function __construct(private BrandRepository $brands)
    {
    }

    public function entityKey(): string
    {
        return 'brand';
    }

    public function entityLabel(): string
    {
        return 'Brand';
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
                'hint'     => 'The brand name as it appears to customers.',
            ],
            'slug' => [
                'label'    => 'Slug',
                'required' => true,
                'hint'     => 'Lowercase, letters, digits, and hyphens only. Used in URLs and as the natural key for idempotency.',
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

        if ($name === '') {
            return 'Name is required.';
        }
        if (mb_strlen($name) > 120) {
            return 'Name must be 120 characters or fewer.';
        }
        if ($slug === '') {
            return 'Slug is required.';
        }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return 'Slug must be lowercase letters, digits, and hyphens, '
                . 'and cannot start or end with a hyphen.';
        }
        if (mb_strlen($slug) > 120) {
            return 'Slug must be 120 characters or fewer.';
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
        return $this->brands->findBySlug($naturalKey) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        $brand = new Brand();
        $brand->slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $brand->name = trim((string) ($row['name'] ?? ''));
        $brand->status = 'active';

        return $this->brands->create($brand);
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        $existing = $this->brands->findBySlug($naturalKey);
        if ($existing === null) {
            return [];
        }

        // Capture the values an update can change. Only
        // the fields the CSV supplied are overwritten; the
        // rest of the brand row is untouched.
        $previous = [
            'name' => $existing->name,
            'slug' => $existing->slug,
        ];

        $existing->name = trim((string) ($row['name'] ?? $existing->name));
        $existing->slug = strtolower(
            trim((string) ($row['slug'] ?? $existing->slug))
        );

        $this->brands->update($existing);

        return $previous;
    }

    public function delete(int $entityId): void
    {
        $this->brands->softDelete($entityId);
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        $existing = $this->brands->findById($entityId);
        if ($existing === null) {
            return;
        }

        if (array_key_exists('name', $previousValues)) {
            $existing->name = (string) $previousValues['name'];
        }
        if (array_key_exists('slug', $previousValues)) {
            $existing->slug = (string) $previousValues['slug'];
        }

        $this->brands->update($existing);
    }

    public function findIdByNaturalKey(string $naturalKey): ?int
    {
        return $this->brands->findBySlug($naturalKey)?->id;
    }
}
