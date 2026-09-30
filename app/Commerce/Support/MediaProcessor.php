<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Image post-processor for the media pipeline.
 *
 * Re-encodes uploaded images to strip EXIF metadata before
 * they land in storage. GD-based, no Imagick dependency.
 *
 * Why this exists:
 *   Phone photos carry GPS coordinates, camera make and
 *   model, serial numbers, timestamps, sometimes the
 *   photographer's name. Under DSGVO Art. 4 and Art. 5, GPS
 *   coordinates in particular are personal data. Storing or
 *   serving them without a legal basis is a violation.
 *
 *   Re-encoding through GD's image encoders drops EXIF
 *   because the encoders write fresh pixel data and do not
 *   carry through the APP1 metadata segment (JPEG) or the
 *   textual chunks (PNG). The result is a visually identical
 *   image with no metadata.
 *
 * What this does NOT do:
 *   - No resizing. The original dimensions are preserved.
 *   - No cropping. Aspect ratio is preserved.
 *   - No compression level tuning beyond GD defaults.
 *   - No format conversion. JPEG stays JPEG, PNG stays PNG.
 *
 * Handles: image/jpeg, image/png, image/webp, image/gif.
 * Skips: SVG, PDF, CSV, and any other non-raster type.
 *   SVGs are rejected upstream by the validator.
 *
 * Failure handling:
 *   Returns null on failure. The caller MUST reject the
 *   upload if processing fails. Storing an unprocessed
 *   image (with EXIF intact) is worse than rejecting the
 *   upload.
 *
 * See docs/stages/008g.4a_upload-core-plan.md, Compliance
 * Notes section.
 */
