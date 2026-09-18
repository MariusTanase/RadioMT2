# RadioMT2 — Final Page Design Direction

**Date:** 2026-09-18
**Status:** Approved for build. This is the design that gets implemented.
**Audience:** the engineer implementing it. No prior context assumed.
**Scope:** the public radio page (`resources/views/radio/*`). Settings panel internals,
background categories, theme switching and the footer are unchanged except where stated.

---

## 1. The chosen direction: **The Tuning Deck**

The page stops being a library of artwork cards and becomes the fascia of a broadcast
tuner: one large, calm now-playing readout at the top, a latched genre band under it, and
the catalogue itself as a **manifest** — a numbered, hairline-ruled, single-column list of
rows with tabular numerals — instead of a grid of circular thumbnails.

It won because it is the only direction whose central structure survives 51,600 rows without
needing a framework: a server-paginated list needs no virtualisation, no client-side index
and no artwork per row (and artwork is missing or broken for most of the catalogue, so a
card grid would ship mostly broken). It also won on buildability — every mechanic in it is
reachable with Blade partials, one Alpine controller and `fetch`, with zero new colour
values outside the eleven existing tokens.

---

## 2. What was grafted in from the runners-up

The Tuning Deck as originally drawn was correct about scale and **cold** about feel. Three
grafts fix that, plus four that fix real gaps.

| Grafted idea | From | Why |
|---|---|---|
| **The station's own name is the artwork** — the name is the largest, quietest thing on the page, set alone with real breathing room, not crammed into a dense header | The Quiet Room | The Deck had no moment of visual rest. This is the counterweight that keeps it a personal radio instead of a control room. |
| **Keep one circular artwork — only for the station playing** (120px, with a monogram fallback) | Skywave / The Longitude Dial | Retains the original app's single most-loved element at the one place where a broken image is recoverable: one image, one request, one fallback. Rows stay artwork-free. |
| **Three-bar equalizer glyph as the "this row is playing" marker** | Skywave | A silhouette cue that scales to 50 rows, costs nothing, needs no audio analyser, and gives the playing row a non-colour identifier. |
| **Scale disclosed once, in a sentence** ("3,214 stations in Jazz · Germany") rather than as chrome | The Quiet Room | Tells the truth about catalogue size without turning the page into a dashboard. |
| **The genre list is a real list of links first; the horizontal band is an enhancement over the same links** | The Quiet Room | One implementation, keyboard/screen-reader-first, and it fixes "28 scroll-snapped buttons is not a car radio". |
| **Language popover scoped to languages actually present in the current result set** | The Longitude Dial | Never renders a global 50+ item menu. |
| **Sticky condensed player bar that appears once the Deck scrolls away** (IntersectionObserver) | Skywave | Transport must never require scrolling back up when you are 40 rows down. |

Deliberately **not** grafted: the audio-reactive star field and the artwork amplitude ring
(Skywave), the longitude dial (The Longitude Dial), the peripheral-preview genre dial and
the panel-less hero (The Quiet Room). Reasons in §11.

---

## 3. Every fatal flaw raised, and its resolution

Flaws are listed by the direction they were raised against. None are dropped.

### 3.1 Against The Tuning Deck (the chosen direction)

| # | Flaw | Resolution |
|---|---|---|
| C1 | 4-digit zero-padded row index (`00842`) cannot express 51,600 | Zero-padding is removed. The index cell shows the row's **rank inside the current result set**, unpadded, right-aligned, `tabular-nums`, `min-width: 5ch`. Page 3 of a Jazz result starts at 101. Ranks run 1…N where N is the filtered count, so the widest value the page can ever render is 51,600 → 6 characters with the thousands separator; 5ch plus the separator fits within the 64px index column. |
| C2 | No empty / zero-result state | Fully designed. See §6.5. |
| C3 | No cold-start / idle state (nothing playing on first load) | Fully designed. See §7.1. |
| C4 | Reads as a control room, not a calm personal radio | Structural fix, not a coat of paint: the Deck gets **one quiet region** — 120px circular artwork, station name at 40px in Sono 300, 32px of vertical air above and below, no borders and no rules anywhere inside it. Density starts only at the Band. The metadata cells were reduced from five to three. See §4.2. |
| C5 | "Car-radio presets" metaphor breaks at 28 horizontally-scrolled buttons | The metaphor is dropped as a claim; the mechanics are kept and improved. The Band is a single-row scroller of genre links **plus a persistent `All genres` control that opens a 3-column grid overlay of all 27 with counts**, so no one ever has to scroll horizontally to find a genre. The overlay and the band render the same `<a href>` links from the same Blade partial. |
| C6 | Level meter driven by Web Audio is inert for most streams, yet is sold as the signature moment | **The Web Audio analyser is cut from v1 entirely.** The meter becomes a CSS-only three-bar equalizer glyph driven by `isPlaying`. It indicates *playing*, which is true 100% of the time, instead of *amplitude*, which would be unavailable most of the time. The page's signature is the manifest and the quiet Deck, not the meter. |
| C7 | Row play affordance is hover-only; phones have no hover | **The whole row is the play target** at every width (button semantics, full-width click area, 48px min height desktop / 56px mobile). The index cell swapping to a play glyph is a `@media (hover: hover)` embellishment only; on touch the index stays a number and the row is still tappable. |
| C8 | `crossOrigin="anonymous"` on the playback element can abort loading CORS-less streams — i.e. the meter can silence the audio | Moot: no analyser, so `crossOrigin` is **never set on the playback `<audio>` element**. This is a hard rule in the build: touching `crossOrigin` on that element is a regression. |
| C9 | Stacked sticky fascia could eat 250–300px of a 400px-wide phone viewport | The full Deck is **not sticky**. It scrolls away and is replaced by a 56px condensed bar. Mobile sticky chrome totals **56px (condensed bar) + 44px (Band) = 100px**, leaving ≥ 560px of manifest on a 667px-tall phone. Numbers in §4.6. |
| C10 | URL-state + fetch-swap logic built ad hoc per widget | One Alpine controller, `catalogue`, owns all of it: URL building, `history.pushState`, `popstate`, `AbortController`, fragment swap, loading flag. Every control (search, selects, band links, folio links, sort) calls the same `navigate(params)` method. See §6.1. |

