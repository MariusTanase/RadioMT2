<?php

namespace App\Support;

use Illuminate\Support\Str;

class GenreMapper
{
    /** @var array<string, list<string>> normalised tag => genre slugs */
    private array $index = [];

    /** @var array<string, true> normalised tag => ignored */
    private array $ignore = [];

    /** @param array{genres: array<string, array{label: string, sort: int, tags: list<string>}>, ignore: list<string>}|null $config */
    public function __construct(?array $config = null)
    {
        $config ??= config('radio-genres');

        foreach ($config['ignore'] ?? [] as $tag) {
            $this->ignore[$this->normalise($tag)] = true;
        }

        foreach ($config['genres'] as $slug => $genre) {
            foreach ($genre['tags'] as $tag) {
                $this->index[$this->normalise($tag)][] = $slug;
            }
        }
    }

    /**
     * Map a Radio Browser comma-separated tag string onto canonical genre slugs.
     *
     * @return list<string> never empty; ['other'] when nothing matches
     */
    public function map(string $tags): array
    {
        $slugs = [];

        foreach (explode(',', $tags) as $raw) {
            $tag = $this->normalise($raw);

            if ($tag === '' || isset($this->ignore[$tag])) {
                continue;
            }

            foreach ($this->index[$tag] ?? [] as $slug) {
                $slugs[$slug] = true;
            }
        }

        return $slugs === [] ? ['other'] : array_keys($slugs);
    }

    /**
     * Lowercase, accent-fold and collapse whitespace so that "Música Pop",
     * "musica pop" and "  MUSICA   POP " all compare equal.
     */
    public function normalise(string $tag): string
    {
        $tag = Str::ascii(Str::lower(trim($tag)));

        return trim((string) preg_replace('/\s+/', ' ', $tag));
    }
}
