/**
 * The spectrum.
 *
 * A real Web Audio analyser is not an option here: feeding one requires
 * `crossOrigin` on the media element, and any stream whose server omits CORS
 * headers then plays SILENTLY. Most of this catalogue omits them. Trading
 * working audio for an accurate visualiser is a bad deal.
 *
 * So this is synthesised: each bar is driven by three sine waves at
 * incommensurable frequencies plus a slow drift, which never repeats visibly
 * and reads as music rather than as a loop. Bars ease toward their target so
 * the motion has weight, and on pause they settle to a resting line instead of
 * freezing mid-frame.
 */

const BAR_W = 3;    // a spectrum is thin bars; wide ones read as blobs
const BAR_GAP = 3;
const MAX_BARS = 320;
const RISE = 0.28;   // how fast a bar climbs toward its target
const FALL = 0.09;   // slower, so peaks linger — the thing that reads as "audio"

export function mountSpectrum(canvas, isPlayingFn) {
    if (!canvas || typeof canvas.getContext !== 'function') {
        return () => {};
    }

    const ctx = canvas.getContext('2d', { alpha: true });
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Per-bar phase offsets and rates, fixed once so each bar keeps its own
    // character across the whole session.
    let BARS = 96;

    const makeSeed = (n) => Array.from({ length: n }, (_, i) => ({
        a: 0.6 + Math.sin(i * 12.9898) * 0.5 + 1,
        f1: 0.7 + ((i * 0.37) % 1) * 1.4,
        f2: 1.9 + ((i * 0.71) % 1) * 2.2,
        f3: 4.1 + ((i * 0.13) % 1) * 3.0,
        p: (i * 1.618) % (Math.PI * 2),
    }));

    let seed = makeSeed(BARS);
    let level = new Float32Array(BARS);
    let raf = null;
    let dpr = 1;
    let w = 0;
    let h = 0;

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        const r = canvas.getBoundingClientRect();
        w = Math.max(1, Math.round(r.width));
        h = Math.max(1, Math.round(r.height));
        canvas.width = Math.round(w * dpr);
        canvas.height = Math.round(h * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        const next = Math.max(24, Math.min(MAX_BARS, Math.floor(w / (BAR_W + BAR_GAP))));

        if (next !== BARS) {
            BARS = next;
            seed = makeSeed(BARS);
            level = new Float32Array(BARS);
        }
    }

    function paint(t) {
        const playing = !!isPlayingFn();
        const time = t / 1000;

        ctx.clearRect(0, 0, w, h);

        const bw = BAR_W;
        const gap = BAR_GAP;
        const span = BARS * (bw + gap) - gap;
        const originX = (w - span) / 2;

        // Brass, brighter in the middle of the field — the eye reads the
        // centre as the source.
        const grad = ctx.createLinearGradient(0, 0, w, 0);
        grad.addColorStop(0, 'rgba(210,162,76,0.30)');
        grad.addColorStop(0.5, 'rgba(226,183,95,0.85)');
        grad.addColorStop(1, 'rgba(210,162,76,0.30)');
        ctx.fillStyle = grad;

        for (let i = 0; i < BARS; i++) {
            const s = seed[i];

            // A soft bell across the field so the edges stay quieter than the
            // middle; a flat spectrum looks like a graphic, not like sound.
            const centre = 1 - Math.abs(i / (BARS - 1) - 0.5) * 1.55;
            const bell = Math.max(0.12, centre);

            let target;

            if (playing) {
                const v =
                    Math.sin(time * s.f1 + s.p) * 0.5 +
                    Math.sin(time * s.f2 + s.p * 1.7) * 0.32 +
                    Math.sin(time * s.f3 + s.p * 2.3) * 0.18;
                target = Math.max(0.04, (v * 0.5 + 0.5) * bell * s.a * 0.62);
            } else {
                target = 0.045 * bell;
            }

            const k = target > level[i] ? RISE : FALL;
            level[i] += (target - level[i]) * (reduced ? 1 : k);

            const bh = Math.max(2, level[i] * h);
            const x = originX + i * (bw + gap);
            const y = (h - bh) / 2;

            ctx.beginPath();
            ctx.roundRect(x, y, bw, bh, bw / 2);
            ctx.fill();
        }

        raf = requestAnimationFrame(paint);
    }

    resize();
    const ro = new ResizeObserver(resize);
    ro.observe(canvas);

    // A backgrounded tab should not be painting 60 times a second.
    function onVisibility() {
        if (document.hidden) {
            if (raf) cancelAnimationFrame(raf);
            raf = null;
        } else if (!raf) {
            raf = requestAnimationFrame(paint);
        }
    }
    document.addEventListener('visibilitychange', onVisibility);

    raf = requestAnimationFrame(paint);

    return () => {
        if (raf) cancelAnimationFrame(raf);
        ro.disconnect();
        document.removeEventListener('visibilitychange', onVisibility);
    };
}
