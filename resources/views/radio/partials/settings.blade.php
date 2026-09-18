{{--
    Settings. A quiet top-right affordance that opens a small panel — never a
    second bar competing with the dock for the bottom edge.
--}}
<div x-data="radioSettings" class="settings-root">
    <button class="settings-btn" @click="menuOpen = !menuOpen"
            x-bind:aria-expanded="menuOpen ? 'true' : 'false'"
            aria-label="Settings">
        <x-icon.gear class="animate-gear" />
    </button>

    <div class="settings-panel" x-show="menuOpen" x-transition.opacity
         @click.outside="menuOpen = false"
         x-on:keydown.escape.window="menuOpen = false">

        <p class="settings-h">Theme</p>
        <div class="swatches">
            @foreach ([
                'brass' => '#d2a24c', 'ember' => '#ef6b4a', 'ice' => '#6fb6ff',
                'moss'  => '#6fd39b', 'mono'  => '#ededf0',
            ] as $name => $dot)
                <button class="swatch" style="background: {{ $dot }}"
                        x-bind:aria-pressed="theme === '{{ $name }}' ? 'true' : 'false'"
                        @click="setTheme('{{ $name }}')"
                        aria-label="{{ ucfirst($name) }} theme"></button>
            @endforeach
        </div>

        <p class="settings-h" style="margin-top:18px">Display</p>
        <label class="switch">
            <input type="checkbox" x-model="showSpectrum" @change="applySpectrum()">
            Spectrum
        </label>
    </div>
</div>
