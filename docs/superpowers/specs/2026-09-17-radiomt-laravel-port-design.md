# RadioMT → Laravel Port — Design

Date: 2026-09-17
Status: Approved, ready for implementation planning

## Goal

Port the RadioMT React SPA (`D:\Proiecte 2026\RadioMT`) into this Laravel 13
application, reproducing its behaviour and appearance, with the 20 radio
stations promoted from a hardcoded JavaScript array to a database-backed
library.

"1:1" governs what the user sees and can do. It does not govern the
implementation: the frontend is rewritten from React/TypeScript to Blade +
Alpine.js + Tailwind 4, and four defects in the original are fixed (see
[Deviations](#deviations-from-the-original)).

## Source inventory

The original is a Vite + React 18 + TypeScript SPA with no backend.

| Source file | Lines | Role |
|---|---|---|
| `src/radios.js` | 157 | 20 stations: `id, title, artist, genre, image, url` |
| `src/pages/Main.tsx` | 27 | Composes the five components, owns `showUI` |
| `src/components/player/Player.tsx` | 155 | Audio element, transport controls, volume, spacebar |
| `src/components/radiosList/RadioList.tsx` | 56 | Clickable grid of all stations |
| `src/components/settings/Settings.tsx` | 55 | Gear button, slide-in menu, Hide/Show UI |
| `src/components/ThemeMenu/ThemeMenu.tsx` | 39 | 4 themes → `localStorage` + `data-theme` on `<body>` |
| `src/components/BackgroundMenu/BackgroundMenu.tsx` | 63 | 8 background category buttons |
| `src/components/background/background.tsx` | 106 | tsParticles star field + Unsplash background |
| `src/components/footer/Footer.tsx` | 20 | Author credit, GitHub + LinkedIn links |
| `src/utils/radio.ts` | 46 | Imperative DOM setters for the player |
| `src/utils/unsplashBackgroundGeneration.ts` | 10 | `fetch` to `source.unsplash.com` |
| `src/index.css` | 165 | Theme custom properties, 4 `[data-theme]` blocks, reset |
| 7 component CSS files | ~455 | Layout, spacing, responsive breakpoints |

## Architecture

### 1. Data layer

**Migration** `create_radios_table`:

| Column | Type | Notes |
|---|---|---|
| `id` | `id()` | Seeded explicitly to preserve source ids |
| `title` | `string` | |
| `artist` | `string` | Shown as "Genre: …" by the player (see the note under Deviations) |
| `genre` | `string` | Shown by the radio list |
| `image` | `string` | Absolute URL to external artwork |
| `url` | `string` | Absolute URL to the audio stream |
| `timestamps` | | |

`artist` and `genre` are both kept even though they hold identical values in
all 20 source rows. Dropping either would change the shape the original
exposes, and the two are read by different components.

**Model** `app/Models/Radio.php` — `$fillable` for the five content columns.
No casts needed; every column is a string.

**Seeder** `database/seeders/RadioSeeder.php` — the 20 stations transcribed
verbatim from `radios.js`, including their original ids. **The source ids run
1–6 and 8–21; `id: 7` does not exist.** The seeder writes ids explicitly
rather than relying on auto-increment, so the gap is preserved and every
station keeps the id it had in the original. Registered from
`DatabaseSeeder`.

**Factory** `database/factories/RadioFactory.php` — for tests only.

Ordering is `orderBy('id')`, which reproduces the source array order. The
player's next/previous walk a zero-based position in that ordered list, not
the id, so the id gap is invisible to navigation.

### 2. HTTP layer

All routes live in `routes/web.php`. This skeleton ships no `routes/api.php`,
and creating one means `php artisan install:api`, which pulls in Sanctum —
unnecessary machinery for two public read-only endpoints.

| Route | Action | Response |
|---|---|---|
| `GET /` | `RadioController@index` | `radio.index` view, radios passed in |
| `GET /api/radios` | `RadioController@list` | JSON array of stations |
| `GET /api/background?query=` | `BackgroundController@show` | `{"url": "…"}` |

`GET /` replaces the default `welcome.blade.php`, which is deleted.

The station list is passed into the Blade view directly, so the app renders
and is playable without a round trip. `/api/radios` exists as a public
addition to the original, per the "library of radios" requirement.

### 3. Unsplash background

The original calls `https://source.unsplash.com/random/3840x2160/?{query}`.
That endpoint has been retired by Unsplash and returns **HTTP 503** as of
2026-09-17 — verified against the live host — so the feature is broken in the
original today.

`BackgroundController@show` replaces it with the official Unsplash API:

- `GET https://api.unsplash.com/photos/random` with `query`, `orientation=landscape`
- `Authorization: Client-ID {key}` header, key from `config('services.unsplash.access_key')`,
  backed by `UNSPLASH_ACCESS_KEY` in `.env` (added to `.env.example` too)
- Returns `{"url": urls.full}`
- Responses cached per query for 1 hour, to stay inside Unsplash's
  50 requests/hour demo-tier limit
- The `query` parameter is validated against the fixed list of eight
  categories the UI offers, so the endpoint cannot be used as an open proxy

**Failure handling** — the endpoint degrades to the bundled local image
(`src/assets/images/bgImage1.jpg`, copied to `public/images/`) rather than
erroring, when:

- no API key is configured (the expected state until a key is registered)
- Unsplash returns a non-2xx status or the request times out
- the response JSON lacks the expected `urls.full` key

This keeps the app fully functional with no Unsplash account, which is also
what the CSS already falls back to — `background.css` sets `bgImage1.jpg` as
the element's `background-image`.

### 4. Frontend

```
resources/views/
  radio/
    index.blade.php              page shell, <body data-theme>, composes partials
    partials/
      background.blade.php       .background div + #tsparticles mount point
      player.blade.php           artwork, title, genre, transport, volume
      radio-list.blade.php       @foreach over stations
      settings.blade.php         gear button + slide-in menu
      theme-menu.blade.php       4 theme buttons
      background-menu.blade.php  8 category buttons
      footer.blade.php           credit + social links
  components/
    icon/                        8 anonymous Blade components, inline SVG
      play, pause, forward, backward, shuffle, gear, github, linkedin

resources/js/
  app.js                         Alpine bootstrap, component registration
  radio/
    player.js                    Alpine: radios, index, isPlaying, volume, uiHidden
    settings.js                  Alpine: menuOpen, theme, background selection
    particles.js                 vanilla tsparticles init

resources/css/
  app.css                        Tailwind import, @theme tokens, [data-theme] blocks
```

New npm dependencies: `alpinejs`, `tsparticles`. FontAwesome is **not**
installed — the eight icons the app uses are inlined as SVG Blade components,
which removes a webfont dependency and keeps the icons themeable via
`currentColor`.

`vite.config.js` gains the `Sono` font via the existing `bunny()` plugin,
replacing the original's Google Fonts `@import`. Sono is the body font in
`index.css`; `Open Sans` is declared on two containers but every visible
element overrides it, so it is dropped.

### 5. Theming

The four themes are the part most at risk from a Tailwind rewrite, so the
colour system is carried over as CSS custom properties rather than
translated into utility classes:

- The hue/saturation/lightness/opacity scale variables (`--blue-hue`,
  `--saturation-50`, `--light-30`, `--opacity-9`, …) are copied verbatim into
  a plain `:root` block. The theme blocks are written in terms of them, so
  they must exist unchanged.
- The twelve semantic colours become Tailwind 4 `@theme` tokens, so utilities
  like `bg-menu` and `text-app` compile to `var(--color-menu)` and stay
  runtime-swappable:

  | `@theme` token | Original variable |
  |---|---|
  | `--color-app-bg` | `--background-color` |
  | `--color-app-text` | `--text-color` |
  | `--color-player` | `--player-color` |
  | `--color-button` | `--button-color` |
  | `--color-menu` | `--background-menu` |
  | `--color-menu-hover` | `--hover-background-menu` |
  | `--color-btn` | `--primary-button` |
  | `--color-btn-border` | `--primary-button-border` |
  | `--color-btn-hover` | `--hover-primary-button` |
  | `--color-icon-hover` | `--hover-button-color` |
  | `--color-thumb` | `--hover-thumb-color` |
  | `--color-gear` | `--settings-button-color` |

- The `[data-theme='dark']`, `['crimson']`, `['light']`, `['blue']` blocks
  are transcribed with their HSLA values unchanged, overriding those tokens.

Everything else — layout, flex, spacing, sizing, border radius, transitions,
the three responsive breakpoints at 1200px / 800px / 720px — is rewritten as
Tailwind utilities in the Blade markup.

Two rules resist utilities and stay as a small `@layer components` block in
`app.css`: the `input[type=range]` webkit thumb pseudo-element, and the
`@keyframes spin` used by the gear icon.

The active theme is read from `localStorage` (default `blue`, matching the
original) and written to `document.body.dataset.theme`. To avoid a
flash of the wrong theme on load, a tiny inline script in `<head>` applies
the stored theme before first paint — the original does this in a `useEffect`
and flashes.

### 6. State

One Alpine component owns playback:

```js
{
  radios: [],        // injected from the server
  index: 0,          // position in radios, not a station id
  isPlaying: false,  // mirrors the audio element's real state
  volume: 0.1,
  uiHidden: false,
  get current() { return this.radios[this.index] }
}
```

The player and radio list bind to it (`x-text="current.title"`,
`:src="current.url"`). This replaces `utils/radio.ts`, whose six functions all
reach into the DOM by selector (`document.querySelector('audio').src = …`) —
which is why the original renders empty `<h2 class="title">` elements and
fills them imperatively. Rendered output is the same; the data now flows one
way.

`settings.js` owns `menuOpen`, the active theme, and background selection.
Hide/Show UI is a boolean on the player component that both the player and
the list bind to, matching the original's `showUI` in `Main.tsx`.

## Behaviour to preserve

- Starts on a **random** station at volume **0.1**
- Play / pause, next, previous, shuffle
- Next and previous wrap around both ends of the list
- Spacebar toggles playback
- Volume slider, 0 to 1 in 0.01 steps
- Clicking any station in the list plays it immediately
- Gear opens a slide-in settings panel; X closes it
- 4 themes, persisted across reloads
- 8 background categories
- Hide UI hides the player and the station list; the label becomes "Show UI"
- Footer credit and social links
- tsParticles star field: 50 white stars, size 4, opacity 0.8, speed 2,
  rotating clockwise at speed 5, no linked lines, retina detection on —
  the options object is carried over unchanged
- On screens ≤720px the settings bar moves to the bottom and the footer hides

## Deviations from the original

Four defects, approved for fixing rather than reproduction.

**Bug 1 — the spacebar handler fires on every key.**
`Player.tsx:22` reads:

```js
event.code === 'Space' && state.audioIsRunning ? pauseAudio() : playAudio();
```

The ternary tests the whole conjunction, so any key that is not Space — or
Space while paused — falls to `playAudio()`. Typing anything starts playback.

*Fix:* return early unless `event.code === 'Space'`, then toggle. Also
`preventDefault()` to stop the page scrolling, and ignore the event when
focus is inside the volume slider, where Space would otherwise both toggle
playback and activate the control.

**Bug 2 — the volume slider always reads zero.**
`Player.tsx:139` binds `value={audioRef.current ? audioRef.current.volume : 0}`.
The ref is null on first render, so the slider renders at 0 while audio plays
at 0.1, and never re-renders from the ref afterwards.

*Fix:* bind the slider to the component's `volume` state, which is the same
value that is written to the audio element.

**Bug 3 — a hardcoded station autoplays before the random pick.**
`Player.tsx:150` renders `<audio src={list[2].url} autoPlay />`, so station
index 2 (Heart) begins loading and playing on mount, and is then replaced by
the random station chosen in `useEffect`.

*Fix:* the random station is chosen before the audio element gets a `src`.

**Bug 4 — `isPlaying` is optimistic.**
`playAudio()` sets `audioIsRunning: true` unconditionally, but `startRadio()`
swallows the rejected promise browsers return when autoplay is blocked or a
stream is unreachable. The button then shows pause while nothing is playing.

*Fix:* derive `isPlaying` from the audio element's own `play`, `pause` and
`error` events, so the control always reflects reality.

**Not a bug, noted for accuracy:** `Player.tsx:47` passes `radio.artist` to
`setRadioGenre` while `RadioList.tsx:15` passes `radio.genre` to the same
function. The two columns hold identical values in all 20 rows, so this is
invisible. Both are carried over as-is.

## Testing

Pest feature tests, using `RefreshDatabase`. `phpunit.xml` already pins tests to
in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), so the suite
never touches the MySQL `radio` database used for local development:

| Test | Asserts |
|---|---|
| `RadioSeederTest` | Seeder creates exactly 20 stations; ids 1–6 and 8–21 are present and 7 is absent |
| `RadioModelTest` | Fillable attributes persist; default ordering is by id |
| `HomePageTest` | `GET /` is 200, uses the `radio.index` view, and the response contains every station title |
| `RadioApiTest` | `GET /api/radios` returns 200, a 20-element array, and the exact key set per element |
| `BackgroundApiTest` | With `Http::fake()` returning a photo payload, responds with that URL; a non-2xx upstream response falls back to the local image; a missing API key falls back without any outbound request; an unknown `query` value is rejected |

Streams and artwork are external URLs and are never fetched in tests.

## Out of scope

- Admin CRUD for stations — the library is seeded and read-only
- Authentication — every route is public, as in the original
- Preserving the React or TypeScript source in any form
- Deployment, PWA manifest, or favicon work beyond copying the existing assets
