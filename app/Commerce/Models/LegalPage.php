<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * A storefront legal page (Impressum, AGB, Widerrufsbelehrung,
 * Datenschutzerklaerung).
 *
 * Mirrors commerce_legal_pages. Body holds HTML rendered as-is
 * by the storefront legal view. No markdown parsing.
 *
 * Slug is unique per store. The four slugs used by the German
 * checkout flow are:
 *
 *   impressum       - provider identification (DDG § 5)
 *   agb             - terms and conditions (BGB § 312i)
 *   widerruf        - withdrawal instruction (EGBGB Art. 246a)
 *   datenschutz     - privacy policy (DSGVO Art. 13)
 *
 * A store may add other legal pages (cookie policy, shipping
 * terms) as needed. The framework is not limited to the four.
 */
final class LegalPage
{
    public int $id = 0;
    public int $storeId = 0;
    public string $slug = '';
    public string $title = '';
    public string $body = '';
    public bool $isPublished = true;
    public ?int $updatedBy = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $p = new self();
        $p->id          = (int) $row['id'];
        $p->storeId     = (int) $row['store_id'];
        $p->slug        = (string) $row['slug'];
        $p->title       = (string) $row['title'];
        $p->body        = (string) $row['body'];
        $p->isPublished = (bool) ($row['is_published'] ?? true);
        $p->updatedBy   = isset($row['updated_by']) ? (int) $row['updated_by'] : null;
        $p->createdAt   = $row['created_at'] ?? null;
        $p->updatedAt   = $row['updated_at'] ?? null;
        return $p;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function isEmpty(): bool
    {
        return trim(strip_tags($this->body)) === '';
    }

    /**
     * The four required slugs for the German checkout flow.
     * Used by CheckoutService to verify all legal pages exist
     * before allowing checkout to proceed.
     *
     * @return array<int,string>
     */
    public static function requiredSlugs(): array
    {
        return ['impressum', 'agb', 'widerruf', 'datenschutz'];
    }
}
