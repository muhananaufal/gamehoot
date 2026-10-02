import Alpine from 'alpinejs';
import 'unpoly';
import 'unpoly/unpoly.css';
import { configureNavigation } from './navigation.js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { watchFormSubmits } from './form-busy.js';
import { confirmStore } from './confirm-store.js';
import { hostShell } from './host-shell.js';
import { rowMenuStore } from './row-actions.js';
import { imagePicker } from './image-picker.js';
import { toastStore } from './toasts.js';
import { dateRangePicker } from './date-range.js';
import { kataBoxes } from './kata-boxes.js';
import { liveRegion } from './live-region.js';
import { nameReview } from './name-review.js';
import { initialsOf } from './pentahoot.js';
import { pentahootHost, pentahootPhone, pentahootScreen } from './pentahoot-screens.js';
import { connectRealtime, countLabel, fetchSnapshot, offlineEcho, realtimeStore, screenFor } from './realtime.js';
import { sortableList } from './sortable-list.js';
import { tableView } from './table-view.js';
import { tebakHost, tebakScreen } from './tebak-screens.js';
import { themeToggle } from './theme.js';

watchFormSubmits();
configureNavigation(window.up);

// U3: one toast area per page, used by session messages and by page actions (Copy link).
Alpine.store('toasts', toastStore());
Alpine.store('confirm', confirmStore());
Alpine.store('rowMenu', rowMenuStore());
Alpine.data('tableView', tableView);
Alpine.data('sortableList', sortableList);
Alpine.data('liveRegion', liveRegion);
Alpine.data('dateRangePicker', dateRangePicker);

Alpine.data('hostShell', () => hostShell());
Alpine.data('themeToggle', themeToggle);
Alpine.data('kataBoxes', kataBoxes);
Alpine.data('imagePicker', imagePicker);
Alpine.data('nameReview', nameReview);
Alpine.data('pentahootPhone', pentahootPhone);
Alpine.data('pentahootScreen', pentahootScreen);
Alpine.data('pentahootHost', pentahootHost);
Alpine.data('tebakScreen', tebakScreen);
Alpine.data('tebakHost', tebakHost);

/** F14: hosts reload the per-name tally at most once a second while votes come in. */
const TALLY_RELOAD_MS = 1_000;

// F20: one realtime store for every page. It always exists, so views never read a missing
// store; a page with live content (x-realtime component) fills it below.
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
 * Follows the event of #realtime-config. U18: the host panel replaces pages without reloading,
 * so this runs for every page that brings a config and returns how to leave it again.
 */
function followEvent(element) {
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
    });
    store.refresh = connection.refresh;

    return () => {
        clearTimeout(tallyTimer);
        connection.stop();
        // The next page starts empty instead of showing this event's state.
        Object.assign(store, { snapshot: null, version: -1, connection: 'connecting', me: null, refresh: () => {} });
    };
}

// U18: Unpoly runs compilers on the first page and on every fragment it inserts; the returned
// function runs when that fragment is replaced.
window.up.compiler('#realtime-config', followEvent);

window.Alpine = Alpine;
Alpine.start();
