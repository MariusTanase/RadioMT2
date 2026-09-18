<?php

namespace App\Console\Commands;

use App\Models\Genre;
use App\Models\Radio;
use App\Support\GenreMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportRadios extends Command
{
    protected $signature = 'radios:import
        {--limit= : Maximum number of stations to import}
        {--country= : Restrict to an ISO 3166-1 alpha-2 country code}
        {--fresh : Delete previously imported stations first (never touches seeded favourites)}
        {--server=de1.api.radio-browser.info : Radio Browser host to query}';

    protected $description = 'Import radio stations from the Radio Browser catalogue';

    /** Stations requested per page. Radio Browser tops out well above this. */
    private const PAGE_SIZE = 1000;

    private const USER_AGENT = 'RadioMT2/1.0 (+https://github.com/MariusTanase/RadioMT2)';

    public function handle(GenreMapper $mapper): int
    {
        if ($this->option('fresh')) {
            // Only rows this command itself created. The 20 seeded
            // favourites (source = 'seed') must never be touched here.
            $deleted = Radio::where('source', 'radiobrowser')->delete();
            $this->info("Removed {$deleted} previously imported station(s).");
        }

        $genreIds = Genre::pluck('id', 'slug')->all();
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $offset = 0;
        $imported = 0;

        while ($limit === null || $imported < $limit) {
            $stations = $this->fetchPage($offset, self::PAGE_SIZE);

            if ($stations === null) {
                $this->newLine();
                $this->error("Failed to fetch page at offset {$offset}. Re-run to resume — the import is idempotent.");

                return self::FAILURE;
            }

            if ($stations === []) {
                break;
            }

            $fetchedCount = count($stations);

            // The API may return more than we still need on the final page
            // toward --limit; keep only what we asked for.
            if ($limit !== null && $imported + $fetchedCount > $limit) {
                $stations = array_slice($stations, 0, $limit - $imported);
            }

            $imported += $this->storePage($stations, $mapper, $genreIds);
            $offset += $fetchedCount;

            $this->info("Imported {$imported} station(s) so far...");

            if ($fetchedCount < self::PAGE_SIZE) {
                // A page shorter than requested means we reached the end.
                break;
            }
        }

        $this->info("Done. Imported {$imported} station(s).");

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>>|null null on a failed fetch */
    private function fetchPage(int $offset, int $limit): ?array
    {
        $query = [
            'hidebroken' => 'true',
            // Ordering must be stable across pages. clickcount changes as
            // people listen, which would shuffle stations between pages
            // mid-import and either duplicate or skip rows.
            'order' => 'name',
            'reverse' => 'false',
            'limit' => $limit,
            'offset' => $offset,
        ];

        if ($country = $this->option('country')) {
            $query['countrycode'] = strtoupper($country);
        }

        $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->timeout(30)
            ->get("https://{$this->option('server')}/json/stations/search", $query);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * @param  list<array<string, mixed>>  $stations
     * @param  array<string, int>  $genreIds
     */
    private function storePage(array $stations, GenreMapper $mapper, array $genreIds): int
    {
        $rows = [];
        $genresByUuid = [];

        foreach ($stations as $station) {
            $uuid = trim((string) ($station['stationuuid'] ?? ''));
            $url = $this->pickUrl($station);

            if ($uuid === '' || $url === '') {
                continue;
            }

            $slugs = $mapper->map((string) ($station['tags'] ?? ''));
            $label = config("radio-genres.genres.{$slugs[0]}.label", 'Other');

            $rows[] = [
                'station_uuid' => $uuid,
                'title' => $this->truncate($this->clean((string) ($station['name'] ?? '')), 255),
                'artist' => $label,
                'genre' => $label,
                'image' => $this->truncate((string) ($station['favicon'] ?? ''), 2048),
                'url' => $this->truncate($url, 2048),
                'homepage' => $this->nullableString($station['homepage'] ?? null, 2048),
                'country' => $this->nullableString($station['country'] ?? null),
                'country_code' => $this->nullableString($station['countrycode'] ?? null),
                'language' => $this->nullableString($station['language'] ?? null),
                'codec' => $this->nullableString($station['codec'] ?? null, 16),
                'bitrate' => (int) ($station['bitrate'] ?? 0) ?: null,
                'votes' => max(0, (int) ($station['votes'] ?? 0)),
                'click_count' => max(0, (int) ($station['clickcount'] ?? 0)),
                'source' => 'radiobrowser',
                'is_favourite' => false,
                'is_alive' => (int) ($station['lastcheckok'] ?? 0) === 1,
                'checked_at' => $this->parseDate($station['lastchecktime_iso8601'] ?? null),
            ];

            $genresByUuid[$uuid] = $slugs;
        }

        if ($rows === []) {
            return 0;
        }

        // local_ok, local_checked_at, listeners, listener_peak, probed_at and
        // probe_ok are deliberately absent from both the row data and this
        // update list: they are written by a different (probing) command and
        // must survive a re-import untouched. is_favourite is likewise left
        // out of the update list (though still set on insert) so that a
        // future favouriting feature for imported stations is never undone
        // by a later re-import.
        Radio::upsert($rows, uniqueBy: ['station_uuid'], update: [
            'title', 'artist', 'genre', 'image', 'url', 'homepage', 'country',
            'country_code', 'language', 'codec', 'bitrate', 'votes',
            'click_count', 'source', 'is_alive', 'checked_at',
        ]);

        $this->syncGenres($genresByUuid, $genreIds);

        return count($rows);
    }

    /**
     * @param  array<string, list<string>>  $genresByUuid  station_uuid => genre slugs
     * @param  array<string, int>  $genreIds  slug => genre id
     */
    private function syncGenres(array $genresByUuid, array $genreIds): void
    {
        $radioIds = Radio::whereIn('station_uuid', array_keys($genresByUuid))->pluck('id', 'station_uuid');

        // Drop this page's existing pivot rows first so a station whose tags
        // changed since the last import does not accumulate stale genres.
        DB::table('genre_radio')->whereIn('radio_id', $radioIds->values())->delete();

        $pivot = [];

        foreach ($genresByUuid as $uuid => $slugs) {
            $radioId = $radioIds[$uuid] ?? null;

            if ($radioId === null) {
                continue;
            }

            foreach ($slugs as $slug) {
                if (isset($genreIds[$slug])) {
                    $pivot[] = ['radio_id' => $radioId, 'genre_id' => $genreIds[$slug]];
                }
            }
        }

        foreach (array_chunk($pivot, 1000) as $chunk) {
            DB::table('genre_radio')->insertOrIgnore($chunk);
        }
    }

    /** @param  array<string, mixed>  $station */
    private function pickUrl(array $station): string
    {
        $resolved = trim((string) ($station['url_resolved'] ?? ''));

        return $resolved !== '' ? $resolved : trim((string) ($station['url'] ?? ''));
    }

    private function clean(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    private function truncate(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    private function nullableString(mixed $value, ?int $length = null): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return $length !== null ? $this->truncate($value, $length) : $value;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
