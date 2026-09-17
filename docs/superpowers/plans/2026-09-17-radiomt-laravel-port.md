# RadioMT → Laravel Port Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reproduce the RadioMT React SPA inside this Laravel 13 application as a database-backed library of 20 radio stations, with identical behaviour and appearance.

**Architecture:** Stations move from a hardcoded JS array into a `radios` MySQL table seeded with the original data. A single `RadioController` renders one Blade page and exposes a JSON endpoint; a `BackgroundController` proxies the Unsplash API. The frontend is rewritten from React/TypeScript to Blade partials driven by two Alpine.js components, styled with Tailwind 4 utilities over a CSS-custom-property theme system that preserves the original's four themes exactly.

**Tech Stack:** PHP 8.4.12, Laravel 13.32, Pest 5, MySQL 8.4 (dev) / in-memory SQLite (tests), Vite 8, Tailwind 4, Alpine.js 3, tsParticles 3.

**Spec:** `docs/superpowers/specs/2026-09-17-radiomt-laravel-port-design.md`

## Global Constraints

- **PHP invocation.** The `php` on PATH is herd-lite **8.4.0**, which cannot run this app — Laravel 13's Symfony 8 and PHPUnit 13 dependencies require `>= 8.4.1`. Every command below assumes:

  ```bash
  PHP="/c/laragon/bin/php/php-8.4.12-nts-Win32-vs17-x64/php.exe -c php.ini"
  ```

  `php.ini` lives at the project root, is gitignored, and enables `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `pdo_sqlite`, `mysqli`, `sqlite3`, `zip` — that build loads none by default. If PATH PHP is upgraded to ≥ 8.4.1, use `PHP=php` and delete the ini.
- **Development database:** MySQL `radio` on `127.0.0.1:3306`, user `root`, empty password. Already migrated with Laravel's base tables.
- **Test database:** in-memory SQLite, pinned by `phpunit.xml`. Tests never touch MySQL.
- **Station count is exactly 20**, ids `1–6` and `8–21`. **`id: 7` does not exist** and must not be created.
- **Never fetch a station stream or artwork URL in a test.** They are third-party hosts.
- **Background categories:** exactly `mountain`, `beach`, `sky`, `forest`, `cozy`, `japan`, `cat`, `dog`.
- **Themes:** exactly `light`, `dark`, `crimson`, `blue`. Default `blue`.
- Run `$PHP vendor/bin/pint --dirty` before each commit.

## File Structure

**Backend**

| File | Responsibility |
|---|---|
| `database/migrations/2026_09_17_000001_create_radios_table.php` | `radios` schema |
| `app/Models/Radio.php` | Station model |
| `database/factories/RadioFactory.php` | Test fixtures |
| `database/seeders/RadioSeeder.php` | The 20 original stations |
| `app/Http/Controllers/RadioController.php` | Page + JSON list |
| `app/Http/Controllers/BackgroundController.php` | Unsplash proxy with fallback |
| `config/services.php` | Unsplash credentials (modify) |
| `routes/web.php` | Three routes (modify) |

**Views**

| File | Responsibility |
|---|---|
| `resources/views/radio/index.blade.php` | Page shell, theme no-flash script |
| `resources/views/radio/partials/background.blade.php` | Background layer + particles mount |
| `resources/views/radio/partials/player.blade.php` | Artwork, title, genre, transport, volume |
| `resources/views/radio/partials/radio-list.blade.php` | Station grid |
| `resources/views/radio/partials/settings.blade.php` | Gear + slide-in panel |
| `resources/views/radio/partials/theme-menu.blade.php` | 4 theme buttons |
| `resources/views/radio/partials/background-menu.blade.php` | 8 category buttons |
| `resources/views/radio/partials/footer.blade.php` | Credit + socials |
| `resources/views/components/icon/*.blade.php` | 8 inline SVG icons |

**Frontend assets**

| File | Responsibility |
|---|---|
| `resources/css/app.css` | Tailwind import, theme tokens, `[data-theme]` blocks (modify) |
| `resources/js/app.js` | Alpine bootstrap (modify) |
| `resources/js/radio/player.js` | Playback state machine |
| `resources/js/radio/settings.js` | Menu, theme, background |
| `resources/js/radio/particles.js` | tsParticles init |

**Tests**

| File | Covers |
|---|---|
| `tests/Feature/RadioLibraryTest.php` | Migration, model, seeder |
| `tests/Feature/RadioPageTest.php` | `GET /` |
| `tests/Feature/RadioApiTest.php` | `GET /api/radios` |
| `tests/Feature/BackgroundApiTest.php` | `GET /api/background` |

---

### Task 1: Radio library data layer

**Files:**
- Create: `database/migrations/2026_09_17_000001_create_radios_table.php`
- Create: `app/Models/Radio.php`
- Create: `database/factories/RadioFactory.php`
- Create: `database/seeders/RadioSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `tests/Pest.php` (enable `RefreshDatabase`)
- Test: `tests/Feature/RadioLibraryTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `App\Models\Radio` with string attributes `title`, `artist`, `genre`, `image`, `url` and integer `id`. `Database\Seeders\RadioSeeder::run(): void`, idempotent. `Database\Factories\RadioFactory`.

- [ ] **Step 1: Enable `RefreshDatabase` for feature tests**

In `tests/Pest.php`, uncomment the trait:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/RadioLibraryTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\RadioSeeder;

it('seeds exactly twenty stations', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::count())->toBe(20);
});

it('preserves the original ids, including the gap at seven', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::orderBy('id')->pluck('id')->all())
        ->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21]);
});

it('can be seeded twice without duplicating stations', function () {
    $this->seed(RadioSeeder::class);
    $this->seed(RadioSeeder::class);

    expect(Radio::count())->toBe(20);
});

it('stores every field of a station', function () {
    $this->seed(RadioSeeder::class);

    $station = Radio::find(1);

    expect($station->title)->toBe('Virgin FM')
        ->and($station->artist)->toBe('Pop')
        ->and($station->genre)->toBe('Pop')
        ->and($station->url)->toBe('https://radio.virginradio.co.uk/stream')
        ->and($station->image)->toStartWith('https://');
});

it('orders stations by id to match the original array order', function () {
    $this->seed(RadioSeeder::class);

    expect(Radio::orderBy('id')->first()->title)->toBe('Virgin FM')
        ->and(Radio::orderBy('id')->get()->last()->title)->toBe('BOX : K-POP');
});
```

- [ ] **Step 3: Run the test to verify it fails**

```bash
$PHP vendor/bin/pest tests/Feature/RadioLibraryTest.php
```

Expected: FAIL — `Class "App\Models\Radio" not found`.

- [ ] **Step 4: Create the migration**

`database/migrations/2026_09_17_000001_create_radios_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radios', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist');
            $table->string('genre');
            $table->string('image', 2048);
            $table->string('url', 2048);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radios');
    }
};
```

`image` and `url` are 2048 chars because the Virgin FM artwork URL is a 150-character Google CDN token. `timestamps()` creates nullable columns, which matters because `upsert()` in Step 6 does not populate them.

- [ ] **Step 5: Create the model and factory**

`app/Models/Radio.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Radio extends Model
{
    /** @use HasFactory<\Database\Factories\RadioFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'artist',
        'genre',
        'image',
        'url',
    ];
}
```

`database/factories/RadioFactory.php`:

```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Radio> */
class RadioFactory extends Factory
{
    public function definition(): array
    {
        $genre = fake()->randomElement(['Pop', 'Dance', 'Lofi', 'Deep House', 'J-Pop']);

        return [
            'title' => fake()->unique()->words(2, true),
            'artist' => $genre,
            'genre' => $genre,
            'image' => 'https://example.test/'.fake()->uuid().'.png',
            'url' => 'https://example.test/stream/'.fake()->uuid(),
        ];
    }
}
```

- [ ] **Step 6: Create the seeder**

`database/seeders/RadioSeeder.php`. `upsert()` keyed on `id` makes re-seeding safe — a plain `insert` of fixed primary keys throws on the second run.

```php
<?php

namespace Database\Seeders;