final class MediaProcessor
{
    /**
     * Mime types this processor handles. Other types pass
     * through unchanged (they carry no EXIF to strip).
     *
     * @var array<int, string>
     */
    private const RASTER_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Reprocess the file at $absolutePath to strip metadata.
     * Returns the image dimensions on success, or null on
     * failure.
     *
     * @return array{width:int,height:int}|null
     */
    public function stripMetadata(string $absolutePath, string $mimeType): ?array
    {
        if (!in_array($mimeType, self::RASTER_TYPES, true)) {
            // Not a raster image. Nothing to strip. Return
            // dimensions anyway if it's an image type we can
            // read (SVG has none; PDFs have none).
            return null;
        }

        if (!is_file($absolutePath)) {
            return null;
        }

        // Load the image via GD based on mime type.
        $image = $this->load($absolutePath, $mimeType);
        if ($image === false) {
            return null;
        }

        // GD guarantees positive dimensions on a successfully
        // created image resource. The imagecreatefrom* call
        // above already handled the false-failure case.
        $width  = imagesx($image);
        $height = imagesy($image);

        // Write a fresh copy. GD's encoders do not carry
        // through EXIF or textual metadata.
        $ok = $this->save($image, $absolutePath, $mimeType);

        // Free the resource regardless of success.
        imagedestroy($image);

        if (!$ok) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * @return \GdImage|false
     */
    private function load(string $path, string $mimeType): \GdImage|false
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif'  => @imagecreatefromgif($path),
            default      => false,
        };
    }

    private function save(\GdImage $image, string $path, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/jpeg' => @imagejpeg($image, $path, 92),
            'image/png'  => $this->savePng($image, $path),
            'image/webp' => @imagewebp($image, $path, 92),
            'image/gif'  => @imagegif($image, $path),
            default      => false,
        };
    }

    /**
     * PNG with alpha preservation and a good compression
     * level. quality=9 is max compression, still lossless.
     */
    private function savePng(\GdImage $image, string $path): bool
    {
        // Preserve transparency.
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return @imagepng($image, $path, 9);
    }

    // =================================================================
    // Variant generation
    // =================================================================

    /**
     * Generate the size variants declared by $spec from the
     * source image at $sourceAbsolutePath. Writes each
     * variant as a new file into $outputDir.
     *
     * Does no database work. The caller (MediaRepository)
     * registers each GeneratedVariant via FileRepository.
     *
     * @return array<int, GeneratedVariant>
     */
    public function generateVariants(
        string $sourceAbsolutePath,
        string $sourceMime,
        MediaContextSpec $spec,
        string $outputDir,
        string $filenamePrefix
    ): array {
        if (!in_array($sourceMime, self::RASTER_TYPES, true)) {
            return [];
        }
        if (!is_file($sourceAbsolutePath)) {
            return [];
        }

        $source = $this->load($sourceAbsolutePath, $sourceMime);
        if ($source === false) {
            return [];
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);

        // Ensure the output directory exists.
        if (!is_dir($outputDir) && !@mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            imagedestroy($source);
            return [];
        }

        $results = [];

        foreach ($spec->variants as $variantSpec) {
            $scaled = $this->scale(
                $source,
                $srcW,
                $srcH,
                $variantSpec->width,
                $variantSpec->height,
                $variantSpec->fit
            );
            if ($scaled === null) {
                continue;
            }

            if ($spec->sharpen) {
                $this->sharpen($scaled);
            }

            foreach ($variantSpec->formats as $format) {
                $result = $this->writeVariant(
                    $scaled,
                    $format,
                    $variantSpec->name,
                    $outputDir,
                    $filenamePrefix
                );
                if ($result !== null) {
                    $results[] = $result;
                }
            }

            imagedestroy($scaled);
        }

        imagedestroy($source);
        return $results;
    }

    /**
     * Scale the source image to fit (or cover) the target box.
     * Returns a new image resource, or null on failure.
     */
    private function scale(
        \GdImage $source,
        int $srcW,
        int $srcH,
        int $targetW,
        int $targetH,
        string $fit
    ): ?\GdImage {
        if ($srcW <= 0 || $srcH <= 0) {
            return null;
        }

        if ($fit === MediaVariantSet::FIT_COVER) {
            // Scale to fill, then center-crop to target box.
            $scale = max($targetW / $srcW, $targetH / $srcH);
            $newW  = (int) ceil($srcW * $scale);
            $newH  = (int) ceil($srcH * $scale);

            $resized = $this->resize($source, $srcW, $srcH, $newW, $newH);
            if ($resized === null) {
                return null;
            }

            $offsetX = (int) floor(($newW - $targetW) / 2);
            $offsetY = (int) floor(($newH - $targetH) / 2);

            $cropped = imagecrop($resized, [
                'x'      => $offsetX,
                'y'      => $offsetY,
                'width'  => $targetW,
                'height' => $targetH,
            ]);
            imagedestroy($resized);

            return $cropped !== false ? $cropped : null;
        }

        // FIT_INSIDE: scale to fit within the target box,
        // preserving aspect ratio. Resulting dimensions may
        // be smaller than the box on one axis.
        $scale = min($targetW / $srcW, $targetH / $srcH);

        // Do not upscale. If the source is smaller than the
        // target, keep the source dimensions.
        if ($scale >= 1.0) {
            $newW = $srcW;
            $newH = $srcH;
        } else {
            $newW = (int) max(1, floor($srcW * $scale));
            $newH = (int) max(1, floor($srcH * $scale));
        }

        return $this->resize($source, $srcW, $srcH, $newW, $newH);
    }

    /**
     * Resize with alpha preservation.
     */
    private function resize(
        \GdImage $source,
        int $srcW,
        int $srcH,
        int $dstW,
        int $dstH
    ): ?\GdImage {
        if ($srcW <= 0 || $srcH <= 0 || $dstW <= 0 || $dstH <= 0) {
            return null;
        }

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            return null;
        }

        // Preserve alpha on the destination.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        }
        imagealphablending($dst, true);

        $ok = imagecopyresampled(
            $dst,
            $source,
            0, 0, 0, 0,
            $dstW, $dstH,
            $srcW, $srcH
        );

        if (!$ok) {
            imagedestroy($dst);
            return null;
        }

        return $dst;
    }

    /**
     * Mild unsharp mask. Applied after downscale of more than
     * 50% to preserve edge definition on product photos.
     * Skipped on logos and icons via $spec->sharpen = false.
     */
    private function sharpen(\GdImage $image): void
    {
        $kernel = [
            [ 0,   -0.5,  0],
            [-0.5,  3,   -0.5],
            [ 0,   -0.5,  0],
        ];
        @imageconvolution($image, $kernel, 1.0, 0.0);
    }

    /**
     * Write one variant in one format. Returns a descriptor,
     * or null on failure.
     */
    private function writeVariant(
        \GdImage $image,
        string $format,
        string $variantName,
        string $outputDir,
        string $filenamePrefix
    ): ?GeneratedVariant {
        $extensions = ['webp' => 'webp', 'jpeg' => 'jpg', 'png' => 'png'];
        $mimes      = ['webp' => 'image/webp', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

        if (!isset($extensions[$format], $mimes[$format])) {
            return null;
        }

        $extension = $extensions[$format];
        $mime      = $mimes[$format];

        $uuid       = generate_uuid();
        $storedName = $filenamePrefix . '-' . $variantName . '.' . $extension;
        $absolute   = rtrim($outputDir, '/') . '/' . $storedName;

        if ($format === 'webp') {
            $ok = @imagewebp($image, $absolute, 82);
        } elseif ($format === 'jpeg') {
            $ok = @imagejpeg($image, $absolute, 85);
        } else {
            // format === 'png' (validated above)
            $ok = $this->savePng($image, $absolute);
        }

        if (!$ok) {
            return null;
        }

        $size = @filesize($absolute);
        $hash = @hash_file('sha256', $absolute);
        if ($size === false || $hash === false) {
            @unlink($absolute);
            return null;
        }

        return new GeneratedVariant(
            name:         $variantName,
            format:       $format,
            width:        imagesx($image),
            height:       imagesy($image),
            uuid:         $uuid,
            storedName:   $storedName,
            storagePath:  $absolute, // caller replaces with relative
            absolutePath: $absolute,
            extension:    $extension,
            mimeType:     $mime,
            sizeBytes:    (int) $size,
            sha256:       $hash,
        );
    }
}
