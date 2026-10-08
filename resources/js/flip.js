// U8: lists and tables that move like the app is alive. When rows are sorted, filtered, paged,
// added or removed, rows that stay slide from where they were to where they are now (FLIP), and
// rows that appear fade and rise in. A row is known by data-flip-key when it has one (rank lists
// rebuild a row when its rank changes) or else by the element itself (tables reorder rows).
// Positions are taken against the list, so scrolling the page never moves rows.
import { animate as motionAnimate } from 'motion';
import { SPRING } from './motion.js';

// U27: the spring now lives with the other motion tokens; kept here for the modules that import it.
export { SPRING };

/** At most this many rows wait for their turn when a page opens; the rest come in together. */
const STAGGER_ROWS = 12;
const STAGGER_SECONDS = 0.025;

let enteredAt = -Infinity;

/** Called when a page opens (first load and every page change, U18). */
export function markPageEntered(now = globalThis.performance?.now() ?? 0) {
    enteredAt = now;
}

/** Whether rows that appear now belong to a page that just opened (not a live refresh, U12). */
export function pageJustEntered(now = globalThis.performance?.now() ?? 0) {
    return now - enteredAt < 600;
}

/** U27: the mode on <html data-motion> (device setting or Reduce motion switch), else the device setting. */
export function prefersReducedMotion(win = globalThis) {
    const mode = win.document?.documentElement?.dataset?.motion;

    if (mode === 'full' || mode === 'reduced') {
        return mode === 'reduced';
    }

    return win.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

/**
 * Watches a list and animates its changes. `selector` picks the rows; `enter: false` leaves the
 * appearing rows to CSS (rank lists have their own enter animation).
 */
export function flipList(
    container,
    {
        selector = ':scope > *',
        enter = true,
        animate = motionAnimate,
        observe = true,
        reduced = prefersReducedMotion,
        entering = pageJustEntered,
    } = {},
) {
    const last = new Map();
    const keyOf = (el) => el.dataset?.flipKey ?? el;
    const rows = () => [...container.querySelectorAll(selector)];

    const positions = () => {
        const origin = container.getBoundingClientRect();

        return rows()
            .filter((el) => !el.hidden)
            .map((el) => {
                const rect = el.getBoundingClientRect();

                return {
                    el,
                    key: keyOf(el),
                    left: rect.left - origin.left,
                    top: rect.top - origin.top,
                    size: rect.height,
                };
            })
            .filter(({ size }) => size > 0);
    };

    const play = (first = false) => {
        const now = positions();
        const seen = new Set(now.map(({ key }) => key));
        const still = reduced();
        const appeared = [];

        for (const { el, key, left, top } of now) {
            const before = last.get(key);
            last.set(key, { left, top });

            if (still) {
                continue;
            }

            if (!before) {
                appeared.push(el);
            } else if (before.left !== left || before.top !== top) {
                // A rank list rebuilds the row: it moves instead of entering again.
                el.style.animation = 'none';
                animate(el, { x: [before.left - left, 0], y: [before.top - top, 0] }, SPRING);
            }
        }

        // Hidden or removed rows are forgotten: shown again later, they enter instead of flying.
        for (const key of [...last.keys()]) {
            if (!seen.has(key)) {
                last.delete(key);
            }
        }

        if (!still && enter && appeared.length > 0 && (!first || entering())) {
            animate(
                appeared,
                { opacity: [0, 1], y: [8, 0] },
                { ...SPRING, delay: (index) => Math.min(index, STAGGER_ROWS) * STAGGER_SECONDS },
            );
        }
    };

    play(true);

    // The observer runs after the DOM changed and before the next paint: no row flickers.
    const observer = observe && typeof MutationObserver === 'function' ? new MutationObserver(() => play()) : null;
    observer?.observe(container, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });

    return {
        play: () => play(),
        stop: () => observer?.disconnect(),
    };
}