use App\Models\Radio;
use Illuminate\Database\Seeder;

class RadioSeeder extends Seeder
{
    public function run(): void
    {
        Radio::upsert(
            $this->stations(),
            uniqueBy: ['id'],
            update: ['title', 'artist', 'genre', 'image', 'url'],
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function stations(): array
    {
        return [
            ['id' => 1, 'title' => 'Virgin FM', 'artist' => 'Pop', 'genre' => 'Pop', 'image' => 'https://yt3.googleusercontent.com/u5l6CzL3sSRgQJPzNczUX9bvA81HFyIwfdRz-SJR0EQvezpPQAj4J1zmhUK5mH-hEY_Oayg3ecA=s900-c-k-c0x00ffffff-no-rj', 'url' => 'https://radio.virginradio.co.uk/stream'],
            ['id' => 2, 'title' => 'Classic', 'artist' => 'Classical', 'genre' => 'Classical', 'image' => 'https://uk.radio.net/images/broadcasts/79/5a/121368/1/c300.png', 'url' => 'https://media-ssl.musicradio.com/ClassicFM'],
            ['id' => 3, 'title' => 'Heart', 'artist' => 'Dance', 'genre' => 'Dance', 'image' => 'https://static.mytuner.mobi/media/tvos_radios/mmvGSBqcQB.png', 'url' => 'https://media-ssl.musicradio.com/HeartDance'],
            ['id' => 4, 'title' => 'Monte Carlo', 'artist' => 'Deep House', 'genre' => 'Deep House', 'image' => 'https://cdn.onlineradiobox.com/img/l/8/12078.v9.png', 'url' => 'https://edge.singsingmusic.net/MC2.mp3'],
            ['id' => 5, 'title' => 'Capital UK', 'artist' => 'Pop', 'genre' => 'Pop', 'image' => 'https://radio-live-uk.com/assets/image/radio/180/capital-fm.jpg', 'url' => 'https://media-ssl.musicradio.com/CapitalUK'],
            ['id' => 6, 'title' => 'Kiss FM Deep', 'artist' => 'Deep House', 'genre' => 'Deep House', 'image' => 'https://cdn.onlineradiobox.com/img/l/8/5308.v17.png', 'url' => 'https://online.kissfm.ua/KissFM_Deep'],
            ['id' => 8, 'title' => 'Lofi Radio', 'artist' => 'Lofi', 'genre' => 'Lofi', 'image' => 'https://cdn.onlineradiobox.com/img/l/8/97478.v9.png', 'url' => 'https://play.streamafrica.net/lofiradio'],
            ['id' => 9, 'title' => 'Smooth Radio', 'artist' => 'Smooth', 'genre' => 'Smooth', 'image' => 'https://cdn.onlineradiobox.com/img/l/4/964.v11.png', 'url' => 'https://media-ssl.musicradio.com/SmoothLondonMP3'],
            ['id' => 10, 'title' => 'Vanilla Radio', 'artist' => 'Deep House', 'genre' => 'Deep House', 'image' => 'https://cdn.onlineradiobox.com/img/l/5/18395.v18.png', 'url' => 'https://stream.vanillaradio.com:8012/live'],
            ['id' => 11, 'title' => 'Music Factory', 'artist' => 'Deep House', 'genre' => 'Deep House', 'image' => 'https://cdn.onlineradiobox.com/img/l/8/16548.v12.png', 'url' => 'https://i4.streams.ovh/sc/musicfactory/stream'],
            ['id' => 12, 'title' => 'TRANCE IS STAR RADIO', 'artist' => 'Trace', 'genre' => 'Trace', 'image' => 'https://cdn.onlineradiobox.com/img/l/4/184.v11.png', 'url' => 'https://myradio24.org/tisradio'],
            ['id' => 13, 'title' => 'Athens Up Radio', 'artist' => 'Deep House', 'genre' => 'Deep House', 'image' => 'https://cdn.onlineradiobox.com/img/l/9/72699.v21.png', 'url' => 'https://stream.radiojar.com/9ndpdg3c0s8uv'],
            ['id' => 14, 'title' => 'Kiss FM', 'artist' => 'Dance', 'genre' => 'Dance', 'image' => 'https://cdn.onlineradiobox.com/img/l/1/1.v52.png', 'url' => 'https://online.kissfm.ua/KissFM'],
            ['id' => 15, 'title' => 'One FM Romania', 'artist' => 'Dance', 'genre' => 'Dance', 'image' => 'https://cdn.onlineradiobox.com/img/l/1/64941.v8.png', 'url' => 'https://live.onefm.ro/onefm.aacp'],
            ['id' => 16, 'title' => 'Japan Hits', 'artist' => 'J-Pop', 'genre' => 'J-Pop', 'image' => 'https://cdn.onlineradiobox.com/img/l/0/64360.v9.png', 'url' => 'https://igor.torontocast.com/JapanHits'],
            ['id' => 17, 'title' => 'J-Pop Powerplay', 'artist' => 'J-Pop', 'genre' => 'J-Pop', 'image' => 'https://cdn.onlineradiobox.com/img/l/0/64360.v9.png', 'url' => 'https://kathy.torontocast.com:3560/;'],
            ['id' => 18, 'title' => 'J-Pop Powerplay Kawaii', 'artist' => 'J-Pop - Anime', 'genre' => 'J-Pop - Anime', 'image' => 'https://cdn.onlineradiobox.com/img/l/9/13379.v11.png', 'url' => 'https://kathy.torontocast.com:3060/;'],
            ['id' => 19, 'title' => 'J-Rock Powerplay - Rock', 'artist' => 'J-Rock ', 'genre' => 'J-Rock', 'image' => 'https://cdn.onlineradiobox.com/img/l/3/64373.v11.png', 'url' => 'https://kathy.torontocast.com:3340/;'],
            ['id' => 20, 'title' => 'Southsound Kpop', 'artist' => 'K-Pop', 'genre' => 'K-Pop', 'image' => 'https://cdn.onlineradiobox.com/img/l/1/53781.v12.png', 'url' => 'https://c4.radioboss.fm:18071/stream'],
            ['id' => 21, 'title' => 'BOX : K-POP', 'artist' => 'K-Pop', 'genre' => 'K-Pop', 'image' => 'https://cdn.onlineradiobox.com/img/l/9/97489.v8.png', 'url' => 'https://play.streamafrica.net/kpopradio'],
        ];
    }
}
```

Note `'artist' => 'J-Rock '` on id 19 — the trailing space is in the original data and is preserved deliberately.

- [ ] **Step 7: Register the seeder**

In `database/seeders/DatabaseSeeder.php`, replace the body of `run()`:

```php
public function run(): void
{
    $this->call(RadioSeeder::class);
}
```

- [ ] **Step 8: Run the tests to verify they pass**

```bash
$PHP vendor/bin/pest tests/Feature/RadioLibraryTest.php
```

Expected: PASS, 5 tests.

- [ ] **Step 9: Migrate and seed the development database**

```bash
$PHP artisan migrate
$PHP artisan db:seed --class=RadioSeeder
$PHP artisan tinker --execute="echo App\Models\Radio::count();"
```

Expected: `20`.

- [ ] **Step 10: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add database app/Models/Radio.php tests/Pest.php tests/Feature/RadioLibraryTest.php
git commit -m "feat: add radio library model, migration and seeder"
```

---

### Task 2: Radio page route and JSON endpoint

**Files:**
- Create: `app/Http/Controllers/RadioController.php`
- Create: `resources/views/radio/index.blade.php`
- Modify: `routes/web.php`
- Delete: `resources/views/welcome.blade.php`
- Test: `tests/Feature/RadioPageTest.php`, `tests/Feature/RadioApiTest.php`

**Interfaces:**
- Consumes: `App\Models\Radio`, `Database\Seeders\RadioSeeder` from Task 1.
- Produces: route names `radio.index`, `radio.list`. The `radio.index` view receives `$radios` as `Collection<Radio>` ordered by `id`, each carrying only `id, title, artist, genre, image, url`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/RadioPageTest.php`:

```php
<?php

use App\Models\Radio;
use Database\Seeders\RadioSeeder;

it('serves the radio app at the root url', function () {
    $this->seed(RadioSeeder::class);

    $this->get('/')
        ->assertOk()
        ->assertViewIs('radio.index')
        ->assertViewHas('radios');
});

it('passes every seeded station to the view', function () {
    $this->seed(RadioSeeder::class);

    expect($this->get('/')->viewData('radios'))->toHaveCount(20);
});

it('renders the station titles into the page', function () {
    $this->seed(RadioSeeder::class);

    $response = $this->get('/');

    foreach (Radio::pluck('title') as $title) {
        $response->assertSee($title, escape: true);
    }
});

it('renders without stations rather than erroring', function () {
    $this->get('/')->assertOk();
});
```

Create `tests/Feature/RadioApiTest.php`:

```php
<?php

use Database\Seeders\RadioSeeder;

it('lists every station as json', function () {
    $this->seed(RadioSeeder::class);

    $this->getJson('/api/radios')->assertOk()->assertJsonCount(20);
});

it('exposes exactly the station fields the player needs', function () {
    $this->seed(RadioSeeder::class);

    $this->getJson('/api/radios')->assertJsonStructure([
        '*' => ['id', 'title', 'artist', 'genre', 'image', 'url'],
    ]);
});

it('returns stations ordered by id', function () {
    $this->seed(RadioSeeder::class);

    $ids = collect($this->getJson('/api/radios')->json())->pluck('id')->all();

    expect($ids)->toBe([1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21]);
});

it('returns an empty array when the library is empty', function () {
    $this->getJson('/api/radios')->assertOk()->assertExactJson([]);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
$PHP vendor/bin/pest tests/Feature/RadioPageTest.php tests/Feature/RadioApiTest.php
```

Expected: FAIL — root still returns the `welcome` view, `/api/radios` 404s.

- [ ] **Step 3: Create the controller**

`app/Http/Controllers/RadioController.php`:

```php
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
```

Selecting explicit columns keeps `created_at`/`updated_at` out of the JSON, which is what the structure assertion pins.

- [ ] **Step 4: Register the routes**

Replace `routes/web.php` entirely. `BackgroundController` arrives in Task 3; register its route now so the file is written once.

```php
<?php

use App\Http\Controllers\BackgroundController;
use App\Http\Controllers\RadioController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RadioController::class, 'index'])->name('radio.index');
Route::get('/api/radios', [RadioController::class, 'list'])->name('radio.list');
Route::get('/api/background', BackgroundController::class)->name('radio.background');
```

If route registration fails because `BackgroundController` does not exist yet, create the class stub from Task 3 Step 4 first, then return here.

- [ ] **Step 5: Create the placeholder view**

`resources/views/radio/index.blade.php` — the shell later tasks fill in:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MT Radio</title>
</head>
<body>
    <ul>
        @foreach ($radios as $radio)
            <li>{{ $radio->title }}</li>
        @endforeach
    </ul>
</body>
</html>
```

- [ ] **Step 6: Delete the default welcome view**

```bash
git rm resources/views/welcome.blade.php
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
$PHP vendor/bin/pest tests/Feature/RadioPageTest.php tests/Feature/RadioApiTest.php
```

Expected: PASS, 8 tests.

- [ ] **Step 8: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add app/Http/Controllers/RadioController.php routes/web.php resources/views tests/Feature
git commit -m "feat: serve the radio library from the root route and a json endpoint"
```

---

### Task 3: Unsplash background endpoint

**Files:**
- Create: `app/Http/Controllers/BackgroundController.php`
- Create: `public/images/background.jpg` (copied from the original repo)
- Modify: `config/services.php`, `.env`, `.env.example`
- Test: `tests/Feature/BackgroundApiTest.php`

**Interfaces:**
- Consumes: the `radio.background` route from Task 2.
- Produces: `GET /api/background?query=<category>` → `200 {"url": "<absolute url>"}`, or `422` for an unknown category. `BackgroundController::CATEGORIES` is the canonical category list, reused by the Blade menu in Task 9.

- [ ] **Step 1: Copy the fallback image**

```bash
mkdir -p public/images
cp "../RadioMT/src/assets/images/bgImage1.jpg" public/images/background.jpg
```

- [ ] **Step 2: Add the Unsplash config**

Append to the array in `config/services.php`:

```php
'unsplash' => [
    'access_key' => env('UNSPLASH_ACCESS_KEY'),
],
```

Add to both `.env` and `.env.example`:

```
UNSPLASH_ACCESS_KEY=
```

Leave it empty. The endpoint must work without a key.

- [ ] **Step 3: Write the failing tests**

Create `tests/Feature/BackgroundApiTest.php`:

```php
<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.unsplash.access_key', 'test-key');
    cache()->flush();
});