### 3.2 Against Skywave

| # | Flaw | Resolution |
|---|---|---|
| S1 | No active-filter summary; deep in pagination you lose track of what is filtered | The **Tune bar shows active filters as removable tokens** (`Jazz ×`, `Germany ×`, `"bbc" ×`), and the manifest's count line restates the full scope in words. Both are visible on every page. See §6.4. |
| S2 | No empty state | §6.5. |
| S3 | Shuffle / next / previous undefined once the client no longer holds the list | Defined precisely in §7.4 — the **tuned set** is the current filtered+sorted result set; next/prev walk it across page boundaries; shuffle is a server endpoint. |
| S4 | The one bold idea lives in the background, where nobody is looking | We spend the design budget on the foreground: the manifest's typography and the quiet Deck. The star field stays exactly as it is today, ambient and unreactive. |
| S5 | Invented cosmetic "frequency numerals" (`House 97.3`) | Rejected outright. Every number on this page is a real number. |
| S6 | Zebra striping is a stock admin convention | Rejected. Rows are separated by a single hairline: `color-mix(in srgb, var(--color-app-text) 12%, transparent)`. No alternate-row fills anywhere. |
| S7 | 28 identical pills are hard to scan | Mitigated by the `All genres` grid overlay (C5), by live counts on every genre, and by keeping the band's scroll position anchored on the active genre when the page renders. |
| S8 | Query-param navigation as plain links would full-reload and kill playback | **Non-negotiable rule: navigation never reloads the document.** Genre/search/filter/sort/page controls are real `<a href>`/`<form>` elements (so they work without JS) but are intercepted by the `catalogue` controller, which fetches a server-rendered fragment and swaps only the manifest + folio + count line. The `<audio>` element lives outside the swapped region and is never touched. |
| S9 | Analyser gated on untested CORS coverage | Moot — analyser cut (C6). |
| S10 | 60fps analyser driven through Alpine reactivity would jank | Moot — no rAF loop exists in this design. The equalizer glyph is a CSS keyframe animation. |

### 3.3 Against The Quiet Room

| # | Flaw | Resolution |
|---|---|---|
| E1 | No ranking; votes/`click_count` unused, so a 9,000-station genre is blind alphabetical paging | **Default sort is `Most played` (`click_count desc, votes desc, id asc`)** everywhere, including the unfiltered default view. Sort options: Most played, Most voted, Highest bitrate, A–Z. The good stations are on page 1 by default. |
| E2 | Transport scope undefined | §7.4. |
| E3 | No empty state | §6.5. |
| E4 | Contradictory: does search compose with genre or replace it? | **Search composes.** All five axes (`q`, `genre`, `country`, `language`, `sort`) AND together, always, with no exceptions. The count line and filter tokens make the composition visible. If a search returns nothing inside the current genre, the empty state offers a one-tap "search all genres instead". |
| E5 | Panel-less hero over an arbitrary background image has unsolved contrast, and the light theme is dark-text-on-white while the other three are light-on-dark | We do **not** go panel-less. The Deck sits on a `--color-menu` panel exactly like today's player card (`--color-menu` is theme-defined at 0.8 alpha and already inverts correctly: near-white in light, near-black in the other three). No scrim is invented; no per-theme branching is needed. |
| E6 | Real station names are messy (ALL CAPS, slogans, tab characters) and break a giant hero type treatment | Hero name is 40px (not 96px), Sono 300, clamped to **2 lines** with `-webkit-line-clamp: 2`, `overflow-wrap: anywhere`, and the title is **trimmed and whitespace-collapsed server-side** before render. Manifest row titles are one line with `text-overflow: ellipsis`. A shouting 60-character name degrades to two tidy lines, not to a broken layout. |
| E7 | 50vh hero + a band leaves 3–5 rows above the fold | The Deck is **280px tall on desktop** (not 50vh), Band 56px, Tune 48px. On a 900px-tall window that is 384px of chrome and ~10 manifest rows above the fold, with the Deck scrolling away entirely after that. |
| E8 | `pushState` + fragment swap + race handling is under-scoped | Scoped explicitly as a build requirement: single controller, `AbortController` per navigation, monotonic request token, `popstate` handler, scroll restoration to the top of the manifest (not the document). §6.1. |
| E9 | The player becomes unreachable while browsing | Condensed player bar (grafted from Skywave), §7.3. |
| E10 | The genre dial needs two separate implementations to be accessible | Avoided: one Blade partial renders one list of links, presented two ways by a CSS/markup wrapper (band and overlay). No bespoke dial widget exists. |

### 3.4 Against The Longitude Dial

