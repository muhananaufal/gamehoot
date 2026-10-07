import Alpine from 'alpinejs';
import 'unpoly';
import 'unpoly/unpoly.css';
import { configureNavigation } from './navigation.js';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { trackClicks } from './action-busy.js';
import { closeDialogsOnBackdrop } from './dialogs.js';
import { checkFormsBeforeSubmit } from './form-check.js';
import { watchFormSubmits } from './form-busy.js';
import { confirmStore } from './confirm-store.js';
import { hostShell } from './host-shell.js';
import { rowMenuStore } from './row-actions.js';
import { imagePicker } from './image-picker.js';
import { toastStore } from './toasts.js';
import { dateRangePicker } from './date-range.js';
import { eventLink } from './event-link.js';
import { flipList, markPageEntered } from './flip.js';
import { kataBoxes } from './kata-boxes.js';
import { liveRegion } from './live-region.js';
import { nameBatch } from './name-batch.js';
import { newEventWizard } from './new-event-wizard.js';
import { nameReview } from './name-review.js';
import { packList } from './pack-list.js';
import { packPicker } from './pack-picker.js';
import { replayScreen } from './replay-screen.js';
import { selectBox } from './select-box.js';
import { initialsOf } from './pentahoot.js';
import { pentahootHost, pentahootPhone, pentahootScreen } from './pentahoot-screens.js';
import { connectRealtime, countLabel, fetchSnapshot, offlineEcho, realtimeStore, screenFor } from './realtime.js';
import { sortableList } from './sortable-list.js';
import { tableView } from './table-view.js';
import { tebakHost, tebakScreen } from './tebak-screens.js';
import { themeToggle } from './theme.js';

// U8: the first page just opened, so its rows may come in one by one.
markPageEntered();
watchFormSubmits();
trackClicks();
closeDialogsOnBackdrop();
// U19: simple rules are checked before a form is sent; the layout carries the messages.
checkFormsBeforeSubmit(document, JSON.parse(document.querySelector('meta[name="form-messages"]')?.content ?? '{}'));
// U19: the browser's own bubbles never show; every form speaks in the app's words.
window.up.compiler('form', (form) => {
    form.noValidate = true;
});
configureNavigation(window.up);

// U3: one toast area per page, used by session messages and by page actions (Copy link).
Alpine.store('toasts', toastStore());
Alpine.store('confirm', confirmStore());
Alpine.store('rowMenu', rowMenuStore());
// D-11: the game picked in the Replay card of Live control; it outlives the card's refreshes.
Alpine.store('replayPick', { game: null });
Alpine.data('tableView', tableView);
// U8: lists drawn by Alpine (rank lists) slide rows to their new place. x-flip.moves leaves the
// rows that appear to their own CSS enter animation.
Alpine.directive('flip', (el, { modifiers }, { cleanup }) => {
    const flip = flipList(el, { enter: !modifiers.includes('moves') });
    cleanup(() => flip.stop());
});
Alpine.data('sortableList', sortableList);
Alpine.data('liveRegion', liveRegion);
Alpine.data('replayScreen', replayScreen);
Alpine.data('nameBatch', nameBatch);
Alpine.data('newEventWizard', newEventWizard);
Alpine.data('dateRangePicker', dateRangePicker);
Alpine.data('eventLink', eventLink);

Alpine.data('hostShell', () => hostShell());
Alpine.data('themeToggle', themeToggle);
Alpine.data('kataBoxes', kataBoxes);
Alpine.data('imagePicker', imagePicker);
Alpine.data('nameReview', nameReview);
Alpine.data('packList', packList);
Alpine.data('packPicker', packPicker);
Alpine.data('selectBox', selectBox);
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

// U18: Unpoly runs compilers on the first page and on every fragment it inserts; the returned
// function runs when that fragment is replaced.
window.up.compiler('#realtime-config', followEvent);

window.Alpine = Alpine;
Alpine.start();
