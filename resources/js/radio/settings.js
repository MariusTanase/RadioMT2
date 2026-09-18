function readStoredTheme() {
    try {
        return (THEMES.includes(localStorage.getItem('theme')) ? localStorage.getItem('theme') : 'brass');
    } catch {
        return 'blue';
    }
}

function writeStoredTheme(name) {
    try {
        localStorage.setItem('theme', name);
    } catch {
        // Some privacy/incognito configurations throw on localStorage access.
        // The theme still applies for this session; it just won't persist.
    }
}

const THEMES = ['brass', 'ember', 'ice', 'moss', 'mono'];

export default function radioSettings() {
    return {
        menuOpen: false,
        showSpectrum: (() => { try { return localStorage.getItem('spectrum') !== '0'; } catch { return true; } })(),

        applySpectrum() {
            document.body.classList.toggle('no-spectrum', !this.showSpectrum);
            try { localStorage.setItem('spectrum', this.showSpectrum ? '1' : '0'); } catch {}
        },

        theme: readStoredTheme(),

        open() {
            this.menuOpen = true;
        },

        close() {
            this.menuOpen = false;
        },

        setTheme(name) {
            this.theme = name;
            writeStoredTheme(name);
            document.body.dataset.theme = name;
        },

        async setBackground(category) {
            try {
                const response = await fetch(`/api/background?query=${encodeURIComponent(category)}`);

                if (!response.ok) {
                    return;
                }

                const { url } = await response.json();
                document.querySelector('.background').style.backgroundImage = `url("${url}")`;
            } catch {
                // Leave the current background in place; the server already
                // falls back to the bundled image when Unsplash is unavailable.
            }
        },
    };
}
