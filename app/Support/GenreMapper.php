<?php

namespace App\Support;

use Illuminate\Support\Str;

class GenreMapper
{
    /** @var array<string, list<string>> normalised tag => genre slugs */
    private array $index = [];

    /** @var array<string, true> normalised tag => ignored */
    private array $ignore = [];

    /** @var array<string, int> genre slug => specificity, higher wins in primary() */
    private array $specificity = [];

    /** @var array<string, int> genre slug => declaration order, lower wins primary() ties */
    private array $declarationOrder = [];

    /** @param array{genres: array<string, array{label: string, sort: int, specificity?: int, tags: list<string>}>, ignore: list<string>}|null $config */
    public function __construct(?array $config = null)
    {
        $config ??= config('radio-genres');

        foreach ($config['ignore'] ?? [] as $tag) {
            $this->ignore[$this->normalise($tag)] = true;
        }

        $order = 0;

        foreach ($config['genres'] as $slug => $genre) {
            $this->specificity[$slug] = $genre['specificity'] ?? 0;
            $this->declarationOrder[$slug] = $order++;

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
     * Pick the single most specific genre out of a set of matched slugs, for
     * use as the one display label a station shows. Ties (equal
     * specificity) break on declaration order in config/radio-genres.php, so
     * the result never depends on the order tags happened to appear in the
     * source data.
     *
     * @param  list<string>  $slugs  as returned by map(); must not be empty
     */
    public function primary(array $slugs): string
    {
        $best = null;
        $bestSpecificity = -1;
        $bestOrder = PHP_INT_MAX;

        foreach ($slugs as $slug) {
            $specificity = $this->specificity[$slug] ?? 0;
            $order = $this->declarationOrder[$slug] ?? PHP_INT_MAX;

            if ($specificity > $bestSpecificity || ($specificity === $bestSpecificity && $order < $bestOrder)) {
                $best = $slug;
                $bestSpecificity = $specificity;
                $bestOrder = $order;
            }
        }

        return $best ?? 'other';
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
