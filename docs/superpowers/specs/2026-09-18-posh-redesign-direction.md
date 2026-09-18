# Design direction — "The Night Console"

Status: chosen direction, ready to build. Supersedes `2026-09-18-radio-page-design-direction.md`
("The Tuning Deck") for the radio page. Written for an engineer implementing it cold.

---

## 1. The chosen direction, and why it won

**The Night Console** is the Faceplate direction — the page as a machined instrument: a fixed
footer faceplate you always have your hand on, and above it a quiet catalogue that stops
pretending to be a hero panel — set in the After-Hours Room's dim, single-glow atmosphere so
that "precise" also reads as "vibey" rather than "clinical".

It won because it was the only direction whose *controls* had a real, consistent, theme-proof
visual law (one two-line `color-mix()` bevel on every clickable surface, swapped on `:active`),
and because its now-playing model — a reserved line that starts genuinely empty, reacts only to
value changes, and never resizes the dock — was independently named "best idea worth stealing"
by reviewers of every direction.

---

## 2. What was grafted, and from where

| Grafted | From | Why |
|---|---|---|
| The dim-room atmosphere: three fixed opacity tiers, one accent glow used in exactly two places, star field kept (not dimmed further) | **The After-Hours Room** | The Faceplate had no answer for "vibey". This is it. |
| Two-line catalogue rows (title + one dim caption) instead of a seven-column ledger with bordered chips | **The After-Hours Room** | The Faceplate's "spec sheet / parts index" reproduced the database feel the owner rejected. |
| The breathing ember dot as the playing marker (replacing the three-bar equalizer) | **The After-Hours Room** | A pulsing ember reads as a room; a bar-chart glyph reads as software. |
| Opacity-only cross-dissolve as the treatment for every text change that is *not* the dock title | **The After-Hours Room** | Cheap, jank-free, and keeps the one 3D flip rare enough to stay special. |
| The always-visible "Sorted by" rubric as text links with the active one underlined, plus a bold rank folio on every row | **The Wireless Quarterly** | Directly answers the owner's complaint: a `<select>` gives no feedback that a sort is live. |
| Selection state expressed as **shape** (filled dot + inset bevel + 2px underline), never colour-fill alone | **The Wireless Quarterly** | Survives the light theme, where `--color-btn` is near-white on a near-white `--color-menu`. |
| Counts folded into each genre link's accessible name ("Jazz, 3,214 stations"), decorative marks `aria-hidden` | **The Wireless Quarterly** | Free accessibility win, already proven in `genre-links.blade.php`. |

Rejected outright from the runners-up: the magazine metaphor (drop caps, dot leaders, two-column
directory, classifieds typography) and the text-only, borderless genre rail. Reasons in §11.

---

## 3. Every fatal flaw raised, and how this design resolves it

All 34 flaws raised across the three critiques. None omitted.

### 3.1 From The Faceplate

| # | Flaw | Resolution |
|---|---|---|
| T-R1 | No connecting / buffering / dead-stream state; only a status line buried in a drawer. | Five explicit dock states — Idle, Connecting, Playing, Rebuffering, Failed — all rendered on the dock's primary readout, never in a drawer. §6.4. |
| T-R2 | Catalogue framed as a "spec sheet / parts index" — the database feel the owner rejected. | The ledger is gone. Rows are two lines: title, and one dim caption. No column grid, no per-row chips. §7.1. |
| T-R3 | Volume hidden in a drawer below 400px. | **The drawer is deleted entirely.** At 400px the dock is two rows, 88px, and the volume slider is on row 2, always visible, one tap. §5.4. |
| T-R4 | Does not fix the sort; `click_count` is a weak signal for "worth tuning into now". | §8 specifies the data fix (local `play_count`, honest labels, alphabetical tiebreak) as a hard prerequisite, not a caption. |
| T-V1 | Breaks its own "radius 4–6px, never soft" law with a 12px-radius drawer. | Drawer deleted. Every radius on the page is now 4px (controls), 6px (control housings), or 0 (the dock plate and row hairlines). No exceptions. |
| T-V2 | 150+ bordered chips across 50 rows reads as an admin table export. | Zero bordered chips in the catalogue. Bevels exist only on the dock, the transport cluster, the row play glyph and the genre keys — nowhere else. |
| T-V3 | "Vibey" undefended; star field frozen out. | §4 answers "vibey" concretely; the star field stays at its current intensity and is deliberately visible through `--color-menu` panels. |
| T-V4 | 1px `color-mix()` bevels over translucent panels on a moving star field — legibility never addressed. | The dock is the one **fully opaque** surface on the page (`--color-menu` at 0.8α stacked over an opaque `--color-app` plate), which is exactly where the bevel must read. Panels elsewhere carry no bevels, only 10–12% hairlines. Gate G1 in §13 requires a four-theme render over the brightest real background image before anything else is built. |
| T-V5 | The tune/filter surface — home of the sort control the owner complained about — was barely touched. | §7.3 respecifies the tune bar completely: search, the sort rubric, a single Filters button, and the count line. |
| T-E1 | Drawer handle contradicts itself: "a button, not a gesture" vs "tappable or draggable". | Drawer deleted. There are **no gestures anywhere** in this design; every control is a `<button>` or an `<a href>`. |
| T-E2 | 20px per-row play toggle regresses a shipped fix and is under the 24px WCAG minimum. | The **whole row stays the button** (48px desktop / 64px mobile, full width). The rocker glyph is a 20px *indicator inside* that button, not the hit area. |
| T-E3 | The split-flap flip has no interruption guard; overlapping polls can stack timers. | §6.3 specifies a monotonic `_titleToken` plus coalescing: a value arriving mid-flip is stored as pending and applied at `animationend`; never queued, never stacked. Same pattern as `catalogue.js`'s existing `_token`. |

