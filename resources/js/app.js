import Alpine from 'alpinejs';
import { initParticles } from './radio/particles';

window.Alpine = Alpine;

Alpine.start();

initParticles('tsparticles');
