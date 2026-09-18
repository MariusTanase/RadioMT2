# Browsing at Scale — Design (Spec 2 of 2)

Date: 2026-09-18
Status: Approved, ready for implementation planning

## Goal

Make the ~51,600-station catalogue browsable, and rebuild the page as **The
Tuning Deck**.

This spec owns the **technical architecture**: routes, the query contract,
caching, the Alpine controller, and testing. It does **not** re-decide any
visual question. Every appearance, interaction, state and typography decision
is already settled in:

> `docs/superpowers/specs/2026-09-18-radio-page-design-direction.md`

That document is the binding authority for *what it looks like and how it
behaves*. This one is the binding authority for *how it is built*. Where they
appear to conflict, the design direction wins on presentation and this spec
wins on data access.

**Depends on Spec 1** (`2026-09-18-radio-library-at-scale-design.md`): the
`radios` catalogue columns, the `genres` table and pivot, and the imported
stations. Spec 2 cannot ship before Spec 1's import has run.

## What changes structurally

The current page embeds every station in the HTML and renders all of them.
That inverts:

| | Today | After |
|---|---|---|
| Station data | All rows in `window.RADIO_STATIONS` | One page (50 rows) of server-rendered HTML |
| Filtering | None | Server-side, seven params |
| Player's `stations` | The whole library | The **tuned set** — the current filtered result |
| Navigation | None | URL-driven, `pushState`, fragment swap |

The player's transport (`next`, `previous`, `shuffle`) must therefore operate
over the tuned set rather than a client-held array, which is the single
largest behavioural change in this spec.

## Routes

| Route | Action | Returns |
|---|---|---|
| `GET /` | `RadioController@index` | Full page: Deck + Tune bar + `#catalogue` |
| `GET /api/catalogue` | `CatalogueController@index` | **HTML fragment** for `#catalogue` |
| `GET /api/catalogue/random` | `CatalogueController@random` | JSON: one random station from the tuned set |
| `POST /api/stations/{radio}/unreachable` | `StationHealthController@unreachable` | `204` |
| `GET /api/radios` | `RadioController@list` | Unchanged — kept for compatibility |

All in `routes/web.php`. The app remains public and unauthenticated.

### The query contract

Seven parameters, which **always AND together**. They are parsed once by a
single value object, `App\Support\CatalogueFilter`, used by every route above
so the fragment, the random endpoint and the full page can never disagree.

| Param | Type | Default | Notes |
|---|---|---|---|
| `q` | string | — | Min 2 chars, else ignored. `title LIKE %q%` |
| `genre` | genre slug | — | Must exist in `config('radio-genres.genres')`, else ignored |
| `country` | ISO alpha-2 | — | Mutually exclusive with `region` |
| `region` | continent key | — | Expands to that continent's country codes |
| `language` | string | — | Exact match |
| `sort` | enum | `played` | `played`, `voted`, `bitrate`, `az` |
| `alive` | bool | `1` | `0` includes known-dead streams |
| `fav` | bool | `0` | `1` restricts to `is_favourite` |
| `page` | int | `1` | 50 rows per page |

**Unknown or malformed values are ignored, never fatal.** A hand-edited URL
with `?genre=nonsense` renders the unfiltered catalogue, not a 500 or a 404 —
this is a public page reachable by link and by crawler.

### Sort mapping

| `sort` | Order |
|---|---|
| `played` | `click_count DESC, votes DESC, id ASC` |
| `voted` | `votes DESC, click_count DESC, id ASC` |
| `bitrate` | `bitrate DESC, click_count DESC, id ASC` |
| `az` | `title ASC, id ASC` |

Every sort ends in `id ASC`. Without a unique tiebreaker, rows shift between
pages when the leading column ties — and `click_count` ties constantly across
51,600 rows, so page 2 would silently repeat and skip stations.

## Continents

The design's country `<select>` groups 241 countries by continent, but Radio
Browser supplies only `countrycode`. Nothing in the app maps one to the other.

`config/continents.php` adds that mapping: `['DE' => 'europe', 'JP' => 'asia', …]`,
covering every ISO 3166-1 alpha-2 code Radio Browser emits, plus an
`unknown` bucket. Seven continent keys: `africa`, `americas`, `asia`,
`europe`, `oceania`, `antarctica`, `unknown`.

