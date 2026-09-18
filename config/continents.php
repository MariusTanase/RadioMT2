<?php

/**
 * ISO 3166-1 alpha-2 country code -> continent key.
 *
 * Seven continent keys: africa, americas, asia, europe, oceania, antarctica,
 * unknown. Radio Browser supplies only `countrycode`; nothing else in the app
 * maps it to a continent for the country <select>'s grouping.
 *
 * This list was derived from the real imported catalogue, not guessed:
 *
 *     SELECT DISTINCT country_code FROM radios;
 *
 * every code returned by that query is mapped below. `XX` is Radio Browser's
 * own "not specified" code and is mapped explicitly to `unknown`. A code that
 * shows up in a future import and is absent from this map is not silently
 * dropped: CatalogueFilter treats anything missing here as `unknown` too, so
 * this file only needs to stay a superset of what has been *seen*, not a
 * priori complete for all 249 ISO codes.
 */

return [
    // Africa
    'AO' => 'africa', 'BF' => 'africa', 'BI' => 'africa', 'BJ' => 'africa',
    'BW' => 'africa', 'CD' => 'africa', 'CF' => 'africa', 'CG' => 'africa',
    'CI' => 'africa', 'CM' => 'africa', 'CV' => 'africa', 'DJ' => 'africa',
    'DZ' => 'africa', 'EG' => 'africa', 'ER' => 'africa', 'ET' => 'africa',
    'GA' => 'africa', 'GH' => 'africa', 'GM' => 'africa', 'GN' => 'africa',
    'GQ' => 'africa', 'GW' => 'africa', 'IO' => 'africa', 'KE' => 'africa',
    'KM' => 'africa', 'LR' => 'africa', 'LS' => 'africa', 'LY' => 'africa',
    'MA' => 'africa', 'MG' => 'africa', 'ML' => 'africa', 'MR' => 'africa',
    'MU' => 'africa', 'MW' => 'africa', 'MZ' => 'africa', 'NA' => 'africa',
    'NE' => 'africa', 'NG' => 'africa', 'RE' => 'africa', 'RW' => 'africa',
    'SC' => 'africa', 'SD' => 'africa', 'SH' => 'africa', 'SL' => 'africa',
    'SN' => 'africa', 'SO' => 'africa', 'SS' => 'africa', 'ST' => 'africa',
    'SZ' => 'africa', 'TD' => 'africa', 'TG' => 'africa', 'TN' => 'africa',
    'TZ' => 'africa', 'UG' => 'africa', 'YT' => 'africa', 'ZA' => 'africa',
    'ZM' => 'africa', 'ZW' => 'africa',

    // Americas
    'AG' => 'americas', 'AI' => 'americas', 'AR' => 'americas', 'AW' => 'americas',
    'BB' => 'americas', 'BM' => 'americas', 'BO' => 'americas', 'BQ' => 'americas',
    'BR' => 'americas', 'BS' => 'americas', 'BZ' => 'americas', 'CA' => 'americas',
    'CL' => 'americas', 'CO' => 'americas', 'CR' => 'americas', 'CU' => 'americas',
    'CW' => 'americas', 'DM' => 'americas', 'DO' => 'americas', 'EC' => 'americas',
    'FK' => 'americas', 'GD' => 'americas', 'GF' => 'americas', 'GL' => 'americas',
    'GP' => 'americas', 'GT' => 'americas', 'GY' => 'americas', 'HN' => 'americas',
    'HT' => 'americas', 'JM' => 'americas', 'KN' => 'americas', 'KY' => 'americas',
    'LC' => 'americas', 'MQ' => 'americas', 'MS' => 'americas', 'MX' => 'americas',
    'NI' => 'americas', 'PA' => 'americas', 'PE' => 'americas', 'PM' => 'americas',
    'PR' => 'americas', 'PY' => 'americas', 'SR' => 'americas', 'SV' => 'americas',
    'TC' => 'americas', 'TT' => 'americas', 'US' => 'americas', 'UY' => 'americas',
    'VC' => 'americas', 'VE' => 'americas', 'VG' => 'americas', 'VI' => 'americas',

    // Asia
    'AE' => 'asia', 'AF' => 'asia', 'AM' => 'asia', 'AZ' => 'asia',
    'BD' => 'asia', 'BH' => 'asia', 'BN' => 'asia', 'BT' => 'asia',
    'CN' => 'asia', 'GE' => 'asia', 'HK' => 'asia', 'ID' => 'asia',
    'IL' => 'asia', 'IN' => 'asia', 'IQ' => 'asia', 'IR' => 'asia',
    'JO' => 'asia', 'JP' => 'asia', 'KG' => 'asia', 'KH' => 'asia',
    'KP' => 'asia', 'KR' => 'asia', 'KW' => 'asia', 'KZ' => 'asia',
    'LA' => 'asia', 'LB' => 'asia', 'LK' => 'asia', 'MM' => 'asia',
    'MN' => 'asia', 'MO' => 'asia', 'MV' => 'asia', 'MY' => 'asia',
    'NP' => 'asia', 'OM' => 'asia', 'PH' => 'asia', 'PK' => 'asia',
    'PS' => 'asia', 'QA' => 'asia', 'SA' => 'asia', 'SG' => 'asia',
    'SY' => 'asia', 'TH' => 'asia', 'TJ' => 'asia', 'TL' => 'asia',
    'TM' => 'asia', 'TR' => 'asia', 'TW' => 'asia', 'UZ' => 'asia',
    'VN' => 'asia', 'YE' => 'asia',

    // Europe
    'AD' => 'europe', 'AL' => 'europe', 'AT' => 'europe', 'AX' => 'europe',
    'BA' => 'europe', 'BE' => 'europe', 'BG' => 'europe', 'BY' => 'europe',
    'CH' => 'europe', 'CY' => 'europe', 'CZ' => 'europe', 'DE' => 'europe',
    'DK' => 'europe', 'EE' => 'europe', 'ES' => 'europe', 'FI' => 'europe',
    'FO' => 'europe', 'FR' => 'europe', 'GB' => 'europe', 'GG' => 'europe',
    'GI' => 'europe', 'GR' => 'europe', 'HR' => 'europe', 'HU' => 'europe',
    'IE' => 'europe', 'IM' => 'europe', 'IS' => 'europe', 'IT' => 'europe',
    'JE' => 'europe', 'LI' => 'europe', 'LT' => 'europe', 'LU' => 'europe',
    'LV' => 'europe', 'MC' => 'europe', 'MD' => 'europe', 'ME' => 'europe',
    'MK' => 'europe', 'MT' => 'europe', 'NL' => 'europe', 'NO' => 'europe',
    'PL' => 'europe', 'PT' => 'europe', 'RO' => 'europe', 'RS' => 'europe',
    'RU' => 'europe', 'SE' => 'europe', 'SI' => 'europe', 'SJ' => 'europe',
    'SK' => 'europe', 'SM' => 'europe', 'UA' => 'europe', 'VA' => 'europe',
    'XK' => 'europe',

    // Oceania
    'AS' => 'oceania', 'AU' => 'oceania', 'CC' => 'oceania', 'CK' => 'oceania',
    'CX' => 'oceania', 'FJ' => 'oceania', 'FM' => 'oceania', 'GU' => 'oceania',
    'KI' => 'oceania', 'MH' => 'oceania', 'NC' => 'oceania', 'NR' => 'oceania',
    'NU' => 'oceania', 'NZ' => 'oceania', 'PF' => 'oceania', 'PG' => 'oceania',
    'PW' => 'oceania', 'SB' => 'oceania', 'TO' => 'oceania', 'TV' => 'oceania',
    'UM' => 'oceania', 'VU' => 'oceania', 'WF' => 'oceania',

    // Antarctica
    'AQ' => 'antarctica', 'TF' => 'antarctica',

    // Unknown — Radio Browser's own "not specified" code.
    'XX' => 'unknown',
];
