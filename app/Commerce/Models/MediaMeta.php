<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

/**
 * Media metadata DTO.
 *
 * Pairs with a File (Platform model, core_files table). Holds
 * image-specific metadata that does not belong on the file
 * row: dimensions, aspect ratio, alt text, upload context.
 *
 * A file either has one MediaMeta row or none. Non-image
 * files (PDFs, CSVs) have no MediaMeta row at all. The
 * UNIQUE key on file_id enforces this.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaMeta
{
    public int $id = 0;
    public int $fileId = 0;
    public ?int $width = null;
    public ?int $height = null;
    public ?float $aspectRatio = null;
    public ?string $altText = null;
    public string $context = 'general';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        $m = new self();
        $m->id          = (int) $row['id'];
        $m->fileId      = (int) $row['file_id'];
        $m->width       = $row['width']  !== null ? (int) $row['width'] : null;
        $m->height      = $row['height'] !== null ? (int) $row['height'] : null;
        $m->aspectRatio = $row['aspect_ratio'] !== null
            ? (float) $row['aspect_ratio']
            : null;
        $m->altText     = $row['alt_text'] !== null ? (string) $row['alt_text'] : null;
        $m->context     = (string) ($row['context'] ?? 'general');
        $m->createdAt   = $row['created_at'] !== null ? (string) $row['created_at'] : null;
        $m->updatedAt   = $row['updated_at'] !== null ? (string) $row['updated_at'] : null;
        return $m;
    }

    public function hasDimensions(): bool
    {
        return $this->width !== null
            && $this->height !== null
            && $this->width > 0
            && $this->height > 0;
    }

    /**
     * Human-readable dimensions string, e.g. "1200 x 630".
     * Returns an empty string when dimensions are not set.
     */
    public function dimensionsLabel(): string
    {
        if (!$this->hasDimensions()) {
            return '';
        }
        return $this->width . ' x ' . $this->height;
    }

    /**
     * Aspect ratio expressed as a simple ratio string when it
     * matches a common value (16:9, 4:3, 1:1, 3:2, 9:16, 3:4),
     * otherwise a decimal like "1.78". Used by the media page
     * and by the upload widget for previews.
     */
    public function aspectRatioLabel(): string
    {
        if (!$this->hasDimensions()) {
            return '';
        }

        $w = (int) $this->width;
        $h = (int) $this->height;
        $ratio = $w / $h;

        // Tuple list, not a float-keyed map. PHP truncates
        // float array keys to integers, so 1.778 and 1.333
        // would both become key 1 and collide. The list form
        // keeps the values intact.
        $common = [
            [1.778,  '16:9'],
            [1.333,  '4:3'],
            [1.000,  '1:1'],
            [1.500,  '3:2'],
            [0.5625, '9:16'],
            [0.750,  '3:4'],
            [0.667,  '2:3'],
            [2.333,  '21:9'],
        ];

        foreach ($common as [$value, $label]) {
            if (abs($ratio - $value) < 0.01) {
                return $label;
            }
        }

        return number_format($ratio, 2, '.', '');
    }
}
