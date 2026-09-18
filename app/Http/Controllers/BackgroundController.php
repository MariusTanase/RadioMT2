<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BackgroundController extends Controller
{
    /**
     * The only categories the settings menu offers. Validating against this
     * list keeps the endpoint from being used as an open Unsplash proxy.
     */
    public const CATEGORIES = [
        'mountain', 'beach', 'sky', 'forest', 'cozy', 'japan', 'cat', 'dog',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $category = $request->query('query', 'mountain');

        abort_unless(
            is_string($category) && in_array($category, self::CATEGORIES, true),
            422,
            'Unknown background category.'
        );

        return response()->json(['url' => $this->photoUrl($category)]);
    }

    private function photoUrl(string $category): string
    {
        $key = config('services.unsplash.access_key');

        if (blank($key)) {
            return $this->fallback();
        }

        if ($cached = Cache::get($this->cacheKey($category))) {
            return $cached;
        }

        $url = $this->fetch($category, $key);

        if ($url === null) {
            return $this->fallback();
        }

        Cache::put($this->cacheKey($category), $url, now()->addHour());

        return $url;
    }

    private function fetch(string $category, string $key): ?string
    {
        try {
            $response = Http::withHeaders(['Authorization' => "Client-ID {$key}"])
                ->timeout(5)
                ->get('https://api.unsplash.com/photos/random', [
                    'query' => $category,
                    'orientation' => 'landscape',
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $url = $response->json('urls.full');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function cacheKey(string $category): string
    {
        return "background:{$category}";
    }

    private function fallback(): string
    {
        return asset('images/background.jpg');
    }
}
