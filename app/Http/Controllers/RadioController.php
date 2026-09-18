<?php

namespace App\Http\Controllers;

use App\Models\Genre;
use App\Models\Radio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RadioController extends Controller
{
    /**
     * Stations rendered per page.
     *
     * The catalogue holds ~49,000 stations. Rendering them all cost 174MB against
     * a 128MB limit — the page returned HTTP 500 — and embedded a 9.7MB JSON blob.
     * Every path through this controller is paginated for that reason.
     */
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $genre = $this->genre($request);

        return view('radio.index', [
            'radios' => $this->query($genre)->paginate(self::PER_PAGE)->withQueryString(),
            'genres' => Genre::query()->orderBy('sort_order')->get(['id', 'name', 'slug']),
            'activeGenre' => $genre,
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));

        return response()->json(
            $this->query($this->genre($request))
                ->forPage($page, self::PER_PAGE)
                ->get(['id', 'title', 'artist', 'genre', 'image', 'url'])
        );
    }

    /**
     * An unknown slug is ignored rather than fatal — this page is public and
     * reachable by hand-edited URL and by crawler.
     */
    private function genre(Request $request): ?string
    {
        $genre = $request->query('genre');

        if (! is_string($genre) || ! array_key_exists($genre, config('radio-genres.genres', []))) {
            return null;
        }

        return $genre;
    }

    /** @return Builder<Radio> */
    private function query(?string $genre): Builder
    {
        return Radio::query()
            ->when($genre, fn (Builder $q, string $slug) => $q->whereHas(
                'genres',
                fn (Builder $g) => $g->where('slug', $slug)
            ))
            ->orderBy('id');
    }

    /** @return LengthAwarePaginator<int, Radio> */
    public function paginator(Request $request): LengthAwarePaginator
    {
        return $this->query($this->genre($request))->paginate(self::PER_PAGE);
    }
}
