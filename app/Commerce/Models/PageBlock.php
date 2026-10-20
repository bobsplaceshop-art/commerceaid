<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Page block DTO.
 *
 * One row in commerce_page_blocks. A page is composed of an
 * ordered list of blocks. Each block is a section the
 * merchant can add, remove, reorder, and configure.
 *
 * The block_type determines the shape of config_json. Each
 * block type has its own schema:
 *
 *   hero           { headline, subheadline, tagline,
 *                    cta_text, cta_url, image_file_id,
 *                    background_style }
 *
 *   product-grid   { title, source, category_slug,
 *                    product_ids, limit, columns,
 *                    show_view_all }
 *
 *   category-grid  { title, category_ids, show_all,
 *                    columns }
 *
 *   promo-panel    { eyebrow, title, cta_text, cta_url,
 *                    image_file_id, height }
 *
 *   banner         { headline, subheadline, cta_text,
 *                    cta_url, image_file_id, text_position }
 *
 *   rich-text      { html, max_width }
 *
 *   newsletter     { title, subtitle, button_text }
 *
 * The model does not validate config_json. The builder UI
 * and the block renderer each have their own validation.
 * The model is a passive DTO.
 *
 * is_enabled lets the merchant hide a block without
 * deleting it. A disabled block still occupies its sort
 * position, so re-enabling it restores its place.
 *
 * See docs/stages/023_page-builder-plan.md.
 */
final class PageBlock
{
    public int $id = 0;
    public int $storeId = 0;
    public string $pageSlug = 'home';
    public string $blockType = '';
    public int $sortOrder = 0;
    /** @var array<string,mixed> */
    public array $config = [];
    public bool $isEnabled = true;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $b = new self();
        $b->id        = (int) $row['id'];
        $b->storeId   = (int) $row['store_id'];
        $b->pageSlug  = (string) $row['page_slug'];
        $b->blockType = (string) $row['block_type'];
        $b->sortOrder = (int) $row['sort_order'];

        $configJson = $row['config_json'] ?? '{}';
        $decoded    = is_string($configJson) ? json_decode($configJson, true) : $configJson;
        $b->config  = is_array($decoded) ? $decoded : [];

        $b->isEnabled = (bool) ($row['is_enabled'] ?? true);
        $b->createdAt = isset($row['created_at']) ? (string) $row['created_at'] : null;
        $b->updatedAt = isset($row['updated_at']) ? (string) $row['updated_at'] : null;
        return $b;
    }

    /**
     * A human-readable label for the block type.
     * Used in the admin block list.
     */
    public function typeLabel(): string
    {
        $labels = [
            'hero'          => 'Hero',
            'product-grid'  => 'Product Grid',
            'category-grid' => 'Category Grid',
            'promo-panel'   => 'Promo Panel',
            'banner'        => 'Banner',
            'rich-text'     => 'Rich Text',
            'newsletter'    => 'Newsletter',
        ];
        return $labels[$this->blockType] ?? ucfirst(str_replace('-', ' ', $this->blockType));
    }
}
