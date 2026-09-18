# Radio Library at Scale Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Grow the radio library from 20 seeded stations to the full Radio Browser catalogue (~51,600 working stations) with a curated genre taxonomy, liveness data, and real listener counts where stream servers publish them.

**Architecture:** Three migrations extend `radios` and add a `genres` table with a many-to-many pivot. A config-driven `GenreMapper` collapses Radio Browser's 12,261 free-text tags onto ~27 canonical genres. Two artisan commands do the work: `radios:import` pages the Radio Browser API and upserts on `station_uuid`; `radios:probe` reads Icecast status endpoints for liveness and listener counts. No UI changes — the app keeps showing the 20 seeded stations, now flagged as favourites.

**Tech Stack:** PHP 8.4.12, Laravel 13.32, Pest 5, MySQL 8.4 (dev) / in-memory SQLite (tests), Radio Browser JSON API.

**Spec:** `docs/superpowers/specs/2026-09-18-radio-library-at-scale-design.md`

## Global Constraints

- **PHP invocation.** The `php` on PATH is 8.4.0 and CANNOT run this app (Laravel 13 needs `>= 8.4.1`; you get a `platform_check.php` fatal error). Use, from the project root:
  ```bash
  PHP="/c/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe -c php.ini"
  ```
- **`php artisan test` is UNUSABLE** — it re-execs a child PHP via PATH, dropping `-c php.ini`, and every DB test fails with `could not find driver`. Run `$PHP vendor/bin/pest` directly.
- **`php artisan serve` is UNUSABLE** for the same reason. Use `$PHP -S 127.0.0.1:8123 -t public`.
- **No test may make a real HTTP request.** Every test uses `Http::fake()`. Do not call `api.radio-browser.info` or any stream host from a test.
- **The 20 seeded stations are sacred.** No import or probe may delete, overwrite or re-key them. They carry `source = 'seed'`, `is_favourite = true`, `station_uuid = NULL`.
- **Station ids 1–6 and 8–21** exist; `id: 7` does not. Existing tests pin that sequence.
- **`radios:probe` never writes `is_alive`** — that stays Radio Browser's verdict. It writes `probe_ok` instead.
- **`radios:probe` never sweeps the whole catalogue.** With no selector it probes favourites only.
- Run `$PHP vendor/bin/pint --dirty` before each commit.
- End every commit message with, after a blank line:
  ```
  Co-Authored-By: Claude Opus 5 (1M context) <noreply@anthropic.com>
  ```

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_09_18_000001_add_catalogue_fields_to_radios_table.php` | 19 new columns + indexes on `radios` |
| `database/migrations/2026_09_18_000002_create_genres_table.php` | Canonical genre taxonomy |
| `database/migrations/2026_09_18_000003_create_genre_radio_table.php` | Many-to-many pivot |
| `app/Models/Genre.php` | Genre model + `radios()` relation |
| `app/Models/Radio.php` | Extend `$fillable`, add `genres()` relation and casts (modify) |
| `config/radio-genres.php` | The taxonomy: ~27 genres, their tag synonyms, and the ignore list |
| `database/seeders/GenreSeeder.php` | Populates `genres` from config |
| `database/seeders/RadioSeeder.php` | Mark the 20 as `source='seed'`, `is_favourite=true` (modify) |
| `database/seeders/DatabaseSeeder.php` | Call `GenreSeeder` before `RadioSeeder` (modify) |
| `app/Support/GenreMapper.php` | Tag string → genre slugs |
| `app/Support/IcecastStatus.php` | Parse an Icecast `status-json.xsl` payload |
| `app/Console/Commands/ImportRadios.php` | `radios:import` |
| `app/Console/Commands/ProbeRadios.php` | `radios:probe` |

Tests: `tests/Unit/GenreMapperTest.php`, `tests/Unit/IcecastStatusTest.php`, `tests/Feature/GenreSeederTest.php`, `tests/Feature/RadioImportTest.php`, `tests/Feature/RadioImportPagingTest.php`, `tests/Feature/RadioImportIdempotencyTest.php`, `tests/Feature/RadioImportFreshTest.php`, `tests/Feature/RadioProbeTest.php`.

---

### Task 1: Schema, models, genre taxonomy

**Files:**
- Create: the three migrations above, `app/Models/Genre.php`, `config/radio-genres.php`, `database/seeders/GenreSeeder.php`
- Modify: `app/Models/Radio.php`, `database/seeders/RadioSeeder.php`, `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/GenreSeederTest.php`

**Interfaces:**
- Consumes: the existing `radios` table and `RadioSeeder` (20 stations, ids 1–6 and 8–21).
- Produces: `App\Models\Genre` (`name`, `slug`, `sort_order`, `radios()`); `App\Models\Radio::genres()` BelongsToMany; `config('radio-genres')` with keys `genres` and `ignore`; `Database\Seeders\GenreSeeder`, idempotent, upserts on `slug`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/GenreSeederTest.php`:

```php
<?php

use App\Models\Genre;
use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;

it('seeds every genre defined in config', function () {
    $this->seed(GenreSeeder::class);

    expect(Genre::count())->toBe(count(config('radio-genres.genres')));
});

it('always includes the other genre as a fallback bucket', function () {
    $this->seed(GenreSeeder::class);

    expect(Genre::where('slug', 'other')->exists())->toBeTrue();
});

it('can be seeded twice without duplicating genres', function () {
    $this->seed(GenreSeeder::class);
    $before = Genre::count();

    $this->seed(GenreSeeder::class);

    expect(Genre::count())->toBe($before);
});

it('marks the twenty seeded stations as favourites from the seed source', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::where('source', 'seed')->count())->toBe(20)
        ->and(Radio::where('is_favourite', true)->count())->toBe(20)
        ->and(Radio::whereNotNull('station_uuid')->count())->toBe(0);
});

it('relates radios to genres both ways', function () {
    $this->seed(GenreSeeder::class);
    $this->seed(RadioSeeder::class);

    $radio = Radio::first();
    $pop = Genre::where('slug', 'pop')->firstOrFail();

    $radio->genres()->attach($pop);

    expect($radio->fresh()->genres)->toHaveCount(1)
        ->and($pop->radios()->count())->toBe(1);
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
$PHP vendor/bin/pest tests/Feature/GenreSeederTest.php
```

