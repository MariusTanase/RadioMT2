<?php

namespace App\Support;

use App\Models\Radio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Parses the catalogue page's search/genre/sort/page query params into a
 * validated value object and turns them into a Radio query.
 *
 * Unknown or malformed values are ignored rather than fatal: this is a
 * public page reachable by a hand-edited or bookmarked URL, so `?genre=bogus`
 * must render the unfiltered catalogue, never a 500.
 */
class CatalogueFilter
{
    public const PER_PAGE = 20;

    private const SORTS = ['played', 'voted', 'bitrate', 'az'];

    public readonly ?string $q;

    public readonly ?string $genre;

    public readonly string $sort;

    public readonly int $page;

    public function __construct(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $this->q = mb_strlen($q) >= 2 ? $q : null;

        $genre = $request->query('genre');
        $genres = config('radio-genres.genres', []);
        $this->genre = is_string($genre) && array_key_exists($genre, $genres) ? $genre : null;

        $sort = $request->query('sort');
        $this->sort = is_string($sort) && in_array($sort, self::SORTS, true) ? $sort : 'played';

        $page = (int) $request->query('page', 1);
        $this->page = max(1, $page);
    }

    /** @return Builder<Radio> */
    public function query(): Builder
    {
        $query = Radio::query();

        if ($this->q !== null) {
            $query->where('title', 'like', '%'.$this->q.'%');
        }

        if ($this->genre !== null) {
            $genre = $this->genre;
            $query->whereHas('genres', fn (Builder $g) => $g->where('slug', $genre));
        }

        // Every sort ends in id ASC. Without a unique tiebreaker, rows shift
        // between pages whenever the leading column ties — and it ties
        // constantly at this scale — silently repeating or skipping stations
        // across pages.
        return match ($this->sort) {
            'voted' => $query->orderByDesc('votes')->orderByDesc('click_count')->orderBy('id'),
            'bitrate' => $query->orderByDesc('bitrate')->orderByDesc('click_count')->orderBy('id'),
            'az' => $query->orderBy('title')->orderBy('id'),
            default => $query->orderByDesc('click_count')->orderByDesc('votes')->orderBy('id'),
        };
    }

    public function isActive(): bool
    {
        return $this->q !== null || $this->genre !== null || $this->sort !== 'played';
    }
}
