<?php

namespace App\Console\Commands;

use App\Models\Genre;
use App\Models\Radio;
use App\Support\GenreMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemapGenres extends Command
{
    protected $signature = 'radios:remap-genres
        {--chunk=1000 : Number of stations to process per batch}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Recompute every station\'s genre pivot rows and its genre/artist display label from the stored tags column, with no network access';

    public function handle(GenreMapper $mapper): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $genreIds = Genre::pluck('id', 'slug')->all();

        $skipped = Radio::whereNull('tags')->count();
        $processed = 0;
        $stationsChanged = 0;
        $labelsChanged = 0;
        $pivotRowsAdded = 0;
        $pivotRowsRemoved = 0;

        Radio::query()
            ->whereNotNull('tags')
            ->chunkById($chunkSize, function ($radios) use (
                $mapper, $genreIds, $dryRun,
                &$processed, &$stationsChanged, &$labelsChanged, &$pivotRowsAdded, &$pivotRowsRemoved,
            ) {
                $processed += $radios->count();

                $radioIds = $radios->pluck('id');

                $currentPivots = DB::table('genre_radio')
                    ->whereIn('radio_id', $radioIds)
                    ->get(['radio_id', 'genre_id'])
                    ->groupBy('radio_id')
                    ->map(fn ($rows) => $rows->pluck('genre_id')->sort()->values()->all());

                $touchedRadioIds = [];
                $pivotInserts = [];

                foreach ($radios as $radio) {
                    $slugs = $mapper->map((string) $radio->tags);
                    $primary = $mapper->primary($slugs);
                    $label = config("radio-genres.genres.{$primary}.label", 'Other');

                    $desiredGenreIds = collect($slugs)
                        ->map(fn ($slug) => $genreIds[$slug] ?? null)
                        ->filter()
                        ->sort()
                        ->values()
                        ->all();

                    $currentGenreIds = $currentPivots->get($radio->id, []);

                    $labelChanged = $radio->genre !== $label || $radio->artist !== $label;
                    $pivotChanged = $desiredGenreIds !== $currentGenreIds;

                    if ($labelChanged || $pivotChanged) {
                        $stationsChanged++;
                    }

                    if ($labelChanged) {
                        $labelsChanged++;

                        // A plain WHERE update (not upsert) so only the genre
                        // and artist columns are ever touched — every other
                        // column on the row, favourite or not, is untouched.
                        if (! $dryRun) {
                            DB::table('radios')->where('id', $radio->id)->update([
                                'genre' => $label,
                                'artist' => $label,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    if ($pivotChanged) {
                        $pivotRowsRemoved += count(array_diff($currentGenreIds, $desiredGenreIds));
                        $pivotRowsAdded += count(array_diff($desiredGenreIds, $currentGenreIds));

                        $touchedRadioIds[] = $radio->id;

                        foreach ($desiredGenreIds as $genreId) {
                            $pivotInserts[] = ['radio_id' => $radio->id, 'genre_id' => $genreId];
                        }
                    }
                }

                if ($dryRun) {
                    return;
                }

                if ($touchedRadioIds !== []) {
                    DB::table('genre_radio')->whereIn('radio_id', $touchedRadioIds)->delete();
                }

                foreach (array_chunk($pivotInserts, 1000) as $chunk) {
                    DB::table('genre_radio')->insertOrIgnore($chunk);
                }
            });

        $this->info(($dryRun ? '[dry run] ' : '').
            "Processed {$processed} station(s) with tags. {$skipped} station(s) skipped (tags not yet imported).");
        $this->info("{$stationsChanged} station(s) would change.".($dryRun ? '' : ' changed.'));
        $this->info("Labels changed: {$labelsChanged}. Genre pivot rows added: {$pivotRowsAdded}, removed: {$pivotRowsRemoved}.");

        return self::SUCCESS;
    }
}