Expected: FAIL — `Class "App\Models\Genre" not found`.

- [ ] **Step 3: Create the `radios` migration**

`database/migrations/2026_09_18_000001_add_catalogue_fields_to_radios_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            $table->string('station_uuid', 36)->nullable()->unique()->after('id');
            $table->string('country')->nullable();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('language')->nullable();
            $table->string('homepage', 2048)->nullable();
            $table->string('codec', 16)->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            $table->unsignedInteger('votes')->default(0);
            $table->unsignedInteger('click_count')->default(0)->index();
            $table->string('source', 16)->default('seed')->index();
            $table->boolean('is_favourite')->default(false)->index();
            $table->boolean('is_alive')->default(true)->index();
            $table->timestamp('checked_at')->nullable();
            $table->boolean('local_ok')->nullable();
            $table->timestamp('local_checked_at')->nullable();
            $table->unsignedInteger('listeners')->nullable()->index();
            $table->unsignedInteger('listener_peak')->nullable();
            $table->timestamp('probed_at')->nullable();
            $table->boolean('probe_ok')->nullable();
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::table('radios', function (Blueprint $table) {
            $table->dropIndex(['title']);
            $table->dropColumn([
                'station_uuid', 'country', 'country_code', 'language', 'homepage',
                'codec', 'bitrate', 'votes', 'click_count', 'source', 'is_favourite',
                'is_alive', 'checked_at', 'local_ok', 'local_checked_at',
                'listeners', 'listener_peak', 'probed_at', 'probe_ok',
            ]);
        });
    }
};
```

- [ ] **Step 4: Create the genre migrations**

`database/migrations/2026_09_18_000002_create_genres_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genres', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug', 64)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genres');
    }
};
```

`database/migrations/2026_09_18_000003_create_genre_radio_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genre_radio', function (Blueprint $table) {
            $table->foreignId('radio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('genre_id')->constrained()->cascadeOnDelete();
            $table->unique(['radio_id', 'genre_id']);
            $table->index('genre_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genre_radio');
    }
};
```

- [ ] **Step 5: Create the config**

`config/radio-genres.php`. Tag lists are lowercase and accent-folded already, because `GenreMapper` normalises both sides. The ignore list holds the high-volume non-genre tags observed in the live data.

```php
<?php

return [
    'genres' => [
        'pop' => ['label' => 'Pop', 'sort' => 10, 'tags' => ['pop', 'pop music', 'musica pop', 'top 40', 'top40', 'chart', 'charts', 'hits', 'adult contemporary', 'contemporary']],
        'rock' => ['label' => 'Rock', 'sort' => 20, 'tags' => ['rock', 'rock music', 'musica rock', 'pop rock', 'rock pop', 'rock and roll']],
        'classic-rock' => ['label' => 'Classic Rock', 'sort' => 30, 'tags' => ['classic rock', 'classicrock', 'rock classics']],
        'alternative' => ['label' => 'Alternative', 'sort' => 40, 'tags' => ['alternative', 'alternative rock', 'indie', 'indie rock', 'grunge']],
        'metal' => ['label' => 'Metal', 'sort' => 50, 'tags' => ['metal', 'heavy metal', 'hard rock', 'punk']],
        'dance' => ['label' => 'Dance', 'sort' => 60, 'tags' => ['dance', 'dance music', 'edm', 'club', 'disco']],
        'electronic' => ['label' => 'Electronic', 'sort' => 70, 'tags' => ['electronic', 'electronica', 'techno', 'trance', 'drum and bass', 'dubstep']],
        'house' => ['label' => 'House', 'sort' => 80, 'tags' => ['house', 'deep house', 'tech house', 'progressive house']],
        'jazz' => ['label' => 'Jazz', 'sort' => 90, 'tags' => ['jazz', 'smooth jazz', 'jazz music']],
        'blues' => ['label' => 'Blues', 'sort' => 100, 'tags' => ['blues', 'rhythm and blues']],
        'classical' => ['label' => 'Classical', 'sort' => 110, 'tags' => ['classical', 'classical music', 'musica clasica', 'opera', 'baroque']],
        'country' => ['label' => 'Country', 'sort' => 120, 'tags' => ['country', 'country music', 'bluegrass']],
        'folk' => ['label' => 'Folk', 'sort' => 130, 'tags' => ['folk', 'folk music', 'acoustic']],
        'latin' => ['label' => 'Latin', 'sort' => 140, 'tags' => ['latin', 'latino', 'latin music', 'musica en espanol', 'regional mexican', 'regional mexicana', 'musica popular mexicana', 'salsa', 'bachata', 'cumbia', 'merengue', 'reggaeton', 'ranchera', 'banda', 'grupera']],
        'reggae' => ['label' => 'Reggae', 'sort' => 150, 'tags' => ['reggae', 'ska', 'dancehall']],
        'hip-hop' => ['label' => 'Hip Hop', 'sort' => 160, 'tags' => ['hip hop', 'hiphop', 'hip-hop', 'rap', 'urban']],
        'rnb' => ['label' => 'R&B', 'sort' => 170, 'tags' => ['r&b', 'rnb', 'rhythm & blues', 'soul', 'funk', 'motown']],
        'oldies' => ['label' => 'Oldies', 'sort' => 180, 'tags' => ['oldies', 'classic hits', 'golden oldies', 'nostalgia', 'evergreen']],
        '80s' => ['label' => '80s', 'sort' => 190, 'tags' => ['80s', '1980s', 'eighties']],
        '90s' => ['label' => '90s', 'sort' => 200, 'tags' => ['90s', '1990s', 'nineties']],
        '70s' => ['label' => '70s', 'sort' => 210, 'tags' => ['70s', '1970s', 'seventies']],
        'lofi' => ['label' => 'Lofi & Chill', 'sort' => 220, 'tags' => ['lofi', 'lo-fi', 'lo fi', 'chillout', 'chill', 'ambient', 'relax', 'relaxing', 'meditation']],
        'religious' => ['label' => 'Religious', 'sort' => 230, 'tags' => ['christian', 'christian music', 'gospel', 'religious', 'catholic', 'worship', 'islamic', 'quran']],
        'news-talk' => ['label' => 'News & Talk', 'sort' => 240, 'tags' => ['news', 'talk', 'news talk', 'talk radio', 'local news', 'public radio', 'information', 'politics', 'current affairs']],
        'sport' => ['label' => 'Sport', 'sort' => 250, 'tags' => ['sport', 'sports', 'football', 'soccer', 'sports talk']],
        'world' => ['label' => 'World', 'sort' => 260, 'tags' => ['world', 'world music', 'international', 'ethnic', 'traditional']],
        'other' => ['label' => 'Other', 'sort' => 999, 'tags' => []],
    ],

    // High-volume tags that carry no genre meaning. Observed in the live top-45:
    // "moi merino" is a person's name attached to 1,783 stations.
    'ignore' => [
        'music', 'musica', 'radio', 'fm', 'am', 'radio station', 'estacion',
        'entretenimiento', 'moi merino', 'regional', 'local radio',
        'community radio', 'generaliste', 'variety', 'live', 'online',
        'stream', 'internet radio', '24/7', 'mexico', 'norteamerica',
        'america', 'latinoamerica', 'espanol', 'english', 'deutsch',
    ],
];
```

