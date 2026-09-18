<?php

namespace App\Support;

use App\Models\Radio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Parses the catalogue page's seven query params into a validated value
 * object and turns them into a Radio query. Used by every catalogue route
 * (the fragment, the random endpoint, the full page) so they can never
 * disagree about what "the current filter" means.
 *
 * Unknown or malformed values are ignored rather than fatal: this is a
 * public page reachable by a hand-edited or bookmarked URL, so
 * `?genre=bogus`, `?sort=garbage`, `?page=-5`, `?country=zz` must all render
 * the (unfiltered, or emptily-filtered) catalogue, never a 500 or a 404.
 */
class CatalogueFilter
{
    /** Stations rendered per page (spec: 50 rows per page). */
    public const PER_PAGE = 50;

    private const SORTS = ['played', 'popular', 'voted', 'bitrate', 'az', 'country'];

    /**
     * The one place sort labels are written. Both the tune bar's <select> and
     * the catalogue's "sorted by" rubric read this, so they cannot drift.
     *
     * "Most played here" counts real local plays; "Popular worldwide" is Radio
     * Browser's imported click count, which this app never increments — the
     * names say which is which rather than implying we measure both.
     */
    public const SORT_LABELS = [
        'played' => 'Most played here',
        'popular' => 'Popular worldwide',
        'voted' => 'Most voted',
        'bitrate' => 'Highest bitrate',
        'az' => 'A–Z',
        'country' => 'By country',
    ];

    public readonly ?string $q;

    public readonly ?string $genre;

    public readonly ?string $country;

    public readonly ?string $region;

    public readonly ?string $language;

    public readonly string $sort;

    public readonly bool $alive;

    public readonly bool $fav;

    public readonly int $page;

    /** The request's URL without its query string, used to build chip/paging links. */
    private readonly string $basePath;

    public function __construct(private readonly Request $request)
    {
        $this->basePath = $request->url();

        $q = trim((string) $request->query('q', ''));
        $this->q = mb_strlen($q) >= 2 ? $q : null;

        $genre = $request->query('genre');
        $genres = config('radio-genres.genres', []);
        $this->genre = is_string($genre) && array_key_exists($genre, $genres) ? $genre : null;

        $country = $request->query('country');
        $country = is_string($country) && preg_match('/^[A-Za-z]{2}$/', $country) === 1
            ? strtoupper($country)
            : null;

        $region = $request->query('region');
        $continentKeys = array_merge(array_values(config('continents', [])), ['unknown']);
        $region = $country === null && is_string($region) && in_array($region, $continentKeys, true)
            ? $region
            : null;

        // country and region are mutually exclusive; country wins when both arrive.
        $this->country = $country;
        $this->region = $country === null ? $region : null;

        $language = $request->query('language');
        $language = is_string($language) ? trim($language) : '';
        $this->language = $language !== '' ? $language : null;

        $sort = $request->query('sort');
        $this->sort = is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : 'played';

        $aliveRaw = $request->query('alive');
        $this->alive = $aliveRaw === null
            ? true
            : (filter_var($aliveRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true);

        $this->fav = filter_var($request->query('fav'), FILTER_VALIDATE_BOOLEAN);

        $page = (int) $request->query('page', 1);
        $this->page = max(1, $page);
    }

    public static function fromRequest(Request $request): self
    {
        return new self($request);
    }

    /** @return Builder<Radio> */
    public function query(): Builder
    {
        return $this->baseQuery(includeGenre: true);
    }

    /**
     * Every filter except genre, for computing per-genre facet counts
     * against the rest of the currently-active filters.
     *
     * @return Builder<Radio>
     */
    public function queryWithoutGenre(): Builder
    {
        return $this->baseQuery(includeGenre: false);
    }

    /**
     * The current page of the filtered set, PER_PAGE rows per page.
     *
     * The page number is passed explicitly rather than left for the
     * paginator to resolve from the ambient container-bound request, so
     * this always reflects $this->page — the value CatalogueFilter itself
     * already validated — regardless of how or whether a real HTTP request
     * is in flight (this matters for tests that build a filter directly).
     *
     * @return LengthAwarePaginator<int, Radio>
     */
    public function paginate(): LengthAwarePaginator
    {
        return $this->query()
            ->paginate(perPage: self::PER_PAGE, page: $this->page)
            ->withQueryString();
    }

    /** @return Builder<Radio> */
    private function baseQuery(bool $includeGenre): Builder
    {
        $query = Radio::query();

        if ($this->q !== null) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $this->q);
            $query->where('title', 'like', '%'.$escaped.'%');
        }

        if ($includeGenre && $this->genre !== null) {
            $genre = $this->genre;
            $query->whereHas('genres', fn (Builder $g) => $g->where('slug', $genre));
        }

        if ($this->country !== null) {
            $query->where('country_code', $this->country);
        } elseif ($this->region !== null) {
            $query->where(function (Builder $q) {
                $mappedCodes = collect(config('continents', []));

                if ($this->region === 'unknown') {
                    // "Unknown" is everything not mapped to a real continent,
                    // so a code seen for the first time in a future import
                    // falls in here too rather than vanishing.
                    $knownCodes = $mappedCodes->filter(fn (string $c) => $c !== 'unknown')->keys()->all();
                    $q->whereNotNull('country_code')->whereNotIn('country_code', $knownCodes);
                } else {
                    $codes = $mappedCodes->filter(fn (string $c) => $c === $this->region)->keys()->all();
                    $q->whereIn('country_code', $codes);
                }
            });
        }