### 3.2 From The Wireless Quarterly

| # | Flaw | Resolution |
|---|---|---|
| E-R1 | No connecting / error state. | Same as T-R1. §6.4. |
| E-R2 | Strips every station of a distinguishing visual mark at the moment recognition matters. | Accepted as a real cost and paid deliberately: per-row artwork stays out (37% missing, many dead URLs, 50 requests/page). Recognition is served by (a) the 44px dock chip once playing, (b) the station title being the single brightest element in its row, (c) search and the existing favourites filter. Stated as a decision in §11, not hidden. |
| E-R3 | Nothing in a magazine-styled list looks clickable. | Every row renders its play glyph **at rest**, not on hover, and every genre key has a bevel — the two things that must read as controls always announce themselves. |
| E-R4 | The in-list playing marker is invisible most of the session, since users roam. | Acknowledged: the dock is the authoritative now-playing indicator. The row marker (ember dot + 3px left edge) is a bonus when you happen to be on that page, and nothing depends on it. |
| E-V1 | "Vibey" absent; star field dimmed further. | §4; star field intensity unchanged. |
| E-V2 | Classifieds is print's least glamorous page — wrong metaphor for "posh". | Metaphor rejected entirely; we are an instrument, not a periodical. |
| E-V3 | Magazine authority needs serif display type; Sono is a technical sans. | Rejecting the magazine metaphor removes the debt. Sono as an instrument voice is native, not borrowed. No third family. |
| E-V4 | Drop caps collide (Classical / Country / Chillout / Children's). | No drop caps anywhere. |
| E-V5 | No rendered comp; risk of reading as an unstyled text list. | Gate G1 (§13): the dock plus ten rows rendered in all four themes over the real background, reviewed, before the rest is built. |
| E-E1 | Now-playing has zero backend design; `StreamTitle` is in-band, not a header. | §6.5 specifies the whole mechanism: `Icy-MetaData: 1`, `icy-metaint`, queued worker, cache, locks, timeouts. |
| E-E2 | A naive per-poll stream read blocks a PHP-FPM worker per listener. | The HTTP endpoint **never opens an upstream socket**. It is a cache read. Upstream reads happen on a queued worker, at most one in flight per station, regardless of listener count. §6.5. |
| E-E3 | Mobile bottom-chrome arithmetic doesn't add up (152px of chrome, 128px of padding). | Exactly one fixed layer at the bottom edge now: the settings gear moves to a fixed top-right control. Clearance is `calc(var(--dock-h) + 24px)` with `--dock-h` set per breakpoint. §5.1. |

### 3.3 From The After-Hours Room

| # | Flaw | Resolution |
|---|---|---|
| L-R1 | `click_count` is a frozen import snapshot; printing "sorted by most played" is a bigger lie, not a fix. | §8. The label "Most played" only appears once a locally-incremented `play_count` exists; the imported signal is labelled "Popular worldwide", which is what it actually is. |
| L-R2 | An empty second line conflates "no song info, all fine" with "the stream is dead". | Five distinct dock states with distinct treatments; absence of a title is silent, failure is loud. §6.4. |
| L-R3 | Both navigation mechanisms (most-played shelf, text-only genre rail) compromised at once. | Sort fixed in §8; the genre rail is bevelled keys with counts, not plain-text captions. |
| L-V1 | A dozen opacity tiers on one base colour reads as indecision, and risks legibility over the star field. | Exactly **three** opacity tiers exist on the whole page: 100 / 72 / 48. Any other value is a bug. §9.2. |
| L-V2 | The glow does not glow in the light theme. | Documented per-theme behaviour, honestly: in dark/crimson/blue it is a bloom; in light it is a soft drop shadow at 18%, and the ember dot switches its fill to `--color-icon-hover` (a real violet at 40% lightness on white). One override, one rule. §9.3. |
| L-V3 | Dock station name at Sono 300 is *lighter* than the rows it is supposed to dominate. | Nothing on the page is lighter than weight 400. The dock name is Sono 500 at 100% opacity in `--color-player`; row titles are Open Sans 600 at 72%. §9. |
| L-V4 | The "shelf" is structurally the old manifest with the alpha turned down. | Stated plainly in §7.1: this *is* an evolution of the manifest, and here is exactly what changes — seven columns to two lines, chips to one caption, hover-only affordance to always-on, plus the rank folio and the rubric. No reinvention is claimed. |
| L-V5 | Pure subtraction lands as austere developer-tool minimalism. | Subtraction is bounded: the bevelled controls, the glow, the ember and the star field are the sensory register that survives it. §4. |
| L-E1 | Now-playing polling needs real infrastructure it never designs. | §6.5. |
| L-E2 | `click_count` has never once reflected a play on this site. | Confirmed by reading `ImportRadios.php:156` and the route table — nothing increments it. §8 adds the counter that makes the word "played" true. |

---

## 4. "Posh, stylish, vibey", answered

The owner's three words, translated into things an engineer can check.

**Posh = machined, not decorated.** Every corner radius on the page is 4px, 6px, or 0 — never the
16–32px friendly-SaaS bubble. Every clickable surface carries the same two-line bevel (one lit
edge, one shadow edge, both `color-mix()`ed from existing tokens) and physically depresses on
`:active`. Alignment is exact: numerals are tabular, numeric columns have fixed `min-width`, icon
sizes sit on a 4px rhythm. Nothing is eyeballed. Cheap UI over-explains itself with eyebrows,
badges and tooltips; this page trusts a well-aligned control to be read at a glance.

**Stylish = restraint that is spent, not hoarded.** Exactly one accent effect exists — a soft glow
derived from the active theme's own `--color-btn-border` — and it appears in exactly two places on
the entire page: the dock's top hairline, and the ember dot beside whatever is playing. It earns
those two spots by being refused everywhere else. Three opacity tiers, two type families, one
motion moment. The largest text on the page is 16px; there is no 40px hero, because a page with
one giant name is a page where everything else is decoration.

**Vibey = a dim room with one lit object.** The page is dark ground (`--color-app`), quiet text
(72% / 48%), and one warm, breathing thing at the bottom edge. The star field stays exactly as
bright as it is today and stays visible through the 0.8α panels, because it is the only
non-linear, non-numeric thing on the screen and it is what stops precision from becoming
clinical. Motion is slow (the ember breathes over 2.4s) rather than snappy. In the light theme
the same bones become a quiet daylight room instead — same structure, different weather; this is
stated rather than pretended away.

---

## 5. The footer dock

### 5.1 Position and behaviour while the catalogue scrolls

```
position: fixed; inset-inline: 0; bottom: 0; z-index: 40;
```

It never moves, never hides on scroll, never collapses on scroll, and has **no scroll listener of
any kind**. The catalogue scrolls underneath it. Clearance is handled purely with CSS:

```
:root { --dock-h: 84px; }
@media (max-width: 640px) { :root { --dock-h: 88px; } }
#catalogue { padding-bottom: calc(var(--dock-h) + 24px); }
```

The dock is the **only** fixed element at the bottom edge. The settings gear moves to a fixed
top-right control (40px, `--color-menu` plate, 4px radius) so nothing ever stacks against the dock.

The dock lives outside `#catalogue`, exactly like today's player, so a fragment swap never touches
it and audio survives navigation.

### 5.2 Material

The dock is the one fully opaque surface on the page — this is deliberate, it is the "metal"
against the "glass" of the panels above, and it is what makes a 1px bevel legible over a moving
star field:

```
background-color: var(--color-app);              /* opaque in all four themes */
background-image: linear-gradient(var(--color-menu), var(--color-menu));
border-top: 1px solid color-mix(in srgb, var(--color-app-text) 22%, transparent);
box-shadow: 0 -1px 24px color-mix(in srgb, var(--color-btn-border) 35%, transparent);
```

Height is **fixed at 84px desktop / 88px mobile regardless of state** — idle, connecting, playing,
long title, no title, failed. Non-negotiable: the one piece of permanent chrome must never reflow.

### 5.3 Desktop layout (≥ 641px), 84px

`display: grid; grid-template-columns: minmax(0,1fr) auto minmax(0,1fr); align-items: center;
padding-inline: 16px; gap: 16px;`

**Left cell — the readout.**

- 44px square station chip, `border-radius: 4px`, bevelled. Artwork via `object-cover` with
  `onerror="this.remove()"` revealing a monogram beneath (first alphanumeric character of the
  title, uppercased, Sono 500 18px, on `--color-menu-hover`). The monogram is the normal state,
  not a degraded one.
- Text stack, 16px gap from the chip:
  - Line 1 (20px tall): station name, Sono 500 16/20, `--color-player`, 100% opacity, one line,
    truncated, full name in `title=`.
  - Line 2 (18px tall, **always reserved, often empty**): the now-playing / status line. §6.
- Idle (nothing selected): chip renders empty at 48% opacity; line 1 reads "Pick a station" at
  72% opacity; line 2 is empty.

**Centre cell — the transport cluster.** Three buttons inside one housing, not three floating
icons:

```
housing: background: var(--color-btn); border: 1px solid var(--color-btn-border);
         border-radius: 6px; padding: 4px; display: flex;
seam:    every button except the last gets border-right: 1px solid var(--color-btn-border);
```

- Previous 40×40px, Play/Pause 48×48px (`border-radius: 4px`, the primary detent), Next 40×40px.
- `:active` on any of the three: `transform: translateY(1px)` and the lit/shadow bevel edges swap.
- Play/Pause is disabled at 48% opacity while Idle; previous / next / shuffle stay enabled
  (shuffle from cold is the fastest route to sound).

**Right cell — right-aligned, `gap: 12px`.** Shuffle (36px bevelled icon button), the existing
120px `.volume-slider` unchanged, and nothing else. The desktop colophon ("Created by Marius
Tanase" + social glyphs, Sono 11px at 48%) sits at the far right only at ≥ 900px.

### 5.4 Mobile (≤ 640px), 88px, two rows — no drawer, no accordion, no sheet

- Row 1, 52px: 32px chip · text stack (name Sono 500 15/19, status line 16px reserved) ·
  Play/Pause 44×44 · Next 40×40, right-aligned.
- Row 2, 36px: Previous 36×36 · Shuffle 36×36 · `.volume-slider` filling the remaining width ·
  8px gutters.

Everything is reachable in one tap at 400px. Nothing is behind an expand. The transport housing
seams are dropped below 400px to save width; the bevels remain.

### 5.5 What the dock does *not* do

No drawer, no bottom sheet, no drag, no detents, no gestures, no marquee, no hide-on-scroll, no
height change ever. Station metadata (genre, country, bitrate, codec) lives in the catalogue row
it came from, not in a dock drawer — the dock is a control surface, not a detail panel.

---

## 6. Now playing

### 6.1 What is known when

- **Station name, artwork, genre, country, bitrate** come from our catalogue and are available the
  instant a station is selected. Line 1 of the dock is therefore never empty while playing.
- **The song title** comes only from the stream's in-band ICY `StreamTitle`, which the browser
  cannot read from an `<audio>` element. It arrives late (1–3s after play at best), may never
  arrive (talk/news, plus ~9% of music stations), and changes mid-listen without warning.

### 6.2 The line contract

Line 2 of the dock's readout is a **fixed 18px (desktop) / 16px (mobile) slot that always exists**
the moment a station is selected. Its height never changes. It has exactly one of these contents:

1. Empty — no title known, nothing wrong. No skeleton, no spinner, no "Loading…", no "Unknown
   track", no em dash. Silence is the honest render of absent data and matches the codebase's
   existing rule that a missing cell renders nothing.
2. The track title: Open Sans 400 13/18 at 72% opacity, one line, truncated, full string in
   `title=`. No marquee — a scrolling ticker is the single most recognisable cheap-web-radio tell.
3. A status string (Connecting / Buffering / Failed): Sono 500 12/18 at 100% opacity. §6.4.

Status outranks title. If a stream fails while a title is showing, the status replaces it.

### 6.3 The flip, and its race guard

The one piece of "device" motion in the whole design. When the displayed title changes to a
different non-empty value, the line performs a single split-flap flip: the container rotates out
`rotateX(-90deg)` over 90ms while fading to 0, then the new text rotates in from `rotateX(90deg)`
over 90ms. `transform-origin: center`, `perspective: 240px` on the parent, 180ms total. Once per
change, on the container — never per character.

Guard (required, not optional):

- Keep a monotonic `_titleToken`, incremented on every applied change — the same pattern
  `catalogue.js::_perform` already uses for fetches.
- If a new value arrives while a flip is in flight, **coalesce**: store it as `_pendingTitle` and
  apply it in the `animationend` handler. Never start a second flip, never chain `setTimeout`,
  never hold more than one pending value (last write wins).
- Transitions empty→text and text→empty use the same flip. Empty→empty is a no-op.
- The station changing (the user tuned elsewhere) resets the line to empty **without** a flip and
  cancels any pending value.

### 6.4 The five dock states

Driven entirely by `<audio>` element events plus one timer. No new library.

| State | Trigger | Line 1 | Line 2 | Ember dot |
|---|---|---|---|---|
| **Idle** | no station selected | "Pick a station", 72% | empty | hidden |
| **Connecting** | `play()` called / `loadstart`, before `playing` | station name | "Connecting…" | static, 48% |
| **Playing** | `playing` fired | station name | title, or empty | breathing |
| **Rebuffering** | `waiting` or `stalled` held ≥ 2s | station name | "Buffering…" | static, 48% |
| **Failed** | `error`, or no `playing` within 8s of `play()` | station name at 72% | "Stream didn't answer — **Try the next one ›**" (the phrase is a real button calling `next()`) | hidden |

"No song info" and "the stream is dead" are never the same pixel. The Failed state also fires the
existing `POST /api/stations/{radio}/unreachable` telemetry; the Playing state fires the existing
`/ok`.

### 6.5 Polling architecture — concrete

**The shape of the problem:** 58,407 stations exist, but each listener is playing exactly one, so
the work scales with *distinct stations currently being listened to*, not with the catalogue.

**Client.** While `state === Playing` and `document.visibilityState === 'visible'`:
`GET /api/stations/{radio}/now-playing`. First poll 2s after the `playing` event, then every
**15s**, honouring a `poll_after` value from the response (the server, not the client, owns the
cadence). Polling stops immediately on pause, on station change, and on
`visibilitychange → hidden`; it resumes with an immediate poll when the tab becomes visible again.
One `AbortController` per in-flight request; a response whose station id no longer matches
`current.id` is discarded. Worst case per listener: 4 requests per minute to our own origin.

**Endpoint.** `GET /api/stations/{radio}/now-playing`, `throttle:20,1`, `missing()` → 204 like the
existing health routes. It is a **cache read and nothing else** — it must never open a socket to
an upstream stream:

```
title       string | null      from Cache::get("nowplaying:{$radio->id}")
state       ok | pending | unsupported | unreachable
poll_after  15 (ok, pending) | 60 (unsupported) | 120 (unreachable)
```

On a cache miss it dispatches `FetchStreamTitle($radio)` and returns `state: pending` immediately.

**Worker.** `FetchStreamTitle` is a queued job guarded by
`Cache::lock("nowplaying:lock:{$id}", 20)->get()` (non-blocking), so N listeners of the same
station produce at most one upstream read per 20s window. It:

1. Opens the stream URL with the request header `Icy-MetaData: 1`; connect timeout **3s**, total
   budget **6s**.
2. Reads `icy-metaint` from the response headers. If absent, the station sends no in-band
   metadata: cache `null` under `nowplaying:{id}` with **TTL 30 minutes** and the `unsupported`
   marker, and stop. This is what stops talk/news stations being polled forever.
3. Otherwise reads `icy-metaint` bytes of audio (discarded), then the single length byte × 16 =
   the metadata block length, then the block; parses `StreamTitle='…';`. Hard cap **64 KB** read.
4. Trims, collapses whitespace, and discards the value if it equals the station's own `icy-name`
   or catalogue title (many stations pad `StreamTitle` with their own name — that is not a song).
5. Caches the result under `nowplaying:{id}` with **TTL 20s**, then closes the connection.
6. On connection failure: cache `null` with the `unreachable` marker, TTL 5 minutes.
7. After three consecutive empty results for a station, promote it to `unsupported` for 30 minutes.

**Cost at steady state:** one upstream connection per *distinct actively-played station* per 20s,
held well under a second, on a queue worker. 500 concurrent listeners spread over 200 distinct
stations is ~10 upstream reads/second worst case, and zero blocked PHP-FPM workers. `queue:work`
on the existing driver is sufficient — no daemon per station, no websockets, no Reverb.

**Optional, not required for v1:** the same worker can opportunistically persist `icy-name`,
`icy-genre` and `icy-br`. No UI may depend on it.

---

## 7. Catalogue and genre navigation

### 7.1 The rows

This is an evolution of today's `manifest.blade.php`, and we say so rather than claiming a
reinvention. What actually changes: seven columns become two lines; bordered metadata chips become
one dim caption; the hover-only affordance becomes an always-visible glyph; a rank folio and a
sort rubric are added.

Server-paginated at 50 rows, single column, no infinite scroll, no per-row artwork, no zebra
striping, no per-row shadow. Each row is a full-width `<button>` — 48px desktop, 64px mobile — so
the hit area is the whole row, which is the fix this codebase already shipped and must not regress.

Row anatomy:

```
display: grid; grid-template-columns: 28px 56px minmax(0,1fr); gap: 12px;
padding-inline: 12px;
border-bottom: 1px solid color-mix(in srgb, var(--color-app-text) 10%, transparent);
```

- **Cell 1 (28px):** the play indicator — a 20×20px bevelled rocker glyph, `border-radius: 4px`,
  visible at rest at 48% opacity, 100% on row hover/focus. When this row is the playing station it
  becomes the ember dot (§10). It is an indicator inside the row button, never a separate control.
- **Cell 2 (56px):** rank folio — the row's position in the whole filtered, sorted set, not a
  page-local counter: `($page - 1) * 50 + $i + 1`. Sono 500 12/16, `tabular-nums`, right-aligned,
  `min-width: 5ch`, 48% opacity. A house number, not a scoreboard.
- **Cell 3:** two lines —
  - Title: Open Sans 600 15/20, one line, truncated, 72% opacity → 100% on hover/focus, `title=`
    carrying the full string. This is the brightest thing in the row on purpose: it is the
    wayfinding cue that replaces a logo.
  - Caption: `Genre · Country · 128k · MP3`, Sono 500 12/16 at 48%, `tabular-nums`, dot-joined,
    each field **omitted** when absent — never dashed, never "0". Identical at every width.

Votes and listener counts leave the resting row (both sparse, mostly null) and survive only in the
row's `title=` tooltip on pointer devices.

Row hover/focus: `background: color-mix(in srgb, var(--color-btn-border) 6%, transparent)` plus the
opacity lift, 120ms ease-out. No lift, no scale, no shadow — a machined surface does not rise to
meet the cursor.

Playing row: a 3px left edge in `--color-btn-border` plus the ember dot in cell 1, and
`aria-current="true"`.

### 7.2 Above the rows: the count line and the rubric

Two lines, always present, directly above the first row:

```
58,407 stations · sorted by Popular worldwide
Sorted by   Popular worldwide   Most played   Most voted   Highest bitrate   A–Z
```

The count line is Sono 500 13/18 at 72%, `tabular-nums`, and always states scope **and** order in
words ("9,041 in Pop · sorted by Most played"). The rubric is a row of real `<a href>` links, Sono
500 13/18, inactive at 48%, active at 100% with
`border-bottom: 2px solid var(--color-btn-border)`. Shape, not colour — it survives the light theme.

When every row on the current page has a zero ordering signal, a third line appears at 48%:
"No play data below this point — ordered A–Z." Computed from the rendered page, so it costs nothing.

### 7.3 The tune bar

One row at ≥ 900px, wrapping to two below: search input (Sono 400 14/20, 36px tall, 4px radius,
bevelled) · the sort rubric · a single **Filters** button (bevelled, showing a filled dot when any
filter is active) opening the existing slide-in drawer for country / region / language / on-air /
favourites. Between 640px and 900px the rubric folds into the Filters drawer as a radio group, and
the count line keeps stating the active order in words, so the information is never lost.

### 7.4 Genre navigation — a preset bank

A 44px bank directly under the tune bar, on `--color-menu`:

- `All` pinned at the left edge.
- **8 pinned keys** (4 at ≤ 640px), each 92×36px (84×32px mobile), bevelled like every other
  control: label Sono 600 13/16, count beneath in Sono 400 11/12 `tabular-nums` at 48%.
- `Browse all 33 ›` pinned at the right edge, opening the existing genre overlay
  (`genre-links.blade.php` — same partial, same focus trap, same keyboard contract) as a centred
  panel at ≥ 640px (max 640px wide, max-height 70vh) or full-screen below.
- Active key: a filled 5px dot before the label **plus** the inset (pressed) bevel. Never colour
  alone.
- No horizontal scroller of 33 buttons exists anywhere.

The eight pinned keys are the eight largest genres by cached count (`App\Support\CatalogueCounts`,
same 24h cycle) but are **rendered in a fixed alphabetical order**, so a count refresh can never
visually reshuffle the bank under a returning user's thumb.

---

## 8. Sorting — the owner's actual complaint

Verified in this codebase before writing this section:

- `App\Support\CatalogueFilter::applySort()` already orders `click_count desc, votes desc, id asc`
  **server-side, over the entire filtered query, before pagination**. The mechanism is not broken.
- `click_count` is imported verbatim from Radio-Browser's `clickcount` (`ImportRadios.php:156`) and
  **nothing in this application ever increments it** — the only station-mutating routes are
  `/unreachable` and `/ok`. It is a frozen third-party snapshot.
- Consequence: the head of the list is genuinely popular stations, and the long tail is a mass of
  ties on `click_count = 0, votes = 0` broken by `id`, i.e. import order, i.e. noise. That is
  exactly what "the sorting doesn't really work" feels like from the outside.

Three changes, all required before the rubric is allowed to say "Most played":

1. **Tell the truth about the imported signal.** The sort key currently labelled "Most played" is
   renamed **"Popular worldwide"** everywhere in the UI. It remains the default.
2. **Make the tail alphabetical instead of arbitrary.** Change the final tiebreak from `id` to
   `title asc, id asc` in every sort branch, and add the covering indexes (e.g.
   `(click_count, votes, title, id)`) so the 58k-row order stays cheap. This alone turns 50,000
   rows of noise into a browsable A–Z tail and keeps pagination deterministic across pages.
3. **Add a real local play counter.** New column `radios.play_count` (unsigned int, default 0,
   indexed). New route `POST /api/stations/{radio}/played`, `throttle:20,1`, `missing()` → 204,
   fired once per (station, session) after **20 seconds of continuous playback** — the
   once-per-session guard lives server-side in the session bag so a reload cannot farm it. A new
   sort key **"Most played"** orders `play_count desc, click_count desc, votes desc, title asc,
   id asc`, and is offered in the rubric only once the column is populated.

Run this audit before implementing, and record the numbers in the PR:

```sql
SELECT COUNT(*) AS total,
       SUM(click_count > 0) AS with_clicks,
       SUM(votes > 0)       AS with_votes,
       MAX(click_count)     AS peak
FROM radios;
```

If `with_clicks` is under roughly 15% of `total`, keep the unfiltered default at "Popular
worldwide" and let the A–Z tiebreak carry the tail as specified. Do not invent a synthetic score.

---

## 9. Type, colour, opacity

### 9.1 Type scale

Two families, both already loaded. **Sono** is the instrument voice: every numeral, label, control
and status string. **Open Sans** is the reading voice: station titles and prose. No italics, no
third family, no uppercase-with-tracking eyebrows. Nothing on the page exceeds 16px. Nothing is
lighter than weight 400. `font-variant-numeric: tabular-nums` on every numeric cell, and because
Sono is not a true monospace, every aligned numeric column also gets a fixed `min-width`.

| Element | Family | Size / line-height | Weight | Opacity |
|---|---|---|---|---|
| Masthead wordmark | Sono | 16 / 20 | 600 | 100% |
| Count line | Sono | 13 / 18 | 500 | 72% |
| Rubric link, inactive | Sono | 13 / 18 | 500 | 48% |
| Rubric link, active | Sono | 13 / 18 | 600 | 100% |
| Search input | Sono | 14 / 20 | 400 | 100% |
| Genre key label | Sono | 13 / 16 | 600 | 100% |
| Genre key count | Sono | 11 / 12 | 400 | 48% |
| Row rank folio | Sono | 12 / 16 | 500 | 48% |
| Row title | Open Sans | 15 / 20 | 600 | 72% → 100% on hover/focus |
| Row caption | Sono | 12 / 16 | 500 | 48% |
| Dock station name | Sono | 16 / 20 | 500 | 100% |
| Dock station name (mobile) | Sono | 15 / 19 | 500 | 100% |
| Dock track title | Open Sans | 13 / 18 | 400 | 72% |
| Dock status string | Sono | 12 / 18 | 500 | 100% |
| Dock monogram | Sono | 18 / 18 | 500 | 72% |
| Folio / pagination | Sono | 13 / 18 | 500 | 72% |
| Colophon | Sono | 11 / 14 | 400 | 48% |
| Empty-state title | Sono | 18 / 24 | 500 | 100% |
| Empty-state body | Open Sans | 14 / 22 | 400 | 72% |

Genre key labels sit at 100% even when inactive — they are controls, and controls are never
dimmed; it is the count beneath that recedes.

### 9.2 The opacity law

Exactly three tiers exist: **100%** (controls, the dock name, anything active), **72%** (primary
reading content at rest), **48%** (secondary metadata, counts, inactive links, disabled controls).
A fourth value in a PR is a bug.

### 9.3 Colour — every value mapped to an existing token

No new tokens, no new hues, no hardcoded colours. Verified against `resources/css/app.css` for all
four themes.

| Surface | Value | Four-theme check |
|---|---|---|
| Page ground | `var(--color-app)` | opaque in all four (black ×3, white in light) |
| All resting text | `var(--color-app-text)` at 100 / 72 / 48% | white ×3, violet-30% in light |
| Panels (tune bar, genre bank, catalogue) | `var(--color-menu)` (0.8α — the star field stays faintly visible) | ✓ |
| Dock plate | `var(--color-app)` under `var(--color-menu)` → opaque | ✓ — this is why the bevel reads |
| Dock readout text (station name) | `var(--color-player)` | white ×3, black in light: the highest-contrast text on each theme's menu colour |
| Control fill (transport housing, genre keys, row glyph, buttons) | `var(--color-btn)` | ✓ |
| Control border / seam | `var(--color-btn-border)` | ✓ |
| Control hover fill | `var(--color-btn-hover)` | ✓ |
| Row hover wash | `color-mix(in srgb, var(--color-btn-border) 6%, transparent)` | ✓ |
| Row hairline | `color-mix(in srgb, var(--color-app-text) 10%, transparent)` | ✓ |
| Bevel — lit edge | `color-mix(in srgb, var(--color-app-text) 22%, transparent)` | ✓ |
| Bevel — shadow edge | `color-mix(in srgb, var(--color-app) 60%, transparent)` | **fails in light** (`--color-app` is white) → the light theme overrides the shadow edge to `color-mix(in srgb, var(--color-btn-border) 35%, transparent)`. One documented override, not four hand-tuned palettes. |
| Focus ring | `outline: 2px solid var(--color-icon-hover)` | ✓ |
| The glow (dock top edge) | `0 -1px 24px color-mix(in srgb, var(--color-btn-border) 35%, transparent)` | a bloom in dark / crimson / blue; in **light** `--color-btn-border` is near-black, so the same shadow at **18%** reads as a soft drop shadow — physically correct for a lit room, and stated honestly rather than faked |
| Ember dot | `var(--color-btn-border)` | **light-theme override:** `var(--color-icon-hover)` (violet, 40% lightness) so it still reads as a lit dot on white |
| Monogram plate | `var(--color-menu-hover)` | ✓ |
| Volume slider | unchanged `.volume-slider` (`--color-thumb`, `--color-app-text`) | ✓ |
| Gear | `var(--color-gear)` unchanged | ✓ |

`--color-icon-hover` stays reserved for focus rings, icon hover and the light-theme ember — never
for a resting state, so it keeps its meaning.

---

## 10. Motion

| Thing | Spec |
|---|---|
| Dock title change | The split-flap flip: 90ms out (`rotateX(-90deg)`, fade to 0) + 90ms in (`rotateX(90deg)`), 180ms total, coalesced per §6.3. The only 3D motion on the page. |
| Dock status change (Connecting / Buffering / Failed) | 150ms opacity cross-dissolve, no rotation — a status must not look like a song change. |
| Ember dot | 2.4s ease-in-out opacity loop, 48% → 100% → 48%. Pure CSS keyframe. No Web Audio analyser, and `crossOrigin` is never touched on the `<audio>` element. |
| Control `:active` | Instant: `translateY(1px)` plus the bevel edges swapping. No easing — it is a state, not an animation. |
| Row / control hover | 120ms ease-out, background and opacity only. Never transform, scale or shadow. |
| Retune (filter / sort / page) | Rows dim to 45% after a 120ms delay (so a fast response never flashes — reuses the existing `pending` flag in `catalogue.js`), a 2px line under the count line fills left→right over 400ms, then rows are replaced instantly with a container-level fade back to full opacity. No per-row stagger, no translate. |
| Genre overlay | 180ms opacity plus 4px translateY, once, on the container. |
| Star field | Unchanged from today. Never wired to audio, never wired to any control. |

`@media (prefers-reduced-motion: reduce)`: the flip becomes an instant text swap; every
cross-dissolve becomes instant; the ember becomes a static 100% dot; the retune's fill line becomes
a static full-width line with `aria-busy="true"` carrying the loading state; the genre overlay
appears instantly; the `:active` bevel swap **is kept** (it is a state) but the 1px translate is
dropped; and the existing gear rotation is finally gated off (`animation: none`).

---

## 11. What we are NOT doing, and why

| Not doing | Why |
|---|---|
| A player drawer / bottom sheet / drag-to-open panel | The drag-vs-tap spec contradicted itself, and hand-rolled drag physics is exactly what this stack should not own. Deleting it also un-hides volume on mobile and removes the one radius that broke our own 4–6px law. |
| Per-row artwork | 37% of stations have none, many stored URLs are dead, and it is 50 image requests per page for an asset that mostly fails. The dock's 44px chip is the entire artwork surface. |
| Bordered metadata chips in rows | 150+ boxes on screen is an admin-table export — the feeling that got the last design rejected. |
| The magazine metaphor — drop caps, dot leaders, two-column directory, small-caps standfirsts | Classifieds is print's least glamorous page; the metaphor also demands a serif we cannot afford to load, and the drop-cap idea collides across Classical / Country / Chillout / Children's. |
| A text-only, borderless genre rail | 33 plain-text labels read as a caption, not as controls. Genre keys keep their bevels. |
| A 40px now-playing hero anywhere | The dock is the identity now. One giant name makes everything else decoration. |
| Marquee / scrolling track titles | The single most recognisable cheap-web-radio tell. Ellipsis plus `title=`. |
| Skeletons, spinners or "Unknown track" for a missing song title | Absent data renders as nothing. Faking it is the difference between honest and cheap. |
| A Web Audio analyser / real spectrum meter | It requires `crossOrigin` on the `<audio>` element, which risks breaking playback on stations without CORS headers. Never worth it. |
| Infinite scroll | 50 rows per page, server-rendered, fragment-swapped: that is what keeps audio alive across navigation. |
| React / Vue / an SPA router / websockets / Reverb | Not in this stack. Now-playing is a cache read plus a queued worker; that is all it needs. |
| Per-station background workers or a metadata daemon | One worker, one lock per station, on demand, only for stations someone is actually listening to. |
| A synthetic "popularity score" invented to hide sparse data | We fix the label and the tiebreak instead. Inventing a number is how "sorting doesn't work" happens twice. |
| Dimming the star field further | It is the only atmospheric element on the page, and it is what makes precision feel like a room. |

---

## 12. Honest open risks

1. **The sort fix is the bet.** Even with `play_count`, the column starts at zero for all 58,407
   rows and only becomes meaningful after weeks of traffic. Until then the honest default is
   "Popular worldwide", and the owner may still feel the tail is arbitrary — the A–Z tiebreak
   mitigates that but does not make the data richer.
2. **The bevel is the whole identity, and it is unverified in a browser.** If the light-theme
   shadow-edge override reads muddy or invisible, the "weighted control" premise collapses in one
   of four required themes. Gate G1 exists precisely for this.
3. **The glow is genuinely a different effect in the light theme** (a drop shadow rather than a
   bloom). That is honest, but it means the page's one signature accent has two personalities, and
   the owner may only ever look at one theme.
4. **ICY extraction is real network code against 58,407 servers we do not control.** Ragged
   responses, chunked-transfer oddities, HTTPS/HTTP mixed content, redirects to playlists (.pls,
   .m3u) rather than streams, and servers that send `icy-metaint` but never a `StreamTitle` are all
   expected. Budget for the fetcher taking longer than the UI around it.
5. **The split-flap flip is bespoke.** Wrong easing or duration reads as a glitch, and it is the
   only motion moment in the design, so nothing else carries the craft if it disappoints.
6. **84–88px of permanent bottom chrome is gone forever** on every view. On a 568px-tall phone with
   the sticky genre bank also present, the visible catalogue is roughly 330px — about five rows.
   This needs measuring on a real short viewport, not assumed from the arithmetic.
7. **Removing votes and listener counts from the resting row** will read as a data regression to
   anyone who used them, even though both are null for most of the catalogue.
8. **Moving the settings gear to a fixed top-right control** touches the app shell, not just the
   radio page. It is required (one fixed layer per edge) but it is scope beyond this page and needs
   sign-off.

---

## 13. Build gates

- **G1 — before anything else is built:** render the dock plus ten catalogue rows in all four
  themes, over the real full-bleed background image with the star field running, at 400px and at
  1440px. Confirm the bevel, the glow, the ember and the 10% hairlines are legible in each. If the
  light theme fails, fix the override before writing any more markup.
- **G2 — before the rubric is allowed to say "Most played":** run the §8 audit query, land the
  tiebreak change and the indexes, and ship `play_count`.
- **G3 — before shipping now-playing:** load-test the endpoint with 100 simulated listeners across
  10 stations and confirm exactly one upstream read per station per 20s window, and zero PHP-FPM
  workers blocked on a socket.
