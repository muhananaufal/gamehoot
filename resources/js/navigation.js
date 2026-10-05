// U18: the host panel moves between pages without reloading (Unpoly). Only links and forms
// inside the panel shell are followed; phone pages and the Public View keep plain page loads.
// Opt out per element with up-follow="false" / up-submit="false" (e.g. sign out).

import { animate as motionAnimate } from 'motion';
import { markPageEntered, SPRING } from './flip.js';

/** U8: leaving is quicker than arriving (NN/g). */
const EXIT = { duration: 0.12, ease: 'easeIn' };

export function configureNavigation(up, { animate = motionAnimate, onPageEnter = markPageEntered } = {}) {
    // Unpoly puts modals at the end of <body>, outside the shell: their links and forms count too.
    up.link.config.followSelectors.push('[data-host-shell] a[href]', 'up-modal a[href]');
    up.form.config.submitSelectors.push('[data-host-shell] form', 'up-modal form');
    // CSV exports are downloads, not pages.
    up.link.config.noFollowSelectors.push('a[href$=".csv"]');

    // U13: when a create modal's form succeeds, the server redirects to the result page (e.g. the
    // new event's Names page, matched by up-accept-location on the link). That page has no modal
    // content, so draw it in the main page instead, with its address and one-time message, and
    // close the modal as accepted.
    up.on('up:fragment:loaded', (event) => {
        // U8: the page about to be drawn just opened: its rows may come in one by one (flip.js).
        onPageEnter();

        const layer = event.request.layer;

        // A request that opens a modal still names its layer as a string ("new").
        if (typeof layer?.isOverlay !== 'function' || !layer.isOverlay() || !layer.acceptLocation) {
            return;
        }

        if (!event.response.ok || !layer.acceptLocation.recognize(event.response.url)) {
            return;
        }

        Object.assign(event.renderOptions, {
            layer: 'root',
            target: ':main',
            history: true,
            location: event.response.url,
            peel: 'accept',
        });
    });

    // U8: the old page fades out quickly while the new one fades in and its <main> rises with
    // the app's spring. Only <main> moves: a transform on the whole content column would carry
    // its fixed toasts along. Unpoly turns motion off for prefers-reduced-motion, so does this.
    up.transition('page', async (oldElement, newElement) => {
        if (!up.motion.config.enabled) {
            return;
        }

        await Promise.all([
            animate(oldElement, { opacity: [1, 0] }, EXIT),
            animate(newElement, { opacity: [0, 1] }, { duration: 0.18, ease: 'easeOut' }),
            animate(newElement.querySelector('main') ?? newElement, { y: [10, 0] }, SPRING),
        ]);
    });
    up.fragment.config.navigateOptions.transition = 'page';

    // U8: modals pop in with the spring and leave a little faster.
    up.animation('pop-in', (element) => animate(element, { opacity: [0, 1], scale: [0.95, 1] }, SPRING));
    up.animation('pop-out', (element) => animate(element, { opacity: [1, 0], scale: [1, 0.97] }, EXIT));
    up.layer.config.modal.openAnimation = 'pop-in';
    up.layer.config.modal.closeAnimation = 'pop-out';
}
