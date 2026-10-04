<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Repositories;

use CorporateAid\Commerce\Support\Fonts\FontCatalog;

/**
 * Store font selection repository.
 *
 * Every store has three font slots: body, display, script.
 * Each is a nullable slug from FontCatalog. Null means
 * "use the platform default".
 *
 * Writes happen through setSelection(). The slug is
 * validated against the catalog; an unknown slug throws.
 *
 * The storefront reads through getSelections(). The
 * FontRepository is a thin wrapper over three columns on
 * commerce_stores - it does not touch core_files. The
 * downloader handles file registration when a slug is
 * first selected.
 *
 * See docs/stages/008g.4c_font-manager-plan.md (to be
 * written at stage close).
 */
final class FontRepository extends CommerceRepository
{
    public const SLOT_BODY    = 'body';
    public const SLOT_DISPLAY = 'display';
    public const SLOT_SCRIPT  = 'script';

    /**
     * Column mapping. Slot key -> commerce_stores column.
     *
     * @var array<string,string>
     */
    private const COLUMNS = [
        self::SLOT_BODY    => 'font_body_slug',
        self::SLOT_DISPLAY => 'font_display_slug',
        self::SLOT_SCRIPT  => 'font_script_slug',
    ];

    /**
     * Read the three font selections for a store.
     *
     * @return array{body:?string, display:?string, script:?string}
     */
    public function getSelections(int $storeId): array
    {
        if ($storeId <= 0) {
            return [
                self::SLOT_BODY    => null,
                self::SLOT_DISPLAY => null,
                self::SLOT_SCRIPT  => null,
            ];
        }

        $row = $this->db->selectOne(
            "SELECT `font_body_slug`, `font_display_slug`, `font_script_slug`
               FROM `commerce_stores`
              WHERE `id` = ?
              LIMIT 1",
            [$storeId]
        );

        if ($row === null) {
            return [
                self::SLOT_BODY    => null,
                self::SLOT_DISPLAY => null,
                self::SLOT_SCRIPT  => null,
            ];
        }

        return [
            self::SLOT_BODY    => $row['font_body_slug']    ?? null,
            self::SLOT_DISPLAY => $row['font_display_slug'] ?? null,
            self::SLOT_SCRIPT  => $row['font_script_slug']  ?? null,
        ];
    }

    /**
     * Write a single font slot.
     *
     * @param string      $slot  one of SLOT_BODY, SLOT_DISPLAY, SLOT_SCRIPT
     * @param ?string     $slug  a catalog slug, or null to clear
     * @throws \InvalidArgumentException on unknown slot or slug
     */
    public function setSelection(int $storeId, string $slot, ?string $slug): void
    {
        if ($storeId <= 0) {
            throw new \InvalidArgumentException('Invalid store id.');
        }

        if (!isset(self::COLUMNS[$slot])) {
            throw new \InvalidArgumentException("Unknown font slot: {$slot}");
        }

        // Validate the slug against the catalog when not null.
        if ($slug !== null && $slug !== '') {
            if (FontCatalog::find($slug) === null) {
                throw new \InvalidArgumentException("Unknown font slug: {$slug}");
            }
        } else {
            $slug = null;
        }

        $column = self::COLUMNS[$slot];

        $this->db->execute(
            "UPDATE `commerce_stores` SET `{$column}` = ? WHERE `id` = ?",
            [$slug, $storeId]
        );
    }
}
