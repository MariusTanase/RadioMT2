<?php

namespace App\Http\Controllers;

use App\Models\Radio;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class RadioController extends Controller
{
    public function index(): View
    {
        return view('radio.index', [
            'radios' => $this->stations(),
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json($this->stations());
    }

    /** @return Collection<int, Radio> */
    private function stations(): Collection
    {
        return Radio::query()
            ->orderBy('id')
            ->get(['id', 'title', 'artist', 'genre', 'image', 'url']);
    }
}
