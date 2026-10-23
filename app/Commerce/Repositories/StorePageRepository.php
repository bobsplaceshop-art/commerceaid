<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

/**
 * Page Builder page metadata.
 *
 * One row per Page Builder page. Stores title, meta description,
 * OG image, robots flag, and publish status. The block content
 * lives in commerce_page_blocks; this table owns the page-level
 * metadata.
 *
 * The homepage is a special case. The storefront root ('/') is
 * served by HomeController, not by /pages/{slug}. But the merchant
 * still needs to edit the homepage's title and meta description.
 * So the page row with slug = 'home' exists here and drives the
 * SEO metadata for the root, but the public route for it is '/'
 * not '/pages/home'.
 */
final class StorePageRepository extends CommerceRepository
{
    /** @return array<int, array<string,mixed>> */
    public function list(): array
    {
        return $this->db->select(
            "SELECT `id`, `slug`, `title`, `status`, `noindex`,
                    `published_at`, `created_at`, `updated_at`
               FROM `commerce_store_pages`
              WHERE `store_id` = ?
              ORDER BY `created_at` ASC",
            [$this->storeId()]
        );
    }

    /** @return array<int, array<string,mixed>> */
    public function listPublished(): array
    {
        return $this->db->select(
            "SELECT `id`, `slug`, `title`, `meta_description`,
                    `og_image_file_id`, `updated_at`
               FROM `commerce_store_pages`
              WHERE `store_id` = ? AND `status` = 'published'
              ORDER BY `slug` ASC",
            [$this->storeId()]
        );
    }

    /** @return array<string,mixed>|null */
    public function find(string $slug): ?array
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_store_pages`
              WHERE `store_id` = ? AND `slug` = ?",
            [$this->storeId(), $slug]
        );
        return $row ?? null;
    }

    /** @return array<string,mixed>|null */
    public function findPublished(string $slug): ?array
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_store_pages`
              WHERE `store_id` = ? AND `slug` = ? AND `status` = 'published'",
            [$this->storeId(), $slug]
        );
        return $row ?? null;
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    public function create(
        string $slug,
        string $title,
        ?string $metaDescription = null,
        string $status = 'draft',
        ?int $ogImageFileId = null,
        bool $noindex = false
    ): int {
        return (int) $this->db->insert(
            "INSERT INTO `commerce_store_pages`
                (`store_id`, `slug`, `title`, `meta_description`,
                 `og_image_file_id`, `status`, `noindex`, `published_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $this->storeId(),
                $slug,
                $title,
                $metaDescription,
                $ogImageFileId,
                $status,
                $noindex ? 1 : 0,
                $status === 'published' ? date('Y-m-d H:i:s') : null,
            ]
        );
    }

    /** @param array<string,mixed> $fields */
    public function update(string $slug, array $fields): void
    {
        $allowed = ['title', 'meta_description', 'og_image_file_id', 'status', 'noindex'];
        $set = [];
        $params = [];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $fields)) {
                $set[] = "`{$col}` = ?";
                $params[] = $fields[$col];
            }
        }

        if ($set === []) return;

        // Stamp published_at on transition into published.
        if (($fields['status'] ?? null) === 'published') {
            $set[] = "`published_at` = COALESCE(`published_at`, NOW())";
        }

        $params[] = $this->storeId();
        $params[] = $slug;

        $this->db->execute(
            "UPDATE `commerce_store_pages`
                SET " . implode(', ', $set) . "
              WHERE `store_id` = ? AND `slug` = ?",
            $params
        );
    }

    /**
     * Ensure a page row exists for the given slug. Used by the
     * page builder when a legacy block-only page is opened.
     */
    public function ensure(string $slug, string $fallbackTitle): void
    {
        if ($this->exists($slug)) return;
        $this->create($slug, $fallbackTitle, null, 'published');
    }

    public function delete(string $slug): void
    {
        // Refuse to delete the homepage. That protection lives
        // in the controller too, but this is the safety net.
        if ($slug === 'home') return;
        $this->db->execute(
            "DELETE FROM `commerce_store_pages`
              WHERE `store_id` = ? AND `slug` = ?",
            [$this->storeId(), $slug]
        );
    }
}
