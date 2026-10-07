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
    // A <form method="dialog"> only closes its dialog; sent through Unpoly it became a DIALOG
    // request to the page address (405 Method Not Allowed).
    up.form.config.noSubmitSelectors.push('form[method=dialog]');
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

    // U14: pack links stay light in the page (1,000 packs); their Unpoly settings are added here.
    // A pack swaps its panel, the title with its actions and the breadcrumb, and stays marked as
    // open on its question pages.
    up.macro('a[data-pack]', (link) => {
        link.setAttribute('up-target', '#pack-panel, #content-head, #breadcrumbs');
        link.setAttribute('up-history', 'true');
        link.setAttribute('up-alias', `${link.getAttribute('href')}/*`);
    });

    // U8: the old page fades out quickly while the new one fades in and its <main> rises with
    // the app's spring. Only <main> moves: a transform on the whole content column would carry
    // its fixed toasts along. Unpoly turns motion off for prefers-reduced-motion, so does this.
    up.transition('page', async (oldElement, newElement) => {
        if (!up.motion.config.enabled) {
            return;
        }

        // A part of a page (the pack panel, a count), or the same page after a change on it (a
        // deleted name back on Names: same breadcrumb), is updated in place with a short fade, so
        // it never feels like the page reloaded. Only a new page fades out and rises in.
        const crumbs = (element) => element.querySelector('#breadcrumbs')?.textContent?.trim();
        const samePage = crumbs(oldElement) !== undefined && crumbs(oldElement) === crumbs(newElement);

        if (newElement.id !== 'host-content' || samePage) {
            await animate(newElement, { opacity: [0.4, 1] }, { duration: 0.12, ease: 'easeOut' });

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
