<?php

use App\Models\Radio;
use App\Support\CatalogueFilter;
use Illuminate\Http\Request;

// The exact set of distinct, non-null country_code values found in the real
// imported catalogue (`SELECT DISTINCT country_code FROM radios`) at the
// time config/continents.php was written. Encoded as a fixed snapshot here
// so this test can prove full coverage without a live database connection —
// a code disappearing from this array would only mean it stopped importing,
// never that coverage silently regressed.
function importedCountryCodes(): array
{
    return [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
        'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BW', 'BY', 'BZ',
        'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ',
        'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ',
        'EC', 'EE', 'EG', 'ER', 'ES', 'ET',
        'FI', 'FJ', 'FK', 'FM', 'FO', 'FR',
        'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GT', 'GU', 'GW', 'GY',
        'HK', 'HN', 'HR', 'HT', 'HU',
        'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT',
        'JE', 'JM', 'JO', 'JP',
        'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY',
        'MA', 'MC', 'MD', 'ME', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ',
        'NA', 'NC', 'NE', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ',
        'OM',
        'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM', 'PR', 'PS', 'PT', 'PW', 'PY',
        'QA',
        'RE', 'RO', 'RS', 'RU', 'RW',
        'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SY', 'SZ',
        'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ',
        'UA', 'UG', 'UM', 'US', 'UY', 'UZ',
        'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU',
        'WF',
        'XK', 'XX',
        'YE', 'YT',
        'ZA', 'ZM', 'ZW',
    ];
}

it('maps every country code seen in the imported catalogue to a continent', function () {
    $map = config('continents');

    $missing = array_diff(importedCountryCodes(), array_keys($map));

    expect($missing)->toBe([]);
});

it('only ever assigns one of the seven documented continent keys', function () {
    $allowed = ['africa', 'americas', 'asia', 'europe', 'oceania', 'antarctica', 'unknown'];

    $unexpected = array_diff(array_unique(array_values(config('continents'))), $allowed);

    expect($unexpected)->toBe([]);
});

it('uses two-letter uppercase keys throughout', function () {
    foreach (array_keys(config('continents')) as $code) {
        expect($code)->toMatch('/^[A-Z]{2}$/');
    }
});

it('places a representative code from each continent correctly', function () {
    $map = config('continents');

    expect($map['DE'])->toBe('europe')
        ->and($map['US'])->toBe('americas')
        ->and($map['JP'])->toBe('asia')
        ->and($map['NG'])->toBe('africa')
        ->and($map['AU'])->toBe('oceania')
        ->and($map['AQ'])->toBe('antarctica')
        ->and($map['XX'])->toBe('unknown');
});

it('falls a code absent from the map into unknown at runtime rather than vanishing', function () {
    Radio::factory()->create(['title' => 'Truly Unmapped', 'country_code' => 'ZZ']);
    Radio::factory()->create(['title' => 'Mapped', 'country_code' => 'DE']);

    $filter = CatalogueFilter::fromRequest(
        Request::create('/', 'GET', ['region' => 'unknown'])
    );

    expect($filter->query()->pluck('title')->all())->toBe(['Truly Unmapped']);
});
