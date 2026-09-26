<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Category;

/**
 * Category repository.
 *
 * Every method that reads or writes a category is scoped to the
 * current store via CommerceRepository::storeId(). No exceptions.
 */
final class CategoryRepository extends CommerceRepository
{
    /** @return array<int, Category> */
    public function listAll(bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM `commerce_categories`
                 WHERE `store_id` = ?
                   AND `deleted_at` IS NULL";
        if (!$includeArchived) {
            $sql .= " AND `status` = 'active'";
        }
        $sql .= " ORDER BY `sort_order` ASC, `name` ASC";

        $rows = $this->db->select($sql, [$this->storeId()]);
        return array_map(fn ($r) => Category::fromRow($r), $rows);
    }

    /**
     * Return categories as a tree: each node has a `children` array.
     * @return array<int, array<string,mixed>>
     */
    public function tree(bool $includeArchived = false): array
    {
        $flat = $this->listAll($includeArchived);
        $byId = [];
        foreach ($flat as $c) {
            $byId[$c->id] = [
                'category' => $c,
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($byId as $id => &$node) {
            $parentId = $node['category']->parentId;
            if ($parentId === null || !isset($byId[$parentId])) {
                $roots[] = &$node;
            } else {
                $byId[$parentId]['children'][] = &$node;
            }
        }
        unset($node);

        return $roots;
    }

    public function findById(int $id): ?Category
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_categories`
              WHERE `id` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$id, $this->storeId()]
        );
        return $row ? Category::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Category
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_categories`
              WHERE `slug` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$slug, $this->storeId()]
        );
        return $row ? Category::fromRow($row) : null;
    }

    /**
     * Return descendant IDs of a category (all levels below it).
     * Used to prevent "move category into its own child" loops.
     *
     * @return int[]
     */
    public function descendantIds(int $categoryId): array
    {
        $ids = [];
        $queue = [$categoryId];
        while ($queue !== []) {
            $current = array_shift($queue);
            $children = $this->db->select(
                "SELECT `id` FROM `commerce_categories`
                  WHERE `parent_id` = ? AND `store_id` = ? AND `deleted_at` IS NULL",
                [$current, $this->storeId()]
            );
            foreach ($children as $row) {
                $childId = (int) $row['id'];
                if (!in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $ids;
    }

    /**
     * Create a new category. Returns the new id.
     */
    public function create(Category $category): int
    {
        $category->assertNotOwnParent();

        return $this->db->insert(
            "INSERT INTO `commerce_categories`
                (`store_id`, `parent_id`, `slug`, `name`, `description`,
                 `image_file_id`, `sort_order`,
                 `seo_title`, `seo_description`, `seo_keywords`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $category->parentId,
                $category->slug,
                $category->name,
                $category->description,
                $category->imageFileId,
                $category->sortOrder,
                $category->seoTitle,
                $category->seoDescription,
                $category->seoKeywords,
                $category->status,
            ]
        );
    }

    public function update(Category $category): void
    {
        $category->assertNotOwnParent();

        // Prevent moving a category into one of its own descendants.
        if ($category->parentId !== null) {
            $descendants = $this->descendantIds($category->id);
            if (in_array($category->parentId, $descendants, true)) {
                throw new \InvalidArgumentException(
                    'Cannot move a category into one of its own descendants.'
                );
            }
        }

        $this->db->execute(
            "UPDATE `commerce_categories`
                SET `parent_id` = ?, `slug` = ?, `name` = ?, `description` = ?,
                    `image_file_id` = ?, `sort_order` = ?,
                    `seo_title` = ?, `seo_description` = ?, `seo_keywords` = ?,
                    `status` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                $category->parentId,
                $category->slug,
                $category->name,
                $category->description,
                $category->imageFileId,
                $category->sortOrder,
                $category->seoTitle,
                $category->seoDescription,
                $category->seoKeywords,
                $category->status,
                $category->id,
                $this->storeId(),
            ]
        );
    }

    /**
     * Soft-archive a category. Products in this category stay in
     * other categories (via the junction) or become uncategorized.
     */
    public function archive(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_categories`
                SET `status` = 'archived'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    /**
     * Restore an archived category to active.
     */
    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_categories`
                SET `status` = 'active'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    /**
     * Soft-delete a category. Moves it to deleted_at, removes it
     * from every active listing. The DB row survives for audit.
     */
    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_categories`
                SET `deleted_at` = NOW()
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_categories`
              WHERE `store_id` = ? AND `deleted_at` IS NULL",
            [$this->storeId()]
        );
    }
}
