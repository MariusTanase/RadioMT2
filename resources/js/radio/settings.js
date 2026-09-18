function readStoredTheme() {
    try {
        return localStorage.getItem('theme') ?? 'blue';
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

export default function radioSettings() {
    return {
        menuOpen: false,
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
                document.querySelector('.background').style.backgroundImage = `url(${url})`;
            } catch {
                // Leave the current background in place; the server already
                // falls back to the bundled image when Unsplash is unavailable.
            }
        },
    };
}
