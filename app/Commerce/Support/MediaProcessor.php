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
}