it('returns the photo url from unsplash', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=mountain')
        ->assertOk()
        ->assertExactJson(['url' => 'https://images.unsplash.test/photo.jpg']);
});

it('sends the category and the client id to unsplash', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=forest');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'query=forest')
        && str_contains($request->url(), 'orientation=landscape')
        && $request->hasHeader('Authorization', 'Client-ID test-key'));
});

it('falls back to the local image when unsplash fails', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(status: 503)]);

    $this->getJson('/api/background?query=beach')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);
});

it('falls back to the local image when the response has no photo url', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(['urls' => []])]);

    $this->getJson('/api/background?query=sky')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);
});

it('falls back to the local image when unsplash is unreachable', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('timed out'));

    $this->getJson('/api/background?query=cozy')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);
});

it('never calls unsplash when no api key is configured', function () {
    config()->set('services.unsplash.access_key', null);
    Http::fake();

    $this->getJson('/api/background?query=japan')
        ->assertOk()
        ->assertExactJson(['url' => asset('images/background.jpg')]);

    Http::assertNothingSent();
});

it('rejects a category the ui does not offer', function () {
    Http::fake();

    $this->getJson('/api/background?query=../../etc/passwd')->assertStatus(422);

    Http::assertNothingSent();
});

it('defaults to the mountain category', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background')->assertOk();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'query=mountain'));
});

it('caches a successful lookup instead of calling unsplash twice', function () {
    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=cat');
    $this->getJson('/api/background?query=cat');

    Http::assertSentCount(1);
});

it('does not cache a fallback', function () {
    Http::fake(['api.unsplash.com/*' => Http::response(status: 500)]);
    $this->getJson('/api/background?query=dog');

    Http::fake([
        'api.unsplash.com/*' => Http::response(['urls' => ['full' => 'https://images.unsplash.test/photo.jpg']]),
    ]);

    $this->getJson('/api/background?query=dog')
        ->assertExactJson(['url' => 'https://images.unsplash.test/photo.jpg']);
});
```

- [ ] **Step 4: Run the tests to verify they fail**

```bash
$PHP vendor/bin/pest tests/Feature/BackgroundApiTest.php
```

Expected: FAIL — `Class "App\Http\Controllers\BackgroundController" not found`.

- [ ] **Step 5: Create the controller**

`app/Http/Controllers/BackgroundController.php`:

```php
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
```

Only successful lookups are cached. Caching a fallback would pin the local image for an hour after one transient failure.

- [ ] **Step 6: Run the tests to verify they pass**

```bash
$PHP vendor/bin/pest tests/Feature/BackgroundApiTest.php
```

Expected: PASS, 10 tests.

- [ ] **Step 7: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add app/Http/Controllers/BackgroundController.php config/services.php .env.example public/images tests/Feature/BackgroundApiTest.php
git commit -m "feat: proxy unsplash backgrounds with a local fallback"
```

---

### Task 4: Frontend toolchain and theme system

**Files:**
- Modify: `package.json` (via npm), `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`
- Create: `resources/js/radio/particles.js`

**Interfaces:**
- Consumes: nothing.
- Produces: a global `Alpine` instance started in `app.js`; Tailwind colour utilities `bg-app`, `text-app`, `bg-menu`, `bg-menu-hover`, `bg-btn`, `border-btn`, `bg-btn-hover`, `text-player`, `text-icon-hover`, `bg-thumb`, `text-gear`; `initParticles(selector: string): Promise<void>` exported from `particles.js`.

