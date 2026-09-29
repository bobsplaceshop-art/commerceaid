<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Media variant DTO.
 *
 * One row in core_media_variants. Maps a source file to one
 * of its generated derivatives.
 *
 * A variant is itself a file (a core_files row). This model
 * carries the link metadata: the source it derives from, its
 * name (e.g. 'card', 'detail', 'thumb'), its dimensions, and
 * its output format.
 *
 * The URL to fetch a variant is built by MediaItem, not here.
 * MediaItem knows the source UUID and can construct
 * /media/{sourceUuid}/{name}.{format}. The variant's own UUID
 * is not exposed in URLs - variants are always requested
 * through their source.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaVariant
{
    public int $id = 0;
    public int $sourceFileId = 0;
    public int $variantFileId = 0;
    public string $variantName = '';
    public int $width = 0;
    public int $height = 0;
    public string $format = 'webp';
    public ?string $createdAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $v = new self();
        $v->id            = (int) $row['id'];
        $v->sourceFileId  = (int) $row['source_file_id'];
        $v->variantFileId = (int) $row['variant_file_id'];
        $v->variantName   = (string) $row['variant_name'];
        $v->width         = (int) $row['width'];
        $v->height        = (int) $row['height'];
        $v->format        = (string) $row['format'];
        $v->createdAt     = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        return $v;
    }

    public function isWebp(): bool
    {
        return $this->format === 'webp';
    }

    public function isJpeg(): bool
    {
        return $this->format === 'jpeg';
    }

    public function isPng(): bool
    {
        return $this->format === 'png';
    }

    /**
     * Mime type for the format. Used by the serving controller
     * if it ever needs to override the stored mime (it usually
     * should not - the core_files row already has the right
     * mime from the upload pipeline).
     */
    public function mimeType(): string
    {
        return match ($this->format) {
            'webp' => 'image/webp',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            default => 'application/octet-stream',
        };
    }
}
