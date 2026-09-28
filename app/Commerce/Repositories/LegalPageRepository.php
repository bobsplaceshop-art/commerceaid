<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\LegalPage;

/**
 * Legal page read/write.
 *
 * Slugs are per-store unique. findBySlug returns null for a
 * missing page - the storefront legal view shows a 404 or a
 * placeholder depending on the caller.
 *
 * The four required slugs (impressum, agb, widerruf,
 * datenschutz) are checked by CheckoutService before allowing
 * checkout to proceed. If any is missing or unpublished,
 * checkout shows a setup error.
 */
final class LegalPageRepository extends CommerceRepository
{
    public function findBySlug(string $slug): ?LegalPage
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_legal_pages`
              WHERE `store_id` = ? AND `slug` = ? AND `is_published` = 1",
            [$this->storeId(), $slug]
        );
        return $row === null ? null : LegalPage::fromRow($row);
    }

    public function findById(int $id): ?LegalPage
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `commerce_legal_pages`
              WHERE `store_id` = ? AND `id` = ?",
            [$this->storeId(), $id]
        );
        return $row === null ? null : LegalPage::fromRow($row);
    }

    /**
     * All published legal pages for the store, ordered by slug.
     * Used by the storefront footer to render the legal link
     * list.
     *
     * @return array<int, LegalPage>
     */
    public function listPublished(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_legal_pages`
              WHERE `store_id` = ? AND `is_published` = 1
              ORDER BY `slug` ASC",
            [$this->storeId()]
        );
        return array_map(fn ($r) => LegalPage::fromRow($r), $rows);
    }

    /**
     * All pages including unpublished. For admin CRUD.
     *
     * @return array<int, LegalPage>
     */
    public function listAll(): array
    {
        $rows = $this->db->select(
            "SELECT * FROM `commerce_legal_pages`
              WHERE `store_id` = ?
              ORDER BY `slug` ASC",
            [$this->storeId()]
        );
        return array_map(fn ($r) => LegalPage::fromRow($r), $rows);
    }

    /**
     * Which of the required slugs are missing (not present or
     * not published). Returns an empty array when all four
     * exist. Used by CheckoutService to gate checkout.
     *
     * @return array<int,string>
     */
    public function missingRequiredSlugs(): array
    {
        $required = LegalPage::requiredSlugs();
        $present  = [];

        $rows = $this->db->select(
            "SELECT `slug` FROM `commerce_legal_pages`
              WHERE `store_id` = ? AND `is_published` = 1",
            [$this->storeId()]
        );
        foreach ($rows as $r) {
            $present[(string) $r['slug']] = true;
        }

        $missing = [];
        foreach ($required as $slug) {
            if (!isset($present[$slug])) {
                $missing[] = $slug;
            }
        }
        return $missing;
    }

    /**
     * Upsert a page. Used by the admin CRUD (008E.1) and by the
     * seed that populates default German legal content on store
     * setup.
     */
    public function upsert(
        string $slug,
        string $title,
        string $body,
        bool $isPublished,
        ?int $updatedBy
    ): int {
        $existing = $this->db->selectOne(
            "SELECT `id` FROM `commerce_legal_pages`
              WHERE `store_id` = ? AND `slug` = ?",
            [$this->storeId(), $slug]
        );

        if ($existing !== null) {
            $this->db->execute(
                "UPDATE `commerce_legal_pages`
                    SET `title` = ?, `body` = ?, `is_published` = ?,
                        `updated_by` = ?
                  WHERE `store_id` = ? AND `id` = ?",
                [$title, $body, $isPublished ? 1 : 0, $updatedBy, $this->storeId(), (int) $existing['id']]
            );
            return (int) $existing['id'];
        }

        return $this->db->insert(
            "INSERT INTO `commerce_legal_pages`
                (`store_id`, `slug`, `title`, `body`, `is_published`, `updated_by`)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$this->storeId(), $slug, $title, $body, $isPublished ? 1 : 0, $updatedBy]
        );
    }
}