- [ ] **Step 1: Install the runtime dependencies**

```bash
npm install alpinejs tsparticles
```

`tsparticles` v3 is the framework-agnostic engine; the `react-tsparticles` wrapper is not needed.

- [ ] **Step 2: Add the Sono font to the Vite config**

In `vite.config.js`, replace the `bunny('Instrument Sans', ...)` entry with Sono, which is the original's body font (verified available on Bunny Fonts at weights 300–700):

```js
fonts: [
    bunny('Sono', {
        weights: [300, 400, 500, 600, 700],
    }),
],
```

- [ ] **Step 3: Write the theme system**

Replace `resources/css/app.css` entirely. The original indirects every colour through scale variables (`--blue-hue: 220`, `--saturation-50: 50%`, `--light-30: 30%`, `--opacity-9: 0.9`). Those are resolved to literal `hsla()` values here — identical output, far less indirection. Resolution rule: `--<name>-hue` → degrees, `--saturation-N`/`--light-N` → `N%`, `--opacity-N` → `N/10` (so `--opacity-10` is `1`).

```css
@import 'tailwindcss';

@source '../views';
@source '../js';

@theme {
    --font-sans: 'Sono', ui-sans-serif, system-ui, sans-serif;

    /* Defaults match the original :root block, which is the blue-ish base. */
    --color-app: hsla(0, 0%, 0%, 1);
    --color-app-text: hsla(0, 0%, 100%, 1);
    --color-player: hsla(0, 0%, 100%, 1);
    --color-button: hsla(0, 0%, 100%, 1);
    --color-thumb: hsla(220, 50%, 10%, 1);
    --color-icon-hover: hsla(220, 50%, 80%, 1);
    --color-btn: hsla(220, 50%, 30%, 0.9);
    --color-btn-border: hsla(220, 50%, 40%, 0.9);
    --color-btn-hover: hsla(220, 30%, 50%, 0.9);
    --color-menu: hsla(220, 50%, 10%, 0.8);
    --color-menu-hover: hsla(220, 50%, 20%, 0.8);
    --color-gear: hsla(220, 50%, 80%, 1);
}

[data-theme='dark'] {
    --color-app: hsla(0, 0%, 0%, 1);
    --color-app-text: hsla(0, 0%, 100%, 1);
    --color-player: hsla(0, 0%, 100%, 1);
    --color-button: hsla(0, 0%, 100%, 1);
    --color-thumb: hsla(220, 50%, 10%, 1);
    --color-icon-hover: hsla(220, 100%, 40%, 1);
    --color-btn: hsla(220, 50%, 10%, 0.9);
    --color-btn-border: hsla(220, 50%, 80%, 0.9);
    --color-btn-hover: hsla(220, 90%, 20%, 0.9);
    --color-menu: hsla(220, 50%, 10%, 0.8);
    --color-menu-hover: hsla(220, 100%, 40%, 0.8);
    --color-gear: hsla(220, 50%, 80%, 1);
}

[data-theme='crimson'] {
    --color-app: hsla(0, 0%, 0%, 1);
    --color-app-text: hsla(0, 0%, 100%, 1);
    --color-player: hsla(0, 0%, 100%, 1);
    --color-button: hsla(0, 0%, 100%, 1);
    --color-thumb: hsla(0, 50%, 10%, 1);
    --color-icon-hover: hsla(0, 50%, 80%, 1);
    --color-btn: hsla(0, 50%, 30%, 0.9);
    --color-btn-border: hsla(0, 50%, 50%, 0.9);
    --color-btn-hover: hsla(0, 100%, 50%, 0.9);
    --color-menu: hsla(0, 50%, 10%, 0.8);
    --color-menu-hover: hsla(0, 50%, 20%, 0.8);
    --color-gear: hsla(0, 50%, 40%, 1);
}

[data-theme='light'] {
    --color-app: hsla(0, 0%, 100%, 1);
    --color-app-text: hsla(260, 100%, 30%, 1);
    --color-player: hsla(0, 0%, 0%, 1);
    --color-button: hsla(0, 0%, 0%, 1);
    --color-thumb: hsla(0, 100%, 80%, 1);
    --color-icon-hover: hsla(260, 100%, 40%, 1);
    --color-btn: hsla(0, 10%, 100%, 0.9);
    --color-btn-border: hsla(0, 100%, 10%, 0.9);
    --color-btn-hover: hsla(0, 10%, 90%, 0.9);
    --color-menu: hsla(0, 10%, 100%, 0.8);
    --color-menu-hover: hsla(0, 50%, 80%, 0.8);
    --color-gear: hsla(0, 50%, 80%, 1);
}

[data-theme='blue'] {
    --color-app: hsla(0, 0%, 0%, 1);
    --color-app-text: hsla(0, 100%, 99%, 1);
    --color-player: hsla(0, 0%, 100%, 1);
    --color-button: hsla(0, 0%, 100%, 1);
    --color-thumb: hsla(220, 100%, 20%, 1);
    --color-icon-hover: hsla(220, 100%, 50%, 1);
    --color-btn: hsla(220, 50%, 20%, 0.9);
    --color-btn-border: hsla(220, 100%, 40%, 0.9);
    --color-btn-hover: hsla(220, 100%, 50%, 0.9);
    --color-menu: hsla(220, 100%, 20%, 0.8);
    --color-menu-hover: hsla(220, 100%, 40%, 0.8);
    --color-gear: hsla(220, 50%, 90%, 1);
}

/*
 * Two rules resist utilities: the webkit range thumb pseudo-element, and the
 * gear's rotation keyframes.
 */
@layer components {
    .volume-slider {
        -webkit-appearance: none;
        appearance: none;
        width: 100%;
        height: 3px;
        background: var(--color-app-text);
        opacity: 0.9;
    }

    .volume-slider:hover {
        opacity: 1;
    }

    .volume-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: var(--color-thumb);
        border: 1px solid var(--color-app-text);
        cursor: pointer;
    }

    .volume-slider::-moz-range-thumb {
        width: 15px;
        height: 15px;
        border-radius: 50%;
        background: var(--color-thumb);
        border: 1px solid var(--color-app-text);
        cursor: pointer;
    }

    .animate-gear {
        animation: gear-spin 10s linear infinite;
    }
}

@keyframes gear-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
```

The original's `--hover-primary-button-border` is defined only inside `[data-theme='light']` and referenced by no rule; it is dropped.

- [ ] **Step 4: Create the particles module**

`resources/js/radio/particles.js`. The options object is carried over from `background.tsx` unchanged, translated to tsParticles v3 key names (`links` replaces `line_linked`, `outModes` replaces `out_mode`):

```js
import { tsParticles } from 'tsparticles';
import { loadFull } from 'tsparticles';

export async function initParticles(selector) {
    await loadFull(tsParticles);

    await tsParticles.load({
        id: selector,
        options: {
            particles: {
                number: { value: 50, density: { enable: false, area: 800 } },
                color: { value: '#fff' },
                shape: { type: 'star' },
                opacity: { value: 0.8 },
                size: { value: 4 },
                rotate: {
                    value: 0,
                    random: true,
                    direction: 'clockwise',
                    animation: { enable: true, speed: 5, sync: false },
                },
                links: { enable: false },
                move: {
                    enable: true,
                    speed: 2,
                    direction: 'none',
                    random: false,
                    straight: false,
                    outModes: { default: 'out' },
                },
            },
            detectRetina: true,
            fullScreen: { enable: false },
        },
    });
}
```

`fullScreen.enable: false` keeps the canvas inside the background element rather than covering the page, which is what the original's wrapping `div.background` achieved.

- [ ] **Step 5: Bootstrap Alpine**

Replace `resources/js/app.js`:

```js
import Alpine from 'alpinejs';
import { initParticles } from './radio/particles';

window.Alpine = Alpine;

Alpine.start();

initParticles('tsparticles');
```

Later tasks register Alpine components here, before `Alpine.start()`.

- [ ] **Step 6: Verify the build**

```bash
npm run build
```

