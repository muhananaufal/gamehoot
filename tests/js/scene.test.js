import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    connectionBanner,
    connectionNotice,
    lobbyMood,
    lobbyPenta,
    loginPenta,
    loginReaction,
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
        expect(Object.keys(NUDGES)).toEqual(['pop', 'pulse', 'flip', 'shake', 'fade']);
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

    it('shakes for a new problem, never when the problem goes away (U32)', () => {
        expect(shouldNudge('shake', null, 'The state moved on.', false)).toBe(true);
        expect(shouldNudge('shake', 'The state moved on.', '', false)).toBe(false);
        expect(shouldNudge('shake', 'The state moved on.', null, false)).toBe(false);
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

describe('U30 the connection banner component', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    function mountBanner(connection) {
        const realtime = { connection };
        let watcher = null;
        const banner = connectionBanner({ holdMs: 1_500 });
        banner.$store = { realtime };
        banner.$watch = (read, fn) => {
            watcher = { read, fn };
        };
        banner.init();

        return {
            banner,
            change(next) {
                realtime.connection = next;
                watcher.fn(watcher.read());
            },
        };
    }

    it('says Back online for a moment once the socket returns', () => {
        const { banner, change } = mountBanner('connected');

        change('reconnecting');
        expect(banner.back).toBe(false);

        change('connected');
        expect(banner.back).toBe(true);

        vi.advanceTimersByTime(1_600);
        expect(banner.back).toBe(false);
    });

    it('stays quiet on the first connection', () => {
        const { banner, change } = mountBanner('connecting');

        change('connected');

        expect(banner.back).toBe(false);
    });
});

describe('U30 the lobby tells Penta what happens', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('hands the moods to the Penta named lobby as players join', () => {
        const realtime = { snapshot: { lobby: { joined: 2 } } };
        let watcher = null;
        const told = [];
        const lobby = lobbyPenta({ quietMs: 60_000 });
        lobby.$store = { realtime };
        lobby.$watch = (read, fn) => {
            watcher = { read, fn };
        };
        lobby.$dispatch = (name, detail) => told.push([name, detail]);
        lobby.init();

        realtime.snapshot.lobby.joined = 3;
        watcher.fn(watcher.read());

        expect(told).toEqual([['mascot-react', { name: 'lobby', mood: 'wave' }]]);

        lobby.destroy();
        vi.advanceTimersByTime(120_000);
        expect(told).toHaveLength(1);
    });
});

describe('U32 Penta on the sign in page', () => {
    const input = (props) => ({
        tagName: 'INPUT',
        name: 'login',
        type: 'text',
        value: '',
        selectionStart: 0,
        ...props,
    });

    it('follows the caret of the login field from left to right', () => {
        expect(loginReaction(input({ value: '', selectionStart: 0 }))).toEqual({ look: { x: -5, y: 4 } });
        expect(loginReaction(input({ value: 'arif@company.test', selectionStart: 12 }))).toEqual({
            look: { x: 0, y: 4 },
        });
        expect(loginReaction(input({ value: 'a'.repeat(40), selectionStart: 40 }))).toEqual({ look: { x: 5, y: 4 } });
    });

    it('covers its eyes while the password is hidden and peeks once it is shown', () => {
        expect(loginReaction(input({ name: 'password', type: 'password' }))).toEqual({ mood: 'shy' });
        expect(loginReaction(input({ name: 'password', type: 'text' }))).toEqual({ mood: 'peek' });
        expect(loginReaction({ tagName: 'BUTTON' })).toEqual({ mood: 'idle' });
    });

    it('tells Penta a mood only when it changes, so typing does not replay it', () => {
        const told = [];
        const penta = loginPenta();
        penta.$dispatch = (name, detail) => told.push(detail);

        penta.follow(input({ name: 'password', type: 'password' }));
        penta.follow(input({ name: 'password', type: 'password' }));
        penta.follow(input({ value: 'ar', selectionStart: 2 }));
        penta.follow(input({ name: 'password', type: 'text' }));

        expect(told).toEqual([
            { name: 'login', mood: 'shy' },
            { name: 'login', mood: 'idle' },
            { name: 'login', look: { x: -4, y: 4 } },
            { name: 'login', mood: 'peek' },
        ]);
    });
});
