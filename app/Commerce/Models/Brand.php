<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Brand DTO.
 *
 * Plain data object. Hydrated via fromRow(), written via
 * BrandRepository::create/update. Flat list per store, no hierarchy.
 */
final class Brand
{
    public int $id = 0;
    public int $storeId = 0;
    public string $slug = '';
    public string $name = '';
    public ?string $description = null;
    public ?int $logoFileId = null;
    public ?string $websiteUrl = null;
    public int $sortOrder = 0;
    public ?string $seoTitle = null;
    public ?string $seoDescription = null;
    public ?string $seoKeywords = null;
    public string $status = 'active';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $deletedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $b = new self();
        $b->id             = (int) $row['id'];
        $b->storeId        = (int) $row['store_id'];
        $b->slug           = (string) $row['slug'];
        $b->name           = (string) $row['name'];
        $b->description    = $row['description'] ?? null;
        $b->logoFileId     = isset($row['logo_file_id']) ? (int) $row['logo_file_id'] : null;
        $b->websiteUrl     = $row['website_url'] ?? null;
        $b->sortOrder      = (int) ($row['sort_order'] ?? 0);
        $b->seoTitle       = $row['seo_title'] ?? null;
        $b->seoDescription = $row['seo_description'] ?? null;
        $b->seoKeywords    = $row['seo_keywords'] ?? null;
        $b->status         = (string) ($row['status'] ?? 'active');
        $b->createdAt      = $row['created_at'] ?? null;
        $b->updatedAt      = $row['updated_at'] ?? null;
        $b->deletedAt      = $row['deleted_at'] ?? null;
        return $b;
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function hasLogo(): bool
    {
        return $this->logoFileId !== null && $this->logoFileId > 0;
    }
}