Expected: Vite completes with no errors and emits `public/build/manifest.json`.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json vite.config.js resources/css/app.css resources/js
git commit -m "feat: add alpine, tsparticles and the four-theme colour system"
```

---

### Task 5: Inline SVG icon components

**Files:**
- Create: `resources/views/components/icon/play.blade.php`, `pause.blade.php`, `forward.blade.php`, `backward.blade.php`, `shuffle.blade.php`, `gear.blade.php`, `github.blade.php`, `linkedin.blade.php`

**Interfaces:**
- Consumes: nothing.
- Produces: eight anonymous Blade components usable as `<x-icon.play class="..." />`. Each renders a `<svg>` with `fill="currentColor"` and forwards arbitrary attributes via `{{ $attributes }}`.

- [ ] **Step 1: Create the components**

Each file follows the same shape. The paths are the Font Awesome 6 Free Solid / Brands glyphs the original imported, with their native viewBoxes.

`resources/views/components/icon/play.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor" aria-hidden="true">
    <path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z"/>
</svg>
```

`pause.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512" fill="currentColor" aria-hidden="true">
    <path d="M48 64C21.5 64 0 85.5 0 112L0 400c0 26.5 21.5 48 48 48l32 0c26.5 0 48-21.5 48-48l0-288c0-26.5-21.5-48-48-48L48 64zm192 0c-26.5 0-48 21.5-48 48l0 288c0 26.5 21.5 48 48 48l32 0c26.5 0 48-21.5 48-48l0-288c0-26.5-21.5-48-48-48l-32 0z"/>
</svg>
```

`forward.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
    <path d="M52.5 440.6c-9.5 7.9-22.8 9.7-34.1 4.4S0 428.4 0 416L0 96C0 83.6 7.2 72.3 18.4 67s24.5-3.6 34.1 4.4l192 160L256 241l0-145c0-12.4 7.2-23.7 18.4-29s24.5-3.6 34.1 4.4l192 160c7.3 6.1 11.5 15.1 11.5 24.6s-4.2 18.5-11.5 24.6l-192 160c-9.5 7.9-22.8 9.7-34.1 4.4s-18.4-16.6-18.4-29l0-145-11.5 9.6-192 160z"/>
</svg>
```

`backward.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
    <path d="M459.5 440.6c9.5 7.9 22.8 9.7 34.1 4.4s18.4-16.6 18.4-29L512 96c0-12.4-7.2-23.7-18.4-29s-24.5-3.6-34.1 4.4l-192 160L256 241l0-145c0-12.4-7.2-23.7-18.4-29s-24.5-3.6-34.1 4.4l-192 160C4.2 237.5 0 246.5 0 256s4.2 18.5 11.5 24.6l192 160c9.5 7.9 22.8 9.7 34.1 4.4s18.4-16.6 18.4-29l0-145 11.5 9.6 192 160z"/>
</svg>
```

`shuffle.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
    <path d="M403.8 34.4c12-5 25.7-2.2 34.9 6.9l64 64c6 6 9.4 14.1 9.4 22.6s-3.4 16.6-9.4 22.6l-64 64c-9.2 9.2-22.9 11.9-34.9 6.9s-19.8-16.6-19.8-29.6l0-32-32 0c-10.1 0-19.6 4.7-25.6 12.8L284 229.3 244 176l31.2-41.6C293.3 110.2 321.8 96 352 96l32 0 0-32c0-12.9 7.8-24.6 19.8-29.6zM164 282.7L204 336l-31.2 41.6C154.7 401.8 126.2 416 96 416l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l64 0c10.1 0 19.6-4.7 25.6-12.8L164 282.7zm274.6 41.6l64 64c6 6 9.4 14.1 9.4 22.6s-3.4 16.6-9.4 22.6l-64 64c-9.2 9.2-22.9 11.9-34.9 6.9s-19.8-16.6-19.8-29.6l0-32-32 0c-30.2 0-58.7-14.2-76.8-38.4L121.6 172.8c-6-8.1-15.5-12.8-25.6-12.8l-64 0c-17.7 0-32-14.3-32-32s14.3-32 32-32l64 0c30.2 0 58.7 14.2 76.8 38.4L326.4 339.2c6 8.1 15.5 12.8 25.6 12.8l32 0 0-32c0-12.9 7.8-24.6 19.8-29.6s25.7-2.2 34.9 6.9z"/>
</svg>
```

`gear.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">
    <path d="M495.9 166.6c3.2 8.7 .5 18.4-6.4 24.6l-43.3 39.4c1.1 8.3 1.7 16.8 1.7 25.4s-.6 17.1-1.7 25.4l43.3 39.4c6.9 6.2 9.6 15.9 6.4 24.6c-4.4 11.9-9.7 23.3-15.8 34.3l-4.7 8.1c-6.6 11-14 21.4-22.1 31.2c-5.9 7.2-15.7 9.6-24.5 6.8l-55.7-17.7c-13.4 10.3-28.2 18.9-44 25.4l-12.5 57.1c-2 9.1-9 16.3-18.2 17.8c-13.8 2.3-28 3.5-42.5 3.5s-28.7-1.2-42.5-3.5c-9.2-1.5-16.2-8.7-18.2-17.8l-12.5-57.1c-15.8-6.5-30.6-15.1-44-25.4L83.1 425.9c-8.8 2.8-18.6 .3-24.5-6.8c-8.1-9.8-15.5-20.2-22.1-31.2l-4.7-8.1c-6.1-11-11.4-22.4-15.8-34.3c-3.2-8.7-.5-18.4 6.4-24.6l43.3-39.4C64.6 273.1 64 264.6 64 256s.6-17.1 1.7-25.4L22.4 191.2c-6.9-6.2-9.6-15.9-6.4-24.6c4.4-11.9 9.7-23.3 15.8-34.3l4.7-8.1c6.6-11 14-21.4 22.1-31.2c5.9-7.2 15.7-9.6 24.5-6.8l55.7 17.7c13.4-10.3 28.2-18.9 44-25.4l12.5-57.1c2-9.1 9-16.3 18.2-17.8C227.3 1.2 241.5 0 256 0s28.7 1.2 42.5 3.5c9.2 1.5 16.2 8.7 18.2 17.8l12.5 57.1c15.8 6.5 30.6 15.1 44 25.4l55.7-17.7c8.8-2.8 18.6-.3 24.5 6.8c8.1 9.8 15.5 20.2 22.1 31.2l4.7 8.1c6.1 11 11.4 22.4 15.8 34.3zM256 336a80 80 0 1 0 0-160 80 80 0 1 0 0 160z"/>
</svg>
```

`github.blade.php`:

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" fill="currentColor" aria-hidden="true">
    <path d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3 .3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5 .3-6.2 2.3zm44.2-1.7c-2.9 .7-4.9 2.6-4.6 4.9 .3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.2-114.8 110.5 9.2 7.9 17 22.9 17 46.4 0 33.7-.3 75.4-.3 83.6 0 6.5 4.6 14.4 17.3 12.1C428.2 457.8 496 362.9 496 252 496 113.3 383.5 8 244.8 8zM97.2 352.9c-1.3 1-1 3.3 .7 5.2 1.6 1.6 3.9 2.3 5.2 1 1.3-1 1-3.3-.7-5.2-1.6-1.6-3.9-2.3-5.2-1zm-10.8-8.1c-.7 1.3 .3 2.9 2.3 3.9 1.6 1 3.6 .7 4.3-.7 .7-1.3-.3-2.9-2.3-3.9-2-.6-3.6-.3-4.3 .7zm32.4 35.6c-1.6 1.3-1 4.3 1.3 6.2 2.3 2.3 5.2 2.6 6.5 1 1.3-1.3 .7-4.3-1.3-6.2-2.2-2.3-5.2-2.6-6.5-1zm-11.4-14.7c-1.6 1-1.6 3.6 0 5.9s4.3 3.3 5.6 2.3c1.6-1.3 1.6-3.9 0-6.2-1.4-2.3-4-3.3-5.6-2z"/>
</svg>
```

`linkedin.blade.php` (the `linkedin-in` brand glyph the original imported):

