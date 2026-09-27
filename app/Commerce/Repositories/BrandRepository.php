<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\Brand;

/**
 * Brand repository.
 *
 * Every query is scoped to the current store via
 * CommerceRepository::storeId().
 */
final class BrandRepository extends CommerceRepository
{
    /** @return array<int, Brand> */
    public function listAll(bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM `commerce_brands`
                 WHERE `store_id` = ?
                   AND `deleted_at` IS NULL";
        if (!$includeArchived) {
            $sql .= " AND `status` = 'active'";
        }
        $sql .= " ORDER BY `sort_order` ASC, `name` ASC";

        $rows = $this->db->select($sql, [$this->storeId()]);
        return array_map(fn ($r) => Brand::fromRow($r), $rows);
    }

    /**
     * Lightweight list for dropdowns (id + name only).
     * @return array<int, array<string,mixed>>
     */
    public function listForSelect(): array
    {
        return $this->db->select(
            "SELECT `id`, `name` FROM `commerce_brands`
              WHERE `store_id` = ? AND `status` = 'active' AND `deleted_at` IS NULL
              ORDER BY `name` ASC",
            [$this->storeId()]
        );
    }

    public function findById(int $id): ?Brand
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_brands`
              WHERE `id` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$id, $this->storeId()]
        );
        return $row ? Brand::fromRow($row) : null;
    }

    public function findBySlug(string $slug): ?Brand
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_brands`
              WHERE `slug` = ? AND `store_id` = ? AND `deleted_at` IS NULL
              LIMIT 1",
            [$slug, $this->storeId()]
        );
        return $row ? Brand::fromRow($row) : null;
    }

    /**
     * True if the slug is already used (optionally excluding a
     * specific brand id, for edit forms).
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM `commerce_brands`
                 WHERE `store_id` = ? AND `slug` = ? AND `deleted_at` IS NULL";
        $params = [$this->storeId(), $slug];

        if ($excludeId !== null) {
            $sql .= " AND `id` <> ?";
            $params[] = $excludeId;
        }

        return (int) $this->db->scalar($sql, $params) > 0;
    }

    public function create(Brand $brand): int
    {
        return $this->db->insert(
            "INSERT INTO `commerce_brands`
                (`store_id`, `slug`, `name`, `description`, `logo_file_id`,
                 `website_url`, `sort_order`,
                 `seo_title`, `seo_description`, `seo_keywords`, `status`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $brand->slug,
                $brand->name,
                $brand->description,
                $brand->logoFileId,
                $brand->websiteUrl,
                $brand->sortOrder,
                $brand->seoTitle,
                $brand->seoDescription,
                $brand->seoKeywords,
                $brand->status,
            ]
        );
    }

    public function update(Brand $brand): void
    {
        $this->db->execute(
            "UPDATE `commerce_brands`
                SET `slug` = ?, `name` = ?, `description` = ?, `logo_file_id` = ?,
                    `website_url` = ?, `sort_order` = ?,
                    `seo_title` = ?, `seo_description` = ?, `seo_keywords` = ?,
                    `status` = ?
              WHERE `id` = ? AND `store_id` = ?",
            [
                $brand->slug,
                $brand->name,
                $brand->description,
                $brand->logoFileId,
                $brand->websiteUrl,
                $brand->sortOrder,
                $brand->seoTitle,
                $brand->seoDescription,
                $brand->seoKeywords,
                $brand->status,
                $brand->id,
                $this->storeId(),
            ]
        );
    }

    public function archive(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_brands` SET `status` = 'archived'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function restore(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_brands` SET `status` = 'active'
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            "UPDATE `commerce_brands` SET `deleted_at` = NOW()
              WHERE `id` = ? AND `store_id` = ?",
            [$id, $this->storeId()]
        );
    }

    /**
     * Count of products assigned to this brand. Used before delete
     * to warn "N products use this brand".
     */
    public function productCount(int $brandId): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_products`
              WHERE `brand_id` = ? AND `store_id` = ? AND `deleted_at` IS NULL",
            [$brandId, $this->storeId()]
        );
    }

    public function count(): int
    {
        return (int) $this->db->scalar(
            "SELECT COUNT(*) FROM `commerce_brands`
              WHERE `store_id` = ? AND `deleted_at` IS NULL",
            [$this->storeId()]
        );
    }
}