        if ($this->language !== null) {
            $query->where('language', $this->language);
        }

        if ($this->alive) {
            $query->where('is_alive', true);
        }

        if ($this->fav) {
            $query->where('is_favourite', true);
        }

        return $this->applySort($query);
    }

    /**
     * Every sort ends in title ASC, id ASC — except `az`, which already
     * sorts by title and keeps id ASC as its own unique tiebreak.
     *
     * `title` (not `id`) is the second-to-last column deliberately: with
     * `click_count` at 0 or near-0 for most of the catalogue, the old
     * `id ASC` final tiebreak made the tail of every sort read as raw
     * import order — indistinguishable from no sort at all. Ending in
     * `title ASC` instead turns that flat tail into a browsable
     * alphabetical run.
     *
     * `title` alone isn't unique, so `id ASC` still comes after it as the
     * true final tiebreak — without a unique tiebreaker, rows shift between
     * pages whenever every other column ties, and at ~50,000 rows that
     * happens constantly, so page 2 would silently repeat and skip
     * stations.
     *
     * @param  Builder<Radio>  $query
     * @return Builder<Radio>
     */
    private function applySort(Builder $query): Builder
    {
        return match ($this->sort) {
            // Local play count first; falls back to click_count while
            // play_count is still zero everywhere, which is today's
            // behaviour and the reason `played` stays the default sort.
            'popular' => $query->orderByDesc('click_count')->orderByDesc('votes')->orderBy('title')->orderBy('id'),
            'voted' => $query->orderByDesc('votes')->orderByDesc('click_count')->orderBy('title')->orderBy('id'),
            'bitrate' => $query->orderByDesc('bitrate')->orderByDesc('click_count')->orderBy('title')->orderBy('id'),
            'az' => $query->orderBy('title')->orderBy('id'),
            // `country` is nullable on imported rows. Ordered explicitly via
            // `country IS NULL` (0/1) rather than relying on the engine's
            // default NULL placement, because that default is not guaranteed
            // to agree between MySQL (production) and SQLite (tests) — this
            // form is verified identical on both and puts nulls last.
            'country' => $query->orderByRaw('country is null')->orderBy('country')->orderByDesc('click_count')->orderBy('title')->orderBy('id'),
            default => $query->orderByDesc('play_count')->orderByDesc('click_count')->orderBy('title')->orderBy('id'),
        };
    }

    /** Any narrowing filter (or non-default sort) is set. */
    public function isActive(): bool
    {
        return $this->q !== null
            || $this->genre !== null
            || $this->country !== null
            || $this->region !== null
            || $this->language !== null
            || $this->sort !== 'played'
            || $this->alive !== true
            || $this->fav !== false;
    }

    /**
     * Removable filter chips for the UI: what's active, its display label,
     * and the URL that results from removing just that one param.
     *
     * @return list<array{key: string, label: string, url: string}>
     */
    public function tokens(): array
    {
        $tokens = [];

        if ($this->q !== null) {
            $tokens[] = ['key' => 'q', 'label' => $this->q, 'url' => $this->urlWithout('q')];
        }

        if ($this->genre !== null) {
            $label = config("radio-genres.genres.{$this->genre}.label", $this->genre);
            $tokens[] = ['key' => 'genre', 'label' => $label, 'url' => $this->urlWithout('genre')];
        }

        if ($this->country !== null) {
            $tokens[] = ['key' => 'country', 'label' => $this->country, 'url' => $this->urlWithout('country')];
        }

        if ($this->region !== null) {
            $tokens[] = ['key' => 'region', 'label' => ucfirst($this->region), 'url' => $this->urlWithout('region')];
        }

        if ($this->language !== null) {
            $tokens[] = ['key' => 'language', 'label' => ucfirst($this->language), 'url' => $this->urlWithout('language')];
        }

        if ($this->fav) {
            $tokens[] = ['key' => 'fav', 'label' => 'Favourites', 'url' => $this->urlWithout('fav')];
        }

        return $tokens;
    }

    /**
     * The canonical, minimal query string for this filter's current state,
     * merged with $overrides (a null override removes that key). Defaults
     * (sort=played, alive=1, fav=0, page=1) are omitted so links stay short.
     */
    public function toQueryString(array $overrides = []): string
    {
        $params = array_merge($this->toArray(), $overrides);
        $params = array_filter($params, fn ($value) => $value !== null && $value !== '');

        return http_build_query($params);
    }

    /** @return array<string, string|int|null> */
    private function toArray(): array
    {
        return [
            'q' => $this->q,
            'genre' => $this->genre,
            'country' => $this->country,
            'region' => $this->region,
            'language' => $this->language,
            'sort' => $this->sort !== 'played' ? $this->sort : null,
            'alive' => $this->alive ? null : '0',
            'fav' => $this->fav ? '1' : null,
            'page' => $this->page > 1 ? $this->page : null,
        ];
    }

    private function urlWithout(string $key): string
    {
        $qs = $this->toQueryString([$key => null, 'page' => null]);

        return $qs === '' ? $this->basePath : $this->basePath.'?'.$qs;
    }
}
