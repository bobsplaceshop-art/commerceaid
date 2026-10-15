<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * Phone number validation.
 *
 * Not a full international format parser. It catches two
 * classes of mistake:
 *
 *   1. An empty phone number on a form where one is
 *      required.
 *   2. A number whose length does not match the country
 *      the customer selected. A German customer typing a
 *      Kenyan number by mistake, for example.
 *
 * Length bands for twenty markets. Everything else falls
 * through to a loose check (6 to 15 digits total). The
 * loose check accepts any reasonable number, which is the
 * correct default for countries where the platform does not
 * have a length rule.
 *
 * A future pass can replace this with a per-country regex
 * library such as libphonenumber. This class is small enough
 * to make that replacement cheap.
 *
 * Stage 010.
 */
final class PhoneValidator
{
    /**
     * Length bands and dial codes for the countries the
     * platform serves most. The values are the number of
     * digits expected after stripping the international
     * prefix and any spaces, dashes, or parentheses.
     *
     * @var array<string, array{dial: string, min: int, max: int}>
     */
    private const COUNTRIES = [
        'DE' => ['dial' => '49',  'min' => 10, 'max' => 11],
        'KE' => ['dial' => '254', 'min' => 9,  'max' => 12],
        'US' => ['dial' => '1',   'min' => 10, 'max' => 10],
        'CA' => ['dial' => '1',   'min' => 10, 'max' => 10],
        'GB' => ['dial' => '44',  'min' => 10, 'max' => 11],
        'FR' => ['dial' => '33',  'min' => 9,  'max' => 10],
        'IT' => ['dial' => '39',  'min' => 9,  'max' => 11],
        'ES' => ['dial' => '34',  'min' => 9,  'max' => 9],
        'NL' => ['dial' => '31',  'min' => 9,  'max' => 10],
        'AT' => ['dial' => '43',  'min' => 10, 'max' => 13],
        'CH' => ['dial' => '41',  'min' => 9,  'max' => 10],
        'BE' => ['dial' => '32',  'min' => 8,  'max' => 9],
        'IE' => ['dial' => '353', 'min' => 7,  'max' => 9],
        'PT' => ['dial' => '351', 'min' => 9,  'max' => 9],
        'SE' => ['dial' => '46',  'min' => 7,  'max' => 9],
        'NO' => ['dial' => '47',  'min' => 8,  'max' => 8],
        'DK' => ['dial' => '45',  'min' => 8,  'max' => 8],
        'FI' => ['dial' => '358', 'min' => 9,  'max' => 10],
        'PL' => ['dial' => '48',  'min' => 9,  'max' => 9],
        'AU' => ['dial' => '61',  'min' => 9,  'max' => 9],
    ];

    /**
     * Validate a phone number against a country. Returns an
     * empty string when valid, a merchant-readable error
     * message when not.
     */
    public static function validate(string $phone, string $countryCode): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return 'Phone number is required.';
        }

        // Strip everything except digits and a leading plus
        // sign. Spaces, dashes, parentheses, and dots are
        // cosmetic. The customer can type in any common
        // format.
        $clean = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if ($clean === '') {
            return 'This does not look like a phone number.';
        }

        // The presence of any letter in the original input is
        // a signal that the customer typed something that is
        // not a phone number.
        if (preg_match('/[A-Za-z]/', $phone)) {
            return 'This does not look like a phone number.';
        }

        // Reduce to just the digits for length checking.
        $digits = ltrim($clean, '+');
        if ($digits === '') {
            return 'This does not look like a phone number.';
        }

        // Detect the international prefix. Two accepted forms:
        // a leading plus sign, or a leading double zero.
        $hasPlusPrefix = str_starts_with($clean, '+');
        $hasZeroZeroPrefix = str_starts_with($digits, '00');

        $lookupCode = strtoupper($countryCode);
        $band = self::COUNTRIES[$lookupCode] ?? null;

        // Strip the international prefix when present. The
        // remaining digits are the national portion.
        if ($hasPlusPrefix) {
            $digits = substr($digits, 0);
        } elseif ($hasZeroZeroPrefix) {
            $digits = substr($digits, 2);
        }

        // When a country-specific band exists, first check the
        // dial code if the customer typed an international
        // form.
        if ($band !== null && ($hasPlusPrefix || $hasZeroZeroPrefix)) {
            $expectedDial = $band['dial'];
            if (!str_starts_with($digits, $expectedDial)) {
                return 'The country code in this phone number does not match the selected country.';
            }
            $digits = substr($digits, strlen($expectedDial));
        }

        $digitCount = strlen($digits);

        if ($band !== null) {
            if ($digitCount < $band['min'] || $digitCount > $band['max']) {
                $countryName = CountryCatalog::name($lookupCode) ?? $lookupCode;
                return 'This phone number does not look valid for ' . $countryName . '.';
            }
            return '';
        }

        // Fallback for countries without a length band.
        if ($digitCount < 6 || $digitCount > 15) {
            return 'This phone number does not look valid. Include the country code if you are calling from outside your country.';
        }

        return '';
    }
}
