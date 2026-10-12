import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    POLL_INTERVAL_MS,
    POLL_JITTER_MS,
    RECONNECT_JITTER_MS,
    connectRealtime,
    countLabel,
    offlineEcho,
    realtimeStore,
    screenFor,
} from '../../resources/js/realtime.js';

const snapshot = (version, serverNow = 1_000_000) => ({ version, server_now: serverNow, event: { status: 'open' } });

describe('F15 versioned snapshots', () => {
    it('keeps the newest snapshot and drops an older one that arrives late', () => {
        const store = realtimeStore({ now: () => 1_000_000 });

        expect(store.accept(snapshot(5))).toBe(true);
        expect(store.accept(snapshot(4))).toBe(false);
        expect(store.version).toBe(5);
        expect(store.accept(snapshot(5))).toBe(true);
        expect(store.accept(snapshot(6))).toBe(true);
        expect(store.snapshot.version).toBe(6);
    });

    it('ignores anything that is not a snapshot', () => {
        const store = realtimeStore();

        expect(store.accept(null)).toBe(false);
        expect(store.accept({ version: '3' })).toBe(false);
        expect(store.snapshot).toBe(null);
    });
});

describe('F4 server clock', () => {
    it('follows the server clock even when the phone clock is minutes off', () => {
        let phone = 500_000; // the phone runs 500 s behind the server
        const store = realtimeStore({ now: () => phone });

        store.accept(snapshot(1, 1_000_000), { sentAt: 499_900, receivedAt: 500_100 });

        expect(store.serverNow()).toBe(1_000_000);
        phone += 4_000;
        expect(store.msUntil(1_010_000)).toBe(6_000);
        expect(store.msUntil(990_000)).toBe(0);
    });

    it('keeps the clock from a newer snapshot only', () => {
        const store = realtimeStore({ now: () => 0 });
        store.accept(snapshot(2, 10_000));
        store.accept(snapshot(1, 99_000));

        expect(store.serverNow()).toBe(10_000);
    });
});

/**
 * A stand-in for Echo: channels record their listeners, and the test drives the
 * connection status the way the Pusher connector reports it.
 */
function fakeEcho() {
    const listeners = {};
    let onChange = () => {};
    const channel = (name) => ({
        listen(event, callback) {
            listeners[`${name}${event}`] = callback;
            return this;
        },
    });

    const left = [];

    return {
        listeners,
        left,
        channel,
        private: (name) => channel(`private-${name}`),
        leave: (name) => left.push(name),
        connector: {
            onConnectionChange(callback) {
                onChange = callback;
                return () => {
                    onChange = () => {};
                };
            },
        },
        status: (value) => onChange(value),
    };
}

function fakeDocument() {
    const handlers = {};
    return {
        visibilityState: 'visible',
        addEventListener: (type, handler) => {
            handlers[type] = handler;
        },
        removeEventListener: (type, handler) => {
            if (handlers[type] === handler) {
                delete handlers[type];
            }
        },
        show() {
            this.visibilityState = 'visible';
            handlers.visibilitychange?.();
        },
    };
}

