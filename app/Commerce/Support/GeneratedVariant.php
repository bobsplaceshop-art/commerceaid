<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Describes one generated variant file that has been written
 * to disk but not yet registered in the database.
 *
 * Returned by MediaProcessor::generateVariants(). Consumed by
 * MediaRepository, which registers each file via
 * FileRepository::create() and inserts a core_media_variants
 * row linking it to the source.
 *
 * Every property here is either:
 *   - a value FileRepository::create() needs, or
 *   - a value the core_media_variants row needs.
 *
 * The processor does no database work. It writes files and
 * describes what it wrote. Persistence is the repository's
 * job.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class GeneratedVariant
{
    public function __construct(
        public readonly string $name,
        public readonly string $format,
        public readonly int $width,
        public readonly int $height,
        public readonly string $uuid,
        public readonly string $storedName,
        public readonly string $storagePath,
        public readonly string $absolutePath,
        public readonly string $extension,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $sha256,
    ) {
    }
}
