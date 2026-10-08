// U29: Penta, the logo come to life. The parts of components/mascot.blade.php move with the Web
// Animations API (transform and opacity only). WCAG 2.2.2: the idle motion comes in bursts of at
// most five seconds and then rests until something happens; the reduced mode (U27) only takes the
// pose. Holding Penta calms the motion of this device (U27, option C).
import { EASE, MOTION } from './motion.js';

/** WCAG 2.2.2: moving content that starts by itself must stop within five seconds. */
export const BURST_MS = 5_000;
export const LONG_PRESS_MS = 600;
const BUBBLE_MS = 2_500;

export const MOODS = Object.freeze([
    'idle',
    'wave',
    'happy',
    'cheer',
    'thinking',
    'surprised',
    'sad',
    'sleepy',
    'loading',
    'peek',
    'dizzy',
    'shy',
]);

/** Where each segment flies when Penta cheers: from the centre to the middle of its triangle. */
export const SEGMENT_DIRECTIONS = Object.freeze([
    [0.59, -0.81],
    [0.95, 0.31],
    [0, 1],
    [-0.95, 0.31],
    [-0.59, -0.81],
]);

const BASE_POSE = Object.freeze({ eyes: true, happy: false, smile: false, oh: false, zz: false });
const POSES = {
    happy: { eyes: false, happy: true, smile: true },
    cheer: { eyes: false, happy: true, smile: true },
    surprised: { oh: true },
    sleepy: { zz: true },
};

/** Which parts show for a mood; an unknown mood is idle. */
export function posesOf(mood) {
    return { ...BASE_POSE, ...(MOODS.includes(mood) ? (POSES[mood] ?? {}) : {}) };
}

const round = (value) => Math.round(value * 10) / 10;

/** The blinks and looks of one idle burst, all within BURST_MS, ending with a look back ahead. */
export function idleSchedule({ random = Math.random, burstMs = BURST_MS } = {}) {
    const plan = [];
    let at = 400 + random() * 600;

    while (at < burstMs) {
        if (random() < 0.55) {
            plan.push({ at: Math.round(at), kind: 'blink', twice: random() < 0.2 });
        } else {
            const angle = random() * Math.PI * 2;
            const reach = 3 + random() * 4;
            plan.push({
                at: Math.round(at),
                kind: 'look',
                x: round(Math.cos(angle) * reach),
                y: round(Math.sin(angle) * reach * 0.6),
            });
        }

        at += 900 + random() * 1600;
    }

    plan.push({ at: burstMs, kind: 'look', x: 0, y: 0 });

    return plan;
}

const KEYFRAMES = {
    breathe: [
        { transform: 'translateY(0) scaleY(1)' },
        { transform: 'translateY(-4px) scaleY(1.02)' },
        { transform: 'translateY(0) scaleY(1)' },
    ],
    wave: [
        { transform: 'translateY(0) rotate(0)' },
        { transform: 'translateY(-16px) rotate(-10deg)' },
        { transform: 'translateY(-6px) rotate(10deg)' },
        { transform: 'translateY(-2px) rotate(-6deg)' },
        { transform: 'translateY(0) rotate(0)' },
    ],
    squash: [
        { transform: 'scale(1, 1)' },
        { transform: 'scale(1.06, 0.9)' },
        { transform: 'scale(0.96, 1.06) translateY(-10px)' },
        { transform: 'scale(1, 1)' },
    ],
    jumpSpin: [
        { transform: 'translateY(0) rotate(0)' },
        { transform: 'translateY(-26px) rotate(180deg)' },
        { transform: 'translateY(0) rotate(360deg)' },
    ],
    hop: [{ transform: 'translateY(0)' }, { transform: 'translateY(-12px)' }, { transform: 'translateY(0)' }],
    peek: [
        { transform: 'translateY(60%)', opacity: 0 },
        { transform: 'translateY(-6%)', opacity: 1 },
        { transform: 'translateY(0)', opacity: 1 },
    ],
};