describe('F17, T2, T3 connection', () => {
    let echo;
    let doc;
    let store;
    let fetchState;
    let version;

    beforeEach(() => {
        vi.useFakeTimers();
        echo = fakeEcho();
        doc = fakeDocument();
        store = realtimeStore({ now: () => Date.now() });
        version = 1;
        fetchState = vi.fn(async () => snapshot(version++, Date.now()));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    const start = (random = () => 0.5) =>
        connectRealtime({
            store,
            echo,
            channels: [
                { name: 'event.abc.public', private: false },
                { name: 'event.abc.host', private: true },
            ],
            fetchState,
            random,
            doc,
        });

    it('loads the state at once and applies broadcasts from every channel', async () => {
        start();
        await vi.runOnlyPendingTimersAsync();

        expect(fetchState).toHaveBeenCalledTimes(1);
        echo.listeners['event.abc.public.state'](snapshot(7));
        expect(store.version).toBe(7);
        echo.listeners['private-event.abc.host.state'](snapshot(8));
        expect(store.version).toBe(8);
    });

    it('reports connecting, connected, reconnecting and offline', () => {
        start();
        expect(store.connection).toBe('connecting');
        echo.status('connected');
        expect(store.connection).toBe('connected');
        echo.status('connecting');
        expect(store.connection).toBe('reconnecting');
        echo.status('failed');
        expect(store.connection).toBe('offline');
    });

    it('waits a random moment before refreshing after a reconnect (F17)', async () => {
        start(() => 0.25);
        echo.status('connected');
        await vi.advanceTimersByTimeAsync(0);
        const afterFirstConnect = fetchState.mock.calls.length;

        echo.status('disconnected');
        echo.status('connected');
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS * 0.25 - 1);
        expect(fetchState).toHaveBeenCalledTimes(afterFirstConnect);
        await vi.advanceTimersByTimeAsync(1);
        expect(fetchState).toHaveBeenCalledTimes(afterFirstConnect + 1);
    });

    it('polls with jitter while the socket is down and stops once it is back (T2)', async () => {
        start(() => 0.5);
        await vi.advanceTimersByTimeAsync(0);
        const before = fetchState.mock.calls.length;
        const interval = POLL_INTERVAL_MS + POLL_JITTER_MS * 0.5;

        echo.status('failed');
        await vi.advanceTimersByTimeAsync(interval);
        expect(fetchState).toHaveBeenCalledTimes(before + 1);
        await vi.advanceTimersByTimeAsync(interval);
        expect(fetchState).toHaveBeenCalledTimes(before + 2);

        echo.status('connected');
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS);
        const afterReconnect = fetchState.mock.calls.length;
        await vi.advanceTimersByTimeAsync(interval * 3);
        expect(fetchState).toHaveBeenCalledTimes(afterReconnect);
    });

    it('refreshes when the phone comes back to the page (T3)', async () => {
        start();
        await vi.advanceTimersByTimeAsync(0);
        const before = fetchState.mock.calls.length;

        doc.show();
        await vi.advanceTimersByTimeAsync(0);
        expect(fetchState).toHaveBeenCalledTimes(before + 1);
    });

    it('marks the event missing when the server no longer knows it', async () => {
        fetchState = vi.fn(async () => null);
        start();
        await vi.advanceTimersByTimeAsync(0);

        expect(store.missing).toBe(true);
    });

    it('survives a failed refresh and tries again on the next poll', async () => {
        fetchState = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValue(snapshot(3));
        start(() => 0);
        await vi.advanceTimersByTimeAsync(0);
        echo.status('failed');
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);

        expect(store.version).toBe(3);
    });
});

describe('U18 leaving a page', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('stops listening, polling and following the page once the page is replaced', async () => {
        const echo = fakeEcho();
        const doc = fakeDocument();
        const store = realtimeStore({ now: () => Date.now() });
        let version = 1;
        const fetchState = vi.fn(async () => snapshot(version++, Date.now()));
        const connection = connectRealtime({
            store,
            echo,
            channels: [
                { name: 'event.abc.public', private: false },
                { name: 'event.abc.host', private: true },
            ],
            fetchState,
            random: () => 0.5,
            doc,
        });
        await vi.runOnlyPendingTimersAsync();
        echo.status('failed');

        connection.stop();
        const calls = fetchState.mock.calls.length;
        const held = store.version;

        expect(echo.left).toEqual(['event.abc.public', 'event.abc.host']);
        // No more polling, no reaction to the socket, the tab or late broadcasts.
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS + POLL_JITTER_MS + 1);
        echo.status('connected');
        doc.show();
        echo.listeners['event.abc.public.state'](snapshot(99));
        await vi.runOnlyPendingTimersAsync();

        expect(fetchState.mock.calls.length).toBe(calls);
        expect(store.version).toBe(held);
    });
});

describe('U18 joining a page while the socket is already up', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('shows the page as live at once, since no connection change will come', async () => {
        const echo = fakeEcho();
        echo.connector.connectionStatus = () => 'connected';
        const store = realtimeStore({ now: () => Date.now() });

        connectRealtime({
            store,
            echo,
            channels: [{ name: 'event.xyz.public', private: false }],
            fetchState: async () => snapshot(3, Date.now()),
            random: () => 0.5,
            doc: fakeDocument(),
        });
        await vi.runOnlyPendingTimersAsync();

        expect(store.connection).toBe('connected');
    });
});

