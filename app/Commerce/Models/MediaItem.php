<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Models;

use CorporateAid\Platform\Models\File;

/**
 * Media item view-model.
 *
 * Pairs a File (Platform model, core_files row) with its
 * optional MediaMeta (image metadata). Same shape as
 * CartLineItem from 008D: a DTO wrapper around a base model
 * plus optional associated data.
 *
 * Not every File has a MediaMeta row. Non-image files
 * (PDFs, CSVs) have no meta. Files uploaded before this
 * stage have no meta. In both cases $meta is null, and the
 * helpers return sensible defaults.
 *
 * The media repository is the only thing that builds these.
 * Controllers and views consume them read-only.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaItem
{
    public function __construct(
        public File $file,
        public ?MediaMeta $meta = null,
    ) {
    }

    /**
     * Public URL for the file. Uses the UUID, never the
     * numeric ID.
     */
    public function url(): string
    {
        return '/media/' . rawurlencode($this->file->uuid);
    }

    /**
     * Whether this item has image dimensions. True when the
     * meta row exists and both width and height are set.
     */
    public function hasDimensions(): bool
    {
        return $this->meta !== null && $this->meta->hasDimensions();
    }

    /**
     * Whether this is an image at all. Based on mime type.
     * Does not require the meta row - a legacy file uploaded
     * before this stage has no meta but may still be an
     * image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->file->mimeType, 'image/');
    }

    /**
     * Human-readable dimensions, e.g. "1200 x 630".
     * Returns an empty string when dimensions are unknown.
     */
    public function dimensionsLabel(): string
    {
        return $this->meta !== null ? $this->meta->dimensionsLabel() : '';
    }

    /**
     * Aspect ratio label from the meta row. Empty string when
     * dimensions are unknown.
     */
    public function aspectRatioLabel(): string
    {
        return $this->meta !== null ? $this->meta->aspectRatioLabel() : '';
    }

    /**
     * Alt text, or an empty string. Prefers the meta row's
     * alt_text. Falls back to the original filename without
     * extension for a rough default when no alt was set.
     */
    public function altText(): string
    {
        if ($this->meta !== null && $this->meta->altText !== null && $this->meta->altText !== '') {
            return $this->meta->altText;
        }
        return pathinfo($this->file->originalName, PATHINFO_FILENAME);
    }

    /**
     * Human-readable file size, e.g. "1.2 MB".
     */
    public function humanSize(): string
    {
        return $this->file->humanSize();
    }
}
