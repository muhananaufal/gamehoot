import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { BURST_MS, idleSchedule, LONG_PRESS_MS, mascot, MOODS, posesOf } from '../../resources/js/mascot.js';

/** A seeded random, so the idle plan is the same in every run. */
const seeded = (seed = 7) => {
    let value = seed;

    return () => {
        value = (value * 16807) % 2147483647;

        return (value - 1) / 2147483646;
    };
};

describe('U29 idle bursts (WCAG 2.2.2)', () => {
    it('ends every idle burst within five seconds', () => {
        for (let seed = 1; seed < 40; seed++) {
            const plan = idleSchedule({ random: seeded(seed) });

            expect(plan.length).toBeGreaterThan(0);
            expect(Math.max(...plan.map((step) => step.at))).toBeLessThanOrEqual(BURST_MS);
        }
    });

    it('blinks and looks around, never stares', () => {
        const kinds = new Set(
            Array.from({ length: 20 }, (_, i) => idleSchedule({ random: seeded(i + 1) }))
                .flat()
                .map((step) => step.kind),
        );

        expect(kinds).toContain('blink');
        expect(kinds).toContain('look');
    });

    it('keeps looks within the eye', () => {
        const looks = Array.from({ length: 30 }, (_, i) => idleSchedule({ random: seeded(i + 3) }))
            .flat()
            .filter((step) => step.kind === 'look');

        for (const look of looks) {
            expect(Math.hypot(look.x, look.y)).toBeLessThanOrEqual(7);
        }
    });
});