- [ ] **Step 6: Create the Genre model and extend Radio**

`app/Models/Genre.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    /** @return BelongsToMany<Radio, $this> */
    public function radios(): BelongsToMany
    {
        return $this->belongsToMany(Radio::class);
    }
}
```

In `app/Models/Radio.php`, extend `$fillable` with every new column, add casts, and add the relation:

```php
    protected $fillable = [
        'station_uuid', 'title', 'artist', 'genre', 'image', 'url',
        'country', 'country_code', 'language', 'homepage', 'codec', 'bitrate',
        'votes', 'click_count', 'source', 'is_favourite', 'is_alive', 'checked_at',
        'local_ok', 'local_checked_at', 'listeners', 'listener_peak',
        'probed_at', 'probe_ok',
    ];

    protected function casts(): array
    {
        return [
            'is_favourite' => 'boolean',
            'is_alive' => 'boolean',
            'local_ok' => 'boolean',
            'probe_ok' => 'boolean',
            'checked_at' => 'datetime',
            'local_checked_at' => 'datetime',
            'probed_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Genre, $this> */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class);
    }
```

Add `use Illuminate\Database\Eloquent\Relations\BelongsToMany;` to the imports.

- [ ] **Step 7: Create GenreSeeder and update the others**

`database/seeders/GenreSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        foreach (config('radio-genres.genres') as $slug => $genre) {
            $rows[] = [
                'slug' => $slug,
                'name' => $genre['label'],
                'sort_order' => $genre['sort'],
            ];
        }

        Genre::upsert($rows, uniqueBy: ['slug'], update: ['name', 'sort_order']);
    }
}
```

In `database/seeders/RadioSeeder.php`, add the two flags to every row and to the update list. Change the `upsert` call to:

```php
        Radio::upsert(
            $this->stations(),
            uniqueBy: ['id'],
            update: ['title', 'artist', 'genre', 'image', 'url', 'source', 'is_favourite'],
        );
```

and add `'source' => 'seed', 'is_favourite' => true,` to each of the 20 rows. Do not change any station's data fields.

In `database/seeders/DatabaseSeeder.php`:

```php
    public function run(): void
    {
        $this->call([
            GenreSeeder::class,
            RadioSeeder::class,
        ]);
    }
```

- [ ] **Step 8: Run the tests**

```bash
$PHP vendor/bin/pest
```

Expected: PASS. 25 existing + 5 new = 30 tests.

- [ ] **Step 9: Migrate the dev database**

```bash
$PHP artisan migrate
$PHP artisan db:seed
$PHP artisan tinker --execute="echo App\Models\Genre::count().' genres, '.App\Models\Radio::where('is_favourite',true)->count().' favourites';"
```

Expected: `27 genres, 20 favourites`.

- [ ] **Step 10: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add database config app/Models tests/Feature/GenreSeederTest.php
git commit -m "feat: add catalogue schema and curated genre taxonomy"
```

---

### Task 2: GenreMapper

**Files:**
- Create: `app/Support/GenreMapper.php`
- Test: `tests/Unit/GenreMapperTest.php`

**Interfaces:**
- Consumes: `config('radio-genres')` from Task 1.
- Produces: `App\Support\GenreMapper` with `map(string $tags): array` returning a list of genre slugs (never empty — falls back to `['other']`), and `normalise(string $tag): string`. The constructor takes an optional `?array $config` so tests can inject a taxonomy instead of mutating global config.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/GenreMapperTest.php`:

```php
<?php

use App\Support\GenreMapper;

function mapper(): GenreMapper
{
    return new GenreMapper([
        'genres' => [
            'pop' => ['label' => 'Pop', 'sort' => 10, 'tags' => ['pop', 'pop music', 'musica pop']],
            'rock' => ['label' => 'Rock', 'sort' => 20, 'tags' => ['rock', 'pop rock']],
            'latin' => ['label' => 'Latin', 'sort' => 30, 'tags' => ['regional mexicana']],
            'other' => ['label' => 'Other', 'sort' => 999, 'tags' => []],
        ],
        'ignore' => ['music', 'radio', 'moi merino'],
    ]);
}

it('maps a simple tag to its genre', function () {
    expect(mapper()->map('pop'))->toBe(['pop']);
});

it('folds accents so spanish tags match', function () {
    expect(mapper()->map('música pop'))->toBe(['pop']);
});

it('is case and whitespace insensitive', function () {
    expect(mapper()->map('  POP   MUSIC '))->toBe(['pop']);
});

it('maps one tag onto several genres when the taxonomy says so', function () {
    expect(mapper()->map('pop rock'))->toBe(['rock']);
});

it('collects genres from every tag in the list', function () {
    expect(mapper()->map('pop, rock'))->toBe(['pop', 'rock']);
});

it('never returns the same genre twice', function () {
    expect(mapper()->map('pop, pop music, música pop'))->toBe(['pop']);
});

it('drops tags on the ignore list', function () {
    expect(mapper()->map('music, radio, pop'))->toBe(['pop']);
});

it('drops the moi merino tag spam', function () {
    expect(mapper()->map('moi merino'))->toBe(['other']);
});

it('falls back to other when nothing matches', function () {
    expect(mapper()->map('kittens'))->toBe(['other']);
});

it('falls back to other for an empty tag string', function () {
    expect(mapper()->map(''))->toBe(['other'])
        ->and(mapper()->map('  ,  , '))->toBe(['other']);
});

it('matches whole tags only, never substrings', function () {
    // "estacion" must not match because "pop" appears nowhere in it, and a
    // substring matcher would wrongly pair short genre names with longer tags.
    expect(mapper()->map('estacion popular'))->toBe(['other']);
});

it('maps a multiword accented tag', function () {
    expect(mapper()->map('Regional Mexicana'))->toBe(['latin']);
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
$PHP vendor/bin/pest tests/Unit/GenreMapperTest.php
```

Expected: FAIL — `Class "App\Support\GenreMapper" not found`.

- [ ] **Step 3: Implement the mapper**

`app/Support/GenreMapper.php`:

```php
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
```

- [ ] **Step 4: Run the tests**

```bash
$PHP vendor/bin/pest tests/Unit/GenreMapperTest.php
```

Expected: PASS, 12 tests.

- [ ] **Step 5: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add app/Support/GenreMapper.php tests/Unit/GenreMapperTest.php
git commit -m "feat: map radio browser tags onto canonical genres"
```

---

### Task 3: radios:import

**Files:**
- Create: `app/Console/Commands/ImportRadios.php`
- Test: `tests/Feature/RadioImportTest.php`, `tests/Feature/RadioImportPagingTest.php`, `tests/Feature/RadioImportIdempotencyTest.php`, `tests/Feature/RadioImportFreshTest.php`

**Interfaces:**
- Consumes: `App\Models\Radio`, `App\Models\Genre`, `App\Support\GenreMapper`, `GenreSeeder`.
- Produces: the `radios:import` command. Later work relies on imported rows carrying `source = 'radiobrowser'` and a non-null `station_uuid`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/RadioImportTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(GenreSeeder::class));

function station(array $overrides = []): array
{
    return array_merge([
        'stationuuid' => 'uuid-1',
        'name' => 'Test FM',
        'url' => 'https://example.test/stream',
        'url_resolved' => 'https://example.test/resolved',
        'homepage' => 'https://example.test',
        'favicon' => 'https://example.test/icon.png',
        'tags' => 'pop,rock',
        'country' => 'Germany',
        'countrycode' => 'DE',
        'language' => 'german',
        'codec' => 'MP3',
        'bitrate' => 128,
        'votes' => 42,
        'clickcount' => 99,
        'lastcheckok' => 1,
        'lastchecktime_iso8601' => '2026-09-16T13:32:14Z',
    ], $overrides);
}

function fakePages(array ...$pages): void
{
    $responses = array_map(fn ($p) => Http::response($p), $pages);
    $responses[] = Http::response([]);
    Http::fake(['*radio-browser.info*' => Http::sequence()->pushResponse(...$responses)]);
}

it('imports a station with every field mapped', function () {
    fakePages([station()]);

    $this->artisan('radios:import')->assertSuccessful();

    $radio = Radio::where('station_uuid', 'uuid-1')->firstOrFail();

    expect($radio->title)->toBe('Test FM')
        ->and($radio->country)->toBe('Germany')
        ->and($radio->country_code)->toBe('DE')
        ->and($radio->codec)->toBe('MP3')
        ->and($radio->bitrate)->toBe(128)
        ->and($radio->votes)->toBe(42)
        ->and($radio->click_count)->toBe(99)
        ->and($radio->source)->toBe('radiobrowser')
        ->and($radio->is_favourite)->toBeFalse()
        ->and($radio->is_alive)->toBeTrue()
        ->and($radio->checked_at)->not->toBeNull();
});

it('prefers the resolved url over the raw url', function () {
    fakePages([station()]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('url'))
        ->toBe('https://example.test/resolved');
});

it('falls back to the raw url when there is no resolved url', function () {
    fakePages([station(['url_resolved' => ''])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('url'))
        ->toBe('https://example.test/stream');
});

it('skips stations with no usable url', function () {
    fakePages([station(['url' => '', 'url_resolved' => ''])]);

    $this->artisan('radios:import');

    expect(Radio::where('source', 'radiobrowser')->count())->toBe(0);
});

it('trims the tab characters real station names arrive with', function () {
    fakePages([station(['name' => "\t\tLOVE FM: All you need  "])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('title'))
        ->toBe('LOVE FM: All you need');
});

it('attaches every mapped genre to the station', function () {
    fakePages([station(['tags' => 'pop,rock'])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->firstOrFail()->genres->pluck('slug')->sort()->values()->all())
        ->toBe(['pop', 'rock']);
});

it('files an unmappable station under other', function () {
    fakePages([station(['tags' => 'moi merino'])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->firstOrFail()->genres->pluck('slug')->all())
        ->toBe(['other']);
});

it('records a dead station as not alive', function () {
    fakePages([station(['lastcheckok' => 0])]);

    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->value('is_alive'))->toBeFalsy();
});
```

