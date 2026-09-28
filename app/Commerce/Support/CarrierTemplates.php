<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Carrier URL templates.
 *
 * Maps a carrier_key to a public tracking URL template. The
 * {number} placeholder is replaced with the URL-encoded
 * tracking number at render time.
 *
 * No API integration. The customer's browser opens the
 * carrier's public tracking page directly. This is the same
 * pattern used by AfterShip's link-building feature and by
 * most e-commerce platforms that display tracking without
 * an API contract.
 *
 * Stage 010 (payments + shipping deep) adds API polling and
 * webhook-driven status updates. This map stays as the
 * fallback and for carriers without an API.
 *
 * See docs/stages/008f_order-lifecycle-plan.md.
 */
final class CarrierTemplates
{
    /**
     * Carrier key => template. Keep labels short - they render
     * inline on the order detail page.
     *
     * @var array<string, array{label: string, url: string}>
     */
    public const TEMPLATES = [
        'dhl' => [
            'label' => 'DHL',
            'url'   => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id={number}',
        ],
        'fedex' => [
            'label' => 'FedEx',
            'url'   => 'https://www.fedex.com/fedextrack/?trknbr={number}',
        ],
        'ups' => [
            'label' => 'UPS',
            'url'   => 'https://www.ups.com/track?tracknum={number}',
        ],
        'dpd' => [
            'label' => 'DPD',
            'url'   => 'https://tracking.dpd.de/status/en_US/parcel/{number}',
        ],
        'hermes' => [
            'label' => 'Hermes',
            'url'   => 'https://www.myhermes.co.uk/track#/parcel/{number}',
        ],
        'royal_mail' => [
            'label' => 'Royal Mail',
            'url'   => 'https://www.royalmail.com/track-your-item#/tracking-results/{number}',
        ],
        'posta_kenya' => [
            'label' => 'Postal Corporation of Kenya',
            'url'   => 'https://www.posta.co.ke/track/{number}',
        ],
        'g4s' => [
            'label' => 'G4S',
            'url'   => 'https://www.g4s.com/en-ke/track/{number}',
        ],
    ];

    /**
     * Human-readable label for a carrier key. Falls back to a
     * titlecased version of the key when the carrier is not in
     * the map. Returns null for an empty key.
     */
    public static function label(?string $carrierKey): ?string
    {
        if ($carrierKey === null || $carrierKey === '') {
            return null;
        }
        $key = strtolower($carrierKey);
        return self::TEMPLATES[$key]['label']
            ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Resolve the tracking URL. Returns null when either the
     * carrier key or the tracking number is missing, or when
     * the carrier is not in the map.
     *
     * The tracking number is URL-encoded so spaces and
     * special characters survive the round-trip. Most carrier
     * tracking numbers are alphanumeric, but this makes the
     * method safe for any input.
     */
    public static function url(?string $carrierKey, ?string $trackingNumber): ?string
    {
        if ($carrierKey === null || $carrierKey === '') {
            return null;
        }
        if ($trackingNumber === null || $trackingNumber === '') {
            return null;
        }
        $key = strtolower($carrierKey);
        if (!isset(self::TEMPLATES[$key])) {
            return null;
        }
        $encoded = rawurlencode($trackingNumber);
        return str_replace('{number}', $encoded, self::TEMPLATES[$key]['url']);
    }

    /**
     * The full list of carriers, for admin select dropdowns.
     *
     * @return array<string, string>  key => label
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::TEMPLATES as $key => $t) {
            $out[$key] = $t['label'];
        }
        return $out;
    }

    /**
     * Is this carrier key known to the map?
     */
    public static function isKnown(string $carrierKey): bool
    {
        return isset(self::TEMPLATES[strtolower($carrierKey)]);
    }
}
