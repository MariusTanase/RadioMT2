// The navigation controller for the manifest (§6.1 of the design direction).
//
// Every filter/sort/page control on the page is a real <a href> or <form
// method="get"> so the page works with JS disabled. This controller
// intercepts those same elements, replays their navigation as a
// fetch-and-swap of #catalogue, and keeps the URL in sync via
// history.pushState/popstate. The Deck, the <audio> element, the settings
// panel and the footer live outside #catalogue and this file never touches
// them — that is what keeps audio playing while the list changes underneath.
export default function catalogue() {
    return {
        // ---- reactive state Blade binds to for the loading/error UI (§6.5) --
        // `loading` flips immediately; `pending` only flips true after a 120ms
        // grace period so a fast response never flashes the dim/progress UI.
        loading: false,
        pending: false,
        error: false,
        errorMessage: "Couldn't reach the catalogue.",

        // ---- private bookkeeping (not meant to be read from Blade) ---------
        _controller: null,
        _token: 0,
        _pendingTimer: null,
        _lastPathname: null,
        _lastSearch: '',

        init() {
            document.addEventListener('click', (event) => this._onClick(event));
            document.addEventListener('submit', (event) => this._onSubmit(event));
            window.addEventListener('popstate', () => this._onPopstate());

            this._lastPathname = location.pathname;
            this._lastSearch = location.search;
        },

        // ---- public API ------------------------------------------------------

        // Merge `patch` into the current URL's params and navigate there.
        // `page` resets to 1 (i.e. removed from the URL) unless the patch
        // itself is a page change — this is what every filter/sort/search
        // control calls.
        navigate(patch = {}) {
            const params = new URLSearchParams(location.search);

            for (const [key, value] of Object.entries(patch)) {
                if (value === null || value === undefined || value === '') {
                    params.delete(key);
                } else {
                    params.set(key, value);
                }
            }

            if (!('page' in patch)) {
                params.delete('page');
            }

            this._perform(params, { push: true, pathname: location.pathname });
        },

        // Re-run the last attempted navigation without pushing a new history
        // entry — the URL didn't change, only the network attempt failed.
        retry() {
            this._perform(new URLSearchParams(this._lastSearch), {
                push: false,
                pathname: this._lastPathname,
            });
        },

        // ---- click / submit interception (progressive enhancement) --------

        _onClick(event) {
            // Let modified clicks (open in new tab, middle click, etc.) behave
            // like ordinary browser navigation instead of being hijacked.
            if (
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey
            ) {
                return;
            }

            const link = event.target.closest('a[href]');
            if (!link || !this._isOwned(link)) {
                return;
            }

            const url = new URL(link.href, location.href);
            if (url.origin !== location.origin) {
                return;
            }

            event.preventDefault();
            this._perform(new URLSearchParams(url.search), { push: true, pathname: url.pathname });
        },

        _onSubmit(event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            // Only GET forms describe a catalogue view via the URL; anything
            // else (there shouldn't be any inside our regions) is left alone.
            if ((form.method || 'get').toLowerCase() !== 'get' || !this._isOwned(form)) {
                return;
            }

            const actionUrl = new URL(form.getAttribute('action') || location.href, location.href);
            if (actionUrl.origin !== location.origin) {
                return;
            }

            event.preventDefault();
            const params = new URLSearchParams(new FormData(form));
            this._perform(params, { push: true, pathname: actionUrl.pathname });
        },

        // Back/forward: the browser already changed location.search for us —
        // just re-fetch it, without pushing another history entry.
        _onPopstate() {
            this._perform(new URLSearchParams(location.search), {
                push: false,
                pathname: location.pathname,
            });
        },

        // A click/submit belongs to us if it originates inside the swapped
        // manifest region, or inside the genre band/overlay — the band lives
        // inside #catalogue in this build (its counts are re-rendered on every
        // navigation, per §5.3/§6.1), but `nav[aria-label="Genres"]` and the
        // `data-catalogue-nav` escape hatch are also honoured in case a future
        // change moves the band or the "All genres" overlay outside it.
        _isOwned(el) {
            return Boolean(
                el.closest('#catalogue') ||
                    el.closest('nav[aria-label="Genres"]') ||
                    el.closest('[data-catalogue-nav]')
            );
        },

        // ---- fetch + swap ----------------------------------------------------

        _perform(params, { push, pathname }) {
            const qs = params.toString();
            const pageUrl = `${pathname}${qs ? `?${qs}` : ''}`;

            this._lastPathname = pathname;
            this._lastSearch = qs ? `?${qs}` : '';

            if (push) {
                history.pushState(null, '', pageUrl);
            }

            // Each navigation aborts whatever is still in flight...
            this._controller?.abort();
            this._controller = new AbortController();

            // ...and takes a fresh token. AbortController handles the common
            // case (cancel the network work), but an aborted fetch's promise
            // can still resolve in some browsers, and a slow-but-never-aborted
            // response can lose a race against a newer request that finished
            // first. The token is checked right before anything touches the
            // DOM, so only the most recently issued navigation's response is
            // ever allowed to render — even if a stale one "wins" the network.
            const token = ++this._token;

            this.loading = true;
            this.error = false;

            clearTimeout(this._pendingTimer);
            this._pendingTimer = setTimeout(() => {
                if (this._token === token) {
                    this.pending = true;
                }
            }, 120);

            fetch(`/api/catalogue${qs ? `?${qs}` : ''}`, {
                signal: this._controller.signal,
                headers: { Accept: 'text/html' },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`catalogue fetch failed: ${response.status}`);
                    }

                    return response.text();
                })
                .then((html) => {
                    if (this._token !== token) {
                        return; // superseded by a newer navigation — discard
                    }

                    this._swap(html);
                })
                .catch((err) => {
                    // A deliberate abort is not a failure — the request that
                    // superseded it is the one that gets to report an error.
                    if (err?.name === 'AbortError' || this._token !== token) {
                        return;
                    }

                    this.error = true;
                })
                .finally(() => {
                    if (this._token === token) {
                        clearTimeout(this._pendingTimer);
                        this.loading = false;
                        this.pending = false;
                    }
                });
        },

        // Replace the manifest fragment and tell the rest of the page about it.
        _swap(html) {
            const container = document.getElementById('catalogue');
            if (!container) {
                return; // page markup changed under us — nothing safe to do
            }

            container.innerHTML = html;
            this.error = false;

            window.dispatchEvent(
                new CustomEvent('catalogue:swapped', {
                    detail: { stations: this._extractStations(container) },
                })
            );

            // Scroll the manifest back to its own top — never the document's —
            // so a page turn doesn't drag the Deck back into view.
            container.scrollIntoView({ block: 'start' });
        },

        // The fragment is HTML, not JSON, so the fresh page-local station list
        // the player needs travels as a JSON data island the fragment embeds:
        // <script type="application/json" id="catalogue-stations">[...]</script>,
        // mirroring how the initial page load seeds window.RADIO_STATIONS. If a
        // fragment doesn't carry one, fail soft with an empty list rather than
        // throwing — the swap itself must never be blocked by this.
        _extractStations(container) {
            const island = container.querySelector('#catalogue-stations');
            if (!island) {
                return [];
            }

            try {
                const data = JSON.parse(island.textContent);
                return Array.isArray(data) ? data : [];
            } catch {
                return [];
            }
        },
    };
}
