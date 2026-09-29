<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Declares a single size variant within a media context.
 *
 * Read-only DTO. Built by MediaVariantSet's factory methods,
 * consumed by MediaProcessor::generateVariants().
 *
 * Replaces the nested array shape that MediaVariantSet
 * originally produced. A DTO gives PHPStan real types to
 * check, without a docblock shape that has to be maintained
 * in every factory method.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaVariantSpec
{
    /**
     * @param array<int, string> $formats Output formats for this
     *                                   variant. Values: 'webp',
     *                                   'jpeg', 'png'.
     */
    public function __construct(
        public readonly string $name,
        public readonly int $width,
        public readonly int $height,
        public readonly array $formats,
        public readonly string $fit,
    ) {
    }
}
