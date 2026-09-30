import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { kataBoxes } from './kata-boxes.js';
import { nameReview } from './name-review.js';
import { connectRealtime, countLabel, fetchSnapshot, offlineEcho, realtimeStore, screenFor } from './realtime.js';
import { themeToggle } from './theme.js';

Alpine.data('themeToggle', themeToggle);
Alpine.data('kataBoxes', kataBoxes);
Alpine.data('nameReview', nameReview);

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

    connectRealtime({ store, echo, channels: config.channels, fetchState: () => fetchSnapshot(config.stateUrl) });
}

window.Alpine = Alpine;
Alpine.start();
