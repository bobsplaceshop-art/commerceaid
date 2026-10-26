<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support\Blocks;

/**
 * Builds the inline style string for a block's outer wrapper
 * from the block's background config.
 *
 * Every block supports the same five fields:
 *   background_type        none | solid | gradient | image
 *   background_color       hex color (for solid)
 *   background_gradient    CSS gradient string (for gradient)
 *   background_image_file_id  media id (for image)
 *   background_overlay     none | light | dark (for image)
 *
 * The storefront partial calls style() and inlines the result
 * on the outer wrapper. The partial never inspects the type
 * itself.
 */
final class BlockBackground
{
    /**
     * @param array<string,mixed> $config
     * @param string $bgUrl   Resolved URL for background_image_file_id,
     *                        or '' if not set. Computed by BlockRenderer.
     */
    public static function style(array $config, string $bgUrl = ''): string
    {
        $type = (string) ($config['background_type'] ?? 'none');

        if ($type === 'solid') {
            $color = (string) ($config['background_color'] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                return "background-color: {$color};";
            }
            return '';
        }

        if ($type === 'gradient') {
            $gradient = trim((string) ($config['background_gradient'] ?? ''));
            if ($gradient !== '' && preg_match('/^[a-zA-Z\-]+\\(/', $gradient)) {
                return "background: {$gradient};";
            }
            return '';
        }

        if ($type === 'image') {
            if ($bgUrl === '') {
                return '';
            }
            $style = "background-image: url('{$bgUrl}'); background-size: cover; background-position: center;";

            $overlay = (string) ($config['background_overlay'] ?? 'none');
            if ($overlay === 'dark') {
                $style .= " position: relative;";
            } elseif ($overlay === 'light') {
                $style .= " position: relative;";
            }

            return $style;
        }

        return '';
    }

    /**
     * Returns the CSS for the overlay pseudo-element, or '' if
     * no overlay is configured. The partial inlines this in a
     * <style> block scoped to the block's wrapper class.
     *
     * @param array<string,mixed> $config
     */
    public static function overlayStyle(array $config, string $wrapperClass): string
    {
        if ((string) ($config['background_type'] ?? 'none') !== 'image') {
            return '';
        }

        $overlay = (string) ($config['background_overlay'] ?? 'none');
        $color = match ($overlay) {
            'dark'  => 'rgba(0,0,0,0.45)',
            'light' => 'rgba(255,255,255,0.55)',
            default => '',
        };
        if ($color === '') {
            return '';
        }

        return "<style>"
            . ".{$wrapperClass}::before{content:'';position:absolute;inset:0;background:{$color};pointer-events:none;z-index:0;}"
            . ".{$wrapperClass} > *{position:relative;z-index:1;}"
            . "</style>";
    }
}