A country code absent from the map falls into `unknown` rather than
disappearing. Its group renders last and is omitted entirely when empty.

## Caching

Three cached aggregates. All are **derived data with a TTL** — a cold cache
costs a slow first request, never a wrong page.

| Key | TTL | Contents |
|---|---|---|
| `catalogue:genre-counts` | 24h | `{genre_slug => alive_count}` for the unfiltered catalogue |
| `catalogue:country-counts` | 24h | `{country_code => alive_count}`, for the select's labels |
| `catalogue:languages` | 24h | Distinct languages with counts, for the unfiltered fallback |

`radios:import` flushes all three on completion, so a fresh import never
serves stale counts.

**Scoped counts** — the per-genre counts shown when a country/language/search
filter is active — are computed per request in **one** grouped query against
the already-filtered set, never 27 separate counts. Per the design, if that
query exceeds its budget the band falls back to the global cached counts and
marks them with a hairline underline; it never spins and never blanks.

## Search at 51,600 rows

`title LIKE '%q%'` cannot use a B-tree index because of the leading wildcard,
so it is a table scan. At 51,600 rows on MySQL 8 that is single-digit to low
tens of milliseconds — acceptable, and measured rather than assumed during
implementation.

A MySQL `FULLTEXT` index is deliberately **not** added: it would not work on
SQLite, so the test suite and development would diverge on the one behaviour
most worth testing. If search becomes slow at a larger catalogue size, that
is the moment to revisit it, not now.

The minimum query length of 2 characters exists to stop a single-character
`%a%` from matching essentially the whole catalogue.

## The player's tuned set

The player currently holds every station and walks an array. It now holds
**the current page** plus the means to move beyond it.

```js
{
  stations: [],       // the 50 rows of the current page, from the fragment
  index: 0,
  total: 0,           // total rows in the tuned set, from the server
  page: 1,
  lastPage: 1,
  // … isPlaying, volume, uiHidden, current unchanged
}
```

- **`next()` / `previous()`** walk the current page. At either edge they fetch
  the adjacent page and continue at its first/last row. At the very ends of
  the tuned set they wrap, matching today's behaviour.
- **`shuffle()`** calls `GET /api/catalogue/random` with the active filters and
  plays what comes back. It does not pick from the current 50 — "surprise me
  inside Pop" must reach all 9,041, not the 50 on screen.
- **Playback survives navigation.** The `<audio>` element and the Deck live
  **outside** `#catalogue`, so a fragment swap never touches them. Changing
  genre while a station plays does not interrupt it.

### Playback failure reporting

When playback fails — the `error` event, a rejected `play()`, or no `playing`
event within 8 seconds — the Deck shows the design's inline message and the
client `POST`s to `/api/stations/{radio}/unreachable`. That endpoint:

- sets `local_ok = false` and `local_checked_at = now()`
- **never** touches `is_alive`, which remains Radio Browser's verdict
- is rate-limited to **20 requests per minute per IP** (`throttle:20,1`)
- returns `204` always, including for an unknown id — it is telemetry, and a
  probing client learns nothing from its response

A successful `playing` event reports the converse, setting `local_ok = true`.

**This is a public unauthenticated write, and it is treated as advisory.**
`local_ok` may demote a station in ordering; it must never delete one, never
hide one by default, and never override `is_alive`. The worst a malicious
client achieves is mislabelling stations it could have mislabelled by simply
playing them.

## File structure

**Backend**

| File | Responsibility |
|---|---|
| `app/Support/CatalogueFilter.php` | Parse and validate the seven params; build the query |
| `app/Http/Controllers/CatalogueController.php` | Fragment + random endpoints |
| `app/Http/Controllers/StationHealthController.php` | Playback outcome reporting |
| `app/Support/CatalogueCounts.php` | Cached genre/country/language aggregates |
| `config/continents.php` | ISO alpha-2 → continent |
| `app/Http/Controllers/RadioController.php` | Render the full page (modify) |
| `routes/web.php` | Four new routes (modify) |

**Views** — per the design direction's layout section

