// F20: the one realtime store of a page, and how a page follows its event. Shared by the host
// panel entry (app.js, where Unpoly runs it for every page it swaps in) and the phone entry
// (phone.js, F27, where it runs once).
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { initialsOf } from './pentahoot.js';
import { connectRealtime, countLabel, fetchSnapshot, offlineEcho, realtimeStore, screenFor } from './realtime.js';

/** F14: hosts reload the per-name tally at most once a second while votes come in. */
const TALLY_RELOAD_MS = 1_000;

/**
 * F20: one realtime store for every page. It always exists, so views never read a missing
 * store; a page with live content (x-realtime component) fills it through followEvent().
 */
export function registerRealtimeStore(Alpine) {
    Alpine.store('realtime', {
        ...realtimeStore(),
        refresh() {},
        screen() {
            return screenFor(this);
        },
        count(labels, value) {
            return countLabel(labels, value);
        },
        initials(name) {
            return initialsOf(name);
        },
    });
}

let echo = null;

/** P1: the socket settings come from the page, not from the build; one socket for the tab. */
function echoFor(socket) {
    if (socket === null) {
        return offlineEcho();
    }

    echo ??= new Echo({
        broadcaster: 'reverb',
        key: socket.key,
        wsHost: socket.host,
        wsPort: socket.port,
        wssPort: socket.port,
        forceTLS: socket.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        Pusher,
    });

    return echo;
}

/**
 * Follows the event of a #realtime-config element and returns how to leave it again (U18: the
 * host panel replaces pages without reloading).
 */
export function followEvent(Alpine, element) {
    const config = JSON.parse(element.textContent);
    const store = Alpine.store('realtime');
    Object.assign(store, { snapshot: null, version: -1, connection: 'connecting', missing: false, me: null });
    store.accept(config.initial);

    // The host page follows the private channel and reloads its tally as votes come in.
    const isHost = config.channels.some((channel) => channel.private);
    let tallyTimer = null;
    const onAnswered = () => {
        if (isHost && tallyTimer === null) {
            tallyTimer = setTimeout(() => {
                tallyTimer = null;
                store.refresh();
            }, TALLY_RELOAD_MS);
        }
    };

    const connection = connectRealtime({
        store,
        echo: echoFor(config.socket),
        channels: config.channels,
        fetchState: () => fetchSnapshot(config.stateUrl),
        onAnswered,
        // F26: someone lost access to this event; load the page again with the new host key.
        onRotated: () => window.location.reload(),
        // B-6: the host released or deleted this phone's name; pick a name again.
        onNameLost: () => {
            if (config.joinUrl) {
                window.location.assign(config.joinUrl);
            }
        },
    });
    store.refresh = connection.refresh;

    return () => {
        clearTimeout(tallyTimer);
        connection.stop();
        // The next page starts empty instead of showing this event's state.
        Object.assign(store, { snapshot: null, version: -1, connection: 'connecting', me: null, refresh: () => {} });
    };
}
