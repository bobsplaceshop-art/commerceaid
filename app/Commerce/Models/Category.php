<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Category DTO.
 *
 * Plain data object. No ORM magic. Hydrated from a DB row via
 * fromRow() and written back via CategoryRepository::create/update.
 *
 * Enforces the "category cannot be its own parent" rule that we
 * could not enforce via a CHECK constraint (MySQL 8 rejects CHECK
 * constraints that reference AUTO_INCREMENT columns).
 */
final class Category
{
    public int $id = 0;
    public int $storeId = 0;
    public ?int $parentId = null;
    public string $slug = '';
    public string $name = '';
    public ?string $description = null;
    public ?int $imageFileId = null;
    public ?int $cardImageFileId = null;
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
        $c = new self();
        $c->id             = (int) $row['id'];
        $c->storeId        = (int) $row['store_id'];
        $c->parentId       = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
        $c->slug           = (string) $row['slug'];
        $c->name           = (string) $row['name'];
        $c->description    = $row['description'] ?? null;
        $c->imageFileId    = isset($row['image_file_id']) ? (int) $row['image_file_id'] : null;
        $c->cardImageFileId = isset($row['card_image_file_id']) && $row['card_image_file_id'] !== null
            ? (int) $row['card_image_file_id']
            : null;
        $c->sortOrder      = (int) ($row['sort_order'] ?? 0);
        $c->seoTitle       = $row['seo_title'] ?? null;
        $c->seoDescription = $row['seo_description'] ?? null;
        $c->seoKeywords    = $row['seo_keywords'] ?? null;
        $c->status         = (string) ($row['status'] ?? 'active');
        $c->createdAt      = $row['created_at'] ?? null;
        $c->updatedAt      = $row['updated_at'] ?? null;
        $c->deletedAt      = $row['deleted_at'] ?? null;
        return $c;
    }

    /**
     * Validate the parent-child relationship. Called by the repository
     * before insert/update. Throws on violation.
     *
     * @throws \InvalidArgumentException
     */
    public function assertNotOwnParent(): void
    {
        if ($this->parentId !== null && $this->id > 0 && $this->parentId === $this->id) {
            throw new \InvalidArgumentException(
                'A category cannot be its own parent.'
            );
        }
    }

    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
