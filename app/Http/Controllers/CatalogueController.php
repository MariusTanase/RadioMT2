<?php

namespace App\Http\Controllers;

use App\Support\CatalogueCounts;
use App\Support\CatalogueFilter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    /**
     * The swappable #catalogue fragment: tokens, count line, manifest, folio.
     * Deliberately no layout — this is included by the full page and also
     * returned directly to the fetch-driven navigation, so it must be one
     * template rendered two ways rather than two implementations.
     */
    public function index(Request $request): View
    {
        $filter = CatalogueFilter::fromRequest($request);

        return view('radio.partials.catalogue', [
            'filter' => $filter,
            'radios' => $filter->paginate(),
            'languages' => CatalogueCounts::scopedLanguages($filter),
        ]);
    }

    /**
     * One random station from the tuned (filtered) set — not from the 50 on
     * screen. 404s when the filtered set is empty.
     */
    public function random(Request $request): JsonResponse
    {
        $filter = CatalogueFilter::fromRequest($request);

        $radio = $filter->query()->reorder()->inRandomOrder()->first();

        abort_if($radio === null, 404);

        return response()->json([
            'id' => $radio->id,
            'title' => $radio->title,
            'url' => $radio->url,
            'genre' => $radio->genre,
            'image' => $radio->image,
        ]);
    }
}
