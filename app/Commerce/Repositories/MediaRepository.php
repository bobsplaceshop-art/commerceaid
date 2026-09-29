<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Models\MediaItem;
use CorporateAid\Commerce\Models\MediaMeta;
use CorporateAid\Commerce\Support\MediaProcessor;
use CorporateAid\Core\Database;
use CorporateAid\Platform\Models\File;
use CorporateAid\Platform\Repositories\FileRepository;
use CorporateAid\Platform\Services\FileService;

/**
 * Media repository.
 *
 * Orchestrates the upload pipeline: validation, storage,
 * EXIF stripping, image metadata, and media row assembly.
 *
 * Does NOT extend CommerceRepository. The tables it touches
 * (core_files, core_media_meta) are Platform tables with no
 * store_id column. CommerceRepository requires a resolved
 * store context and would throw on any non-store caller
 * (a CLI cleanup job, an admin-side audit). This class works
 * with a Database directly.
 *
 * Dependencies:
 *   FileService     - Platform's validated storage pipeline
 *   FileRepository  - core_files CRUD
 *   MediaProcessor  - GD-based EXIF stripping
 *   Database        - core_media_meta queries
 *
 * The full upload flow is storeFromUpload():
 *   1. Delegate to FileService::store() with a category.
 *   2. Read dimensions and re-encode the stored file via
 *      MediaProcessor (strips EXIF).
 *   3. If the re-encode fails, reject the file and remove
 *      the physical asset.
 *   4. Insert a core_media_meta row with the dimensions
 *      and context.
 *   5. Return a MediaItem.
 *
 * See docs/stages/008g.4a_upload-core-plan.md.
 */
final class MediaRepository
{
    public function __construct(
        private Database $db,
        private FileService $files,
        private FileRepository $fileRepo,
        private MediaProcessor $processor,
    ) {
    }

    // =================================================================
    // Upload
    // =================================================================

    /**
     * Process an uploaded file into the media library.
     *
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param string $category  routing category for storage subdirectory
     * @param string $context   media meta context ('store-logo', etc.)
     * @param int    $maxMb     size cap in MB
     * @param int|null $userId  uploader
     * @return MediaItem|array<int,string>  MediaItem on success, errors on failure
     */
    public function storeFromUpload(
        array $file,
        string $category,
        string $context,
        int $maxMb = FileService::DEFAULT_MAX_MB,
        ?int $userId = null
    ): MediaItem|array {
        // ---- 1. Store via Platform's validated pipeline ----
        $stored = $this->files->store($file, $userId, $category, $maxMb);

        if (is_array($stored)) {
            // Validation or storage failed. Return the errors.
            return $stored;
        }

        // $stored is now a File model.
        $absolutePath = $this->files->absolutePath($stored);

        // ---- 2. Strip EXIF and read dimensions ----
        $dimensions = null;
        if (str_starts_with($stored->mimeType, 'image/')) {
            $dimensions = $this->processor->stripMetadata(
                $absolutePath,
                $stored->mimeType
            );

            if ($dimensions === null && $this->isRaster($stored->mimeType)) {
                // Re-encode failed for a raster image. Reject
                // the upload. Storing a file with un-stripped
                // EXIF is worse than no file.
                $this->files->softDelete($stored->id);
                return ['The image could not be processed.'];
            }
        }

        // ---- 3. Write the meta row ----
        $meta = null;
        if ($dimensions !== null) {
            $aspect = $dimensions['height'] > 0
                ? $dimensions['width'] / $dimensions['height']
                : null;

            $metaId = (int) $this->db->insert(
                "INSERT INTO `core_media_meta`
                    (`file_id`, `width`, `height`, `aspect_ratio`, `context`)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    `width`        = VALUES(`width`),
                    `height`       = VALUES(`height`),
                    `aspect_ratio` = VALUES(`aspect_ratio`),
                    `context`      = VALUES(`context`)",
                [
                    $stored->id,
                    $dimensions['width'],
                    $dimensions['height'],
                    $aspect !== null ? round($aspect, 3) : null,
                    $context,
                ]
            );

            $meta = $this->findMetaByFileId($stored->id);
        }

        return new MediaItem($stored, $meta);
    }

    // =================================================================
    // Reads
    // =================================================================

    public function findByUuid(string $uuid): ?MediaItem
    {
        if ($uuid === '') {
            return null;
        }

        $file = $this->fileRepo->findByUuid($uuid);
        if ($file === null || $file->isDeleted()) {
            return null;
        }

        return new MediaItem($file, $this->findMetaByFileId($file->id));
    }

    public function findById(int $fileId): ?MediaItem
    {
        if ($fileId <= 0) {
            return null;
        }

        $file = $this->fileRepo->findById($fileId);
        if ($file === null || $file->isDeleted()) {
            return null;
        }

        return new MediaItem($file, $this->findMetaByFileId($file->id));
    }

    /**
     * @param array<int,int> $fileIds
     * @return array<int, MediaItem>
     */
    public function findByIds(array $fileIds): array
    {
        $clean = [];
        foreach ($fileIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = true;
            }
        }
        if ($clean === []) {
            return [];
        }

        $ids = array_keys($clean);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $rows = $this->db->select(
            "SELECT * FROM `core_files`
              WHERE `id` IN ({$placeholders})
                AND `deleted_at` IS NULL",
            $ids
        );

        $files = [];
        foreach ($rows as $row) {
            $files[] = File::fromRow($row);
        }
        if ($files === []) {
            return [];
        }

        // Bulk-load meta rows in one query.
        $metasByFileId = $this->findMetasByFileIds($ids);

        $out = [];
        foreach ($files as $f) {
            $out[] = new MediaItem($f, $metasByFileId[$f->id] ?? null);
        }
        return $out;
    }

    // =================================================================
    // Mutations on existing media
    // =================================================================

    /**
     * Soft-delete a file. Delegates to FileService, which
     * unlinks every entity reference and soft-deletes the
     * file row.
     */
    public function softDelete(int $fileId): void
    {
        if ($fileId <= 0) {
            return;
        }
        $this->files->softDelete($fileId);
    }

    /**
     * Update alt text and/or credit. Either may be null to
     * clear the field.
     */
    public function updateMetaText(
        int $fileId,
        ?string $altText,
        ?string $credit
    ): void {
        if ($fileId <= 0) {
            return;
        }

        $this->db->execute(
            "UPDATE `core_media_meta`
                SET `alt_text` = ?, `credit` = ?
              WHERE `file_id` = ?",
            [
                $altText !== '' ? $altText : null,
                $credit !== '' ? $credit : null,
                $fileId,
            ]
        );
    }

    // =================================================================
    // Internals
    // =================================================================

    private function findMetaByFileId(int $fileId): ?MediaMeta
    {
        $row = $this->db->selectOne(
            "SELECT * FROM `core_media_meta` WHERE `file_id` = ? LIMIT 1",
            [$fileId]
        );
        return $row !== null ? MediaMeta::fromRow($row) : null;
    }

    /**
     * @param array<int,int> $fileIds
     * @return array<int, MediaMeta>
     */
    private function findMetasByFileIds(array $fileIds): array
    {
        if ($fileIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
        $rows = $this->db->select(
            "SELECT * FROM `core_media_meta`
              WHERE `file_id` IN ({$placeholders})",
            $fileIds
        );

        $out = [];
        foreach ($rows as $row) {
            $meta = MediaMeta::fromRow($row);
            $out[$meta->fileId] = $meta;
        }
        return $out;
    }

    private function isRaster(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ], true);
    }
}