| # | Flaw | Resolution |
|---|---|---|
| A1 | Contradictory default state | **Default state is explicit and singular:** no genre, no country, no language, no search; sort = Most played; `alive_only = true`; page 1. The Deck shows the idle readout (§7.1) until the user picks a station or presses play. Nothing autoplays on load. |
| A2 | 241 ticks at 400px is ~1.5px per tick — untappable | The longitude dial is not built. Country is a native grouped `<select>`. |
| A3 | No sort for large intersections | See E1. |
| A4 | Search vs. dial composition undefined | See E4 — everything composes; there is no second, conflicting navigation surface. |
| A5 | No home for region-level intents ("something Nordic") | The country `<select>` is grouped with `<optgroup>` by continent, and each group's first option is a selectable region (`region=europe`), so "anything in Africa" is one selection. |
| A6 | No empty state | §6.5. |
| A7 | Claimed instrument metaphor with no instrument imagery | The Deck does not claim to be a physical object. Its identity comes from typography and hairline rules, both of which actually render. |
| A8 | Flag emoji import dozens of uncontrolled colours into a token-only palette | **No flag emoji anywhere.** Country is shown as the two-letter ISO code in Sono 500 with `letter-spacing: 0.04em` (`DE`, `US`, `BR`), which is also render-stable across Windows/Chrome where regional-indicator glyphs fail. |
| A9 | Mobile mini-bar + bottom sheet is the Spotify pattern | Our condensed bar is **top-docked** (below the Band, above the manifest), has no bottom sheet, and tapping it scrolls the Deck back into view rather than raising a modal. The bottom edge of the viewport stays reserved for the existing settings bar below 720px. |
| A10 | Log-scaled ticks would look uniform | Not built. |
| A11 | Whole concept resting on unproven hand-rolled drag physics with no plan B | Not built. There is no custom gesture control anywhere in this design; the only horizontally-scrolling element uses native overflow scrolling. |
| A12 | Multi-fetch request races | One fetch per navigation, one `AbortController`, one request token. The Band's counts and the language options come **inside the same fragment response**, not as separate requests. |
| A13 | Continuous scroll-linked reactive work fighting the tsParticles loop | No scroll-linked reactive work exists. The only scroll listener is an `IntersectionObserver` sentinel for the condensed bar. Particle count is reduced from 50 to 28 and speed from 2 to 1.2 on this layout (§10.4). |
| A14 | Ambiguous mobile fixed-chrome budget | Stated in hard numbers, §4.6. |

---

## 4. Page layout

### 4.1 Regions, top to bottom

