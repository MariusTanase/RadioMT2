# Radio Library at Scale — Design (Spec 1 of 2)

Date: 2026-09-18
Status: Approved, ready for implementation planning

## Goal

Grow the radio library from 20 hand-seeded stations to the full Radio Browser
catalogue (~51,600 working stations), with a curated genre taxonomy and
per-station liveness data.

This spec covers the **data layer only**: schema, import, genre mapping, and
health fields. The app's visible behaviour does not change — it continues to
show the 20 seeded stations, now flagged as favourites. Making 51,600 stations
browsable is Spec 2 (`browsing-at-scale`), which depends on this one.

## Data source

[Radio Browser](https://api.radio-browser.info) — free, no API key, community
maintained. Verified live on 2026-09-18:

| Metric | Value |
|---|---|
| Stations | 58,367 |
| Known-broken | 6,711 |
| Working (`hidebroken=true`) | ~51,656 |
| Tags | 12,261 |
| Countries | 241 |

### Facts that shape the design

**1. Most high-volume tags are not genres.** Of the 45 most-used tags, these
are not musical genres: `music`, `radio`, `fm`, `estación`, `méxico`,
`norteamérica`, `américa`, `latinoamérica`, `español`, `regional`,
`local radio`, `community radio`, `public radio`, `entretenimiento`,
`local news`, `hits`. And `moi merino` — a person's name — is tagged on
**1,783 stations**, ranking 10th by volume. A "top N tags" picker would
surface it on the home page. This is the concrete justification for a curated
mapping rather than raw tags.

**2. Genres arrive in several languages.** `pop` / `pop music` / `música pop`;
`regional mexican` / `regional mexicana` / `música popular mexicana`;
`rock` / `pop rock` / `classic rock`. The mapping must be many-to-one and
accent-insensitive.

**3. `order=clickcount` is unsafe for offset paging.** Click counts change
continuously, so rows shift between page requests and stations are silently
skipped. `order=name` is alphabetical and stable (verified against offsets 0
and 1000) and is what the import uses.

**4. Station names contain junk.** Real examples begin with tab characters
(`"\t\tLOVE FM: All you nee…"`, `"\tArrow Classic Rock"`). Names require
trimming of whitespace and control characters on import.

**5. `stationuuid` is a stable primary key**, which makes re-imports
idempotent via upsert.

## Schema

Three migrations.

### 1. Extend `radios`

| Column | Type | Notes |
|---|---|---|
| `station_uuid` | `string(36)` nullable **unique** | Radio Browser key; `NULL` for the 20 seeded stations |
| `country` | `string` nullable | |
| `country_code` | `char(2)` nullable, **index** | For `--country` filtering and Spec 2 |
| `language` | `string` nullable | |
| `homepage` | `string(2048)` nullable | |
| `codec` | `string(16)` nullable | e.g. `AAC`, `MP3` |
| `bitrate` | `unsignedInteger` nullable | |
| `votes` | `unsignedInteger` default 0 | |
| `click_count` | `unsignedInteger` default 0, **index** | Popularity ordering in Spec 2 |
| `source` | `string(16)` default `'seed'`, **index** | `'seed'` or `'radiobrowser'` |
| `is_favourite` | `boolean` default `false`, **index** | The 20 seeded stations |
| `is_alive` | `boolean` default `true`, **index** | From `lastcheckok` |
| `checked_at` | `timestamp` nullable | From `lastchecktime` |
| `local_ok` | `boolean` nullable | Written by Spec 2's playback reporting |
| `local_checked_at` | `timestamp` nullable | Written by Spec 2 |
| `listeners` | `unsignedInteger` nullable, **index** | Real listener count, where the stream server publishes one |
| `listener_peak` | `unsignedInteger` nullable | Peak listeners reported alongside it |
| `probed_at` | `timestamp` nullable | Last local probe attempt |
| `probe_ok` | `boolean` nullable | Whether the last local probe reached the stream |

An index is also added on `title` to support prefix search in Spec 2.

`artist` and `genre` keep their existing `NOT NULL` definitions. Imported rows
set both to the station's primary mapped genre. This preserves the original
app's duplication of the two columns rather than introducing a nullable
column and a second code path.

### 2. `genres`

| Column | Type |
|---|---|
| `id` | `id()` |
| `name` | `string` unique — display label, e.g. `Pop` |
| `slug` | `string(64)` unique — e.g. `pop` |
| `sort_order` | `unsignedInteger` default 0 |
| `timestamps` | |

Seeded from `config/radio-genres.php` by a `GenreSeeder`, so the table and the
config cannot drift.

### 3. `genre_radio`

| Column | Type |
|---|---|
| `genre_id` | FK → `genres`, cascade on delete |
| `radio_id` | FK → `radios`, cascade on delete |

Composite unique on `(radio_id, genre_id)`, plus an index on `genre_id` for
"all stations in genre X". A station is legitimately both `Pop` and `Oldies`,
so this cannot be a single column.

## Genre mapping

### Configuration

`config/radio-genres.php` holds the canonical taxonomy:

```php
return [
    'genres' => [
        'pop' => [
            'label' => 'Pop',
            'sort'  => 10,
            'tags'  => ['pop', 'pop music', 'musica pop', 'top 40', 'chart', 'adult contemporary'],
        ],
        // … ~24 genres
    ],
    'ignore' => ['music', 'radio', 'fm', 'estacion', 'mexico', 'moi merino', /* … */],
];
```

Roughly 24 genres: Pop, Rock, Classic Rock, Alternative, Dance, Electronic,
House, Jazz, Blues, Classical, Country, Folk, Latin, Reggae, Hip Hop, R&B,
Metal, Oldies, 80s, 90s, Lofi, Christian, News & Talk, Sport, World, Other.
The exact list is finalised during implementation against the live tag
distribution; the taxonomy living in config means it can be tuned without a
migration (re-running `GenreSeeder` and the import re-syncs).

### Algorithm

`App\Support\GenreMapper` — a single class with one public method:

```php
public function map(string $tags): array   // returns genre slugs
```

1. Split the tag string on `,`.
2. Normalise each tag: trim, collapse whitespace, lowercase, fold accents
   (`estación` → `estacion`).
3. Drop tags in the `ignore` list.
4. Match the normalised tag **exactly** against each genre's `tags` list.
5. Return the distinct matched slugs; if none matched, return `['other']`.

Matching is exact, not substring. Substring matching would make `estación`
match `station`, and would prevent `pop rock` from mapping to both `Pop` and
`Rock` via an explicit entry — with substring rules it would match `pop`,
`rock` and `pop rock` inconsistently depending on iteration order.

Every station receives at least one genre, so none is unreachable from the
Spec 2 picker. The `genre` display column is set to the **first** matched
genre's label.

## Import command

```
php artisan radios:import [--limit=] [--country=] [--fresh] [--server=]
```

| Flag | Effect |
|---|---|
| `--limit=N` | Stop after N stations. Absent → import everything. |
| `--country=XX` | Restrict to an ISO country code (passed as `countrycode`). |
| `--fresh` | Delete existing imported rows before importing (see safety property). |
| `--server=` | Override the Radio Browser host; defaults to `de1.api.radio-browser.info`. |

### Behaviour

- Requests `/json/stations/search` with `hidebroken=true&order=name&reverse=false`,
  paging via `limit=1000` and an increasing `offset`, until a page returns
  fewer than 1,000 rows or `--limit` is reached.
- Sends a descriptive `User-Agent` (`RadioMT2/1.0 (+https://github.com/MariusTanase/RadioMT2)`)
  as Radio Browser's usage policy requires.
- Shows a progress bar.
- Per page: upsert stations on `station_uuid`, then bulk-sync pivot rows for
  that page. Pivot rows are written in bulk per page, never per station.
- Timeout per request: 30s. A failed page is retried twice, then the command
  reports which offset failed and exits non-zero — a partial import is
  recoverable by re-running, because upsert is idempotent.

### Field mapping

| Radio Browser | `radios` | Transform |
|---|---|---|
| `stationuuid` | `station_uuid` | — |
| `name` | `title` | Trim whitespace/control chars, truncate to 255 |
| `url_resolved` or `url` | `url` | Prefer `url_resolved`; skip station if both empty |
| `favicon` | `image` | Empty string when absent; Spec 2 supplies a display fallback |
| `homepage` | `homepage` | |
| `tags` | pivot + `genre` + `artist` | Via `GenreMapper` |
| `country`, `countrycode` | `country`, `country_code` | |
| `language` | `language` | |
| `codec`, `bitrate` | `codec`, `bitrate` | |
| `votes`, `clickcount` | `votes`, `click_count` | |
| `lastcheckok` | `is_alive` | `1` → true |
| `lastchecktime_iso8601` | `checked_at` | |
| — | `source` | Literal `'radiobrowser'` |

`local_ok` and `local_checked_at` are never written by the import; they belong
to Spec 2 and must survive re-imports, so they are excluded from the upsert's
update column list.

### Safety property

**`--fresh` deletes only `source = 'radiobrowser'` rows.** The 20 seeded
favourites are never touched by any import, with or without the flag. This is
the single most important invariant in this spec and gets a dedicated test.

## Popularity and listener counts

Two distinct notions, deliberately kept apart.

**Popularity (universal).** `click_count` and `votes` come from the import and
exist for every station. These are Radio Browser's own engagement figures and
are the default ordering for Spec 2's lists. They are a proxy for audience,
not an audience measurement.

**Listeners (partial).** Radio Browser exposes no listener count — verified
against its full station schema on 2026-09-18. Real figures are only available
from stream servers that publish a status endpoint. Probing four seeded
stations found exactly one that does:

| Stream | Result |
|---|---|
| `stream.vanillaradio.com:8012` | Icecast — `listeners: 4982`, `listener_peak: 4998` |
| `online.kissfm.ua` | No public status endpoint |
| `myradio24.org` | No public status endpoint |
| `c4.radioboss.fm:18071` | No public status endpoint |

So `listeners` is **nullable and expected to be null for most stations**. Any
UI built on it in Spec 2 must treat absence as the normal case, and must never
sort the catalogue by a column that is null for the majority of rows — sorting
by listeners is only offered as a filtered view of stations that have one.

## Probe command

```
php artisan radios:probe [--limit=] [--country=] [--genre=] [--favourites] [--timeout=8]
```

One request per station answers both questions the user asked for — is it
alive, and how many people are listening:

- `GET {stream_base}/status-json.xsl` (Icecast). On a JSON response, read
  `icestats.source.listeners` and `listener_peak`; `source` may be an object
  or an array of mounts, in which case the mount matching the station's path
  is used, else the first.
- On any non-JSON or failed response, fall back to a `GET` of the stream URL
  itself with a short timeout, reading only headers and the first bytes, to
  determine liveness alone.
- Writes `probe_ok`, `probed_at`, and — when available — `listeners` and
  `listener_peak`. It never writes `is_alive`, which stays Radio Browser's
  verdict, so the two signals remain separable.

**Scoping is mandatory, not optional.** With no selector the command probes
only `is_favourite` stations. `--limit`, `--country` and `--genre` narrow it
otherwise. A blind 51,600-request sweep is not an available behaviour: it
would take hours, and hammering 51k third-party servers from one host is
antisocial and likely to get the IP blocked.

Concurrency is capped (default 10 in flight) and every failure is swallowed
into `probe_ok = false` — an unreachable stream is data, not an error.

## Seeder changes

`RadioSeeder` sets `source = 'seed'` and `is_favourite = true` on all 20
stations; `station_uuid` stays `NULL`. Its existing upsert update-column list
is extended accordingly. The station data itself is unchanged, so the five
existing `RadioLibraryTest` assertions continue to hold.

`GenreSeeder` populates `genres` from `config/radio-genres.php`, upserting on
`slug` so re-running is safe. `DatabaseSeeder` calls `GenreSeeder` before
`RadioSeeder`.

## Testing

Pest, in-memory SQLite, `Http::fake()` throughout. **No test makes a real
request to Radio Browser.**

| Test | Asserts |
|---|---|
| `GenreMapperTest` | Exact matching; accent folding (`música pop` → Pop); multilingual synonyms; multi-genre tags (`pop rock` → Pop + Rock); ignore-list drops `music`/`radio`/`moi merino`; unmapped → `other`; empty tag string → `other` |
| `GenreSeederTest` | Every config genre exists; re-running does not duplicate |
| `RadioImportTest` | Imports a faked page; maps every field; prefers `url_resolved`; skips stations with no URL; trims tabbed names; sets `source='radiobrowser'`; sets `is_alive` from `lastcheckok` |
| `RadioImportPagingTest` | Follows offsets until a short page; respects `--limit`; passes `countrycode` for `--country`; retries a failed page then exits non-zero |
| `RadioImportIdempotencyTest` | Running twice yields no duplicate rows and no duplicate pivot rows; `local_ok` set between runs survives the second |
| `RadioImportFreshTest` | `--fresh` removes imported rows **and leaves all 20 seeded favourites intact** |
| `RadioProbeTest` | Parses an Icecast `status-json.xsl` payload (object *and* array `source` forms); records listeners and peak; a non-JSON response yields `probe_ok` from the fallback with `listeners` left null; a timeout yields `probe_ok=false`; `is_alive` is never modified |
| `RadioProbeScopeTest` | With no selector, probes only favourites; `--limit`/`--country`/`--genre` narrow correctly; never issues 51k requests |
| `RadioLibraryTest` (existing) | Still passes; seeded rows now carry `source='seed'`, `is_favourite=true` |

## Out of scope — belongs to Spec 2

- Any UI change. The page continues to render the 20 favourites.
- The search/filter/paginate API and the genre picker.
- The player's working-set refactor.
- The playback-outcome endpoint that writes `local_ok`.
- Sorting and filtering UI for popularity or listeners.
- Scheduled/automatic re-imports. The command is run manually.

## Risks

| Risk | Mitigation |
|---|---|
| A full import is ~52 HTTP requests and a large write volume; a mid-run failure leaves partial data | Upsert is idempotent — re-running completes the import. The command reports the failing offset. |
| Radio Browser rate-limits or blocks the import | Descriptive User-Agent per their policy; single-threaded paging with no concurrency. `--limit` allows small trial runs. |
| The genre taxonomy proves wrong after seeing real data | Taxonomy lives in config, not a migration. Re-running `GenreSeeder` plus the import re-syncs without schema changes. |
| Probing third-party stream servers could look abusive | Scoped by default to favourites, concurrency capped at 10, short timeouts, never a full-catalogue sweep. |
| `listeners` is null for most stations, tempting a misleading "sort by listeners" | Spec 2 may only offer that sort as a filtered view of rows where `listeners IS NOT NULL`; recorded here as a constraint on the next spec. |
| MySQL `utf8mb4` and station names containing emoji/RTL text | The existing connection is already `utf8mb4`; names are truncated by character count, not bytes. |