Create `tests/Feature/RadioImportPagingTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(GenreSeeder::class));

function page(int $from, int $count): array
{
    return collect(range($from, $from + $count - 1))
        ->map(fn ($i) => station(['stationuuid' => "uuid-$i", 'name' => "Station $i"]))
        ->all();
}

it('keeps paging until a short page arrives', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()
        ->push(page(1, 1000))
        ->push(page(1001, 5))]);

    $this->artisan('radios:import')->assertSuccessful();

    expect(Radio::where('source', 'radiobrowser')->count())->toBe(1005);
});

it('stops at the requested limit', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()->push(page(1, 1000))]);

    $this->artisan('radios:import', ['--limit' => 10])->assertSuccessful();

    expect(Radio::where('source', 'radiobrowser')->count())->toBe(10);
});

it('passes the country filter to the api', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()->push([])]);

    $this->artisan('radios:import', ['--country' => 'RO']);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'countrycode=RO'));
});

it('requests a stable ordering, never clickcount', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()->push([])]);

    $this->artisan('radios:import');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'order=name')
        && str_contains($request->url(), 'hidebroken=true'));
});

it('identifies itself with a descriptive user agent', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()->push([])]);

    $this->artisan('radios:import');

    Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'RadioMT2'));
});

it('fails loudly when a page cannot be fetched', function () {
    Http::fake(['*radio-browser.info*' => Http::response(status: 500)]);

    $this->artisan('radios:import')->assertFailed();
});
```

Create `tests/Feature/RadioImportIdempotencyTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => $this->seed(GenreSeeder::class));

it('does not duplicate stations when run twice', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()
        ->push([station()])->push([])
        ->push([station()])->push([])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->count())->toBe(1);
});

it('does not duplicate pivot rows when run twice', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()
        ->push([station(['tags' => 'pop,rock'])])->push([])
        ->push([station(['tags' => 'pop,rock'])])->push([])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import');

    expect(DB::table('genre_radio')->count())->toBe(2);
});

it('replaces stale genres rather than accumulating them', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()
        ->push([station(['tags' => 'pop,rock'])])->push([])
        ->push([station(['tags' => 'jazz'])])->push([])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import');

    expect(Radio::where('station_uuid', 'uuid-1')->firstOrFail()->genres->pluck('slug')->all())
        ->toBe(['jazz']);
});

it('preserves locally probed data across a re-import', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()
        ->push([station()])->push([])
        ->push([station()])->push([])]);

    $this->artisan('radios:import');
    Radio::where('station_uuid', 'uuid-1')->update(['local_ok' => true, 'listeners' => 1234]);

    $this->artisan('radios:import');

    $radio = Radio::where('station_uuid', 'uuid-1')->firstOrFail();
    expect($radio->local_ok)->toBeTrue()->and($radio->listeners)->toBe(1234);
});
```

Create `tests/Feature/RadioImportFreshTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(GenreSeeder::class);
    $this->seed(RadioSeeder::class);
});

it('removes previously imported stations', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()
        ->push([station(['stationuuid' => 'old'])])->push([])
        ->push([station(['stationuuid' => 'new'])])->push([])]);

    $this->artisan('radios:import');
    $this->artisan('radios:import', ['--fresh']);

    expect(Radio::where('station_uuid', 'old')->exists())->toBeFalse()
        ->and(Radio::where('station_uuid', 'new')->exists())->toBeTrue();
});

it('never deletes the seeded favourites', function () {
    Http::fake(['*radio-browser.info*' => Http::sequence()->push([station()])->push([])]);

    $this->artisan('radios:import', ['--fresh'])->assertSuccessful();

    expect(Radio::where('source', 'seed')->count())->toBe(20)
        ->and(Radio::orderBy('id')->pluck('id')->take(7)->all())
        ->toBe([1, 2, 3, 4, 5, 6, 8]);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
$PHP vendor/bin/pest tests/Feature/RadioImport
```

Expected: FAIL — `The command "radios:import" does not exist.`

- [ ] **Step 3: Implement the command**