| # | Region | Desktop height | 400px height | Sticky? |
|---|---|---|---|---|
| 0 | **Backdrop** — background image + tsParticles canvas | full-bleed, `z-index: -1` | same | fixed (today's behaviour) |
| 1 | **Deck** — now playing + transport | 280px | 316px | no |
| 1b | **Condensed bar** — appears when the Deck leaves the viewport | 56px | 56px | yes, `top: 0` |
| 2 | **Band** — genre navigation | 56px | 44px | yes, below the condensed bar |
| 3 | **Tune** — search, filters, sort, filter tokens | 48px (+32px token row when tokens exist) | 44px + 40px | no |
| 4 | **Manifest** — the station list | auto | auto | no |
| 5 | **Folio** — pagination + scope line | 56px | 88px (two rows) | no |
| 6 | **Settings gear / panel** and **Footer** | unchanged | unchanged | unchanged |

Content column: `max-width: 1100px`, centred, `padding-inline: 24px` desktop / `16px` at
≤ 640px. The Backdrop is the only full-bleed layer. Every region except the Deck's interior
sits on a `--color-menu` panel so the star field stays faintly visible at the page edges.

### 4.2 Deck (desktop, 280px)

```
+-- bg-menu panel, radius 16px 16px 32px 32px, padding 32px 32px 24px ---------+
|                                                                             |
|   ( O 120px )   Radio Nacional Clasica                     |<   >   >|      |
|   artwork       --------------------------------           2.25rem icons    |
|   circle        [ CLASSICAL ] [ AR ] [ 128k · MP3 ]                         |
|                                                            SH  =====o====   |
|                                                          shuffle  volume    |
+-----------------------------------------------------------------------------+
```

- **Left cluster** (`display: flex; gap: 24px; align-items: center`): 120px circular
  artwork with a 1px `--color-app-text` border (today's treatment, smaller), then the text
  block.
- **Station name**: 40px Sono 300, `line-height: 1.1`, `letter-spacing: -0.01em`, max 2 lines,
  colour `--color-player`. This is the largest and quietest thing on the page. 16px of space
  below it before anything else.
- **Three metadata cells**, in a single row, `gap: 8px`: genre · country code · `128k · MP3`.
  Each cell is a box: `padding: 3px 8px`,
  `border: 1px solid color-mix(in srgb, var(--color-app-text) 18%, transparent)`,
  `border-radius: 4px`, 12px Sono 500, `tabular-nums`. A cell with no data is **omitted**, never
  rendered as an em-dash placeholder.
- **Right cluster**, right-aligned, two rows: transport row (backward / play-pause / forward
  at 2.25rem, `gap: 28px`, hover `--color-icon-hover`), then shuffle + a 160px volume slider
  (`.volume-slider`, unchanged CSS) with `gap: 16px`.
- No rules, no dividers, no borders inside the Deck other than the artwork ring and the three
  cells. This is the page's only low-density region and it must stay that way.

### 4.3 Band (56px)

Full-width panel, `--color-menu`, no radius, hairline bottom border. Inside: a
horizontally-scrolling row (`overflow-x: auto; scrollbar-width: none; scroll-snap-type: x proximity`)
of genre controls, and pinned to the right edge (outside the scroller, with a
`--color-menu` background so it occludes cleanly) a 40px `All genres` button that opens
the overlay. The left edge carries the `All` (clear-genre) control, also pinned outside the
scroller so it never scrolls away.

Each genre control: 96 × 40px, `border-radius: 6px`, `background: --color-btn`,
`border: 1px solid --color-btn-border`, label 13px Sono 600 above an 11px Sono 400 `tabular-nums`
count at 70% opacity. `gap: 8px`.

### 4.4 Tune (48px, + token row)

One row: search input (flex 1, max 420px), then `Country`, `Language`, `Sort` native
selects (each ~160px), then the `On air only` toggle. 12px gaps.
Below it, when any filter is active, a 32px token row: each active filter as a removable
chip (`label ×`, 12px Open Sans, `--color-btn` fill, `--color-btn-border` hairline) plus a
`Clear all` text button.

### 4.5 Manifest + Folio

Count line, right-aligned, 8px above the first row: *"3,214 stations in Jazz · Germany"* —
14px Open Sans 600.

Row grid (desktop, `display: grid`):

| Column | Width | Content | Type |
|---|---|---|---|
| index | 64px | rank in the tuned set, or the equalizer glyph when this row is playing | 13px Sono 500 `tabular-nums`, right-aligned |
| title | `minmax(0, 1fr)` | station name, single line, ellipsis | 15px Open Sans 600 |
| genre | 120px | one genre label | 12px Open Sans 400, 65% opacity |
| country | 56px | ISO code | 13px Sono 500, `letter-spacing: 0.04em` |
| stream | 104px | `128k · MP3` | 13px Sono 500 `tabular-nums` |
| votes | 72px | vote count | 13px Sono 500 `tabular-nums`, right-aligned |
| live | 88px | `● 412` when `listeners` is non-null; **empty** otherwise | 12px Sono 500 `tabular-nums` |

Row height 48px, `padding-inline: 12px`, hairline bottom border, hover
`background: --color-menu-hover`, 150ms ease-out. The playing row carries a **3px left bar in
`--color-btn-border`** and its index cell becomes the equalizer glyph — two cues, one of them
non-colour.

Folio: `Prev` · page numbers with the current one underlined (window of 7, with ellipsis gaps)
· `Next` · a `Go to` number input (64px wide) · `Page 3 of 65` in 13px Sono `tabular-nums`.

### 4.6 400px layout — hard numbers

- **Deck 316px**: artwork 88px centred; name 28px Sono 300, 2-line clamp; metadata cells in
  one row that may scroll horizontally; transport row full width, `justify-content: space-evenly`,
  icons 2rem; shuffle + volume on their own 44px row. Padding 20px 16px.
- **Condensed bar 56px**, sticky `top: 0`: 32px artwork/monogram, title (14px Open Sans 600,
  ellipsis), play/pause 1.75rem, forward 1.75rem. Tapping the title scrolls to the Deck.
- **Band 44px** sticky at `top: 56px`; controls narrow to 84 × 36px, count retained; both
  edges get a 24px `mask-image` fade. `All genres` stays pinned right at 36px.
- **Total sticky chrome: 100px.** On a 667px viewport that leaves 567px — about 10 manifest
  rows at 56px.
- **Tune** stacks: search on row 1 (44px), then a single `Filters` button which opens the
  **existing settings-panel slide-in mechanic** (`translate-x-full` → `translate-x-0`, full-screen
  below 720px) containing Country / Region / Language / Sort / On air only. No new interaction
  pattern is introduced. The token row stays inline and horizontally scrollable.
- **Manifest rows 56px**, two lines: title on line 1 (15px), then `Jazz · DE` on line 2
  (12px Open Sans, 65%). The genre, country, stream, votes and live columns are dropped as
  columns below 640px; only genre + country survive on line 2.
- **Folio** collapses to `Prev` · `Page 3 of 65` (tapping it reveals the `Go to` input) · `Next`,
  44px tap targets.
- The existing settings bar keeps its bottom dock below 720px. The manifest gets
  `padding-bottom: 6rem` so the last row is never under it. Nothing else docks to the bottom.

---

## 5. The genre picker

### 5.1 Source of truth

`config/radio-genres.php`, ordered by `sort` (Pop, Rock, Classic Rock, Alternative, Metal,
Dance, Electronic, House, Jazz, Blues, Classical, Country, Folk, Latin, Reggae, Hip Hop, R&B,
Oldies, 80s, 90s, 70s, Lofi & Chill, Religious, News & Talk, Sport, World, Other). 27 entries,
plus a leading `All`.

### 5.2 Markup and appearance

One Blade partial (`partials/genre-links.blade.php`) emits 28 `<a>` elements with real
`href`s (`?genre=jazz&sort=played`, preserving every other active param and resetting `page`).
It is included twice:

- **Band**: `<nav aria-label="Genres">` wrapping a horizontal scroller. Each link is
  96 × 40px (84 × 36 at ≤ 640px), `--color-btn` fill, 1px `--color-btn-border`, radius 6px,
  label 13px Sono 600, count 11px Sono 400 `tabular-nums` at 70% opacity.
- **Overlay**: the same links in a 3-column grid (2 columns at ≤ 640px), 12px gap, inside a
  `--color-menu` panel that opens from the `All genres` button. Max height 70vh, scrollable.

**Selected state**: `background: --color-btn-hover`,
`box-shadow: inset 0 1px 3px rgb(0 0 0 / 0.35)`, `aria-current="true"`, and a 5px filled dot in
`--color-app-text` before the label. The dot is the non-colour cue — the state must be readable
without relying on the fill alone.

### 5.3 Counts

Counts are **cached aggregates**, never per-request joins. A `genre_counts` cache key (24h TTL,
refreshed by the catalogue import job) holds `{genre_key => alive_count}` for the *unfiltered*
catalogue. When a country/language/search filter is active, the fragment response includes a
second map of counts **scoped to the current filter**, computed in one grouped query against
the already-filtered set, and the band re-renders with those. If that scoped query exceeds its
budget the band falls back to the global counts and marks them with a hairline underline —
never a spinner, never a blank.

### 5.4 Interaction

- Click / tap a genre → intercepted by `catalogue.navigate()`, fragment swap, `pushState`.
  The Deck, the audio element and playback are untouched.
- Selecting the already-selected genre is a no-op (not a toggle-off); `All` is how you clear.
- On render, the band scrolls the selected control into view
  (`scrollIntoView({ inline: 'center', behavior: 'auto' })`).

### 5.5 Keyboard

- The band is a normal list of links: `Tab` enters it, `Tab`/`Shift+Tab` move through, `Enter`
  activates. No roving tabindex, no arrow-key hijacking — arrow keys scroll the container as
  the browser intends.
- `All genres` is a `<button aria-expanded>` controlling the overlay. `Enter`/`Space` opens it,
  focus moves to the first link inside, focus is trapped while open, `Escape` closes and
  returns focus to the button.
- Every link has a visible focus ring:
  `outline: 2px solid var(--color-icon-hover); outline-offset: 2px`.
- Screen readers get the overlay, which is a plain grid of 28 links with counts in the accessible
  name (`Jazz, 1,204 stations`). Nothing is mouse-only.

### 5.6 A genre with 9,000 stations

Nothing about the picker changes — the count reads `9,041` and the click behaves identically.
What makes 9,041 navigable is downstream:

1. **Default sort is Most played**, so the first page is the 50 most-listened stations in that
   genre, not an alphabetical accident.
2. The count line states the size honestly: *"9,041 stations in Pop"*.
3. The Tune bar is the narrowing path — country, language, search — and each is one control away.
4. The Folio's `Go to` input means page 94 of 181 is reachable without 93 clicks.
5. Shuffle (§7.4) picks randomly **within the tuned set**, so "surprise me inside Pop" is one tap.

We explicitly do not add sub-genres, facet counts per country, or an A–Z index. Sort + three
filters + shuffle is the answer.

---

## 6. The browsing model

### 6.1 State, URL, and the single controller

Five query params define a view: `q`, `genre`, `country` (or `region`), `language`, `sort`,
plus `alive` and `page`. They **always AND together**. The URL is the only source of truth —
every view is linkable and back-button-safe.

One Alpine controller, `catalogue`, owns navigation:

- `navigate(patch)` merges the patch into the current params, resets `page` to 1 unless the
  patch contains `page`, builds the URL, calls `history.pushState`, and fetches
  `GET /api/catalogue?<params>` with `Accept: text/html`.
- Each call aborts the previous one (`AbortController`) and carries a monotonically increasing
  token; a response whose token is stale is discarded even if it arrives last.
- The response is a server-rendered fragment containing: the band's scoped counts, the filter
  token row, the count line, the manifest rows and the folio. It replaces `#catalogue` via
  `innerHTML`. The Deck, `<audio>`, settings and footer are outside `#catalogue`.
- `popstate` re-reads `location.search` and fetches without pushing.
- After a successful swap, scroll to the top of `#catalogue` (`scrollIntoView`), never to the
  top of the document — the Deck should not be dragged back into view on every page turn.
- Without JS, the same `<a>`/`<form>` elements perform an ordinary full navigation and the page
  renders identically server-side. Playback does not survive a no-JS navigation; that is
  accepted for the no-JS path only.

### 6.2 Search

Single text input, placeholder `Find a station`. Debounce 350ms after the last keystroke;
`Enter` submits immediately. Matches `title LIKE %q%` (prefix-indexed where possible), scoped
by every other active filter. Minimum 2 characters. The input keeps focus across the fragment
swap (the input lives in the Tune region, which is outside `#catalogue`).

### 6.3 Filters

- **Country**: native `<select>` with `<optgroup>` per continent. Each group's first option is
  the region itself (`All of Europe`), then its countries sorted by station count descending,
  each with its count: `Germany (2,841)`. 241 options in a native control — no custom combobox.
- **Language**: native `<select>` listing **only languages present in the current result set**,
  with counts. Rebuilt from the fragment response on every navigation. Never a global list.
- **Sort**: `Most played` (default, `click_count desc, votes desc, id asc`), `Most voted`,
  `Highest bitrate`, `A–Z`.
- **On air only**: defaults **on** (`is_alive = true`). Turning it off is an explicit opt-in and
  adds a token reading `Including offline ×`.
- **Favourites**: a `Favourites` toggle at the left of the Tune bar; it is just another param
  (`fav=1`) reusing the same manifest and folio.

### 6.4 Staying oriented

Two always-visible affordances, on every page of every result:

1. The **filter token row** in the Tune bar — one chip per active param, each removable with a
   single click, plus `Clear all`.
2. The **count line** above the manifest, restating the scope in a sentence:
   *"3,214 stations in Jazz · Germany · matching \"bbc\""*.

### 6.5 Empty, loading, error, no-artwork and dead-station states

**Empty (zero rows).** The manifest area renders a centred block, 240px tall, no border, no
illustration:

> **Nothing here.**
> No on-air stations match *Jazz · Iceland · "bbc"*.
>
> `[ Search all genres ]` `[ Remove Iceland ]` `[ Include offline stations ]` `[ Clear all ]`

Title 20px Sono 400; body 14px Open Sans; buttons in the standard `--color-btn` style. The
offered buttons are generated from the active params — each one drops exactly one constraint,
widest-first (search → language → country → genre → alive). If only one filter is active, only
`Clear all` is shown. The folio is hidden; the count line reads *"0 stations"*.

**Loading.** No spinner. On navigation the manifest gets `opacity: 0.45` and
`pointer-events: none` after a 120ms delay (so fast responses never flash), and a 2px
progress rule in `--color-btn-border` animates left-to-right across the top edge of `#catalogue`.
Row skeletons are **not** used — the previous page's rows stay visible, dimmed, until replaced.

**Error / timeout.** The dimming clears and an inline strip appears above the manifest:
*"Couldn't reach the catalogue. `[ Retry ]`"* — 14px Open Sans on `--color-menu-hover`,
hairline border. The previous rows stay on screen and remain playable.

**No artwork.** No artwork exists in the manifest at all, so the problem only arises in the Deck
and the condensed bar. Fallback is a **monogram**: the station's first letter (uppercased,
non-alphanumeric stripped; `?` if nothing survives), centred in the circle, 40px Sono 300 in
`--color-player` on `--color-menu-hover`, with the same 1px `--color-app-text` ring. The
`<img>` uses `onerror` to swap to the monogram, so broken URLs and missing URLs look identical.
No broken-image icon ever renders.

**Dead station.** Two layers. (1) `alive=1` by default keeps known-dead streams out of results.
(2) If playback fails anyway (`<audio>` `error`, or `play()` rejects, or no `playing` event
within 8s), the Deck shows a one-line status under the station name:
*"This stream didn't answer. `[ Try the next one ]`"* in 13px Open Sans at 80% opacity, and the
transport stays interactive. We do not auto-skip — silently jumping stations is worse than
saying what happened. The failure is reported to `POST /api/stations/{id}/unreachable` so the
catalogue job can re-check it; no UI acknowledges that.

### 6.6 Paging

Server-side, **50 rows per page**, Laravel's paginator. No infinite scroll: it fights the audio
stream for bandwidth, breaks back-button scroll position, and makes deep pages unreachable.
The client never holds more than one page.

---

## 7. The now-playing player

### 7.1 Idle / cold start

On first load nothing plays and nothing autoplays. The Deck renders its idle readout:

- Artwork circle: empty, 1px ring, no monogram, `--color-menu-hover` fill.
- Name slot: **"Pick a station"** in 40px Sono 300 at 55% opacity.
- Metadata cells: absent.
- Transport: play/pause disabled (`aria-disabled`, 40% opacity); forward/backward/shuffle
  **enabled** — shuffle from idle is the fastest path to sound and is the intended first move.
- Volume slider: live, remembers the last value from `localStorage` (wrapped in try/catch),
  default 0.1 as today.

Once a station is selected the Deck swaps to the full readout with a 200ms opacity settle.

### 7.2 Controls

Every existing control survives, unchanged in behaviour: play/pause, next, previous, shuffle,
the volume slider (`.volume-slider`, existing CSS, `--color-thumb` knob), and the spacebar
toggle with its existing "ignore when focus is in a form control" guard. That guard must also
cover the search input — the existing
`closest('input, button, select, textarea, [contenteditable]')` check already does; do not
weaken it.

### 7.3 Condensed bar

An `IntersectionObserver` on a 1px sentinel below the Deck toggles a 56px sticky bar at
`top: 0`, spanning the content column, `--color-menu`, hairline bottom border:
32px artwork/monogram · title (ellipsis) · play/pause · forward. It fades in over 150ms
(opacity + 4px translate; opacity only under reduced motion). It never appears while the
Deck is visible, and it is hidden entirely when `uiHidden` is set.

### 7.4 Transport semantics at catalogue scale — the **tuned set**

The **tuned set** is the current filtered and sorted result set, identified entirely by the
query params. It may be 51,600 rows or 3.

- **Next / Previous** walk the tuned set by rank, not by page. The controller keeps the current
  row's absolute rank. Moving past the edge of the rows it holds triggers a fetch of the
  adjacent page's rows (`/api/catalogue?...&page=N&rows=json`) and continues; the manifest view
  does *not* jump pages just because playback crossed a boundary — but if the newly playing row
  happens to be on the displayed page, it highlights normally. Next at the last rank wraps to
  rank 1, matching today's wrap-around.
- **Shuffle** calls `GET /api/catalogue/random?<current params>` which returns one row chosen
  uniformly at random from the tuned set (`offset = rand(0, count-1)` against the cached count,
  not `ORDER BY RANDOM()` over 51,600 rows). The Deck swaps to it; the manifest does not move.
- **Changing filters does not stop playback** and does not change what Next will play until the
  user plays something from the new set. The tuned set is re-read at the moment a transport
  button is pressed, so the behaviour is "next within what I'm currently looking at", which is
  the only rule that stays predictable.
- If the tuned set is empty, Next/Previous/Shuffle are disabled with the same 40% treatment as
  the idle play button.

---

## 8. Typography

Two families, already loaded. **Sono** carries every number, control, label and readout.
**Open Sans** carries dense reading content — manifest titles, secondary lines, prose.

| Role | Family | Size / line-height | Weight | Notes |
|---|---|---|---|---|
| Deck station name | Sono | 40px / 1.1 | 300 | `-0.01em`, 2-line clamp, `--color-player`. 28px at ≤ 640px |
| Deck idle name | Sono | 40px / 1.1 | 300 | 55% opacity |
| Deck metadata cell | Sono | 12px / 1 | 500 | `tabular-nums`, bordered cell |
| Deck status line (stream failed) | Open Sans | 13px / 1.4 | 400 | 80% opacity |
| Condensed bar title | Open Sans | 14px / 1.2 | 600 | single line, ellipsis |
| Band genre label | Sono | 13px / 1.2 | 600 | |
| Band genre count | Sono | 11px / 1 | 400 | `tabular-nums`, 70% opacity |
| Tune input / select | Sono | 14px / 1.4 | 400 | |
| Filter token | Open Sans | 12px / 1.2 | 600 | |
| Count line | Open Sans | 14px / 1.4 | 600 | right-aligned |
| Manifest index | Sono | 13px / 1 | 500 | `tabular-nums`, `min-width: 5ch` |
| Manifest title | Open Sans | 15px / 1.3 | 600 | one line, ellipsis |
| Manifest secondary (mobile line 2) | Open Sans | 12px / 1.3 | 400 | 65% opacity |
| Manifest genre column | Open Sans | 12px / 1.3 | 400 | 65% opacity |
| Manifest country code | Sono | 13px / 1 | 500 | `letter-spacing: 0.04em` |
| Manifest stream / votes / live | Sono | 13px / 1 | 500 | `tabular-nums` |
| Folio numerals | Sono | 13px / 1 | 500 | `tabular-nums` |
| Empty-state title | Sono | 20px / 1.3 | 400 | |
| Empty-state body | Open Sans | 14px / 1.5 | 400 | |
| Settings panel | — | unchanged | — | |
| Footer credit | Open Sans | 12px / 1.4 | 400 | unchanged position |

Rules: no uppercase-with-tracking eyebrows anywhere; no italics; no third family; every numeric
column sets `font-variant-numeric: tabular-nums`. Sono is not a monospace — verify column
alignment visually in all four themes before sign-off, and widen a column rather than
letter-spacing it if numerals drift.

---

## 9. Colour → existing tokens

No new colour values. Everything below already exists in `resources/css/app.css` under
`@theme` and the four `[data-theme]` blocks.

| Surface | Token | Notes |
|---|---|---|
| Page base behind the background image + stars | `--color-app` | unchanged |
| All body text, icons at rest | `--color-app-text` | the only resting text colour |
| Deck station name | `--color-player` | today unused; highest-contrast readout text. White in dark/crimson/blue, black in light — correct on `--color-menu` in all four |
| Every panel: Deck, Band, Tune, condensed bar, manifest backing, folio | `--color-menu` | already 0.8 alpha, so stars stay faintly visible at the edges |
| Row hover, monogram fill, error strip | `--color-menu-hover` | |
| Genre control fill, filter token fill, folio buttons, empty-state buttons | `--color-btn` | |
| Genre control border, token border, playing-row 3px left bar, loading progress rule | `--color-btn-border` | distinct hue per theme, so the accent is the theme's own identity |
| Selected genre fill, button hover | `--color-btn-hover` | |
| Transport/gear icon hover, focus ring, folio current-page underline | `--color-icon-hover` | |
| Volume slider knob | `--color-thumb` | unchanged |
| Gear icon | `--color-gear` | unchanged |
| Hairline rules (row separators, metadata cell borders, panel bottoms) | *derived* | `color-mix(in srgb, var(--color-app-text) 12%, transparent)` — 18% for the Deck's metadata cells. Not a new token; inherits each theme's own text colour |

**Four-theme confirmation.**

- **dark / blue / crimson** — `--color-app` black, `--color-app-text` white: panels are dark
  translucent, hairlines are white at 12%, `--color-player` is white. Accents differ per theme
  (`--color-btn-border` is `hsla(220,50%,80%)`, `hsla(220,100%,40%)`, `hsla(0,50%,50%)`
  respectively), so the playing-row bar and selected genre carry each theme's identity for free.
- **light** — `--color-app` white, `--color-app-text` dark purple, `--color-menu` near-white at
  0.8, `--color-player` black. The derived hairline becomes dark purple at 12% on near-white:
  correct, whereas any fixed grey would have been wrong. `--color-btn` is near-white and
  `--color-btn-border` near-black, so the selected genre's `--color-btn-hover` (light grey) needs
  the **inset shadow + leading dot** to read — which is why those cues are mandatory, not
  decorative.
- Three meanings currently share `--color-btn-border` (playing-row bar, selected-genre border,
  loading rule). Each is disambiguated by shape or position, never by colour alone: the playing
  row also has the equalizer glyph, the selected genre also has the inset shadow and dot, the
  loading rule is a 2px animated line at a fixed edge.
- The star field is white at 0.8 opacity in every theme, which is near-invisible on the light
  theme's white background. That is a pre-existing bug, not introduced here; see §12.

---

## 10. Motion

### 10.1 The one orchestrated moment: the retune

On any navigation (genre, search, filter, sort, page), the manifest performs a single **retune**:
the rows drop to `opacity: 0.45` (after a 120ms delay so quick responses never flash), a 2px
`--color-btn-border` rule sweeps left-to-right along the top edge of `#catalogue` over 600ms
(looping if the request takes longer), and on swap the new rows appear with a 120ms
`opacity: 0.6 → 1` plus a 2px `translateY(-2px) → 0` settle **on the container, not per row**.
No staggered per-row reveals. No spinner.

### 10.2 Everything else

- Hover / focus on rows, genre controls, transport icons, folio links: colour and background
  only, 150ms `ease-out`. No scale, no shadow, no lift.
- Deck station change: 200ms opacity settle on the name + artwork, nothing else.
- Condensed bar: 150ms opacity + 4px translate on enter/leave.
- Playing-row equalizer glyph: three 2px-wide bars, heights animating between 4px and 14px on a
  900ms CSS keyframe loop, offset per bar. Pure CSS, no JS, no audio data.
- Gear icon: today's 10s rotation, unchanged.

### 10.3 `prefers-reduced-motion: reduce`

- Retune: no dim, no sweep, no settle — instant content swap. Loading is communicated by the
  manifest's `aria-busy="true"` and a static (non-animating) 2px rule.
- Deck station change: instant.
- Condensed bar: instant show/hide.
- Equalizer glyph: replaced by a single static 8px filled bar in `--color-app-text`.
- **Gear rotation is stopped** — `animation: none` on `.animate-gear`. This animation is currently
  ungated; adding the guard is part of this work.
- Hover colour transitions drop to `1ms` (kept functional, not animated).

### 10.4 The star field

Unchanged in kind, reduced in intensity for this denser page: particle `number.value` 50 → 28,
`move.speed` 2 → 1.2, `opacity.value` 0.8 → 0.55. Moving particles behind a text-dense manifest
compete with small numerals; this is the minimum change that keeps the field present without
making the list harder to read. It is not audio-reactive and never will be in this design.

---

## 11. What we are NOT doing

| Rejected | Why |
|---|---|
| **Web Audio `AnalyserNode` / audio-reactive anything** (level meter, artwork ring, reactive stars) | Requires `crossOrigin="anonymous"` on the playback element; most public Icecast/Shoutcast streams send no CORS headers, and setting it can make browsers abort the stream — i.e. the decoration can silence the radio. It would also be inert for most stations, so it is a signature feature that mostly does not fire. Cut entirely. |
| **A card grid of circular artwork for the catalogue** | Artwork is missing or broken for most of 51,600 stations, so the grid would be mostly monograms; and a grid of 50 cards conveys less per screen than 50 rows while costing 50 image requests. Artwork survives only for the one station playing. |
| **Infinite scroll** | Competes with the audio stream for bandwidth, destroys back-button scroll position, and makes page 94 unreachable. Numbered pagination with a `Go to` field instead. |
| **A longitude / frequency dial for 241 countries** | 241 ticks across ~360px of usable width at 400px is ~1.5px per stop — untappable, and the whole concept would rest on hand-rolled drag physics with no fallback. A grouped native `<select>` does the job. |
| **Flag emoji** | Dozens of uncontrolled saturated colours in a strictly token-driven palette, and regional-indicator glyphs render as bare letters on several Windows/Chrome configurations anyway. Two-letter ISO codes instead. |
| **Invented "frequency" numerals on genre controls** (`House 97.3`) | Fake data presented as data. Every number on this page is real. |
| **Zebra striping** | Stock admin-table convention. A single hairline does the same job with less noise. |
| **A panel-less hero over the background image** | The one theme that inverts (light: dark text on white) would need a different scrim recipe from the other three, on the element carrying all the page's visual weight. Keeping the `--color-menu` panel makes contrast a solved problem in all four themes. |
| **A 96px hero station name** | Real station names are frequently ALL CAPS, slogan-concatenated and 60+ characters. At 96px they look broken; at 40px with a 2-line clamp they look intentional. |
| **A peripheral-preview "page through genres one at a time" dial** | Needs a second, separate accessible implementation of the same feature, and hides 26 of 27 genres to show one. The band plus the `All genres` overlay shows everything. |
| **A bottom-sheet mobile player** | It is the Spotify/Apple Music pattern almost element-for-element, and the bottom edge below 720px is already owned by the settings bar. Our condensed bar docks to the top. |
| **A custom searchable country combobox** | Building an accessible listbox with type-ahead, touch and keyboard support from scratch is a larger project than it looks. Native `<select>` with `<optgroup>` gets all of it free. |
| **Multi-select genres** | Doubles the filter algebra, the URL grammar and the empty-state logic for a gain we cannot demonstrate. One genre at a time. |
| **Sub-genres, A–Z indexes, facet matrices** | Sort + three filters + shuffle already make a 9,000-row genre navigable. More chrome pushes the page toward the database front-end the brief rejects. |
| **Per-row entrance animations** | The generic AI-page tell, and at 50 rows per swap it makes every navigation feel slower than it is. |
| **`A · B · C` middot metadata strings** | Replaced by discrete bordered cells (Deck) and real columns (manifest), both of which parse faster. |
| **New CSS custom properties** | Every surface maps to the eleven existing tokens plus one `color-mix` derivation. A fifth theme would need no design work. |

---

## 12. Open risks

1. **The Deck may still read cooler than the original.** We added one quiet region, kept the
   circular artwork and cut the instrumentation from five cells to three, but "calm personal
   radio" is a feeling, not a spec. Build the Deck first, look at it in all four themes with a
   real background image behind it, and be willing to spend more vertical space on it.
2. **Scoped genre counts are the most expensive thing on the page.** Global counts are cached and
   cheap; counts scoped to an active country+language+search are a grouped query over a filtered
   set on every navigation. The fallback to global counts is specified, but the budget
   (target: < 40ms) needs measuring against a real 51,600-row table, not assumed.
3. **Next/Previous across page boundaries is the subtlest thing here.** Rank-based traversal with
   an on-demand neighbouring-page fetch is correct but fiddly — particularly when the user changes
   filters mid-playback. Write tests for: next at the last rank (wrap), next across a page
   boundary, next after the tuned set changed, next when the tuned set became empty.
4. **Sono is not a monospace.** `tabular-nums` should align the manifest's four numeric columns,
   but this must be verified visually, not assumed.
5. **The light theme's star field is effectively invisible** (white stars on a white
   `--color-app`). This is pre-existing and out of scope here, but this redesign makes it more
   noticeable because more of the page is panelled. A follow-up should drive particle colour from
   `--color-app-text`.
6. **`--color-btn` in the light theme is near-white on a near-white panel**, so genre controls rely
   almost entirely on their `--color-btn-border`. Check that a 27-control band still reads as a set
   of discrete controls in light, and thicken the border there if it does not.
7. **The no-JS path loses playback on navigation.** Progressive enhancement is real for rendering
   and for crawlers, but a no-JS user who filters will stop the music. Accepted; not worth a
   second architecture.
8. **`listeners` is null for most stations**, so the live column will be empty on most rows. That
   is intended (absence is informative), but a column that is 90% blank may read as broken —
   watch it on a real data set and be prepared to fold `listeners` into the votes column instead.
9. **The condensed bar and the Band both stick.** Verify no ancestor of them takes a `transform`,
   `filter` or `overflow: hidden` for the star-field layering, since any of those silently breaks
   `position: sticky`.
