<?php

namespace App\Http\Controllers;

use App\Models\Radio;
use App\Support\CatalogueCounts;
use App\Support\CatalogueFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RadioController extends Controller
{
    /**
     * The full page: the Deck, the Tune bar, and the #catalogue fragment.
     *
     * The fragment partial is shared with CatalogueController, which renders it
     * alone for AJAX swaps. One template, two entry points — so a deep link and
     * a navigated view can never drift apart.
     */
    public function index(Request $request): View
    {
        $filter = CatalogueFilter::fromRequest($request);

        $shared = [
            'filter' => $filter,
            'radios' => $filter->paginate(),
            'genres' => collect(config('radio-genres.genres'))
                ->map(fn ($g, $slug) => ['slug' => $slug, 'label' => $g['label'], 'sort' => $g['sort']])
                ->sortBy('sort')->values()->all(),
            'genreCounts' => CatalogueCounts::genreCounts(),
            'countries' => CatalogueCounts::countryCounts(),
            'languages' => CatalogueCounts::scopedLanguages($filter),
        ];

        return view('radio.index', $shared);
    }

    /**
     * JSON list, paged the same way as the page.
     *
     * The catalogue holds ~58,000 stations; serialising all of them was a 9.7MB
     * response that exhausted the 128MB memory limit.
     */
    public function list(Request $request): JsonResponse
    {
        $filter = CatalogueFilter::fromRequest($request);

        return response()->json(
            $filter->query()
                ->forPage($filter->page, CatalogueFilter::PER_PAGE)
                ->get(['id', 'title', 'artist', 'genre', 'image', 'url'])
        );
    }

    /**
     * One random station from the whole filtered set.
     *
     * Kept as an alias of the catalogue endpoint so an older client calling
     * this path still shuffles across the full tuned set rather than the page
     * it happens to be holding.
     */
    public function random(Request $request): JsonResponse
    {
        $radio = CatalogueFilter::fromRequest($request)
            ->query()
            ->reorder()
            ->inRandomOrder()
            ->first();

        abort_if($radio === null, 404);

        return response()->json($radio->only(['id', 'title', 'genre', 'image', 'url']));
    }

    /** Kept for the model-bound route registered elsewhere. */
    public function show(Radio $radio): JsonResponse
    {
        return response()->json($radio->only(['id', 'title', 'genre', 'image', 'url']));
    }
}