```blade
<svg {{ $attributes->merge(['class' => 'inline-block h-[1em] w-[1em] align-[-0.125em]']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true">
    <path d="M100.28 448H7.4V148.9h92.88zM53.79 108.1C24.09 108.1 0 83.5 0 53.8a53.79 53.79 0 0 1 107.58 0c0 29.7-24.1 54.3-53.79 54.3zM447.9 448h-92.68V302.4c0-34.7-.7-79.2-48.29-79.2-48.29 0-55.69 37.7-55.69 76.7V448h-92.78V148.9h89.08v40.8h1.3c12.4-23.5 42.69-48.3 87.88-48.3 94 0 111.28 61.9 111.28 142.3V448z"/>
</svg>
```

- [ ] **Step 2: Verify the components render**

```bash
$PHP artisan tinker --execute="echo view('components.icon.play')->render();"
```

Expected: an `<svg>` element printed to stdout.

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/icon
git commit -m "feat: add inline svg icon components"
```

---

### Task 6: Page shell, background layer and footer

**Files:**
- Modify: `resources/views/radio/index.blade.php`
- Create: `resources/views/radio/partials/background.blade.php`
- Create: `resources/views/radio/partials/footer.blade.php`
- Create: `public/favicon.ico` and the other favicon assets

**Interfaces:**
- Consumes: `$radios` from Task 2, the icon components from Task 5, `initParticles` from Task 4.
- Produces: `<body data-theme="...">` carrying the active theme; an Alpine-free background layer with `id="tsparticles"`; `window.RADIO_STATIONS` — the station array as JSON — for the Alpine components in Tasks 7–9.

- [ ] **Step 1: Copy the favicon assets**

```bash
cp ../RadioMT/assets/favicon.ico ../RadioMT/assets/favicon-16x16.png ../RadioMT/assets/favicon-32x32.png ../RadioMT/assets/apple-touch-icon.png ../RadioMT/assets/android-chrome-192x192.png ../RadioMT/assets/android-chrome-512x512.png ../RadioMT/assets/site.webmanifest public/
```

- [ ] **Step 2: Write the page shell**

Replace `resources/views/radio/index.blade.php`. The inline `<head>` script applies the stored theme before first paint, which the original's `useEffect` cannot do — it flashes.

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MT Radio</title>
    <meta name="description" content="Project of MariusTanase.com, website made for an easier selection of radios to listen in free time">
    <meta name="author" content="Marius Tanase">
    <meta name="keywords" content="radio, online radio, marius tanase, mariustanase.com, capital radio online, virgin radio, capital UK, heart uk, heart radio online, monte carlo, deep house radio online">
    <meta name="msapplication-TileColor" content="#da532c">
    <meta name="theme-color" content="#ffffff">

    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <script>
        // Applied before first paint so the chosen theme never flashes.
        document.documentElement.dataset.bootTheme = localStorage.getItem('theme') ?? 'blue';
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-app text-app-text m-0 min-h-screen w-full font-sans" data-theme="blue">
    <script>
        document.body.dataset.theme = document.documentElement.dataset.bootTheme;
        window.RADIO_STATIONS = @json($radios);
    </script>

    @include('radio.partials.background')
    @include('radio.partials.player')
    @include('radio.partials.radio-list')
    @include('radio.partials.settings')
    @include('radio.partials.footer')
</body>
</html>
```

Tasks 7–9 create the `player`, `radio-list` and `settings` partials. To keep this task independently testable, create those three files now as empty placeholders and fill them in later:

```bash
mkdir -p resources/views/radio/partials
touch resources/views/radio/partials/player.blade.php
touch resources/views/radio/partials/radio-list.blade.php
touch resources/views/radio/partials/settings.blade.php
```

The `RadioPageTest` assertion that every station title is rendered will fail until Task 8 adds the list. Move that one test to Task 8, or leave the station titles rendered in the placeholder `radio-list.blade.php` as a plain `@foreach` for now. Prefer the latter — it keeps the suite green:

```blade
@foreach ($radios as $radio)
    <span class="sr-only">{{ $radio->title }}</span>
@endforeach
```

- [ ] **Step 3: Write the background partial**

`resources/views/radio/partials/background.blade.php`. `background-image` is set inline to the local fallback and replaced at runtime by the settings menu.

```blade
<div class="background fixed inset-0 -z-10 h-screen w-full bg-cover bg-center bg-no-repeat"
     style="background-image: url('{{ asset('images/background.jpg') }}')">
    <div id="tsparticles" class="h-full w-full"></div>
</div>
```

- [ ] **Step 4: Write the footer partial**

`resources/views/radio/partials/footer.blade.php` — hidden below 720px, matching `Footer.css`:

```blade
<div class="bg-menu text-app-text fixed bottom-0 hidden h-[1.2rem] w-full items-center justify-around p-[1.2rem] text-[12px] md:flex">
    <p class="m-0 text-[1.1rem]">
        Created by <a class="text-app-text text-[1.1rem] no-underline" href="https://mariustanase.com">Marius Tanase</a>
    </p>

    <div class="flex items-center justify-center gap-2 text-[1.1rem]">
        <a class="text-app-text text-[1.3rem] no-underline" href="https://www.github.com/mariustanase" aria-label="GitHub">
            <x-icon.github />
        </a>
        <a class="text-app-text text-[1.3rem] no-underline" href="https://www.linkedin.com/in/marius-tanase/" aria-label="LinkedIn">
            <x-icon.linkedin />
        </a>
    </div>
</div>
```

Tailwind's `md` breakpoint is 768px against the original's 720px. If exactness matters, use `max-[720px]:hidden flex` instead.

- [ ] **Step 5: Run the tests and build**

```bash
$PHP vendor/bin/pest
npm run build
```

Expected: all tests PASS, Vite build succeeds.

- [ ] **Step 6: Commit**

```bash
git add public resources/views/radio
git commit -m "feat: add the page shell, background layer and footer"
```

---

### Task 7: Player

**Files:**
- Create: `resources/js/radio/player.js`
- Modify: `resources/views/radio/partials/player.blade.php`, `resources/js/app.js`

**Interfaces:**
- Consumes: `window.RADIO_STATIONS` from Task 6; icon components from Task 5.
- Produces: an Alpine component registered as `radioPlayer`, exposed on the `$store`-free `x-data` root of the player partial. Its public surface, relied on by Tasks 8 and 9:
  - `stations: Array<{id, title, artist, genre, image, url}>`
  - `index: number` — position in `stations`, not an id
  - `isPlaying: boolean`, `volume: number`, `uiHidden: boolean`
  - `current` — getter returning the active station
  - `play()`, `pause()`, `toggle()`, `next()`, `previous()`, `shuffle()`
  - `select(id: number): void` — play the station with that **id**
  - `setVolume(value: number): void`
  - `toggleUi(): void`

  Tasks 8 and 9 reach it via `x-data` inheritance, so the player's `x-data` must sit on a wrapper that encloses the list and settings. **This task moves `x-data="radioPlayer"` onto `<body>` in `index.blade.php`.**

- [ ] **Step 1: Move the Alpine root onto the body**

In `resources/views/radio/index.blade.php`, change the `<body>` tag to:

```blade
<body class="bg-app text-app-text m-0 min-h-screen w-full font-sans"
      data-theme="blue"
      x-data="radioPlayer"
      x-init="boot()"
      @keydown.window.prevent.space="toggle()">
```

Alpine's `.space` modifier already narrows the handler to the spacebar, which is the fix for the original's bug where any key started playback. `.prevent` stops the page scrolling.

- [ ] **Step 2: Write the player component**

`resources/js/radio/player.js`:

```js
export default function radioPlayer() {
    return {
        stations: window.RADIO_STATIONS ?? [],
        index: 0,
        isPlaying: false,
        volume: 0.1,
        uiHidden: false,

        get current() {
            return this.stations[this.index] ?? null;
        },

        get audio() {
            return this.$refs.audio;
        },

        boot() {
            if (this.stations.length === 0) {
                return;
            }

            // Pick the station before the element ever gets a src, so nothing
            // autoplays and is then replaced.
            this.index = Math.floor(Math.random() * this.stations.length);
            this.setVolume(this.volume);
            this.play();
        },

        play() {
            // Let the element's own events drive isPlaying: play() rejects when
            // autoplay is blocked or the stream is unreachable, and the button
            // must not claim to be playing in that case.
            this.audio?.play().catch(() => {});
        },

        pause() {
            this.audio?.pause();
        },

        toggle() {
            this.isPlaying ? this.pause() : this.play();
        },

        select(id) {
            const index = this.stations.findIndex((station) => station.id === id);

            if (index !== -1) {
                this.index = index;
                this.play();
            }
        },

        next() {
            this.index = this.index === this.stations.length - 1 ? 0 : this.index + 1;
            this.play();
        },

        previous() {
            this.index = this.index === 0 ? this.stations.length - 1 : this.index - 1;
            this.play();
        },

        shuffle() {
            this.index = Math.floor(Math.random() * this.stations.length);
            this.play();
        },

        setVolume(value) {
            this.volume = Number(value);

            if (this.audio) {
                this.audio.volume = this.volume;
            }
        },

        toggleUi() {
            this.uiHidden = !this.uiHidden;
        },
    };
}
```

Changing `index` changes `current.url`, which Alpine writes to the `<audio>` `src`; calling `play()` immediately after is safe because the element reloads on `src` change.

- [ ] **Step 3: Register the component**

In `resources/js/app.js`, register before `Alpine.start()`:

```js
import Alpine from 'alpinejs';
import radioPlayer from './radio/player';
import radioSettings from './radio/settings';
import { initParticles } from './radio/particles';

window.Alpine = Alpine;

Alpine.data('radioPlayer', radioPlayer);
Alpine.data('radioSettings', radioSettings);

Alpine.start();

initParticles('tsparticles');
```

`radioSettings` arrives in Task 9. Create `resources/js/radio/settings.js` now with `export default function radioSettings() { return {}; }` so the build does not break, and fill it in there.

- [ ] **Step 4: Write the player partial**

`resources/views/radio/partials/player.blade.php`:

```blade
<div class="bg-menu text-app-text relative mx-auto mt-8 w-[350px] rounded-t-2xl rounded-b-[2rem] p-6 shadow-[0_18px_28px_var(--color-menu)]"
     x-show="!uiHidden">
    <div class="text-center">
        <img class="border-app-text mx-auto mb-4 block h-[150px] w-[150px] rounded-full border border-solid object-cover"
             x-bind:src="current?.image"
             x-bind:alt="current ? `Image of ${current.title}` : ''">
        <h2 class="mb-1 font-bold" x-text="current?.title"></h2>
        <h3 class="mt-0 font-light" x-text="current ? `Genre: ${current.artist}` : ''"></h3>
    </div>

    <div class="mx-auto my-2 flex w-3/5 flex-col items-center justify-between gap-2.5">
        <div class="mx-auto my-2 flex w-full items-center justify-evenly gap-2.5">
            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="previous()" aria-label="Previous station">
                <x-icon.backward />
            </button>

            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="toggle()" x-bind:aria-label="isPlaying ? 'Pause' : 'Play'">
                <template x-if="isPlaying"><x-icon.pause /></template>
                <template x-if="!isPlaying"><x-icon.play /></template>
            </button>

            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="next()" aria-label="Next station">
                <x-icon.forward />
            </button>
        </div>

        <div class="mx-auto my-2 flex w-full items-center justify-between gap-4">
            <button class="hover:text-icon-hover border-none bg-transparent text-[2em] font-light transition-all duration-200 hover:cursor-pointer"
                    @click="shuffle()" aria-label="Random station">
                <x-icon.shuffle />
            </button>

            <input type="range" min="0" max="1" step="0.01" class="volume-slider"
                   aria-label="Volume"
                   x-model.number="volume"
                   @input="setVolume($event.target.value)">
        </div>
    </div>

    <audio x-ref="audio"
           x-bind:src="current?.url"
           @play="isPlaying = true"
           @pause="isPlaying = false"
           @error="isPlaying = false"></audio>
</div>
```

The slider is bound to `volume`, which is the value actually written to the element — the original bound it to a null ref and always showed zero.

- [ ] **Step 5: Build and check in the browser**

```bash
npm run build
$PHP artisan serve --port=8123
```

Open `http://127.0.0.1:8123`. Verify: a random station's artwork, title and "Genre: …" appear; play/pause swaps the icon; next, previous and shuffle change the station; the volume slider starts at 0.1 and changes loudness; pressing space toggles playback; pressing `k` does **not** start playback.

- [ ] **Step 6: Commit**

```bash
git add resources/js resources/views/radio
git commit -m "feat: add the radio player with keyboard, transport and volume control"
```

---

### Task 8: Station list

**Files:**
- Modify: `resources/views/radio/partials/radio-list.blade.php`

**Interfaces:**
- Consumes: `$radios` from Task 2; `select(id)` and `uiHidden` from Task 7.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Write the partial**

Replace `resources/views/radio/partials/radio-list.blade.php`. Rendering the stations server-side from `$radios` keeps the titles in the HTML, which `RadioPageTest` asserts.

```blade
<div class="mx-auto my-16 max-h-fit w-[90%] max-w-[1200px] max-[720px]:mb-24" x-show="!uiHidden">
    <ul class="mx-auto flex w-full list-none flex-wrap items-center justify-center gap-4 p-0">
        @foreach ($radios as $radio)
            <li class="bg-menu text-app-text hover:bg-menu-hover flex h-auto w-[200px] cursor-pointer flex-row items-center justify-evenly rounded-[10px] p-2 transition-all duration-300 ease-in-out max-[720px]:w-[155px] max-[720px]:text-[.8rem]"
                @click="select({{ $radio->id }})">
                <div class="max-[720px]:flex-[1_0_50px]">
                    <img class="h-[65px] w-[65px] rounded-full object-cover max-[800px]:h-[75px] max-[800px]:w-[75px] max-[720px]:h-[50px] max-[720px]:w-[50px]"
                         src="{{ $radio->image }}" alt="{{ $radio->title }}" loading="lazy">
                </div>
                <div class="max-[720px]:flex max-[720px]:w-full max-[720px]:flex-col max-[720px]:p-1">
                    <h4>{{ $radio->title }}</h4>
                </div>
            </li>
        @endforeach
    </ul>
</div>
```

- [ ] **Step 2: Run the tests**

```bash
$PHP vendor/bin/pest tests/Feature/RadioPageTest.php
```

Expected: PASS, 4 tests — including the assertion that every station title is rendered.

- [ ] **Step 3: Verify in the browser**

Rebuild, reload, and confirm all 20 stations appear as circular-artwork cards and that clicking one starts it playing and updates the player's artwork, title and genre.

- [ ] **Step 4: Commit**

```bash
$PHP vendor/bin/pint --dirty
git add resources/views/radio/partials/radio-list.blade.php
git commit -m "feat: render the station library as a clickable grid"
```

---

### Task 9: Settings panel, themes and background picker

**Files:**
- Modify: `resources/js/radio/settings.js`, `resources/views/radio/partials/settings.blade.php`
- Create: `resources/views/radio/partials/theme-menu.blade.php`, `resources/views/radio/partials/background-menu.blade.php`

**Interfaces:**
- Consumes: `uiHidden` and `toggleUi()` from Task 7; `BackgroundController::CATEGORIES` from Task 3; `x-icon.gear` from Task 5.
- Produces: an Alpine component `radioSettings` with `menuOpen: boolean`, `theme: string`, `open()`, `close()`, `setTheme(name)`, `setBackground(category)`.

- [ ] **Step 1: Write the settings component**

Replace `resources/js/radio/settings.js`:

```js
export default function radioSettings() {
    return {
        menuOpen: false,
        theme: localStorage.getItem('theme') ?? 'blue',

        open() {
            this.menuOpen = true;
        },

        close() {
            this.menuOpen = false;
        },

        setTheme(name) {
            this.theme = name;
            localStorage.setItem('theme', name);
            document.body.dataset.theme = name;
        },

        async setBackground(category) {
            try {
                const response = await fetch(`/api/background?query=${encodeURIComponent(category)}`);

                if (!response.ok) {
                    return;
                }

                const { url } = await response.json();
                document.querySelector('.background').style.backgroundImage = `url(${url})`;
            } catch {
                // Leave the current background in place; the server already
                // falls back to the bundled image when Unsplash is unavailable.
            }
        },
    };
}
```