`app/Console/Commands/ImportRadios.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Genre;
use App\Models\Radio;
use App\Support\GenreMapper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportRadios extends Command
{
    protected $signature = 'radios:import
        {--limit= : Maximum number of stations to import}
        {--country= : Restrict to an ISO 3166-1 alpha-2 country code}
        {--fresh : Delete previously imported stations first}
        {--server=de1.api.radio-browser.info : Radio Browser host}';

    protected $description = 'Import radio stations from the Radio Browser catalogue';

    private const PAGE_SIZE = 1000;

    private const USER_AGENT = 'RadioMT2/1.0 (+https://github.com/MariusTanase/RadioMT2)';

    public function handle(GenreMapper $mapper): int
    {
        if ($this->option('fresh')) {
            // Only ever imported rows. The seeded favourites are untouchable.
            $deleted = Radio::where('source', 'radiobrowser')->delete();
            $this->info("Removed {$deleted} previously imported stations.");
        }

        $genreIds = Genre::pluck('id', 'slug')->all();
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $bar = $this->output->createProgressBar($limit ?? 0);
        $offset = 0;
        $imported = 0;

        while (true) {
            $take = $limit !== null ? min(self::PAGE_SIZE, $limit - $imported) : self::PAGE_SIZE;

            if ($take <= 0) {
                break;
            }

            $stations = $this->fetchPage($offset, $take);

            if ($stations === null) {
                $bar->finish();
                $this->newLine();
                $this->error("Failed to fetch page at offset {$offset}. Re-run to resume — the import is idempotent.");

                return self::FAILURE;
            }

            if ($stations === []) {
                break;
            }

            $imported += $this->storePage($stations, $mapper, $genreIds);
            $bar->advance(count($stations));

            if (count($stations) < $take) {
                break;
            }

            $offset += count($stations);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Imported {$imported} stations.");

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>>|null null on failure */
    private function fetchPage(int $offset, int $limit): ?array
    {
        $query = [
            'hidebroken' => 'true',
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
            ->retry(2, 1000, throw: false)
            ->get("https://{$this->option('server')}/json/stations/search", $query);

        return $response->successful() ? $response->json() : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stations
     * @param  array<string, int>  $genreIds
     */
    private function storePage(array $stations, GenreMapper $mapper, array $genreIds): int
    {
        $rows = [];
        $genresByUuid = [];

        foreach ($stations as $station) {
            $url = $station['url_resolved'] ?: ($station['url'] ?? '');
            $uuid = $station['stationuuid'] ?? '';

            if ($url === '' || $uuid === '') {
                continue;
            }

            $slugs = $mapper->map($station['tags'] ?? '');
            $label = config("radio-genres.genres.{$slugs[0]}.label", 'Other');

            $rows[] = [
                'station_uuid' => $uuid,
                'title' => Str::limit($this->clean($station['name'] ?? ''), 250, ''),
                'artist' => $label,
                'genre' => $label,
                'image' => Str::limit($station['favicon'] ?? '', 2040, ''),
                'url' => Str::limit($url, 2040, ''),
                'homepage' => Str::limit($station['homepage'] ?? '', 2040, '') ?: null,
                'country' => $station['country'] ?: null,
                'country_code' => $station['countrycode'] ?: null,
                'language' => $station['language'] ?: null,
                'codec' => Str::limit($station['codec'] ?? '', 16, '') ?: null,
                'bitrate' => (int) ($station['bitrate'] ?? 0) ?: null,
                'votes' => max(0, (int) ($station['votes'] ?? 0)),
                'click_count' => max(0, (int) ($station['clickcount'] ?? 0)),
                'source' => 'radiobrowser',
                'is_favourite' => false,
                'is_alive' => (int) ($station['lastcheckok'] ?? 0) === 1,
                'checked_at' => $station['lastchecktime_iso8601'] ?? null,
            ];

            $genresByUuid[$uuid] = $slugs;
        }

        if ($rows === []) {
            return 0;
        }

        // local_ok / local_checked_at / listeners / listener_peak / probed_at /
        // probe_ok are deliberately absent: they are locally observed and must
        // survive a re-import.
        Radio::upsert($rows, uniqueBy: ['station_uuid'], update: [
            'title', 'artist', 'genre', 'image', 'url', 'homepage', 'country',
            'country_code', 'language', 'codec', 'bitrate', 'votes',
            'click_count', 'source', 'is_alive', 'checked_at',
        ]);

        $this->syncGenres(array_keys($genresByUuid), $genresByUuid, $genreIds);

        return count($rows);
    }

    /**
     * @param  list<string>  $uuids
     * @param  array<string, list<string>>  $genresByUuid
     * @param  array<string, int>  $genreIds
     */
    private function syncGenres(array $uuids, array $genresByUuid, array $genreIds): void
    {
        $radioIds = Radio::whereIn('station_uuid', $uuids)->pluck('id', 'station_uuid');

        // Drop this page's existing pivot rows so a station whose tags changed
        // does not accumulate stale genres.
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

    private function clean(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}
```

- [ ] **Step 4: Run the tests**

```bash
$PHP vendor/bin/pest tests/Feature/RadioImport
```

Expected: PASS, 22 tests.

- [ ] **Step 5: Run the whole suite**

```bash
$PHP vendor/bin/pest
```

Expected: PASS, 52 tests.

- [ ] **Step 6: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add app/Console/Commands/ImportRadios.php tests/Feature
git commit -m "feat: import the radio browser catalogue"
```

---

### Task 4: radios:probe

**Files:**
- Create: `app/Support/IcecastStatus.php`, `app/Console/Commands/ProbeRadios.php`
- Test: `tests/Unit/IcecastStatusTest.php`, `tests/Feature/RadioProbeTest.php`

**Interfaces:**
- Consumes: `App\Models\Radio`, `App\Models\Genre` from Task 1.
- Produces: `App\Support\IcecastStatus` with `public readonly ?int $listeners`, `public readonly ?int $peak`, and `static fromPayload(array $payload, ?string $mount = null): ?self`; the `radios:probe` command.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/IcecastStatusTest.php`:

```php
<?php

use App\Support\IcecastStatus;

it('reads listeners from a single-mount payload', function () {
    $status = IcecastStatus::fromPayload([
        'icestats' => ['source' => ['listeners' => 4982, 'listener_peak' => 4998]],
    ]);

    expect($status->listeners)->toBe(4982)->and($status->peak)->toBe(4998);
});

it('reads listeners from a multi-mount payload', function () {
    $status = IcecastStatus::fromPayload([
        'icestats' => ['source' => [
            ['listenurl' => 'http://host/one', 'listeners' => 5],
            ['listenurl' => 'http://host/two', 'listeners' => 9],
        ]],
    ]);

    expect($status->listeners)->toBe(5);
});

it('picks the mount matching the station url', function () {
    $status = IcecastStatus::fromPayload([
        'icestats' => ['source' => [
            ['listenurl' => 'http://host/one', 'listeners' => 5],
            ['listenurl' => 'http://host/two', 'listeners' => 9],
        ]],
    ], '/two');

    expect($status->listeners)->toBe(9);
});

it('returns null when the payload is not icecast', function () {
    expect(IcecastStatus::fromPayload(['hello' => 'world']))->toBeNull()
        ->and(IcecastStatus::fromPayload([]))->toBeNull();
});

it('tolerates a missing peak', function () {
    $status = IcecastStatus::fromPayload(['icestats' => ['source' => ['listeners' => 3]]]);

    expect($status->listeners)->toBe(3)->and($status->peak)->toBeNull();
});

it('ignores a non-numeric listener count', function () {
    $status = IcecastStatus::fromPayload(['icestats' => ['source' => ['listeners' => 'lots']]]);

    expect($status->listeners)->toBeNull();
});
```

