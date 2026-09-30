// F20: the one realtime store shared by the phone, the Public View and the host panel.
// Views only read from it; the logic here is covered by tests/js/realtime.test.js.

/** F17: after a reconnect, wait up to this long so hundreds of phones do not ask at once. */
export const RECONNECT_JITTER_MS = 3_000;

/** T2: while the socket is down, ask for the state every few seconds, spread by jitter. */
export const POLL_INTERVAL_MS = 5_000;
export const POLL_JITTER_MS = 2_000;

/**
 * @param {{ now?: () => number }} options
 */
export function realtimeStore({ now = () => Date.now() } = {}) {
    return {
        snapshot: null,
        version: -1,
        // F4: server time minus phone time, so countdowns ignore a wrong phone clock.
        clockOffset: 0,
        // connecting | connected | reconnecting | offline
        connection: 'connecting',
        // The server no longer knows the event (deleted or wrong link).
        missing: false,

        /**
         * F15: keeps a snapshot unless it is older than the one already held.
         *
         * @param {{ sentAt?: number, receivedAt?: number }} timing request timing, when known
         */
        accept(next, { sentAt, receivedAt } = {}) {
            if (
                next === null ||
                typeof next !== 'object' ||
                !Number.isInteger(next.version) ||
                typeof next.server_now !== 'number'
            ) {
                return false;
            }

            if (next.version < this.version) {
                return false;
            }

            const received = receivedAt ?? now();
            // F4: the server clock was read halfway through the round trip.
            this.clockOffset = next.server_now - ((sentAt ?? received) + received) / 2;
            this.snapshot = next;
            this.version = next.version;
            this.missing = false;

            return true;
        },

        serverNow() {
            return now() + this.clockOffset;
        },

        /** Milliseconds left until a server timestamp, never negative. */
        msUntil(serverTime) {
            return Math.max(0, serverTime - this.serverNow());
        },
    };
}

/**
 * Fetches a snapshot: the parsed body, null when the event is gone (404), or an error.
 */
export async function fetchSnapshot(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

    if (response.status === 404) {
        return null;
    }

    if (!response.ok) {
        throw new Error(`State request failed with status ${response.status}.`);
    }

    return response.json();
}

/**
 * Keeps the store in sync: broadcasts on every channel (F1), a jittered refresh after each
 * reconnect (F17), polling while the socket is down (T2) and a refresh when the page is
 * shown again (T3).
 *
 * @param {{
 *   store: ReturnType<typeof realtimeStore>,
 *   echo: { channel: Function, private: Function, connector: { onConnectionChange: Function } },
 *   channels: Array<{ name: string, private: boolean }>,
 *   fetchState: () => Promise<object|null>,
 *   random?: () => number,
 *   doc?: { visibilityState: string, addEventListener: Function },
 * }} options
 */
export function connectRealtime({ store, echo, channels, fetchState, random = Math.random, doc = document }) {
    let everConnected = false;
    let lastStatus = null;
    let pollTimer = null;

    const refresh = async () => {
        const sentAt = Date.now();

        try {
            const next = await fetchState();

            if (next === null) {
                store.missing = true;

                return;
            }

            store.accept(next, { sentAt, receivedAt: Date.now() });
        } catch {
            // T2: the next poll or reconnect tries again.
        }
    };

    const stopPolling = () => {
        clearTimeout(pollTimer);
        pollTimer = null;
    };

    const schedulePoll = () => {
        stopPolling();
        pollTimer = setTimeout(
            async () => {
                pollTimer = null;
                await refresh();

                if (store.connection === 'offline' && pollTimer === null) {
                    schedulePoll();
                }
            },
            POLL_INTERVAL_MS + random() * POLL_JITTER_MS,
        );
    };

    for (const channel of channels) {
        const subscription = channel.private ? echo.private(channel.name) : echo.channel(channel.name);
        subscription.listen('.state', (next) => store.accept(next));
    }

    echo.connector.onConnectionChange((status) => {
        // The connector reports one change through several events; act on changes only.
        if (status === lastStatus) {
            return;
        }

        lastStatus = status;

        if (status === 'connected') {
            stopPolling();
            store.connection = 'connected';

            if (everConnected) {
                setTimeout(refresh, random() * RECONNECT_JITTER_MS);
            } else {
                // Covers broadcasts sent between the first load and the subscription.
                everConnected = true;
                refresh();
            }

            return;
        }

        if (status === 'connecting') {
            store.connection = everConnected ? 'reconnecting' : 'connecting';

            return;
        }

        store.connection = 'offline';

        if (pollTimer === null) {
            schedulePoll();
        }
    });

    doc.addEventListener('visibilitychange', () => {
        if (doc.visibilityState === 'visible') {
            refresh();
        }
    });

    refresh();
}

/**
 * F20: the status screen a snapshot calls for. 'live' means the event runs and the
 * page shows its own content.
 */
export function screenFor(store) {
    if (store.missing) {
        return 'not_found';
    }

    if (store.snapshot === null) {
        return 'loading';
    }

    return (
        {
            open: 'live',
            draft: 'not_open',
            finished: 'ended',
            deleted: 'not_found',
        }[store.snapshot.event.status] ?? 'not_found'
    );
}

/**
 * Fills a live count into a translated label with singular and plural forms (A10).
 *
 * @param {{ one: string, other: string }} labels with __N__ where the count goes
 */
export function countLabel(labels, count) {
    return (count === 1 ? labels.one : labels.other).replace('__N__', String(count));
}

/**
 * Stands in for Echo when the app broadcasts through log or null (tests, a missing
 * socket server): the page reports offline and keeps itself current by polling (T2).
 */
export function offlineEcho() {
    const channel = {
        listen() {
            return channel;
        },
    };

    return {
        channel: () => channel,
        private: () => channel,
        connector: {
            onConnectionChange(callback) {
                setTimeout(() => callback('failed'));

                return () => {};
            },
        },
    };
}
