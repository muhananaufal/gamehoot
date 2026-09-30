import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { imagePicker } from './image-picker.js';
import { kataBoxes } from './kata-boxes.js';
import { kataHost, kataScreen } from './kata-screens.js';
import { nameReview } from './name-review.js';
import { pentahootHost, pentahootPhone, pentahootScreen } from './pentahoot-screens.js';
import { connectRealtime, countLabel, fetchSnapshot, offlineEcho, realtimeStore, screenFor } from './realtime.js';
import { themeToggle } from './theme.js';

Alpine.data('themeToggle', themeToggle);
Alpine.data('kataBoxes', kataBoxes);
Alpine.data('imagePicker', imagePicker);
Alpine.data('nameReview', nameReview);
Alpine.data('pentahootPhone', pentahootPhone);
Alpine.data('pentahootScreen', pentahootScreen);
Alpine.data('pentahootHost', pentahootHost);
Alpine.data('kataScreen', kataScreen);
Alpine.data('kataHost', kataHost);

/** F14: hosts reload the per-name tally at most once a second while votes come in. */
const TALLY_RELOAD_MS = 1_000;

// F20: pages with live content carry their realtime settings (x-realtime component).
const realtimeConfig = document.getElementById('realtime-config');

if (realtimeConfig !== null) {
    const config = JSON.parse(realtimeConfig.textContent);

    Alpine.store('realtime', {
        ...realtimeStore(),
        screen() {
            return screenFor(this);
        },
        count(labels, value) {
            return countLabel(labels, value);
        },
    });

    const store = Alpine.store('realtime');
    store.accept(config.initial);

    // P1: the socket settings come from the page, not from the build.
    const echo =
        config.socket === null
            ? offlineEcho()
            : new Echo({
                  broadcaster: 'reverb',
                  key: config.socket.key,
                  wsHost: config.socket.host,
                  wsPort: config.socket.port,
                  wssPort: config.socket.port,
                  forceTLS: config.socket.scheme === 'https',
                  enabledTransports: ['ws', 'wss'],
                  Pusher,
              });

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

    const { refresh } = connectRealtime({
        store,
        echo,
        channels: config.channels,
        fetchState: () => fetchSnapshot(config.stateUrl),
        onAnswered,
    });
    store.refresh = refresh;
}

window.Alpine = Alpine;
Alpine.start();