- [ ] **Step 2: Write the theme menu partial**

`resources/views/radio/partials/theme-menu.blade.php`:

```blade
<div class="flex w-full flex-col">
    <h5 class="flex w-full flex-col text-base font-extrabold max-[720px]:text-2xl">
        <p>Select Theme</p>
    </h5>
    <div class="mx-auto my-4 flex w-full flex-row flex-wrap items-center justify-center gap-4">
        @foreach (['Light', 'Dark', 'Crimson', 'Blue'] as $name)
            <div class="border-btn-border bg-btn text-app-text hover:bg-btn-hover flex h-auto w-fit cursor-pointer flex-col items-center justify-evenly rounded border border-solid p-2 text-[.7rem] font-bold transition-all duration-300 ease-in-out max-[720px]:mx-[.2rem] max-[720px]:text-base"
                 @click="setTheme('{{ Str::lower($name) }}')">
                <span>{{ $name }}</span>
            </div>
        @endforeach
    </div>
</div>
```

- [ ] **Step 3: Write the background menu partial**

`resources/views/radio/partials/background-menu.blade.php`. The categories come from the controller constant so the UI and the endpoint's allowlist cannot drift apart.

```blade
<div class="flex w-full flex-col">
    <h5 class="flex w-full flex-col text-base font-extrabold max-[720px]:text-2xl">
        Select Background
    </h5>
    <div class="mx-auto my-4 flex w-full flex-row flex-wrap items-center justify-center gap-4 max-[720px]:grid max-[720px]:grid-cols-2 max-[720px]:justify-items-center max-[720px]:gap-y-[1.4rem]">
        @foreach (\App\Http\Controllers\BackgroundController::CATEGORIES as $category)
            <div class="border-btn-border bg-btn text-app-text hover:bg-btn-hover flex h-auto max-w-[150px] min-w-[120px] cursor-pointer flex-col items-center justify-evenly rounded border border-solid p-2 text-[.7rem] font-bold transition-all duration-300 ease-in-out max-[720px]:mx-[.2rem] max-[720px]:text-base"
                 @click="setBackground('{{ $category }}')">
                <div>{{ Str::title($category) }}</div>
            </div>
        @endforeach
    </div>
</div>
```

The original labelled the first button "Mountain" while sending the query `mountain`; `Str::title` reproduces every label exactly.

- [ ] **Step 4: Write the settings partial**

Replace `resources/views/radio/partials/settings.blade.php`:

```blade
<div class="text-app-text fixed top-0 right-0 z-100 flex h-auto w-fit flex-col items-center justify-center p-4 max-[720px]:top-auto max-[720px]:bottom-0 max-[720px]:left-0 max-[720px]:mx-auto max-[720px]:h-20 max-[720px]:w-full"
     x-data="radioSettings"
     x-bind:class="menuOpen ? 'max-[720px]:bg-transparent' : 'max-[720px]:bg-menu'">
    <button class="text-app-text cursor-pointer border-none bg-none text-5xl outline-none transition-all duration-300 ease-in-out"
            x-show="!menuOpen" @click="open()" aria-label="Open settings">
        <x-icon.gear class="animate-gear" />
    </button>

    <div class="bg-menu absolute top-0 right-0 z-100 flex h-auto w-80 translate-x-full flex-col items-center rounded-l-lg rounded-br-lg p-4 transition-all duration-300 ease-in-out max-[720px]:fixed max-[720px]:top-0 max-[720px]:left-0 max-[720px]:h-full max-[720px]:w-full max-[720px]:justify-between max-[720px]:rounded-none max-[720px]:pt-24"
         x-bind:class="menuOpen ? '!translate-x-0' : ''">
        <div class="pt-12">
            @include('radio.partials.theme-menu')
            @include('radio.partials.background-menu')

            <h5 class="flex w-full flex-col text-base font-extrabold max-[720px]:text-2xl">Extra</h5>

            <button class="border-btn-border bg-btn text-app-text hover:bg-btn-hover mx-auto my-4 flex h-auto w-fit cursor-pointer flex-col items-center justify-evenly rounded border border-solid p-2 text-base font-bold transition-all duration-300 ease-in-out max-[720px]:mb-20 max-[720px]:rounded-xl max-[720px]:p-4 max-[720px]:text-[1.3rem]"
                    @click="toggleUi()"
                    x-text="uiHidden ? 'Show UI' : 'Hide UI'"></button>
        </div>

        <button class="text-app-text absolute top-[3%] right-[6%] h-auto w-fit cursor-pointer rounded-full border-none bg-transparent text-5xl font-bold outline-none transition-all duration-300 ease-in-out"
                @click="close()" aria-label="Close settings">X</button>
    </div>
</div>
```

`toggleUi()` and `uiHidden` resolve to the `radioPlayer` component on `<body>` through Alpine's scope chain, which is why Task 7 moved the player's `x-data` up to the body.

- [ ] **Step 5: Build and verify in the browser**

```bash
npm run build
$PHP artisan serve --port=8123
```

Verify: the gear spins and opens the panel; all four themes change the colours and survive a reload with no flash; each of the eight background buttons swaps the background (to the bundled image while `UNSPLASH_ACCESS_KEY` is empty); "Hide UI" hides the player and station list and its label flips to "Show UI"; X closes the panel.

- [ ] **Step 6: Commit**

```bash
git add resources/js/radio/settings.js resources/views/radio/partials
git commit -m "feat: add the settings panel with themes, backgrounds and ui toggle"
```

---

### Task 10: Full verification and documentation

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: everything.
- Produces: nothing.

- [ ] **Step 1: Run the whole suite**

```bash
$PHP vendor/bin/pest
```

Expected: PASS, 27 tests (5 library + 4 page + 4 api + 10 background + 4 pre-existing example tests, minus any example tests removed).

- [ ] **Step 2: Lint**

```bash
$PHP vendor/bin/pint --test
```

Expected: no style violations.

- [ ] **Step 3: Production build**

```bash
npm run build
```

Expected: succeeds with no warnings about unresolved imports.

- [ ] **Step 4: Manual parity pass**

With `$PHP artisan serve --port=8123` running, walk the behaviour list from the spec's "Behaviour to preserve" section and confirm each item. Compare side by side against the original at `https://radio-mt.vercel.app/`.

- [ ] **Step 5: Document the setup**

Replace the Laravel boilerplate `README.md` with the project's own: what the app is, the PHP-version caveat and the `php.ini` workaround from Global Constraints, how to migrate and seed, how to obtain and set `UNSPLASH_ACCESS_KEY`, and a note that the four fixed bugs are catalogued in the design spec.

- [ ] **Step 6: Commit**

```bash
git add README.md
git commit -m "docs: document setup, php version caveat and unsplash configuration"
```

---

## Self-Review Notes

**Spec coverage.** Every spec section maps to a task: data layer → 1; HTTP layer → 2; Unsplash → 3; frontend file structure → 4, 6, 7, 8, 9; theming → 4; state → 7; behaviour-to-preserve → verified in 7, 9 and 10; the four bug fixes → Bug 1 in Task 7 Step 1 (`.space` modifier), Bugs 2–4 in Task 7 Step 2 and Step 4; testing → 1, 2, 3, 8, 10.

**Known ordering hazard.** Task 2 registers the `/api/background` route before Task 3 creates its controller. Task 2 Step 4 says to create the Task 3 stub first if route registration fails. An executor running tasks strictly in order should expect this.

**Interface consistency.** `select(id)` takes a station **id** and is called with `$radio->id`; `index` is always a position. `uiHidden`/`toggleUi()` live on `radioPlayer` and are reached from the settings partial through Alpine's scope chain — this works only because Task 7 Step 1 moves `x-data="radioPlayer"` to `<body>`. The station JSON is `window.RADIO_STATIONS` everywhere.