describe('F20 status screens', () => {
    it('picks the screen from the event status', () => {
        const store = realtimeStore();
        expect(screenFor(store)).toBe('loading');

        for (const [status, screen] of [
            ['open', 'live'],
            ['draft', 'not_open'],
            ['finished', 'ended'],
            ['deleted', 'not_found'],
        ]) {
            store.accept({ ...snapshot(store.version + 1), event: { status } });
            expect(screenFor(store)).toBe(screen);
        }

        store.missing = true;
        expect(screenFor(store)).toBe('not_found');
    });

    it('fills a count into the singular or plural label', () => {
        const labels = { one: '__N__ player joined', other: '__N__ players joined' };

        expect(countLabel(labels, 1)).toBe('1 player joined');
        expect(countLabel(labels, 0)).toBe('0 players joined');
        expect(countLabel(labels, 12)).toBe('12 players joined');
    });

    it('falls back to polling when there is no socket server (T2)', async () => {
        vi.useFakeTimers();
        const store = realtimeStore();
        const fetchState = vi.fn(async () => snapshot(1, Date.now()));

        connectRealtime({
            store,
            echo: offlineEcho(),
            channels: [{ name: 'event.abc.public', private: false }],
            fetchState,
            random: () => 0,
            doc: fakeDocument(),
        });
        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);

        expect(store.connection).toBe('offline');
        expect(fetchState).toHaveBeenCalledTimes(2);
        vi.useRealTimers();
    });
});

describe('F14, F24 small messages', () => {
    it('updates the answered counter of the question on screen only', () => {
        const store = realtimeStore();
        store.accept({ ...snapshot(3), game: { question: { id: 'q1', attempt: 2 } } });

        store.answered({ question_id: 'q1', attempt: 2, answered: 7 });
        expect(store.snapshot.game.answered).toBe(7);

        store.answered({ question_id: 'q1', attempt: 1, answered: 99 });
        store.answered({ question_id: 'q2', attempt: 2, answered: 99 });
        expect(store.snapshot.game.answered).toBe(7);
    });

    it('fetches the full state after a jitter when a snapshot was too big to send', async () => {
        vi.useFakeTimers();
        const echo = fakeEcho();
        const store = realtimeStore();
        const fetchState = vi.fn(async () => snapshot(9, Date.now()));
        connectRealtime({
            store,
            echo,
            channels: [{ name: 'event.abc.public', private: false }],
            fetchState,
            random: () => 0.5,
            doc: fakeDocument(),
        });
        await vi.advanceTimersByTimeAsync(0);
        const before = fetchState.mock.calls.length;

        echo.listeners['event.abc.public.state']({ version: 9, server_now: 0, refresh: true });
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS * 0.5);

        expect(fetchState).toHaveBeenCalledTimes(before + 1);
        expect(store.version).toBe(9);
        vi.useRealTimers();
    });

    it('lets the host page react to each vote, to reload the tally', async () => {
        const echo = fakeEcho();
        const store = realtimeStore();
        store.accept({ ...snapshot(1), game: { question: { id: 'q1', attempt: 1 } } });
        const onAnswered = vi.fn();
        connectRealtime({
            store,
            echo,
            channels: [
                { name: 'event.abc.public', private: false },
                { name: 'event.abc.screen', private: false, counters: true },
            ],
            fetchState: async () => null,
            onAnswered,
            doc: fakeDocument(),
        });

        echo.listeners['event.abc.screen.answered']({ question_id: 'q1', attempt: 1, answered: 3 });

        expect(onAnswered).toHaveBeenCalledOnce();
        expect(store.snapshot.game.answered).toBe(3);
    });

    it('reloads a host page when its host channel gets a new key, on the private channel only (F26)', () => {
        const echo = fakeEcho();
        const onRotated = vi.fn();
        connectRealtime({
            store: realtimeStore(),
            echo,
            channels: [
                { name: 'event.abc.public', private: false },
                { name: 'event.abc.host.k1', private: true },
            ],
            fetchState: async () => null,
            onRotated,
            doc: fakeDocument(),
        });

        expect(echo.listeners['event.abc.public.rotated']).toBeUndefined();
        echo.listeners['private-event.abc.host.k1.rotated']({});

        expect(onRotated).toHaveBeenCalledOnce();
    });

    it('never listens for counters on a phone, which follows the public channel only (F25)', () => {
        const echo = fakeEcho();
        connectRealtime({
            store: realtimeStore(),
            echo,
            channels: [{ name: 'event.abc.public', private: false }],
            fetchState: async () => null,
            doc: fakeDocument(),
        });

        expect(echo.listeners['event.abc.public.answered']).toBeUndefined();
        expect(echo.listeners['event.abc.public.joined']).toBeUndefined();
    });

    it('updates the lobby count from the joined counter, since claims send no snapshot (F23, F25)', () => {
        const echo = fakeEcho();
        const store = realtimeStore();
        store.accept({ ...snapshot(4), lobby: { joined: 2 } });
        const onJoined = vi.fn();
        connectRealtime({
            store,
            echo,
            channels: [{ name: 'event.abc.screen', private: false, counters: true }],
            fetchState: async () => null,
            onJoined,
            doc: fakeDocument(),
        });

        echo.listeners['event.abc.screen.joined']({ joined: 5 });

        expect(store.snapshot.lobby.joined).toBe(5);
        expect(store.version).toBe(4);
        expect(onJoined).toHaveBeenCalledOnce();
        expect(store.joined({ joined: 'many' })).toBe(false);
    });
});

