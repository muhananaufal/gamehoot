import Alpine from 'alpinejs';
import 'unpoly';
import 'unpoly/unpoly.css';
import { configureNavigation } from './navigation.js';
import { trackClicks } from './action-busy.js';
import { closeDialogsOnBackdrop } from './dialogs.js';
import { checkFormsBeforeSubmit } from './form-check.js';
import { watchFormSubmits } from './form-busy.js';
import { confirmStore } from './confirm-store.js';
import { hostShell } from './host-shell.js';
import { rowMenuHost, rowMenuStore } from './row-actions.js';
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
import { sessionGuard } from './session-guard.js';
import { pentahootHost, pentahootPhone, pentahootScreen } from './pentahoot-screens.js';
import { followEvent, registerRealtimeStore } from './realtime-boot.js';
import { sortableList } from './sortable-list.js';
import { tableView } from './table-view.js';
import { tebakHost, tebakScreen } from './tebak-screens.js';
import { themeToggle } from './theme.js';
import { motionStore } from './motion.js';
import { mascot } from './mascot.js';
import { connectionBanner, lobbyPenta, loginPenta, nudgeDirective } from './scene.js';
import { celebration } from './celebrate.js';
import { tooltips } from './tooltip.js';

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

// U27: full or reduced motion for this device. Unpoly's own animations follow the same mode. The
// Public View follows the device setting only (data-motion-device-only, U31).
Alpine.store(
    'motion',
    motionStore({
        storage: document.documentElement.hasAttribute('data-motion-device-only') ? null : globalThis.localStorage,
        onChange: (mode) => {
            window.up.motion.config.enabled = mode === 'full';
        },
    }),
);

// U3: one toast area per page, used by session messages and by page actions (Copy link).
Alpine.store('toasts', toastStore());
Alpine.store('confirm', confirmStore());
Alpine.store('rowMenu', rowMenuStore());
// Audit FE-3: the shared row menu follows the APG menu keys.
Alpine.data('rowMenuHost', rowMenuHost);
// D-11: the game picked in the Replay card of Live control; it outlives the card's refreshes.
Alpine.store('replayPick', { game: null });
Alpine.data('tableView', tableView);
// U29: Penta.
Alpine.data('mascot', mascot);
// U30: x-nudge.pop / .pulse / .flip / .shake: a small move when a shown value changes.
Alpine.directive('nudge', nudgeDirective(Alpine));
Alpine.data('connectionBanner', connectionBanner);
Alpine.data('lobbyPenta', lobbyPenta);
Alpine.data('loginPenta', loginPenta);
// U31: confetti and a cheering Penta for the key moments.
Alpine.data('celebration', celebration);
// U33: the tooltips of the host panel.
tooltips();
// U8: lists drawn by Alpine (rank lists) slide rows to their new place. x-flip.moves leaves the
// rows that appear to their own CSS enter animation.
Alpine.directive('flip', (el, { modifiers }, { cleanup }) => {
    const flip = flipList(el, { enter: !modifiers.includes('moves') });
    cleanup(() => flip.stop());
});
Alpine.data('sortableList', sortableList);
Alpine.data('liveRegion', liveRegion);
// C-8: keeps the host session alive and warns before the 8 hour limit.
Alpine.data('sessionGuard', sessionGuard);
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

// F20: one realtime store for every page; a page with live content fills it.
registerRealtimeStore(Alpine);

// U18: Unpoly runs compilers on the first page and on every fragment it inserts; the returned
// function runs when that fragment is replaced.
window.up.compiler('#realtime-config', (element) => followEvent(Alpine, element));

window.Alpine = Alpine;
Alpine.start();
