<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers;

use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Core\Container;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;
use CorporateAid\Platform\Services\FileService;

/**
 * Serves uploaded media at /media/{uuid} and
 * /media/{uuid}/{variant}.{format}.
 *
 * Public endpoint. No authentication. The UUID is the access
 * token - unguessable, stable, never reused. Store logos and
 * product images are meant to be publicly visible; the UUID
 * scheme only prevents enumeration, not access.
 *
 * The route that reaches this controller is wrapped in the
 * ['store'] middleware so StoreContext is populated during
 * the request, but this controller does not need it. Media
 * rows are not store-scoped. The middleware is there for
 * consistency with the rest of the storefront.
 *
 * Response uses Response::file(), which sets:
 *   Content-Type: from the stored mime
 *   Cache-Control: public, max-age=31536000, immutable
 *
 * Not a view controller. Returns the file bytes directly.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaServeController
{
    public function __construct(private Container $container)
    {
    }

    /**
     * @param array<string,string> $params
     */
    public function serve(Request $request, array $params = []): Response
    {
        $uuid    = (string) ($params['uuid']    ?? '');
        $variant = (string) ($params['variant'] ?? '');
        $format  = (string) ($params['format']  ?? '');

        if ($uuid === '') {
            return $this->notFound();
        }

        $media = $this->container->get(MediaRepository::class);

        // Source request: no variant requested.
        if ($variant === '') {
            $item = $media->findByUuid($uuid);
            if ($item === null) {
                return $this->notFound();
            }

            $absolute = $this->absolutePath($item->file->storagePath);
            if (!is_file($absolute)) {
                return $this->notFound();
            }

            return Response::file(
                $absolute,
                $item->file->mimeType,
                ['Content-Disposition' => 'inline']
            );
        }

        // Variant request: resolve through the source.
        // Format defaults to webp if omitted or unknown.
        if ($format === '' || !in_array($format, ['webp', 'jpeg', 'jpg', 'png'], true)) {
            $format = 'webp';
        }
        if ($format === 'jpg') {
            $format = 'jpeg';
        }

        $variantItem = $media->findVariantBySourceUuid($uuid, $variant, $format);
        if ($variantItem === null) {
            // Fall back to the source. A caller that asked
            // for a variant we do not have still gets an
            // image.
            $item = $media->findByUuid($uuid);
            if ($item === null) {
                return $this->notFound();
            }
            $absolute = $this->absolutePath($item->file->storagePath);
            if (!is_file($absolute)) {
                return $this->notFound();
            }
            return Response::file(
                $absolute,
                $item->file->mimeType,
                ['Content-Disposition' => 'inline']
            );
        }

        $absolute = $this->absolutePath($variantItem->file->storagePath);
        if (!is_file($absolute)) {
            return $this->notFound();
        }

        return Response::file(
            $absolute,
            $variantItem->file->mimeType,
            ['Content-Disposition' => 'inline']
        );
    }

    /**
     * Compose the absolute filesystem path for a storage
     * relative path. Uses FileService::basePath() so the
     * value is guaranteed to match wherever FileService
     * writes files.
     */
    private function absolutePath(string $storagePath): string
    {
        $base = $this->container->get(FileService::class)->basePath();
        return rtrim($base, '/') . '/' . ltrim($storagePath, '/');
    }

    private function notFound(): Response
    {
        return new Response('Not found', 404, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