describe("E4 the phone's own state", () => {
    it('keeps "me" from /state while public broadcasts, which never carry it, come in', () => {
        const store = realtimeStore();
        store.accept({ ...snapshot(1), me: { name: 'Rita', vote: null } });
        store.accept(snapshot(2));

        expect(store.me).toEqual({ name: 'Rita', vote: null });

        store.accept({ ...snapshot(3), me: null });
        expect(store.me).toBe(null);
    });
});

describe('B-6 the phone follows changes to the name list', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    const withRoster = (version, roster) => ({ ...snapshot(version, Date.now()), event: { status: 'open', roster } });
    const start = (store, fetchState, onNameLost = vi.fn()) => {
        const echo = fakeEcho();
        connectRealtime({
            store,
            echo,
            channels: [{ name: 'event.abc.public', private: false }],
            fetchState,
            onNameLost,
            random: () => 0.5,
            doc: fakeDocument(),
        });

        return { send: (next) => echo.listeners['event.abc.public.state'](next), onNameLost };
    };

    it('fetches its own state again, after a jitter, when the name list changed', async () => {
        const store = realtimeStore();
        const fetchState = vi.fn(async () => ({ ...withRoster(1, 0), me: { name: 'Rita', vote: null } }));
        const { send } = start(store, fetchState);
        await vi.advanceTimersByTimeAsync(0);
        expect(fetchState).toHaveBeenCalledTimes(1);

        fetchState.mockImplementation(async () => ({ ...withRoster(3, 1), me: { name: 'Rita Sari', vote: null } }));
        send(withRoster(2, 0));
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS);
        expect(fetchState).toHaveBeenCalledTimes(1);

        send(withRoster(3, 1));
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS * 0.5);
        expect(fetchState).toHaveBeenCalledTimes(2);
        expect(store.me.name).toBe('Rita Sari');
    });

    it('leaves a page without a claimed name (the host pages) alone', async () => {
        const store = realtimeStore();
        const fetchState = vi.fn(async () => withRoster(1, 0));
        const { send } = start(store, fetchState);
        await vi.advanceTimersByTimeAsync(0);

        send(withRoster(2, 1));
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS);

        expect(fetchState).toHaveBeenCalledTimes(1);
    });

    it('says so once when its name was released or deleted', async () => {
        const store = realtimeStore();
        const fetchState = vi.fn(async () => ({ ...withRoster(1, 0), me: { name: 'Rita', vote: null } }));
        const { send, onNameLost } = start(store, fetchState);
        await vi.advanceTimersByTimeAsync(0);

        fetchState.mockImplementation(async () => ({ ...withRoster(2, 1), me: null }));
        send(withRoster(2, 1));
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS);
        send(withRoster(3, 1));
        await vi.advanceTimersByTimeAsync(RECONNECT_JITTER_MS);

        expect(onNameLost).toHaveBeenCalledOnce();
    });
});
