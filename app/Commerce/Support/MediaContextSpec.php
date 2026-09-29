<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Declares the full variant set for a media context.
 *
 * Read-only DTO. Built by MediaVariantSet::for() and consumed
 * by MediaProcessor::generateVariants().
 *
 * Contains:
 *   - source_max_mb: the upload size cap for the source file
 *     in this context
 *   - sharpen: whether the generator should apply an unsharp
 *     mask after downscale
 *   - variants: the ordered list of MediaVariantSpec entries
 *     to produce
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaContextSpec
{
    /**
     * @param array<int, MediaVariantSpec> $variants
     */
    public function __construct(
        public readonly int $sourceMaxMb,
        public readonly bool $sharpen,
        public readonly array $variants,
    ) {
    }
}