describe('U29 poses', () => {
    it('knows every mood of the spec', () => {
        expect(MOODS).toEqual([
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
    });

    it('swaps the eyes for happy arcs when happy or cheering', () => {
        expect(posesOf('happy')).toMatchObject({ eyes: false, happy: true, smile: true });
        expect(posesOf('cheer')).toMatchObject({ eyes: false, happy: true, smile: true });
        expect(posesOf('idle')).toMatchObject({ eyes: true, happy: false, smile: false, oh: false });
        expect(posesOf('surprised')).toMatchObject({ eyes: true, oh: true });
        expect(posesOf('sleepy')).toMatchObject({ zz: true });
    });

    it('falls back to idle for an unknown mood', () => {
        expect(posesOf('angry')).toEqual(posesOf('idle'));
    });
});

/** A fake Penta: parts that record their animations and attributes. */
function fakePenta() {
    const part = (name) => ({
        name,
        attributes: {},
        animations: [],
        setAttribute(key, value) {
            this.attributes[key] = String(value);
        },
        animate(frames, options) {
            const animation = { frames, options, cancel: vi.fn(), finished: Promise.resolve() };
            this.animations.push(animation);

            return animation;
        },
    });
    const parts = {
        body: [part('body')],
        segment: [1, 2, 3, 4, 5].map((i) => part(`segment${i}`)),
        eye: [part('eyeL'), part('eyeR')],
        pupil: [part('pupilL'), part('pupilR')],
        happy: [part('happy')],
        smile: [part('smile')],
        oh: [part('oh')],
        zz: [part('zz')],
    };
    const el = {
        dataset: {},
        querySelectorAll: (selector) => parts[selector.match(/data-part="?([a-z]+)"?/)[1]] ?? [],
    };

    return {
        el,
        parts,
        animated: () =>
            Object.values(parts)
                .flat()
                .some((p) => p.animations.length > 0),
    };
}

function mount({ mood = 'idle', reduced = false, hidden = false, screenAnimations = 'on' } = {}) {
    const penta = fakePenta();
    const motion = {
        reduced,
        toggle: vi.fn(function () {
            this.reduced = !this.reduced;
        }),
    };
    const doc = {
        hidden,
        documentElement: { dataset: { screenAnimations } },
        listeners: {},
        addEventListener(type, fn) {
            this.listeners[type] = fn;
        },
    };
    const component = mascot({
        mood,
        labels: { rest: 'Penta can rest', awake: 'Penta is back' },
        random: seeded(3),
        doc,
    });
    component.$el = penta.el;
    component.$store = { motion };
    component.$watch = vi.fn();
    component.init();

    return { component, penta, motion, doc };
}

describe('U29 Penta component', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('shows its mood on the element and plays it', () => {
        const { penta } = mount({ mood: 'wave' });

        expect(penta.el.dataset.mood).toBe('wave');
        expect(penta.parts.body[0].animations.length).toBeGreaterThan(0);
    });

    it('blinks during an idle burst and then rests until something happens', () => {
        const { penta, component } = mount({ mood: 'idle' });

        vi.advanceTimersByTime(BURST_MS + 100);
        const count = penta.parts.eye[0].animations.length;
        expect(count).toBeGreaterThan(0);

        vi.advanceTimersByTime(60_000);
        expect(penta.parts.eye[0].animations.length).toBe(count);

        component.poke();
        vi.advanceTimersByTime(BURST_MS + 100);
        expect(penta.parts.eye[0].animations.length).toBeGreaterThan(count);
    });

    it('rests on a Public View whose host turned the screen animations off (U31)', () => {
        const { penta, component } = mount({ mood: 'idle', screenAnimations: 'off' });

        vi.advanceTimersByTime(BURST_MS + 100);
        expect(penta.parts.eye[0].animations.length).toBe(0);

        // A click still gets its one blink, but no burst follows.
        component.poke();
        vi.advanceTimersByTime(BURST_MS + 100);
        expect(penta.parts.eye[0].animations.length).toBe(1);
    });

    it('only takes the pose in the reduced mode, without moving', () => {
        const { penta } = mount({ mood: 'cheer', reduced: true });

        vi.advanceTimersByTime(BURST_MS + 100);

        expect(penta.animated()).toBe(false);
        expect(penta.parts.happy[0].attributes.opacity).toBe('1');
        expect(penta.parts.eye[0].attributes.opacity).toBe('0');
    });

    it('reacts to a moment the page tells it about', () => {
        const { penta, component } = mount({ mood: 'idle' });

        component.react('happy');

        expect(penta.el.dataset.mood).toBe('happy');
        expect(penta.parts.smile[0].attributes.opacity).toBe('1');
    });

    it('hears only reactions meant for it when it has a name (U30)', () => {
        const { penta, component } = mount({ mood: 'idle' });
        component.name = 'lobby';

        component.react({ name: 'podium', mood: 'cheer' });
        expect(penta.el.dataset.mood).toBe('idle');

        component.react({ name: 'lobby', mood: 'sleepy' });
        expect(penta.el.dataset.mood).toBe('sleepy');

        component.react('happy');
        expect(penta.el.dataset.mood).toBe('happy');
    });

    it('calms the motion of this device when held, and says so (U27, option C)', () => {
        const { component, motion } = mount();

        component.press();
        vi.advanceTimersByTime(LONG_PRESS_MS + 10);

        expect(motion.toggle).toHaveBeenCalledTimes(1);
        expect(component.bubble).toBe('Penta can rest');

        vi.advanceTimersByTime(3_000);
        expect(component.bubble).toBe('');
    });

    it('ignores a short tap', () => {
        const { component, motion } = mount();

        component.press();
        vi.advanceTimersByTime(LONG_PRESS_MS - 100);
        component.release();
        vi.advanceTimersByTime(LONG_PRESS_MS);

        expect(motion.toggle).not.toHaveBeenCalled();
    });

    it('stops its timers while the page is hidden', () => {
        const { penta, doc } = mount({ mood: 'idle' });

        doc.hidden = true;
        doc.listeners.visibilitychange();
        const count = penta.parts.eye[0].animations.length;
        vi.advanceTimersByTime(BURST_MS + 100);

        expect(penta.parts.eye[0].animations.length).toBe(count);
    });
});
