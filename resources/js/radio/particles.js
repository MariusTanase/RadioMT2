import { tsParticles } from '@tsparticles/engine';
import { loadFull } from 'tsparticles';

export async function initParticles(selector) {
    await loadFull(tsParticles);

    await tsParticles.load({
        id: selector,
        options: {
            particles: {
                number: { value: 50, density: { enable: false, area: 800 } },
                color: { value: '#fff' },
                shape: { type: 'star' },
                opacity: { value: 0.8 },
                size: { value: 4 },
                rotate: {
                    value: { min: 0, max: 360 },
                    direction: 'clockwise',
                    animation: { enable: true, speed: 5, sync: false },
                },
                links: { enable: false },
                move: {
                    enable: true,
                    speed: 2,
                    direction: 'none',
                    random: false,
                    straight: false,
                    outModes: { default: 'out' },
                },
            },
            detectRetina: true,
            fullScreen: { enable: false },
        },
    });
}
