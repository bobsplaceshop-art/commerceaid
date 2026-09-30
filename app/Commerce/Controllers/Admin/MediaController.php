<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Controllers\Admin;

use CorporateAid\Commerce\Context\StoreContext;
use CorporateAid\Commerce\Repositories\MediaRepository;
use CorporateAid\Commerce\Support\MediaVariantSet;
use CorporateAid\Core\Auth;
use CorporateAid\Core\Request;
use CorporateAid\Core\Response;

/**
 * Admin media controller.
 *
 * One endpoint:
 *
 *   POST /commerce/ca-admin/media/upload   JSON upload
 *
 * Designed to be called from an AJAX widget, not a full
 * page POST. Responds with JSON.
 *
 * The full upload flow lives in
 * MediaRepository::storeFromUpload(). This controller is a
 * thin request/response adapter.
 *
 * The old /media/delete action was removed in 008G.4a.1.
 * The media library page's {id}/delete route is the single
 * delete path.
 *
 * See docs/stages/008g.4a_upload-core-plan.md and
 * docs/stages/008g.4a.1_media-library-plan.md.
 */
final class MediaController extends AdminController
{
    /**
     * Accept an uploaded file. Multipart form data.
     *
     * Expected inputs:
     *   file      - the uploaded file (from $_FILES)
     *   category  - routing category for storage subdirectory
     *               (e.g. 'store-branding')
     *   context   - media context for variant generation
     *               (e.g. 'store-logo')
     *
     * @param array<string,string> $params
     */
    public function upload(Request $request, array $params = []): Response
    {
        $file = $request->file('file');
        if ($file === null) {
            return Response::json([
                'ok'      => false,
                'errors'  => ['No file received.'],
            ], 400);
        }

        $category = trim((string) $request->input('category', 'general'));
        $context  = trim((string) $request->input('context', MediaVariantSet::CONTEXT_GENERAL));

        if (!MediaVariantSet::isKnownContext($context)) {
            $context = MediaVariantSet::CONTEXT_GENERAL;
        }

        // Cap the size for the context. This is on top of the
        // FileValidator cap. The context spec is authoritative:
        // a store logo does not need 5 MB.
        $spec   = MediaVariantSet::for($context);
        $maxMb  = $spec->sourceMaxMb;

        $auth   = $this->container->get(Auth::class);
        $userId = $auth->user()?->id;

        $storeContext = $this->container->get(StoreContext::class);
        $storeId = $storeContext->has() ? $storeContext->id() : 0;
        if ($storeId <= 0) {
            return Response::json([
                'ok'     => false,
                'errors' => ['No store context. Cannot store a media file.'],
            ], 400);
        }

        $media = $this->container->get(MediaRepository::class);

        $result = $media->storeFromUpload(
            $file,
            $storeId,
            $category,
            $context,
            $maxMb,
            $userId
        );

        if (is_array($result)) {
            return Response::json([
                'ok'     => false,
                'errors' => $result,
            ], 422);
        }

        // Resolve the variant URLs so the widget can show
        // what was produced.
        $variants = [];
        foreach ($spec->variants as $variantSpec) {
            foreach ($variantSpec->formats as $format) {
                $variants[] = [
                    'name'   => $variantSpec->name,
                    'format' => $format,
                    'url'    => $result->variantUrl($variantSpec->name, $format),
                    'width'  => $variantSpec->width,
                    'height' => $variantSpec->height,
                ];
            }
        }

        return Response::json([
            'ok'         => true,
            'id'         => $result->file->id,
            'uuid'       => $result->file->uuid,
            'url'        => $result->url(),
            'name'       => $result->file->originalName,
            'mime'       => $result->file->mimeType,
            'size'       => $result->file->sizeBytes,
            'dimensions' => $result->dimensionsLabel(),
            'variants'   => $variants,
        ]);
    }

}
