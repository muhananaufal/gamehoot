// F27 (audit FE-10): the entry of the participant pages. Hundreds of phones load it over the
// venue WiFi (A1), so it carries only what those pages use: no Unpoly (U18: host panel only),
// no host forms, tables or wizard. The host panel and the Public View load app.js.
import Alpine from 'alpinejs';
import { trackClicks } from './action-busy.js';
import { celebration } from './celebrate.js';
import { flipList, markPageEntered } from './flip.js';
import { checkFormsBeforeSubmit } from './form-check.js';
import { watchFormSubmits } from './form-busy.js';
import { mascot } from './mascot.js';
import { motionStore } from './motion.js';
import { nameSearch } from './name-search.js';
import { pentahootPhone } from './pentahoot-screens.js';
import { followEvent, registerRealtimeStore } from './realtime-boot.js';
import { connectionBanner, nudgeDirective } from './scene.js';
import { tebakScreen } from './tebak-screens.js';
import { themeToggle } from './theme.js';

markPageEntered();
watchFormSubmits();
trackClicks();
// U19: simple rules are checked before a form is sent; the layout carries the messages.
checkFormsBeforeSubmit(document, JSON.parse(document.querySelector('meta[name="form-messages"]')?.content ?? '{}'));

// U27: each player can calm the motion on their own phone.
Alpine.store('motion', motionStore({ storage: globalThis.localStorage }));
// U30: x-nudge; U8: x-flip, as on the other pages.
Alpine.directive('nudge', nudgeDirective(Alpine));
Alpine.directive('flip', (el, { modifiers }, { cleanup }) => {
    const flip = flipList(el, { enter: !modifiers.includes('moves') });
    cleanup(() => flip.stop());
});
Alpine.data('mascot', mascot);
Alpine.data('connectionBanner', connectionBanner);
Alpine.data('celebration', celebration);
Alpine.data('nameSearch', nameSearch);
Alpine.data('pentahootPhone', pentahootPhone);
Alpine.data('tebakScreen', tebakScreen);
// U17: each player can switch the theme of their own phone.
Alpine.data('themeToggle', themeToggle);

// F20: one realtime store; the phone page follows its event once (no page swaps here).
registerRealtimeStore(Alpine);
const config = document.querySelector('#realtime-config');

if (config) {
    followEvent(Alpine, config);
}

window.Alpine = Alpine;
Alpine.start();
