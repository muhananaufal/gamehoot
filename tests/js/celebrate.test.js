import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { canvasSize, celebration, CONFETTI, confettiPieces, firstTime } from '../../resources/js/celebrate.js';

function memoryStorage() {
    const items = new Map();

    return {
        getItem: (key) => items.get(key) ?? null,
        setItem: (key, value) => items.set(key, String(value)),
        items,
    };
}

function seeded(seed) {
    let value = seed;

    return () => {
        value = (value * 16_807) % 2_147_483_647;

        return (value - 1) / 2_147_483_646;
    };
}

describe('U31 once per moment', () => {
    it('celebrates a moment once per tab, so a reconnect or a reload does not repeat it', () => {
        const storage = memoryStorage();

        expect(firstTime(storage, 'reveal:q1:1')).toBe(true);
        expect(firstTime(storage, 'reveal:q1:1')).toBe(false);
        expect(firstTime(storage, 'reveal:q1:2')).toBe(true);
    });

    it('still celebrates when the browser keeps no storage', () => {
        const broken = {
            getItem: () => {
                throw new Error('blocked');
            },
            setItem: () => {
                throw new Error('blocked');
            },
        };

        expect(firstTime(broken, 'final:g1')).toBe(true);
        expect(firstTime(null, 'final:g1')).toBe(true);
    });
});

describe('U31 confetti', () => {
    it('throws a fixed number of pieces in the five brand colours', () => {
        const pieces = confettiPieces({ count: CONFETTI.projector.count, random: seeded(7) });

        expect(pieces).toHaveLength(CONFETTI.projector.count);
        expect(new Set(pieces.map((piece) => piece.color))).toEqual(
            new Set(['yellow', 'coral', 'mint', 'blue', 'pink']),
        );
        for (const piece of pieces) {
            expect(piece.x).toBeGreaterThanOrEqual(0);
            expect(piece.x).toBeLessThanOrEqual(1);
            expect(piece.delay).toBeLessThanOrEqual(CONFETTI.spreadMs);
        }
    });

    it('throws fewer pieces on phones than on the projector', () => {
        expect(CONFETTI.phone.count).toBeLessThan(CONFETTI.projector.count);
    });

    it('draws the projector canvas at a device pixel ratio of at most 1.5', () => {
        expect(canvasSize({ width: 1920, height: 1080, dpr: 3 })).toEqual({ width: 2880, height: 1620, scale: 1.5 });
        expect(canvasSize({ width: 1280, height: 720, dpr: 1 })).toEqual({ width: 1280, height: 720, scale: 1 });
    });
});

describe('U31 the celebration component', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    function mount({ projector = true, reduced = false, screenAnimations = 'on' } = {}) {
        const told = [];
        const component = celebration({
            projector,
            storage: memoryStorage(),
            doc: { documentElement: { dataset: { screenAnimations } } },
        });
        component.$store = { motion: { reduced } };
        component.$dispatch = (name, detail) => told.push([name, detail]);
        component.throwConfetti = vi.fn();

        return { component, told };
    }

    it('lets Penta cheer and throws confetti once per moment', () => {
        const { component, told } = mount();

        component.play({ key: 'won:q1' });
        component.play({ key: 'won:q1' });

        expect(told).toEqual([['mascot-react', { name: 'celebration', mood: 'cheer' }]]);
        expect(component.throwConfetti).toHaveBeenCalledOnce();
        expect(component.cheering).toBe(true);

        vi.advanceTimersByTime(CONFETTI.projector.durationMs + 100);
        expect(component.cheering).toBe(false);
    });

    it('keeps Penta but drops the confetti in the reduced mode', () => {
        const { component, told } = mount({ reduced: true });

        component.play({ key: 'final:g1' });

        expect(told).toHaveLength(1);
        expect(component.throwConfetti).not.toHaveBeenCalled();
    });

    it('drops the confetti on a Public View whose host turned the screen animations off', () => {
        const { component } = mount({ screenAnimations: 'off' });

        component.play({ key: 'final:g1' });

        expect(component.throwConfetti).not.toHaveBeenCalled();
    });

    it('ignores that setting on phones, where each player has Reduce motion', () => {
        const { component } = mount({ projector: false, screenAnimations: 'off' });

        component.play({ key: 'final:g1' });

        expect(component.throwConfetti).toHaveBeenCalledOnce();
    });
});