Create `tests/Feature/RadioProbeTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\GenreSeeder;
use Database\Seeders\RadioSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(GenreSeeder::class);
    $this->seed(RadioSeeder::class);
});

it('records listeners from an icecast endpoint', function () {
    Http::fake(['*status-json.xsl' => Http::response([
        'icestats' => ['source' => ['listeners' => 4982, 'listener_peak' => 4998]],
    ])]);

    $this->artisan('radios:probe', ['--limit' => 1])->assertSuccessful();

    $probed = Radio::whereNotNull('probed_at')->firstOrFail();
    expect($probed->listeners)->toBe(4982)
        ->and($probed->listener_peak)->toBe(4998)
        ->and($probed->probe_ok)->toBeTrue();
});

it('marks a station reachable but listener-less when the status is not icecast', function () {
    Http::fake([
        '*status-json.xsl' => Http::response('<html>nope</html>'),
        '*' => Http::response('', 200),
    ]);

    $this->artisan('radios:probe', ['--limit' => 1])->assertSuccessful();

    $probed = Radio::whereNotNull('probed_at')->firstOrFail();
    expect($probed->probe_ok)->toBeTrue()->and($probed->listeners)->toBeNull();
});

it('marks an unreachable station as failed rather than erroring', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->artisan('radios:probe', ['--limit' => 1])->assertSuccessful();

    expect(Radio::whereNotNull('probed_at')->firstOrFail()->probe_ok)->toBeFalse();
});

it('never modifies is_alive, which belongs to radio browser', function () {
    Radio::query()->update(['is_alive' => true]);
    Http::fake(fn () => throw new ConnectionException('timed out'));

    $this->artisan('radios:probe', ['--limit' => 1]);

    expect(Radio::where('is_alive', false)->count())->toBe(0);
});

it('probes only favourites when given no selector', function () {
    Radio::factory()->create(['source' => 'radiobrowser', 'is_favourite' => false, 'station_uuid' => 'x']);
    Http::fake(['*' => Http::response(['icestats' => ['source' => ['listeners' => 1]]])]);

    $this->artisan('radios:probe')->assertSuccessful();

    expect(Radio::whereNotNull('probed_at')->where('is_favourite', false)->count())->toBe(0)
        ->and(Radio::whereNotNull('probed_at')->count())->toBe(20);
});

it('honours the limit', function () {
    Http::fake(['*' => Http::response(['icestats' => ['source' => ['listeners' => 1]]])]);

    $this->artisan('radios:probe', ['--limit' => 3]);

    expect(Radio::whereNotNull('probed_at')->count())->toBe(3);
});

it('can target a country', function () {
    Radio::factory()->create(['source' => 'radiobrowser', 'station_uuid' => 'ro-1', 'country_code' => 'RO']);
    Http::fake(['*' => Http::response(['icestats' => ['source' => ['listeners' => 7]]])]);

    $this->artisan('radios:probe', ['--country' => 'RO']);

    expect(Radio::whereNotNull('probed_at')->count())->toBe(1)
        ->and(Radio::where('station_uuid', 'ro-1')->value('listeners'))->toBe(7);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
$PHP vendor/bin/pest tests/Unit/IcecastStatusTest.php tests/Feature/RadioProbeTest.php
```

Expected: FAIL — `Class "App\Support\IcecastStatus" not found`.

- [ ] **Step 3: Implement the status parser**

`app/Support/IcecastStatus.php`:

```php
<?php

namespace App\Support;

class IcecastStatus
{
    public function __construct(
        public readonly ?int $listeners,
        public readonly ?int $peak,
    ) {}

    /**
     * Parse an Icecast /status-json.xsl payload.
     *
     * `icestats.source` is an object for a single-mount server and a list for a
     * multi-mount one, so both shapes have to be handled.
     *
     * @param  array<string, mixed>  $payload
     * @param  string|null  $mount  path of the station's stream, used to pick a mount
     */
    public static function fromPayload(array $payload, ?string $mount = null): ?self
    {
        $source = data_get($payload, 'icestats.source');

        if (is_array($source) && array_is_list($source)) {
            $source = self::pickMount($source, $mount);
        }

        if (! is_array($source) || $source === []) {
            return null;
        }

        $listeners = $source['listeners'] ?? null;
        $peak = $source['listener_peak'] ?? null;

        return new self(
            is_numeric($listeners) ? (int) $listeners : null,
            is_numeric($peak) ? (int) $peak : null,
        );
    }

    /**
     * @param  list<mixed>  $mounts
     * @return array<string, mixed>|null
     */
    private static function pickMount(array $mounts, ?string $mount): ?array
    {
        if ($mount !== null && $mount !== '') {
            foreach ($mounts as $candidate) {
                if (is_array($candidate) && str_ends_with((string) ($candidate['listenurl'] ?? ''), $mount)) {
                    return $candidate;
                }
            }
        }

        $first = $mounts[0] ?? null;

        return is_array($first) ? $first : null;
    }
}
```

- [ ] **Step 4: Implement the probe command**

