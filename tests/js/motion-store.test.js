import { describe, expect, it, vi } from 'vitest';
import { MOTION, MOTION_STORAGE_KEY, motionStore, resolveMotion, SPRING } from '../../resources/js/motion.js';

describe('U27 motion tokens', () => {
    it('keeps the durations in the order instant, quick, base, slow', () => {
        expect(MOTION.instant).toBe(100);
        expect(MOTION.quick).toBeGreaterThan(MOTION.instant);
        expect(MOTION.base).toBeGreaterThan(MOTION.quick);
        expect(MOTION.slow).toBeGreaterThan(MOTION.base);
        expect(MOTION.slow).toBeLessThanOrEqual(400);
    });

    it('keeps the one spring of the app (U8)', () => {
        expect(SPRING).toEqual({ type: 'spring', visualDuration: 0.3, bounce: 0.2 });
    });
});

describe('U27 motion choice', () => {
    it('uses the choice the viewer made on this device', () => {
        expect(resolveMotion('full', true)).toBe('full');
        expect(resolveMotion('reduced', false)).toBe('reduced');
    });

    it('follows the device setting when nothing valid was chosen', () => {
        expect(resolveMotion(null, true)).toBe('reduced');
        expect(resolveMotion(null, false)).toBe('full');
        expect(resolveMotion('wild', true)).toBe('reduced');
    });
});

describe('U27 Reduce motion switch', () => {
    const fakeRoot = (motion) => ({ dataset: motion === undefined ? {} : { motion } });
    const memory = (initial = {}) => {
        const saved = { ...initial };

        return {
            saved,
            getItem: (key) => saved[key] ?? null,
            setItem: (key, value) => (saved[key] = value),
        };
    };
    const media = (matches) => {
        const listeners = [];

        return {
            matches,
            addEventListener: (_type, listener) => listeners.push(listener),
            change(next) {
                this.matches = next;
                listeners.forEach((listener) => listener({ matches: next }));
            },
        };
    };

    it('starts from what the inline script put on the page', () => {
        expect(motionStore({ root: fakeRoot('reduced'), storage: memory(), media: media(false) }).reduced).toBe(true);
        expect(motionStore({ root: fakeRoot('full'), storage: memory(), media: media(true) }).reduced).toBe(false);
    });

    it('resolves the mode itself when the page has none yet', () => {
        const root = fakeRoot(undefined);
        const store = motionStore({ root, storage: memory(), media: media(true) });

        expect(store.mode).toBe('reduced');
        expect(root.dataset.motion).toBe('reduced');
    });

    it('flips the page, its own state and the stored choice together', () => {
        const root = fakeRoot('full');
        const storage = memory();
        const store = motionStore({ root, storage, media: media(false) });

        store.toggle();

        expect(store.mode).toBe('reduced');
        expect(store.reduced).toBe(true);
        expect(root.dataset.motion).toBe('reduced');
        expect(storage.saved[MOTION_STORAGE_KEY]).toBe('reduced');

        store.toggle();

        expect(root.dataset.motion).toBe('full');
        expect(storage.saved[MOTION_STORAGE_KEY]).toBe('full');
    });

    it('tells Unpoly and other listeners when the mode changes', () => {
        const onChange = vi.fn();
        const store = motionStore({ root: fakeRoot('full'), storage: memory(), media: media(false), onChange });

        expect(onChange).toHaveBeenLastCalledWith('full');
        store.toggle();
        expect(onChange).toHaveBeenLastCalledWith('reduced');
    });

    it('follows a change of the device setting while the viewer has not chosen', () => {
        const root = fakeRoot('full');
        const device = media(false);
        const store = motionStore({ root, storage: memory(), media: device });

        device.change(true);

        expect(store.mode).toBe('reduced');
        expect(root.dataset.motion).toBe('reduced');
    });

    it('keeps the viewer choice when the device setting changes', () => {
        const root = fakeRoot('full');
        const device = media(false);
        const store = motionStore({ root, storage: memory({ [MOTION_STORAGE_KEY]: 'full' }), media: device });

        device.change(true);

        expect(store.mode).toBe('full');
    });

    it('still switches when storage is blocked (private mode)', () => {
        const root = fakeRoot('full');
        const blocked = {
            getItem: () => {
                throw new Error('blocked');
            },
            setItem: () => {
                throw new Error('blocked');
            },
        };
        const store = motionStore({ root, storage: blocked, media: media(false) });

        store.toggle();

        expect(root.dataset.motion).toBe('reduced');
    });
});
