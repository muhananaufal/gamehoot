import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { TOAST_MS, toastStore } from '../../resources/js/toasts.js';

beforeEach(() => {
    vi.useFakeTimers();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('U3 toasts', () => {
    it('shows a message and lets it go by itself after a few seconds', () => {
        const toasts = toastStore();
        toasts.push('Pack saved.');

        expect(toasts.items.map((t) => t.message)).toEqual(['Pack saved.']);

        vi.advanceTimersByTime(TOAST_MS);
        expect(toasts.items).toEqual([]);
    });

    it('waits while the pointer or focus is on it, then gives the full time again', () => {
        const toasts = toastStore();
        const id = toasts.push('Link copied.');

        vi.advanceTimersByTime(TOAST_MS - 100);
        toasts.hold(id);
        vi.advanceTimersByTime(TOAST_MS * 2);
        expect(toasts.items).toHaveLength(1);

        toasts.release(id);
        vi.advanceTimersByTime(TOAST_MS - 1);
        expect(toasts.items).toHaveLength(1);
        vi.advanceTimersByTime(1);
        expect(toasts.items).toEqual([]);
    });

    it('closes at once on request and keeps at most three on screen', () => {
        const toasts = toastStore();
        const first = toasts.push('One');
        toasts.push('Two');
        toasts.push('Three');
        toasts.push('Four');

        expect(toasts.items.map((t) => t.message)).toEqual(['Two', 'Three', 'Four']);

        toasts.dismiss(toasts.items[0].id);
        expect(toasts.items.map((t) => t.message)).toEqual(['Three', 'Four']);
        expect(toasts.items.some((t) => t.id === first)).toBe(false);
    });

    it('ignores empty messages', () => {
        const toasts = toastStore();
        toasts.push('   ');

        expect(toasts.items).toEqual([]);
    });
});
