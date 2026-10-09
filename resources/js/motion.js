// U27: one source for motion. The durations match the tokens in resources/css/app.css (checked by
// tests/js/motion.test.js); the store decides between full and reduced motion for this device.
// The inline script in components/motion-script.blade.php applies the same rule before first paint.

/** Durations in milliseconds: feedback, menus and leaving, dialogs and phone scenes, projector scenes. */
export const MOTION = Object.freeze({ instant: 100, quick: 160, base: 240, slow: 360 });

/** U8: the spring of the app: settles in about 300 ms with a small overshoot. */
export const SPRING = Object.freeze({ type: 'spring', visualDuration: 0.3, bounce: 0.2 });

/** Easings, the same strings as the --ease-* tokens in app.css (checked by tests/js/motion.test.js). */
export const EASE = Object.freeze({
    out: 'cubic-bezier(0.2, 0, 0, 1)',
    in: 'cubic-bezier(0.4, 0, 1, 1)',
    overshoot: 'cubic-bezier(0.34, 1.4, 0.64, 1)',
    spring: 'linear(0, 0.029 2.2%, 0.118 4.7%, 0.625 14.4%, 0.826 19%, 0.978 24.2%, 1.072 29.4%, 1.112 33.7%, 1.117 38.4%, 1.083 46.1%, 1.008 59.3%, 0.986 66.4%, 0.988 81.2%, 1)',
    bounce: 'linear(0, 0.2 8%, 0.75 20%, 1.2 32%, 1.32 38%, 1.2 46%, 0.95 60%, 0.92 66%, 1.03 80%, 1)',
});

export const MOTION_STORAGE_KEY = 'pentahoot-motion';

/** The mode for this device: the viewer's own choice, otherwise the device setting. */
export function resolveMotion(stored, prefersReduced) {
    if (stored === 'full' || stored === 'reduced') {
        return stored;
    }

    return prefersReduced ? 'reduced' : 'full';
}

function read(storage) {
    try {
        return storage?.getItem(MOTION_STORAGE_KEY) ?? null;
    } catch {
        return null;
    }
}

/**
 * U27: the Reduce motion switch. The mode lives on <html data-motion> so CSS can read it; a choice
 * is remembered per device, and without one the store follows the device setting live.
 * `onChange` hears every mode, starting with the current one (Unpoly's motion switch uses it).
 */
export function motionStore({
    root = globalThis.document?.documentElement,
    storage = globalThis.localStorage,
    media = globalThis.matchMedia?.('(prefers-reduced-motion: reduce)'),
    onChange = () => {},
} = {}) {
    const apply = (mode) => {
        root.dataset.motion = mode;
        onChange(mode);

        return mode;
    };

    const store = {
        mode: apply(
            root.dataset.motion === 'full' || root.dataset.motion === 'reduced'
                ? root.dataset.motion
                : resolveMotion(read(storage), media?.matches ?? false),
        ),

        get reduced() {
            return this.mode === 'reduced';
        },

        toggle() {
            this.mode = apply(this.reduced ? 'full' : 'reduced');

            try {
                storage?.setItem(MOTION_STORAGE_KEY, this.mode);
            } catch {
                // Storage can be blocked (private mode); the choice then lasts for this page only.
            }
        },
    };

    media?.addEventListener?.('change', (event) => {
        if (read(storage) === null) {
            store.mode = apply(event.matches ? 'reduced' : 'full');
        }
    });

    return store;
}
