<?php
declare(strict_types=1);

namespace CorporateAid\Commerce\Support;

/**
 * The authoritative country list for the platform.
 *
 * Source: ISO 3166-1, via the iso-codes package
 * (/usr/share/iso-codes/json/iso_3166-1.json).
 * Generated once and committed. Country codes and
 * names are stable ISO standards; they do not change
 * often. When the standard is revised, regenerate this
 * file from the updated source.
 *
 * Each entry carries:
 *   alpha_2 - the ISO 3166-1 two-letter code
 *   name    - the common English name
 *   flag    - the emoji flag (rendered natively by the
 *             operating system; on Windows Chrome and
 *             Edge it renders as the two letters)
 *
 * The flag emoji is stored rather than an SVG file path
 * so the platform makes zero external requests when
 * rendering a country. A future pass can replace the
 * emoji with a self-hosted SVG sprite if the Windows
 * letter rendering is not acceptable.
 *
 * Sorted by name. The dropdown reads the order directly.
 */
final class CountryCatalog
{
    /**
     * Every country, keyed by alpha_2 code, sorted by name.
     *
     * @var array<string, array{name:string, flag:string}>
     */
    private const COUNTRIES = [
        'AF' => ['name' => 'Afghanistan', 'flag' => '🇦🇫'],
        'AL' => ['name' => 'Albania', 'flag' => '🇦🇱'],
        'DZ' => ['name' => 'Algeria', 'flag' => '🇩🇿'],
        'AS' => ['name' => 'American Samoa', 'flag' => '🇦🇸'],
        'AD' => ['name' => 'Andorra', 'flag' => '🇦🇩'],
        'AO' => ['name' => 'Angola', 'flag' => '🇦🇴'],
        'AI' => ['name' => 'Anguilla', 'flag' => '🇦🇮'],
        'AQ' => ['name' => 'Antarctica', 'flag' => '🇦🇶'],
        'AG' => ['name' => 'Antigua and Barbuda', 'flag' => '🇦🇬'],
        'AR' => ['name' => 'Argentina', 'flag' => '🇦🇷'],
        'AM' => ['name' => 'Armenia', 'flag' => '🇦🇲'],
        'AW' => ['name' => 'Aruba', 'flag' => '🇦🇼'],
        'AU' => ['name' => 'Australia', 'flag' => '🇦🇺'],
        'AT' => ['name' => 'Austria', 'flag' => '🇦🇹'],
        'AZ' => ['name' => 'Azerbaijan', 'flag' => '🇦🇿'],
        'BS' => ['name' => 'Bahamas', 'flag' => '🇧🇸'],
        'BH' => ['name' => 'Bahrain', 'flag' => '🇧🇭'],
        'BD' => ['name' => 'Bangladesh', 'flag' => '🇧🇩'],
        'BB' => ['name' => 'Barbados', 'flag' => '🇧🇧'],
        'BY' => ['name' => 'Belarus', 'flag' => '🇧🇾'],
        'BE' => ['name' => 'Belgium', 'flag' => '🇧🇪'],
        'BZ' => ['name' => 'Belize', 'flag' => '🇧🇿'],
        'BJ' => ['name' => 'Benin', 'flag' => '🇧🇯'],
        'BM' => ['name' => 'Bermuda', 'flag' => '🇧🇲'],
        'BT' => ['name' => 'Bhutan', 'flag' => '🇧🇹'],
        'BO' => ['name' => 'Bolivia, Plurinational State of', 'flag' => '🇧🇴'],
        'BQ' => ['name' => 'Bonaire, Sint Eustatius and Saba', 'flag' => '🇧🇶'],
        'BA' => ['name' => 'Bosnia and Herzegovina', 'flag' => '🇧🇦'],
        'BW' => ['name' => 'Botswana', 'flag' => '🇧🇼'],
        'BV' => ['name' => 'Bouvet Island', 'flag' => '🇧🇻'],
        'BR' => ['name' => 'Brazil', 'flag' => '🇧🇷'],
        'IO' => ['name' => 'British Indian Ocean Territory', 'flag' => '🇮🇴'],
        'BN' => ['name' => 'Brunei Darussalam', 'flag' => '🇧🇳'],
        'BG' => ['name' => 'Bulgaria', 'flag' => '🇧🇬'],
        'BF' => ['name' => 'Burkina Faso', 'flag' => '🇧🇫'],
        'BI' => ['name' => 'Burundi', 'flag' => '🇧🇮'],
        'CV' => ['name' => 'Cabo Verde', 'flag' => '🇨🇻'],
        'KH' => ['name' => 'Cambodia', 'flag' => '🇰🇭'],
        'CM' => ['name' => 'Cameroon', 'flag' => '🇨🇲'],
        'CA' => ['name' => 'Canada', 'flag' => '🇨🇦'],
        'KY' => ['name' => 'Cayman Islands', 'flag' => '🇰🇾'],
        'CF' => ['name' => 'Central African Republic', 'flag' => '🇨🇫'],
        'TD' => ['name' => 'Chad', 'flag' => '🇹🇩'],
        'CL' => ['name' => 'Chile', 'flag' => '🇨🇱'],
        'CN' => ['name' => 'China', 'flag' => '🇨🇳'],
        'CX' => ['name' => 'Christmas Island', 'flag' => '🇨🇽'],
        'CC' => ['name' => 'Cocos (Keeling) Islands', 'flag' => '🇨🇨'],
        'CO' => ['name' => 'Colombia', 'flag' => '🇨🇴'],
        'KM' => ['name' => 'Comoros', 'flag' => '🇰🇲'],
        'CG' => ['name' => 'Congo', 'flag' => '🇨🇬'],
        'CD' => ['name' => 'Congo, The Democratic Republic of the', 'flag' => '🇨🇩'],
        'CK' => ['name' => 'Cook Islands', 'flag' => '🇨🇰'],
        'CR' => ['name' => 'Costa Rica', 'flag' => '🇨🇷'],
        'HR' => ['name' => 'Croatia', 'flag' => '🇭🇷'],
        'CU' => ['name' => 'Cuba', 'flag' => '🇨🇺'],
        'CW' => ['name' => 'Curaçao', 'flag' => '🇨🇼'],
        'CY' => ['name' => 'Cyprus', 'flag' => '🇨🇾'],
        'CZ' => ['name' => 'Czechia', 'flag' => '🇨🇿'],
        'CI' => ['name' => 'Côte d\'Ivoire', 'flag' => '🇨🇮'],
        'DK' => ['name' => 'Denmark', 'flag' => '🇩🇰'],
        'DJ' => ['name' => 'Djibouti', 'flag' => '🇩🇯'],
        'DM' => ['name' => 'Dominica', 'flag' => '🇩🇲'],
        'DO' => ['name' => 'Dominican Republic', 'flag' => '🇩🇴'],
        'EC' => ['name' => 'Ecuador', 'flag' => '🇪🇨'],
        'EG' => ['name' => 'Egypt', 'flag' => '🇪🇬'],
        'SV' => ['name' => 'El Salvador', 'flag' => '🇸🇻'],
        'GQ' => ['name' => 'Equatorial Guinea', 'flag' => '🇬🇶'],
        'ER' => ['name' => 'Eritrea', 'flag' => '🇪🇷'],
        'EE' => ['name' => 'Estonia', 'flag' => '🇪🇪'],
        'SZ' => ['name' => 'Eswatini', 'flag' => '🇸🇿'],
        'ET' => ['name' => 'Ethiopia', 'flag' => '🇪🇹'],
        'FK' => ['name' => 'Falkland Islands (Malvinas)', 'flag' => '🇫🇰'],
        'FO' => ['name' => 'Faroe Islands', 'flag' => '🇫🇴'],
        'FJ' => ['name' => 'Fiji', 'flag' => '🇫🇯'],
        'FI' => ['name' => 'Finland', 'flag' => '🇫🇮'],
        'FR' => ['name' => 'France', 'flag' => '🇫🇷'],
        'GF' => ['name' => 'French Guiana', 'flag' => '🇬🇫'],
        'PF' => ['name' => 'French Polynesia', 'flag' => '🇵🇫'],
        'TF' => ['name' => 'French Southern Territories', 'flag' => '🇹🇫'],
        'GA' => ['name' => 'Gabon', 'flag' => '🇬🇦'],
        'GM' => ['name' => 'Gambia', 'flag' => '🇬🇲'],
        'GE' => ['name' => 'Georgia', 'flag' => '🇬🇪'],
        'DE' => ['name' => 'Germany', 'flag' => '🇩🇪'],
        'GH' => ['name' => 'Ghana', 'flag' => '🇬🇭'],
        'GI' => ['name' => 'Gibraltar', 'flag' => '🇬🇮'],
        'GR' => ['name' => 'Greece', 'flag' => '🇬🇷'],
        'GL' => ['name' => 'Greenland', 'flag' => '🇬🇱'],
        'GD' => ['name' => 'Grenada', 'flag' => '🇬🇩'],
        'GP' => ['name' => 'Guadeloupe', 'flag' => '🇬🇵'],
        'GU' => ['name' => 'Guam', 'flag' => '🇬🇺'],
        'GT' => ['name' => 'Guatemala', 'flag' => '🇬🇹'],
        'GG' => ['name' => 'Guernsey', 'flag' => '🇬🇬'],
        'GN' => ['name' => 'Guinea', 'flag' => '🇬🇳'],
        'GW' => ['name' => 'Guinea-Bissau', 'flag' => '🇬🇼'],
        'GY' => ['name' => 'Guyana', 'flag' => '🇬🇾'],
        'HT' => ['name' => 'Haiti', 'flag' => '🇭🇹'],
        'HM' => ['name' => 'Heard Island and McDonald Islands', 'flag' => '🇭🇲'],
        'VA' => ['name' => 'Holy See (Vatican City State)', 'flag' => '🇻🇦'],
        'HN' => ['name' => 'Honduras', 'flag' => '🇭🇳'],
        'HK' => ['name' => 'Hong Kong', 'flag' => '🇭🇰'],
        'HU' => ['name' => 'Hungary', 'flag' => '🇭🇺'],
        'IS' => ['name' => 'Iceland', 'flag' => '🇮🇸'],
        'IN' => ['name' => 'India', 'flag' => '🇮🇳'],
        'ID' => ['name' => 'Indonesia', 'flag' => '🇮🇩'],
        'IR' => ['name' => 'Iran, Islamic Republic of', 'flag' => '🇮🇷'],
        'IQ' => ['name' => 'Iraq', 'flag' => '🇮🇶'],
        'IE' => ['name' => 'Ireland', 'flag' => '🇮🇪'],
        'IM' => ['name' => 'Isle of Man', 'flag' => '🇮🇲'],
        'IL' => ['name' => 'Israel', 'flag' => '🇮🇱'],
        'IT' => ['name' => 'Italy', 'flag' => '🇮🇹'],
        'JM' => ['name' => 'Jamaica', 'flag' => '🇯🇲'],
        'JP' => ['name' => 'Japan', 'flag' => '🇯🇵'],
        'JE' => ['name' => 'Jersey', 'flag' => '🇯🇪'],
        'JO' => ['name' => 'Jordan', 'flag' => '🇯🇴'],
        'KZ' => ['name' => 'Kazakhstan', 'flag' => '🇰🇿'],
        'KE' => ['name' => 'Kenya', 'flag' => '🇰🇪'],
        'KI' => ['name' => 'Kiribati', 'flag' => '🇰🇮'],
        'KP' => ['name' => 'Korea, Democratic People\'s Republic of', 'flag' => '🇰🇵'],
        'KR' => ['name' => 'Korea, Republic of', 'flag' => '🇰🇷'],
        'KW' => ['name' => 'Kuwait', 'flag' => '🇰🇼'],
        'KG' => ['name' => 'Kyrgyzstan', 'flag' => '🇰🇬'],
        'LA' => ['name' => 'Lao People\'s Democratic Republic', 'flag' => '🇱🇦'],
        'LV' => ['name' => 'Latvia', 'flag' => '🇱🇻'],
        'LB' => ['name' => 'Lebanon', 'flag' => '🇱🇧'],
        'LS' => ['name' => 'Lesotho', 'flag' => '🇱🇸'],
        'LR' => ['name' => 'Liberia', 'flag' => '🇱🇷'],
        'LY' => ['name' => 'Libya', 'flag' => '🇱🇾'],
        'LI' => ['name' => 'Liechtenstein', 'flag' => '🇱🇮'],
        'LT' => ['name' => 'Lithuania', 'flag' => '🇱🇹'],
        'LU' => ['name' => 'Luxembourg', 'flag' => '🇱🇺'],
        'MO' => ['name' => 'Macao', 'flag' => '🇲🇴'],
        'MG' => ['name' => 'Madagascar', 'flag' => '🇲🇬'],
        'MW' => ['name' => 'Malawi', 'flag' => '🇲🇼'],
        'MY' => ['name' => 'Malaysia', 'flag' => '🇲🇾'],
        'MV' => ['name' => 'Maldives', 'flag' => '🇲🇻'],
        'ML' => ['name' => 'Mali', 'flag' => '🇲🇱'],
        'MT' => ['name' => 'Malta', 'flag' => '🇲🇹'],
        'MH' => ['name' => 'Marshall Islands', 'flag' => '🇲🇭'],
        'MQ' => ['name' => 'Martinique', 'flag' => '🇲🇶'],
        'MR' => ['name' => 'Mauritania', 'flag' => '🇲🇷'],
        'MU' => ['name' => 'Mauritius', 'flag' => '🇲🇺'],
        'YT' => ['name' => 'Mayotte', 'flag' => '🇾🇹'],
        'MX' => ['name' => 'Mexico', 'flag' => '🇲🇽'],
        'FM' => ['name' => 'Micronesia, Federated States of', 'flag' => '🇫🇲'],
        'MD' => ['name' => 'Moldova, Republic of', 'flag' => '🇲🇩'],
        'MC' => ['name' => 'Monaco', 'flag' => '🇲🇨'],
        'MN' => ['name' => 'Mongolia', 'flag' => '🇲🇳'],
        'ME' => ['name' => 'Montenegro', 'flag' => '🇲🇪'],
        'MS' => ['name' => 'Montserrat', 'flag' => '🇲🇸'],
        'MA' => ['name' => 'Morocco', 'flag' => '🇲🇦'],
        'MZ' => ['name' => 'Mozambique', 'flag' => '🇲🇿'],
        'MM' => ['name' => 'Myanmar', 'flag' => '🇲🇲'],
        'NA' => ['name' => 'Namibia', 'flag' => '🇳🇦'],
        'NR' => ['name' => 'Nauru', 'flag' => '🇳🇷'],
        'NP' => ['name' => 'Nepal', 'flag' => '🇳🇵'],
        'NL' => ['name' => 'Netherlands', 'flag' => '🇳🇱'],
        'NC' => ['name' => 'New Caledonia', 'flag' => '🇳🇨'],
        'NZ' => ['name' => 'New Zealand', 'flag' => '🇳🇿'],
        'NI' => ['name' => 'Nicaragua', 'flag' => '🇳🇮'],
        'NE' => ['name' => 'Niger', 'flag' => '🇳🇪'],
        'NG' => ['name' => 'Nigeria', 'flag' => '🇳🇬'],
        'NU' => ['name' => 'Niue', 'flag' => '🇳🇺'],
        'NF' => ['name' => 'Norfolk Island', 'flag' => '🇳🇫'],
        'MK' => ['name' => 'North Macedonia', 'flag' => '🇲🇰'],
        'MP' => ['name' => 'Northern Mariana Islands', 'flag' => '🇲🇵'],
        'NO' => ['name' => 'Norway', 'flag' => '🇳🇴'],
        'OM' => ['name' => 'Oman', 'flag' => '🇴🇲'],
        'PK' => ['name' => 'Pakistan', 'flag' => '🇵🇰'],
        'PW' => ['name' => 'Palau', 'flag' => '🇵🇼'],
        'PS' => ['name' => 'Palestine, State of', 'flag' => '🇵🇸'],
        'PA' => ['name' => 'Panama', 'flag' => '🇵🇦'],
        'PG' => ['name' => 'Papua New Guinea', 'flag' => '🇵🇬'],
        'PY' => ['name' => 'Paraguay', 'flag' => '🇵🇾'],
        'PE' => ['name' => 'Peru', 'flag' => '🇵🇪'],
        'PH' => ['name' => 'Philippines', 'flag' => '🇵🇭'],
        'PN' => ['name' => 'Pitcairn', 'flag' => '🇵🇳'],
        'PL' => ['name' => 'Poland', 'flag' => '🇵🇱'],
        'PT' => ['name' => 'Portugal', 'flag' => '🇵🇹'],
        'PR' => ['name' => 'Puerto Rico', 'flag' => '🇵🇷'],
        'QA' => ['name' => 'Qatar', 'flag' => '🇶🇦'],
        'RO' => ['name' => 'Romania', 'flag' => '🇷🇴'],
        'RU' => ['name' => 'Russian Federation', 'flag' => '🇷🇺'],
        'RW' => ['name' => 'Rwanda', 'flag' => '🇷🇼'],
        'RE' => ['name' => 'Réunion', 'flag' => '🇷🇪'],
        'BL' => ['name' => 'Saint Barthélemy', 'flag' => '🇧🇱'],
        'SH' => ['name' => 'Saint Helena, Ascension and Tristan da Cunha', 'flag' => '🇸🇭'],
        'KN' => ['name' => 'Saint Kitts and Nevis', 'flag' => '🇰🇳'],
        'LC' => ['name' => 'Saint Lucia', 'flag' => '🇱🇨'],
        'MF' => ['name' => 'Saint Martin (French part)', 'flag' => '🇲🇫'],
        'PM' => ['name' => 'Saint Pierre and Miquelon', 'flag' => '🇵🇲'],
        'VC' => ['name' => 'Saint Vincent and the Grenadines', 'flag' => '🇻🇨'],
        'WS' => ['name' => 'Samoa', 'flag' => '🇼🇸'],
        'SM' => ['name' => 'San Marino', 'flag' => '🇸🇲'],
        'ST' => ['name' => 'Sao Tome and Principe', 'flag' => '🇸🇹'],
        'SA' => ['name' => 'Saudi Arabia', 'flag' => '🇸🇦'],
        'SN' => ['name' => 'Senegal', 'flag' => '🇸🇳'],
        'RS' => ['name' => 'Serbia', 'flag' => '🇷🇸'],
        'SC' => ['name' => 'Seychelles', 'flag' => '🇸🇨'],
        'SL' => ['name' => 'Sierra Leone', 'flag' => '🇸🇱'],
        'SG' => ['name' => 'Singapore', 'flag' => '🇸🇬'],
        'SX' => ['name' => 'Sint Maarten (Dutch part)', 'flag' => '🇸🇽'],
        'SK' => ['name' => 'Slovakia', 'flag' => '🇸🇰'],
        'SI' => ['name' => 'Slovenia', 'flag' => '🇸🇮'],
        'SB' => ['name' => 'Solomon Islands', 'flag' => '🇸🇧'],
        'SO' => ['name' => 'Somalia', 'flag' => '🇸🇴'],
        'ZA' => ['name' => 'South Africa', 'flag' => '🇿🇦'],
        'GS' => ['name' => 'South Georgia and the South Sandwich Islands', 'flag' => '🇬🇸'],
        'SS' => ['name' => 'South Sudan', 'flag' => '🇸🇸'],
        'ES' => ['name' => 'Spain', 'flag' => '🇪🇸'],
        'LK' => ['name' => 'Sri Lanka', 'flag' => '🇱🇰'],
        'SD' => ['name' => 'Sudan', 'flag' => '🇸🇩'],
        'SR' => ['name' => 'Suriname', 'flag' => '🇸🇷'],
        'SJ' => ['name' => 'Svalbard and Jan Mayen', 'flag' => '🇸🇯'],
        'SE' => ['name' => 'Sweden', 'flag' => '🇸🇪'],
        'CH' => ['name' => 'Switzerland', 'flag' => '🇨🇭'],
        'SY' => ['name' => 'Syrian Arab Republic', 'flag' => '🇸🇾'],
        'TW' => ['name' => 'Taiwan, Province of China', 'flag' => '🇹🇼'],
        'TJ' => ['name' => 'Tajikistan', 'flag' => '🇹🇯'],
        'TZ' => ['name' => 'Tanzania, United Republic of', 'flag' => '🇹🇿'],
        'TH' => ['name' => 'Thailand', 'flag' => '🇹🇭'],
        'TL' => ['name' => 'Timor-Leste', 'flag' => '🇹🇱'],
        'TG' => ['name' => 'Togo', 'flag' => '🇹🇬'],
        'TK' => ['name' => 'Tokelau', 'flag' => '🇹🇰'],
        'TO' => ['name' => 'Tonga', 'flag' => '🇹🇴'],
        'TT' => ['name' => 'Trinidad and Tobago', 'flag' => '🇹🇹'],
        'TN' => ['name' => 'Tunisia', 'flag' => '🇹🇳'],
        'TM' => ['name' => 'Turkmenistan', 'flag' => '🇹🇲'],
        'TC' => ['name' => 'Turks and Caicos Islands', 'flag' => '🇹🇨'],
        'TV' => ['name' => 'Tuvalu', 'flag' => '🇹🇻'],
        'TR' => ['name' => 'Türkiye', 'flag' => '🇹🇷'],
        'UG' => ['name' => 'Uganda', 'flag' => '🇺🇬'],
        'UA' => ['name' => 'Ukraine', 'flag' => '🇺🇦'],
        'AE' => ['name' => 'United Arab Emirates', 'flag' => '🇦🇪'],
        'GB' => ['name' => 'United Kingdom', 'flag' => '🇬🇧'],
        'US' => ['name' => 'United States', 'flag' => '🇺🇸'],
        'UM' => ['name' => 'United States Minor Outlying Islands', 'flag' => '🇺🇲'],
        'UY' => ['name' => 'Uruguay', 'flag' => '🇺🇾'],
        'UZ' => ['name' => 'Uzbekistan', 'flag' => '🇺🇿'],
        'VU' => ['name' => 'Vanuatu', 'flag' => '🇻🇺'],
        'VE' => ['name' => 'Venezuela, Bolivarian Republic of', 'flag' => '🇻🇪'],
        'VN' => ['name' => 'Viet Nam', 'flag' => '🇻🇳'],
        'VG' => ['name' => 'Virgin Islands, British', 'flag' => '🇻🇬'],
        'VI' => ['name' => 'Virgin Islands, U.S.', 'flag' => '🇻🇮'],
        'WF' => ['name' => 'Wallis and Futuna', 'flag' => '🇼🇫'],
        'EH' => ['name' => 'Western Sahara', 'flag' => '🇪🇭'],
        'YE' => ['name' => 'Yemen', 'flag' => '🇾🇪'],
        'ZM' => ['name' => 'Zambia', 'flag' => '🇿🇲'],
        'ZW' => ['name' => 'Zimbabwe', 'flag' => '🇿🇼'],
        'AX' => ['name' => 'Åland Islands', 'flag' => '🇦🇽'],
    ];

    /**
     * The full list, sorted by name.
     *
     * @return array<string, array{name:string, flag:string}>
     */
    public static function all(): array
    {
        return self::COUNTRIES;
    }

    /**
     * The country name for an alpha_2 code, or null.
     */
    public static function name(string $alpha2): ?string
    {
        return self::COUNTRIES[strtoupper($alpha2)]['name'] ?? null;
    }

    /**
     * The flag emoji for an alpha_2 code, or empty string.
     */
    public static function flag(string $alpha2): string
    {
        return self::COUNTRIES[strtoupper($alpha2)]['flag'] ?? '';
    }

    /**
     * Whether an alpha_2 code is in the catalog.
     */
    public static function exists(string $alpha2): bool
    {
        return isset(self::COUNTRIES[strtoupper($alpha2)]);
    }
}
