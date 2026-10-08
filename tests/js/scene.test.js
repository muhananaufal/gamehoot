import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    connectionNotice,
    lobbyMood,
    nudge,
    NUDGES,
    ringOffset,
    shouldNudge,
    urgencyOf,
} from '../../resources/js/scene.js';

describe('U30 countdown ring', () => {
    const CIRCLE = 100;

    it('is full when the question opens and empty when time is up', () => {
        expect(ringOffset(20_000, 20, CIRCLE)).toBe(0);
        expect(ringOffset(0, 20, CIRCLE)).toBe(CIRCLE);
        expect(ringOffset(10_000, 20, CIRCLE)).toBe(50);
    });

    it('never runs past either end, also for a stopped or an unknown question', () => {
        expect(ringOffset(-500, 20, CIRCLE)).toBe(CIRCLE);
        expect(ringOffset(30_000, 20, CIRCLE)).toBe(0);
        expect(ringOffset(5_000, 0, CIRCLE)).toBe(CIRCLE);
        expect(ringOffset(5_000, null, CIRCLE)).toBe(CIRCLE);
    });
});

describe('U30 the last seconds', () => {
    it('is calm until five seconds are left, then soon, then last at three', () => {
        expect(urgencyOf(12)).toBe('calm');
        expect(urgencyOf(6)).toBe('calm');
        expect(urgencyOf(5)).toBe('soon');
        expect(urgencyOf(4)).toBe('soon');
        expect(urgencyOf(3)).toBe('last');
        expect(urgencyOf(1)).toBe('last');
        expect(urgencyOf(0)).toBe('calm');
        expect(urgencyOf(null)).toBe('calm');
    });
});

describe('U30 connection banner', () => {
    it('says back online only when the socket returns after a drop', () => {
        expect(connectionNotice('reconnecting', 'connected')).toBe('back_online');
        expect(connectionNotice('offline', 'connected')).toBe('back_online');
        expect(connectionNotice('connecting', 'connected')).toBe(null);
        expect(connectionNotice('connected', 'connected')).toBe(null);
        expect(connectionNotice('connected', 'reconnecting')).toBe(null);
    });
});

describe('U30 nudges', () => {
    it('knows its kinds', () => {
        expect(Object.keys(NUDGES)).toEqual(['pop', 'pulse', 'flip', 'shake']);
    });

    it('never moves on the first value, only on a change', () => {
        expect(shouldNudge('pop', undefined, 3, true)).toBe(false);
        expect(shouldNudge('pop', 3, 3, false)).toBe(false);
        expect(shouldNudge('pop', 3, 4, false)).toBe(true);
    });

    it('pulses only while there is something to count', () => {
        expect(shouldNudge('pulse', null, 5, false)).toBe(true);
        expect(shouldNudge('pulse', 5, 4, false)).toBe(true);
        expect(shouldNudge('pulse', 1, null, false)).toBe(false);
    });

    it('flips a letter box when it opens, never when a new question closes it again', () => {
        expect(shouldNudge('flip', 'closed', 'hint', false)).toBe(true);
        expect(shouldNudge('flip', 'closed', 'revealed', false)).toBe(true);
        expect(shouldNudge('flip', 'revealed', 'closed', false)).toBe(false);
        expect(shouldNudge('flip', 'hint', 'revealed', false)).toBe(false);
    });

    it("animates the element with the kind's keyframes, and not at all in the reduced mode", () => {
        const el = { animate: vi.fn() };

        nudge(el, 'pop', { reduced: false });
        expect(el.animate).toHaveBeenCalledTimes(1);
        expect(el.animate.mock.calls[0][0]).toEqual(NUDGES.pop.frames);

        nudge(el, 'pop', { reduced: true });
        expect(el.animate).toHaveBeenCalledTimes(1);
    });
});

describe('U30 Penta in the lobby', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('waves when a player joins and dozes off after a quiet minute', () => {
        const moods = [];
        const lobby = lobbyMood({ quietMs: 60_000, tell: (mood) => moods.push(mood) });

        lobby.joined(0);
        lobby.joined(1);
        expect(moods).toEqual(['wave']);

        vi.advanceTimersByTime(59_000);
        expect(moods).toEqual(['wave']);

        vi.advanceTimersByTime(2_000);
        expect(moods).toEqual(['wave', 'sleepy']);

        lobby.joined(2);
        expect(moods).toEqual(['wave', 'sleepy', 'wave']);
    });

    it('does not wave for the first count or when nobody new came', () => {
        const moods = [];
        const lobby = lobbyMood({ quietMs: 60_000, tell: (mood) => moods.push(mood) });

        lobby.joined(5);
        lobby.joined(5);
        lobby.joined(4);

        expect(moods).toEqual([]);
    });

    it('stops its timer when the lobby goes', () => {
        const moods = [];
        const lobby = lobbyMood({ quietMs: 60_000, tell: (mood) => moods.push(mood) });

        lobby.joined(0);
        lobby.stop();
        vi.advanceTimersByTime(120_000);

        expect(moods).toEqual([]);
    });
});