`app/Console/Commands/ProbeRadios.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Radio;
use App\Support\IcecastStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;

class ProbeRadios extends Command
{
    protected $signature = 'radios:probe
        {--limit= : Maximum number of stations to probe}
        {--country= : Probe stations from this ISO country code}
        {--genre= : Probe stations in this genre slug}
        {--favourites : Probe the favourites (the default when nothing else is given)}
        {--timeout=8 : Seconds to wait for each stream}';

    protected $description = 'Probe stream servers for liveness and listener counts';

    public function handle(): int
    {
        $stations = $this->target()->get();

        if ($stations->isEmpty()) {
            $this->warn('No stations matched.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($stations->count());
        $withListeners = 0;

        foreach ($stations as $station) {
            $status = $this->probe($station);

            $station->forceFill([
                'probed_at' => now(),
                'probe_ok' => $status !== false,
                'listeners' => $status instanceof IcecastStatus ? $status->listeners : $station->listeners,
                'listener_peak' => $status instanceof IcecastStatus ? $status->peak : $station->listener_peak,
            ])->save();

            if ($status instanceof IcecastStatus && $status->listeners !== null) {
                $withListeners++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Probed {$stations->count()} stations; {$withListeners} reported listener counts.");

        return self::SUCCESS;
    }

    /** @return Builder<Radio> */
    private function target(): Builder
    {
        $query = Radio::query();
        $narrowed = false;

        if ($country = $this->option('country')) {
            $query->where('country_code', strtoupper($country));
            $narrowed = true;
        }

        if ($genre = $this->option('genre')) {
            $query->whereHas('genres', fn (Builder $q) => $q->where('slug', $genre));
            $narrowed = true;
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
            $narrowed = true;
        }

        // Probing the whole catalogue would be ~51,600 requests to third-party
        // servers. Without an explicit selector, stay with the favourites.
        if ($this->option('favourites') || ! $narrowed) {
            $query->where('is_favourite', true);
        }

        return $query;
    }

    /** @return IcecastStatus|true|false parsed status, bare reachability, or failure */
    private function probe(Radio $station): IcecastStatus|bool
    {
        $parts = parse_url($station->url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $base = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $timeout = (int) $this->option('timeout');

        try {
            $response = Http::timeout($timeout)->get($base.'/status-json.xsl');

            if ($response->successful() && is_array($response->json())) {
                $status = IcecastStatus::fromPayload($response->json(), $parts['path'] ?? null);

                if ($status !== null) {
                    return $status;
                }
            }
        } catch (\Throwable) {
            // Fall through to the plain reachability check below.
        }

        try {
            return Http::timeout($timeout)->get($station->url)->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
```

- [ ] **Step 5: Run the tests**

```bash
$PHP vendor/bin/pest tests/Unit/IcecastStatusTest.php tests/Feature/RadioProbeTest.php
```

Expected: PASS, 13 tests.

- [ ] **Step 6: Run the whole suite**

```bash
$PHP vendor/bin/pest
```

Expected: PASS, 65 tests.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add app/Support/IcecastStatus.php app/Console/Commands/ProbeRadios.php tests
git commit -m "feat: probe streams for liveness and listener counts"
```

---

### Task 5: Live import and documentation

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: both commands from Tasks 3 and 4.
- Produces: nothing other work depends on.

- [ ] **Step 1: Trial import against the live API**

This is the first real network call in the plan. Start small.

```bash
$PHP artisan radios:import --limit=50
$PHP artisan tinker --execute="echo App\Models\Radio::where('source','radiobrowser')->count();"
```

Expected: `50`. If the count is lower, some stations were skipped for having no URL — check and report the number.

- [ ] **Step 2: Verify genre mapping quality on real data**

```bash
$PHP artisan tinker --execute="App\Models\Genre::withCount('radios')->orderByDesc('radios_count')->get()->each(fn(\$g) => print(\$g->name.': '.\$g->radios_count.PHP_EOL));"
```

Read the output. If `Other` dominates overwhelmingly (say above 60%), the taxonomy is missing common tags — report the top unmapped tags rather than silently accepting it:

```bash
$PHP artisan tinker --execute="echo App\Models\Radio::where('genre','Other')->inRandomOrder()->limit(20)->pluck('title')->implode(', ');"
```

- [ ] **Step 3: Full import**

```bash
$PHP artisan radios:import
```

This makes ~52 requests and takes several minutes. Report the final count.

```bash
$PHP artisan tinker --execute="echo App\Models\Radio::count().' total, '.App\Models\Radio::where('is_favourite',true)->count().' favourites';"
```

Expected: roughly 51,000+ total, exactly 20 favourites.

- [ ] **Step 4: Probe the favourites**

```bash
$PHP artisan radios:probe
```

Expected: 20 stations probed, a handful reporting listener counts (only Icecast servers with public status do). Report how many.

- [ ] **Step 5: Confirm the app still works**

```bash
$PHP vendor/bin/pest
$PHP -S 127.0.0.1:8123 -t public
```

Curl the homepage. It must still render only the 20 favourites — this spec changes no UI. Confirm the page weight has not exploded. Stop the server.

- [ ] **Step 6: Document the commands**

Add a "Radio catalogue" section to `README.md` covering: what `radios:import` does and its flags; that a full import is ~52 requests and several minutes; that `--fresh` never touches the seeded favourites; what `radios:probe` does, that it defaults to favourites only, and why a full-catalogue sweep is not offered; and that listener counts are available only from Icecast servers that publish a status endpoint, so most stations will have none.

- [ ] **Step 7: Commit**

```bash
git add README.md
git commit -m "docs: document the catalogue import and probe commands"
```

---

## Self-Review Notes

**Spec coverage.** Schema → Task 1. Genre mapping → Tasks 1 (config) and 2 (algorithm). Import command, field mapping, paging, `--fresh` safety → Task 3. Popularity columns → Task 1 (`votes`, `click_count` imported in Task 3). Probe command and listener counts → Task 4. Seeder changes → Task 1. Every test named in the spec appears in a task, with `RadioImportPagingTest` also covering the stable-ordering and User-Agent requirements.

**Known risks carried into execution.** Task 3's tests define helper functions (`station()`, `page()`, `fakePages()`) in one Pest file and use them from another — Pest loads all test files, so this works, but if the executor hits "function already declared" they should move the helpers to `tests/Pest.php`. Task 5 is the only task that touches the network; everything before it is fully faked.

**Interface consistency.** `GenreMapper::map()` returns slugs, and `ImportRadios` looks the label up via `config("radio-genres.genres.{$slug}.label")` — the slug is the key in both places. `IcecastStatus::fromPayload()` returns `?self`, and `ProbeRadios::probe()` widens that to `IcecastStatus|bool` so the caller can distinguish "reachable, no listener data" (`true`) from "unreachable" (`false`).