| File | Responsibility |
|---|---|
| `resources/views/radio/index.blade.php` | Page shell: Deck, Tune bar, `#catalogue` (modify) |
| `partials/deck.blade.php` | Now-playing Deck |
| `partials/tune-bar.blade.php` | Search, country, language, sort, on-air, favourites |
| `partials/catalogue.blade.php` | The swappable fragment: tokens, count line, manifest, folio |
| `partials/genre-links.blade.php` | The 28 links, included by both band and overlay |
| `partials/manifest.blade.php` | The 50 rows |
| `partials/folio.blade.php` | Paging control with `Go to` |
| `partials/empty-state.blade.php` | The generated drop-one-constraint buttons |

**Frontend**

| File | Responsibility |
|---|---|
| `resources/js/radio/catalogue.js` | The `catalogue` Alpine controller: navigate, abort, popstate |
| `resources/js/radio/player.js` | Tuned-set transport, failure reporting (modify) |
| `resources/css/app.css` | Deck/manifest/band styles using existing tokens only (modify) |

**No new CSS custom properties.** The design direction states every colour
maps onto the existing eleven tokens, so all four themes keep working with no
per-theme work. A new token would need defining five times and is a signal the
design was not followed.

## Testing

Pest, in-memory SQLite, `Http::fake()` where relevant.

| Test | Asserts |
|---|---|
| `CatalogueFilterTest` | Each param parsed; unknown genre/country/sort ignored not fatal; `q` under 2 chars ignored; `alive` defaults on; `country` and `region` mutually exclusive |
| `CatalogueQueryTest` | Every filter narrows correctly; filters AND together; each sort orders as specified **and every sort tiebreaks on `id`** |
| `CataloguePagingTest` | 50 per page; page 2 continues without repeating or skipping a row across a `click_count` tie; out-of-range page yields an empty manifest, not an error |
| `CatalogueFragmentTest` | `/api/catalogue` returns a fragment, not a full document; contains the count line, token row and folio; renders the empty state with one drop-constraint button per active filter |
| `CatalogueRandomTest` | Returns a station from the tuned set, honouring every active filter; 404 when the set is empty |
| `CatalogueCountsTest` | Counts cached; import flushes them; scoped counts reflect the active filter |
| `StationHealthTest` | Sets `local_ok`; **never** changes `is_alive`; unknown id still returns 204; throttles past 20/min |
| `ContinentMapTest` | Every country code present in the seeded/imported data maps to a continent or to `unknown`; no code is silently dropped |
| `RadioPageTest` (existing) | Still passes — `/` renders 200 with the Deck |

**The existing test that asserts all 20 station titles appear in `/` must be
revisited.** With the catalogue paginated and favourites no longer the whole
library, that assertion no longer describes the page. It becomes: the first
page of the default view renders 50 rows, and the favourites are reachable via
`?fav=1`.

## Out of scope

- Any change to `radios:import` or `radios:probe` beyond flushing the count caches.
- Sub-genres, per-country facet counts, an A–Z index — explicitly rejected by the design.
- Infinite scroll — explicitly rejected; it fights the audio stream for bandwidth.
- Audio-reactive visuals — rejected: `crossOrigin` on the playback element can silence
  CORS-less streams, and the effect would be inert for most of the catalogue.
- User accounts, saved favourites beyond the seeded 20, playlists.

## Risks

| Risk | Mitigation |
|---|---|
| The Deck reads colder than the original 20-station mixtape — the design's own stated biggest risk | Build the Deck first and review it in all four themes over a real background **before** building the manifest. It is an aesthetic risk, so it needs eyes, not more planning. |
| Fragment swapping diverges from the full-page render, so a deep link renders differently from a navigated view | The fragment partial is `@include`d by the full page. One template, two entry points — never two implementations. A test asserts a deep link and a navigated view produce the same manifest. |
| A stale in-flight response overwrites a newer one | `AbortController` plus a monotonic token; a stale token is discarded even if it arrives last. Specified in the design, tested in the controller. |
| The public `unreachable` endpoint is abused | Rate-limited, advisory only, never overrides `is_alive`, never deletes or hides. |
| `LIKE '%q%'` degrades as the catalogue grows | Measured during implementation at real scale. FULLTEXT is rejected for now because it would not work on SQLite and would split test from production behaviour. |