/** U29: the Alpine component of components/mascot.blade.php. */
export function mascot({
    mood = 'idle',
    name = null,
    labels = {},
    random = Math.random,
    doc = globalThis.document,
} = {}) {
    let timers = [];
    let running = [];
    let pressTimer = null;
    let bubbleTimer = null;

    return {
        mood,
        // U30: a named Penta hears only reactions for its name; unnamed reactions reach every Penta.
        name,
        bubble: '',

        init() {
            doc?.addEventListener?.('visibilitychange', () => {
                if (doc.hidden) {
                    this.stop();
                }
            });
            this.$watch(
                () => this.$store.motion?.reduced,
                () => this.play(this.mood),
            );

            // A Penta in a hidden view starts once it shows, so its greeting is seen.
            const Observer = globalThis.IntersectionObserver;

            if (typeof Observer !== 'function') {
                this.play(this.mood);

                return;
            }

            this.$el.dataset.mood = this.mood;
            this.pose(this.mood);
            let shown = false;
            new Observer((entries) => {
                const visible = entries.some((entry) => entry.isIntersecting);

                if (visible && !shown) {
                    this.play(this.mood);
                }

                shown = visible;
            }).observe(this.$el);
        },

        get reduced() {
            return this.$store.motion?.reduced ?? false;
        },

        parts(name) {
            return [...this.$el.querySelectorAll(`[data-part="${name}"]`)];
        },

        stop() {
            timers.forEach((timer) => clearTimeout(timer));
            timers = [];
        },

        later(fn, ms) {
            timers.push(setTimeout(fn, ms));
        },

        animate(part, frames, options = {}) {
            const animation = part.animate(frames, { easing: EASE.out, ...options });
            running.push(animation);

            return animation;
        },

        pose(mood) {
            const pose = posesOf(mood);
            const show = (name, on) => this.parts(name).forEach((part) => part.setAttribute('opacity', on ? 1 : 0));
            show('eye', pose.eyes);
            show('happy', pose.happy);
            show('smile', pose.smile);
            show('oh', pose.oh);
            show('zz', pose.zz);
        },

        play(next) {
            this.stop();
            running.forEach((animation) => animation.cancel());
            running = [];
            this.mood = MOODS.includes(next) ? next : 'idle';
            this.$el.dataset.mood = this.mood;
            this.pose(this.mood);

            if (this.reduced || doc?.hidden) {
                return;
            }

            this.moves[this.mood].call(this);
        },

        /** A moment the page tells Penta about: $dispatch('mascot-react', 'cheer') or { name, mood }. */
        react(detail) {
            if (detail !== null && typeof detail === 'object') {
                if (detail.name && detail.name !== this.name) {
                    return;
                }

                this.play(detail.mood);

                return;
            }

            this.play(detail);
        },

        poke() {
            if (this.mood === 'idle' && !this.reduced) {
                this.blink(false);
                this.burst();
            }
        },

        burst() {
            this.stop();
            this.parts('body').forEach((body) =>
                this.animate(body, KEYFRAMES.breathe, { duration: 2_400, iterations: 2, easing: 'ease-in-out' }),
            );
            for (const step of idleSchedule({ random })) {
                this.later(() => (step.kind === 'blink' ? this.blink(step.twice) : this.look(step.x, step.y)), step.at);
            }
        },

        blink(twice) {
            this.parts('eye').forEach((eye) =>
                this.animate(
                    eye,
                    [{ transform: 'scaleY(1)' }, { transform: 'scaleY(0.08)' }, { transform: 'scaleY(1)' }],
                    { duration: 140, easing: 'ease-in-out' },
                ),
            );

            if (twice) {
                this.later(() => this.blink(false), 220);
            }
        },

        look(x, y) {
            this.parts('pupil').forEach((pupil) =>
                this.animate(pupil, [{ transform: `translate(${x}px, ${y}px)` }], {
                    duration: MOTION.base,
                    fill: 'forwards',
                }),
            );
        },

        /** U27, option C: holding Penta switches the motion of this device. */
        press() {
            this.release();
            pressTimer = setTimeout(() => {
                pressTimer = null;
                this.$store.motion.toggle();
                this.bubble = this.reduced ? (labels.rest ?? '') : (labels.awake ?? '');
                clearTimeout(bubbleTimer);
                bubbleTimer = setTimeout(() => {
                    this.bubble = '';
                }, BUBBLE_MS);
                this.play(this.mood);
            }, LONG_PRESS_MS);
        },

        release() {
            if (pressTimer !== null) {
                clearTimeout(pressTimer);
                pressTimer = null;
            }
        },

        moves: {
            idle() {
                this.burst();
            },
            wave() {
                this.parts('body').forEach((body) =>
                    this.animate(body, KEYFRAMES.wave, { duration: 1_200, easing: EASE.overshoot }),
                );
                this.later(() => this.blink(true), 900);
            },
            happy() {
                this.parts('body').forEach((body) =>
                    this.animate(body, KEYFRAMES.squash, { duration: 800, easing: EASE.bounce }),
                );
            },
            cheer() {
                this.parts('segment').forEach((segment, i) => {
                    const [x, y] = SEGMENT_DIRECTIONS[i] ?? [0, 0];
                    const dx = x * 16;
                    const dy = y * 16;
                    this.animate(
                        segment,
                        [
                            { transform: 'translate(0, 0) rotate(0)' },
                            { transform: `translate(${dx}px, ${dy}px) rotate(${i % 2 ? 12 : -12}deg)` },
                            { transform: 'translate(0, 0) rotate(0)' },
                        ],
                        { duration: 900, easing: EASE.overshoot },
                    );
                });
                this.parts('body').forEach((body) =>
                    this.animate(body, KEYFRAMES.jumpSpin, { duration: 1_100, delay: 300 }),
                );
            },
            thinking() {
                this.look(-5, -6);
                this.parts('body').forEach((body) =>
                    this.animate(body, [{ transform: 'rotate(0)' }, { transform: 'rotate(-8deg)' }], {
                        duration: 400,
                        fill: 'forwards',
                    }),
                );
            },
            surprised() {
                this.parts('eye').forEach((eye) =>
                    this.animate(eye, [{ transform: 'scale(1)' }, { transform: 'scale(1.15)' }], {
                        duration: MOTION.quick,
                        fill: 'forwards',
                    }),
                );
                this.parts('pupil').forEach((pupil) =>
                    this.animate(pupil, [{ transform: 'scale(1)' }, { transform: 'scale(0.6)' }], {
                        duration: MOTION.quick,
                        fill: 'forwards',
                    }),
                );
                this.parts('body').forEach((body) => this.animate(body, KEYFRAMES.hop, { duration: 420 }));
            },
            sad() {
                this.look(0, 6);
                this.parts('eye').forEach((eye) =>
                    this.animate(eye, [{ transform: 'scaleY(1)' }, { transform: 'scaleY(0.75)' }], {
                        duration: 400,
                        fill: 'forwards',
                    }),
                );
                this.parts('body').forEach((body) =>
                    this.animate(
                        body,
                        [{ transform: 'translateY(0) scale(1)' }, { transform: 'translateY(6px) scale(1.03, 0.95)' }],
                        { duration: 700, fill: 'forwards' },
                    ),
                );
            },
            sleepy() {
                this.parts('eye').forEach((eye) =>
                    this.animate(eye, [{ transform: 'scaleY(1)' }, { transform: 'scaleY(0.3)' }], {
                        duration: 600,
                        fill: 'forwards',
                    }),
                );
                this.parts('zz').forEach((zz) =>
                    this.animate(
                        zz,
                        [
                            { transform: 'translate(0, 0)', opacity: 0 },
                            { transform: 'translate(8px, -14px)', opacity: 1 },
                            { transform: 'translate(14px, -26px)', opacity: 0 },
                        ],
                        { duration: 1_600, iterations: 3 },
                    ),
                );
            },
            loading() {
                // Shows a process, so it may run longer than five seconds (WCAG 2.2.2, essential).
                this.parts('segment').forEach((segment, i) =>
                    this.animate(segment, [{ opacity: 1 }, { opacity: 0.25 }, { opacity: 1 }], {
                        duration: 1_000,
                        delay: i * 200,
                        iterations: Infinity,
                        easing: 'ease-in-out',
                    }),
                );
            },
            peek() {
                this.parts('body').forEach((body) =>
                    this.animate(body, KEYFRAMES.peek, { duration: 900, easing: EASE.overshoot }),
                );
                this.later(() => this.look(6, 0), 900);
                this.later(() => this.look(-6, 0), 1_300);
                this.later(() => {
                    this.look(0, 0);
                    this.blink(false);
                }, 1_800);
            },
            dizzy() {
                this.parts('pupil').forEach((pupil) =>
                    this.animate(
                        pupil,
                        [
                            { transform: 'translate(5px, 0)' },
                            { transform: 'translate(0, 5px)' },
                            { transform: 'translate(-5px, 0)' },
                            { transform: 'translate(0, -5px)' },
                            { transform: 'translate(5px, 0)' },
                        ],
                        { duration: 500, iterations: 3 },
                    ),
                );
            },
            shy() {
                this.parts('eye').forEach((eye) =>
                    this.animate(eye, [{ transform: 'scaleY(1)' }, { transform: 'scaleY(0.1)' }], {
                        duration: MOTION.quick,
                        fill: 'forwards',
                    }),
                );
                this.parts('body').forEach((body) =>
                    this.animate(body, [{ transform: 'scale(1)' }, { transform: 'scale(0.94)' }], {
                        duration: MOTION.base,
                        fill: 'forwards',
                    }),
                );
            },
        },
    };
}
