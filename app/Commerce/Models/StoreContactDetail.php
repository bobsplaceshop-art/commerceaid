<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Store contact detail DTO.
 *
 * Contact information is store data, not navigation. The footer
 * builder renders contact rows as a sibling of the navigation
 * columns. The four types are fixed: email, phone, address, custom.
 */
final class StoreContactDetail
{
    public int $id = 0;
    public int $storeId = 0;
    public string $type = 'custom';
    public string $label = '';
    public string $value = '';
    public int $sortOrder = 0;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $d = new self();
        $d->id        = (int) $row['id'];
        $d->storeId   = (int) $row['store_id'];
        $d->type      = (string) ($row['type'] ?? 'custom');
        $d->label     = (string) $row['label'];
        $d->value     = (string) $row['value'];
        $d->sortOrder = (int) ($row['sort_order'] ?? 0);
        $d->createdAt = $row['created_at'] ?? null;
        $d->updatedAt = $row['updated_at'] ?? null;
        return $d;
    }

    /**
     * Render the value as an href if the type is linkable.
     * Email -> mailto, phone -> tel, address/custom -> plain text (#).
     */
    public function href(): string
    {
        return match ($this->type) {
            'email' => 'mailto:' . $this->value,
            'phone' => 'tel:' . preg_replace('/\s+/', '', $this->value),
            default => '#',
        };
    }
}
