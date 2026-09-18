import Alpine from 'alpinejs';
import radioPlayer from './radio/player';
import radioSettings from './radio/settings';
import { initParticles } from './radio/particles';

window.Alpine = Alpine;

Alpine.data('radioPlayer', radioPlayer);
Alpine.data('radioSettings', radioSettings);

Alpine.start();

initParticles('tsparticles');
