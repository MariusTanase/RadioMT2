import Alpine from 'alpinejs';
import catalogue from './radio/catalogue';
import { mountSpectrum } from './radio/visualiser';
import './radio/now-playing';
import radioPlayer from './radio/player';
import radioSettings from './radio/settings';
import { initParticles } from './radio/particles';

window.Alpine = Alpine;
// Exposed so the dock's inline x-init can mount it without an import map.
window.__mountSpectrum = mountSpectrum;

Alpine.data('catalogue', catalogue);
Alpine.data('radioPlayer', radioPlayer);
Alpine.data('radioSettings', radioSettings);

Alpine.start();

initParticles('tsparticles').catch(() => {});

// The spectrum is mounted here rather than from an x-init expression: reading
// `isPlaying` through Alpine's scope proxy inside a rAF closure did not resolve,
// and a canvas that silently paints nothing is hard to notice.

// Mounted immediately after Alpine.start(): the 'alpine:initialized' event has
// already fired by this line, so listening for it here never runs. A canvas
// that silently paints nothing gives no error to notice.
{
    const canvas = document.querySelector('.dock-spectrum');

    if (canvas) {
        mountSpectrum(canvas, () => !!Alpine.$data(document.body)?.isPlaying);
    }
}
