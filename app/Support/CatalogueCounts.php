<?php

namespace App\Support;

use App\Models\Radio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cached catalogue aggregates. These are derived data with a TTL — a cold
 * cache costs one slow request, never a wrong page. `radios:import` flushes
 * all three on completion so a fresh import never serves stale counts.
 */
class CatalogueCounts
{
    private const TTL = 60 * 60 * 24; // 24h

    public const GENRE_KEY = 'catalogue:genre-counts';

    public const COUNTRY_KEY = 'catalogue:country-counts';

    public const LANGUAGE_KEY = 'catalogue:languages';

    /** @return array<string, int> genre_slug => alive_count */
    public static function genreCounts(): array
    {
        return Cache::remember(self::GENRE_KEY, self::TTL, function (): array {
            return DB::table('genre_radio')
                ->join('genres', 'genres.id', '=', 'genre_radio.genre_id')
                ->join('radios', 'radios.id', '=', 'genre_radio.radio_id')
                ->where('radios.is_alive', true)
                ->selectRaw('genres.slug as slug, count(*) as aggregate')
                ->groupBy('genres.slug')
                ->orderBy('genres.slug')
                ->pluck('aggregate', 'slug')
                ->map(fn ($count) => (int) $count)
                ->all();
        });
    }

    /** @return array<string, int> country_code => alive_count */
    public static function countryCounts(): array
    {
        return Cache::remember(self::COUNTRY_KEY, self::TTL, function (): array {
            return Radio::query()
                ->where('is_alive', true)
                ->whereNotNull('country_code')
                ->selectRaw('country_code, count(*) as aggregate')
                ->groupBy('country_code')
                ->orderBy('country_code')
                ->pluck('aggregate', 'country_code')
                ->map(fn ($count) => (int) $count)
                ->all();
        });
    }

    /** @return array<string, int> language => count (unfiltered, all stations) */
    public static function languages(): array
    {
        return Cache::remember(self::LANGUAGE_KEY, self::TTL, function (): array {
            return Radio::query()
                ->whereNotNull('language')
                ->where('language', '!=', '')
                ->selectRaw('language, count(*) as aggregate')
                ->groupBy('language')
                ->orderBy('language')
                ->pluck('aggregate', 'language')
                ->map(fn ($count) => (int) $count)
                ->all();
        });
    }

    /**
     * Per-genre counts for the currently-filtered set, computed in one
     * grouped query (not one count per genre) against every filter except
     * genre itself, so switching genres while a country/language/search
     * filter is active shows accurate facet counts.
     *
     * @return array<string, int> genre_slug => count within the scoped set
     */
    public static function scopedGenreCounts(CatalogueFilter $filter): array
    {
        $scoped = $filter->queryWithoutGenre()->reorder()->select('id');

        return DB::table('genre_radio')
            ->joinSub($scoped, 'scoped_radios', 'scoped_radios.id', '=', 'genre_radio.radio_id')
            ->join('genres', 'genres.id', '=', 'genre_radio.genre_id')
            ->selectRaw('genres.slug as slug, count(*) as aggregate')
            ->groupBy('genres.slug')
            ->orderBy('genres.slug')
            ->pluck('aggregate', 'slug')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Languages present in the CURRENT filtered set, with counts.
     *
     * Deliberately not cached: it depends on every active filter, so a cache
     * key would have to encode the whole filter and would almost never hit.
     * One grouped query against the already-filtered set is cheaper than
     * maintaining that.
     */
    /**
     * The most common languages, capped — the raw field holds comma-joined
     * values ("english,german"), so an uncapped list is 1,281 entries and
     * useless as a dropdown.
     */
    private const LANGUAGE_LIMIT = 40;

    public static function scopedLanguages(CatalogueFilter $filter): array
    {
        // With no filter active this is the same query for every visitor and
        // costs ~104ms, so serve it from the 24h cache instead.
        if (! $filter->isActive()) {
            return Cache::remember(
                'catalogue:languages-top',
                now()->addDay(),
                fn () => self::languageQuery($filter)
            );
        }

        return self::languageQuery($filter);
    }

    /** @return array<string, int> */
    private static function languageQuery(CatalogueFilter $filter): array
    {
        return $filter->query()
            ->reorder()
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->groupBy('language')
            ->selectRaw('language, COUNT(*) as aggregate')
            ->orderByDesc('aggregate')
            ->limit(self::LANGUAGE_LIMIT)
            ->pluck('aggregate', 'language')
            ->all();
    }

    public static function flush(): void
    {
        Cache::forget(self::GENRE_KEY);
        Cache::forget(self::COUNTRY_KEY);
        Cache::forget(self::LANGUAGE_KEY);
    }
}
