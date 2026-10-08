// U31: celebrations for the key moments only: rank 1 of a Pentahoot question after the E2 pause,
// a Tebak winner and a final podium (E17). Penta cheers and confetti falls in the five brand
// colours. The projector draws on one canvas (device pixel ratio at most 1.5, a fixed number of
// pieces); phones use a few DOM pieces. Each moment plays once per tab (sessionStorage), never in
// the reduced mode (U27), and never on a Public View whose host turned Screen animations off.
const STORAGE_PREFIX = 'pentahoot-celebrated:';

export const CONFETTI = Object.freeze({
    colors: Object.freeze(['yellow', 'coral', 'mint', 'blue', 'pink']),
    spreadMs: 400,
    projector: Object.freeze({ count: 140, durationMs: 2_800, maxDpr: 1.5 }),
    phone: Object.freeze({ count: 28, durationMs: 1_800 }),
    card: Object.freeze({ count: 14, durationMs: 1_400 }),
});

/** True the first time this tab sees the moment; a reconnect or a reload then stays quiet. */
export function firstTime(storage, key) {
    try {
        if (storage?.getItem(STORAGE_PREFIX + key)) {
            return false;
        }

        storage?.setItem(STORAGE_PREFIX + key, '1');
    } catch {
        // Blocked storage only means a repeat after a reload.
    }

    return true;
}

export function confettiPieces({ count, random = Math.random }) {
    return Array.from({ length: count }, (_, index) => ({
        x: random(),
        delay: Math.round(random() * CONFETTI.spreadMs),
        drift: (random() - 0.5) * 0.3,
        turns: 1 + random() * 3,
        size: 6 + random() * 6,
        // Every colour shows, also for a small count.
        color: CONFETTI.colors[index % CONFETTI.colors.length],
    }));
}

export function canvasSize({ width, height, dpr }) {
    const scale = Math.min(dpr || 1, CONFETTI.projector.maxDpr);

    return { width: Math.round(width * scale), height: Math.round(height * scale), scale };
}

/** One frame of a piece at `t` ms, in CSS pixels: falls with a sway and turns. */
function pieceAt(piece, t, duration, width, height) {
    const p = Math.min(1, Math.max(0, (t - piece.delay) / (duration - CONFETTI.spreadMs)));

    return {
        p,
        x: (piece.x + piece.drift * p + Math.sin(p * Math.PI * 2 * piece.turns) * 0.02) * width,
        y: -piece.size + p * p * (height + piece.size * 2),
        angle: p * Math.PI * 2 * piece.turns,
    };
}

function colorsOf(doc) {
    const style = doc.defaultView.getComputedStyle(doc.documentElement);

    return Object.fromEntries(
        CONFETTI.colors.map((name) => [name, style.getPropertyValue(`--color-brand-${name}`).trim()]),
    );
}

function canvasConfetti(doc, layer, random) {
    const { count, durationMs } = CONFETTI.projector;
    const win = doc.defaultView;
    const canvas = doc.createElement('canvas');
    const size = canvasSize({ width: win.innerWidth, height: win.innerHeight, dpr: win.devicePixelRatio });
    canvas.width = size.width;
    canvas.height = size.height;
    canvas.className = 'size-full';
    layer.append(canvas);

    const context = canvas.getContext('2d');
    const pieces = confettiPieces({ count, random });
    const colors = colorsOf(doc);
    const start = win.performance.now();

    const frame = (now) => {
        const t = now - start;
        context.setTransform(size.scale, 0, 0, size.scale, 0, 0);
        context.clearRect(0, 0, win.innerWidth, win.innerHeight);

        for (const piece of pieces) {
            const at = pieceAt(piece, t, durationMs, win.innerWidth, win.innerHeight);

            if (at.p <= 0 || at.p >= 1) {
                continue;
            }

            context.save();
            context.translate(at.x, at.y);
            context.rotate(at.angle);
            context.fillStyle = colors[piece.color];
            context.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size / 2);
            context.restore();
        }

        if (t < durationMs) {
            win.requestAnimationFrame(frame);
        } else {
            canvas.remove();
        }
    };

    win.requestAnimationFrame(frame);
}

function domConfetti(doc, layer, random, { count, durationMs } = CONFETTI.phone, height = doc.defaultView.innerHeight) {
    for (const piece of confettiPieces({ count, random })) {
        const el = doc.createElement('span');
        el.className = 'confetti-piece';
        el.style.left = `${piece.x * 100}%`;
        el.style.width = `${piece.size}px`;
        el.style.height = `${piece.size / 2}px`;
        el.style.background = `var(--color-brand-${piece.color})`;
        layer.append(el);

        el.animate(
            [
                { transform: 'translate(0, -20px) rotate(0turn)' },
                { transform: `translate(${piece.drift * 200}px, ${height + 20}px) rotate(${piece.turns}turn)` },
            ],
            {
                duration: durationMs - CONFETTI.spreadMs,
                delay: piece.delay,
                easing: 'cubic-bezier(0.4, 0, 1, 1)',
                fill: 'backwards',
            },
        ).onfinish = () => el.remove();
    }
}

/** U32: a few pieces over a card (the winner of Live control), falling the card's own height. */
export function cardConfetti(layer, { doc = globalThis.document, random = Math.random, reduced = false } = {}) {
    if (reduced) {
        return;
    }

    domConfetti(doc, layer, random, CONFETTI.card, layer.clientHeight);
}

/**
 * The celebration layer of the Public View (projector: true) or a phone. Pages announce a moment
 * with $dispatch('celebrate', { key }); the key names the moment, e.g. "won:<question id>".
 */
export function celebration({
    projector = false,
    storage = globalThis.sessionStorage,
    random = Math.random,
    doc = globalThis.document,
} = {}) {
    let timer = null;

    return {
        cheering: false,

        get duration() {
            return projector ? CONFETTI.projector.durationMs : CONFETTI.phone.durationMs;
        },

        /** U27 on every screen; on the Public View also the host's Screen animations (U31). */
        get confettiAllowed() {
            if (this.$store.motion?.reduced) {
                return false;
            }

            return !projector || doc?.documentElement?.dataset?.screenAnimations !== 'off';
        },

        play({ key }) {
            if (!firstTime(storage, key)) {
                return;
            }

            this.cheering = true;
            this.$dispatch('mascot-react', { name: 'celebration', mood: 'cheer' });
            clearTimeout(timer);
            timer = setTimeout(() => {
                this.cheering = false;
            }, this.duration);

            if (this.confettiAllowed) {
                this.throwConfetti();
            }
        },

        throwConfetti() {
            const layer = this.$refs.confetti;

            if (!layer) {
                return;
            }

            (projector ? canvasConfetti : domConfetti)(doc, layer, random);
        },

        destroy() {
            clearTimeout(timer);
        },
    };
}
