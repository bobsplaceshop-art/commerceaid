<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Services\Import\Handlers;

use CorporateAid\Commerce\Models\Category;
use CorporateAid\Commerce\Repositories\CategoryRepository;
use CorporateAid\Commerce\Services\Import\ImportHandler;

/**
 * Import handler for commerce_categories. Stage 016.
 *
 * Three fields: name, slug, optional parent_slug.
 *
 * Natural key: the slug, trimmed and lowercased. A CSV
 * with "Summer" and "summer" resolves to the same
 * category.
 *
 * Parent resolution: parent_slug names another category
 * in the same store. The parent must already exist -
 * either from an earlier row in this batch or from a
 * prior import or the admin UI. A parent_slug that does
 * not resolve is a per-row validation error.
 *
 * Update semantics: name, slug, parent_id, description
 * are the fields the CSV touches. A missing field leaves
 * the existing value alone.
 *
 * Rollback: a create is soft-deleted; an update restores
 * the previous name, slug, parent_id, and description.
 *
 * The CategoryRepository::update() method enforces the
 * "cannot move a category into its own descendant" rule.
 * A batch that would create a cycle fails at that row
 * during commit, and the chunk rolls back. Validation
 * does not pre-check the cycle - it would need to walk
 * the full tree per row, which is O(n^2). The database
 * enforces it at commit time, which is where the cycle
 * would actually be created.
 *
 * See docs/stages/016_data-import-plan.md.
 */
final class ImportCategoryHandler implements ImportHandler
{
    public function __construct(private CategoryRepository $categories)
    {
    }

    public function entityKey(): string
    {
        return 'category';
    }

    public function entityLabel(): string
    {
        return 'Category';
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
                'hint'     => 'The category name as it appears to customers.',
            ],
            'slug' => [
                'label'    => 'Slug',
                'required' => true,
                'hint'     => 'Lowercase, letters, digits, and hyphens only. Used in URLs and as the natural key.',
            ],
            'parent_slug' => [
                'label'    => 'Parent slug',
                'required' => false,
                'hint'     => 'Optional. The slug of an existing category. Leave blank for a root category.',
            ],
            'description' => [
                'label'    => 'Description',
                'required' => false,
                'hint'     => 'Optional. Short text shown on the category page.',
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
        $parentSlug = strtolower(trim((string) ($row['parent_slug'] ?? '')));

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

        // A self-referencing parent is a validation error,
        // not a database error. The controller would
        // otherwise see an exception from
        // Category::assertNotOwnParent() at commit time.
        if ($parentSlug !== '' && $parentSlug === strtolower($slug)) {
            return 'A category cannot be its own parent.';
        }

        // A non-empty parent_slug must resolve to a
        // category in this store. The parent can be from
        // an earlier row in this batch (validated and
        // committed) or from a prior import or the admin.
        if ($parentSlug !== '') {
            $parent = $this->categories->findBySlug($parentSlug);
            if ($parent === null) {
                return "Parent category '{$parentSlug}' not found. Import the parent first.";
            }
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
        return $this->categories->findBySlug($naturalKey) !== null;
    }

    /**
     * @param array<string, string> $row
     */
    public function create(array $row): int
    {
        $category = new Category();
        $category->slug = strtolower(trim((string) ($row['slug'] ?? '')));
        $category->name = trim((string) ($row['name'] ?? ''));
        $category->description = $this->nullIfBlank($row['description'] ?? null);
        $category->status = 'active';
        $category->parentId = $this->resolveParentId(
            (string) ($row['parent_slug'] ?? '')
        );

        return $this->categories->create($category);
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    public function update(string $naturalKey, array $row): array
    {
        $existing = $this->categories->findBySlug($naturalKey);
        if ($existing === null) {
            return [];
        }

        $previous = [
            'name'        => $existing->name,
            'slug'        => $existing->slug,
            'parent_id'   => $existing->parentId,
            'description' => $existing->description,
        ];

        $existing->name = trim((string) ($row['name'] ?? $existing->name));
        $existing->slug = strtolower(
            trim((string) ($row['slug'] ?? $existing->slug))
        );
        if (array_key_exists('description', $row)) {
            $existing->description = $this->nullIfBlank($row['description']);
        }
        if (array_key_exists('parent_slug', $row)) {
            $existing->parentId = $this->resolveParentId(
                (string) $row['parent_slug']
            );
        }

        $this->categories->update($existing);

        return $previous;
    }

    public function delete(int $entityId): void
    {
        $this->categories->softDelete($entityId);
    }

    /**
     * @param array<string, mixed> $previousValues
     */
    public function restore(int $entityId, array $previousValues): void
    {
        $existing = $this->categories->findById($entityId);
        if ($existing === null) {
            return;
        }

        if (array_key_exists('name', $previousValues)) {
            $existing->name = (string) $previousValues['name'];
        }
        if (array_key_exists('slug', $previousValues)) {
            $existing->slug = (string) $previousValues['slug'];
        }
        if (array_key_exists('parent_id', $previousValues)) {
            $existing->parentId = $previousValues['parent_id'] === null
                ? null
                : (int) $previousValues['parent_id'];
        }
        if (array_key_exists('description', $previousValues)) {
            $existing->description = $previousValues['description'] === null
                ? null
                : (string) $previousValues['description'];
        }

        $this->categories->update($existing);
    }

    // -----------------------------------------------------------------

    private function resolveParentId(string $parentSlug): ?int
    {
        $parentSlug = strtolower(trim($parentSlug));
        if ($parentSlug === '') {
            return null;
        }
        $parent = $this->categories->findBySlug($parentSlug);
        return $parent?->id;
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
