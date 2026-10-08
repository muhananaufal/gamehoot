// U30: the small moves of the Public View and the phones. Scenes themselves change with Alpine's
// x-transition and the scene-* classes in app.css (components/scene.blade.php); this module holds
// the rules that decide when something moves. Data never waits for motion: a nudge only decorates
// a value that is already on screen.
import { EASE, MOTION } from './motion.js';

/** The stroke offset of the countdown ring: 0 is full, the circumference is empty. */
export function ringOffset(msLeft, durationSeconds, circumference) {
    if (!durationSeconds || durationSeconds <= 0) {
        return circumference;
    }

    const left = Math.min(1, Math.max(0, msLeft / (durationSeconds * 1_000)));

    return Math.round((1 - left) * circumference * 100) / 100;
}

/** How tense the countdown looks: pulsing from five seconds, red-orange from three (U30). */
export function urgencyOf(seconds) {
    if (!seconds || seconds <= 0) {
        return 'calm';
    }

    if (seconds <= 3) {
        return 'last';
    }

    return seconds <= 5 ? 'soon' : 'calm';
}

/** A short "Back online" once the socket returns after a drop (T2). */
export function connectionNotice(previous, current) {
    return current === 'connected' && (previous === 'reconnecting' || previous === 'offline') ? 'back_online' : null;
}

export const NUDGES = Object.freeze({
    // A number that changed: a quick pop.
    pop: {
        frames: [{ transform: 'scale(1)' }, { transform: 'scale(1.25)' }, { transform: 'scale(1)' }],
        duration: MOTION.slow,
        easing: EASE.overshoot,
    },
    // A countdown second in the last five: a smaller beat.
    pulse: {
        frames: [{ transform: 'scale(1)' }, { transform: 'scale(1.12)' }, { transform: 'scale(1)' }],
        duration: MOTION.slow,
        easing: EASE.overshoot,
    },
    // A letter box that opens turns to show its face (E5).
    flip: {
        frames: [{ transform: 'perspective(600px) rotateY(90deg)' }, { transform: 'perspective(600px) rotateY(0)' }],
        duration: MOTION.slow,
        easing: EASE.overshoot,
    },
    // A refused action: a small no.
    shake: {
        frames: [
            { transform: 'translateX(0)' },
            { transform: 'translateX(-6px)' },
            { transform: 'translateX(5px)' },
            { transform: 'translateX(-3px)' },
            { transform: 'translateX(0)' },
        ],
        duration: MOTION.quick,
        easing: 'linear',
    },
});

/** Whether a value change deserves a nudge. The first value never moves (a reload is not news). */
export function shouldNudge(kind, previous, next, first) {
    if (first || previous === next) {
        return false;
    }

    if (kind === 'pulse') {
        return next !== null && next !== undefined;
    }

    if (kind === 'flip') {
        return previous === 'closed' && next !== 'closed';
    }

    return true;
}

export function nudge(el, kind, { reduced = false } = {}) {
    const move = NUDGES[kind];

    if (!move || reduced || typeof el.animate !== 'function') {
        return null;
    }

    return el.animate(move.frames, { duration: move.duration, easing: move.easing });
}

/**
 * U30: the x-nudge directive. x-nudge.pop="count" pops the element whenever count changes.
 * Kinds: pop, pulse, flip, shake.
 */
export function nudgeDirective(Alpine) {
    return (el, { expression, modifiers }, { effect, evaluateLater }) => {
        const kind = modifiers.find((modifier) => modifier in NUDGES) ?? 'pop';
        const read = evaluateLater(expression);
        let previous;
        let first = true;

        effect(() => {
            read((value) => {
                if (shouldNudge(kind, previous, value, first)) {
                    nudge(el, kind, { reduced: Alpine.store('motion')?.reduced ?? false });
                }

                previous = value;
                first = false;
            });
        });
    };
}

/**
 * U30: Penta in the lobby waves when someone joins and dozes off after a quiet minute.
 * `tell` hands a mood to Penta.
 */
export function lobbyMood({ quietMs = 60_000, tell }) {
    let last = null;
    let timer = null;

    const arm = () => {
        clearTimeout(timer);
        timer = setTimeout(() => tell('sleepy'), quietMs);
    };

    return {
        joined(count) {
            if (last !== null && count > last) {
                tell('wave');
            }

            if (last === null || count > last) {
                arm();
            }

            last = count;
        },

        stop() {
            clearTimeout(timer);
            timer = null;
        },
    };
}
