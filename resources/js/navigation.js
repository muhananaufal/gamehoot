// U18: the host panel moves between pages without reloading (Unpoly). Only links and forms
// inside the panel shell are followed; phone pages and the Public View keep plain page loads.
// Opt out per element with up-follow="false" / up-submit="false" (e.g. sign out).

/** U8: the parts of a page that animate when they are swapped in. */
const ENTERING = '#host-content, #pack-panel';

export function configureNavigation(up) {
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

    // U8: new page content and the pack panel arrive with a short rise. Only what was swapped
    // moves: the sidebar and the toasts update in place. Unpoly turns its motion off for
    // prefers-reduced-motion, and so does this.
    up.on('up:fragment:inserted', (event) => {
        if (!up.motion.config.enabled || !event.target.matches(ENTERING)) {
            return;
        }

        // A transform on the whole content column would pin its fixed toasts to it while moving;
        // the page body alone rises.
        const element = event.target.querySelector?.('main') ?? event.target;

        element.animate(
            [
                { opacity: 0, transform: 'translateY(6px)' },
                { opacity: 1, transform: 'none' },
            ],
            { duration: 180, easing: 'ease-out' },
        );
    });

    // U8: modals pop in a little instead of only fading.
    up.animation(
        'pop-in',
        (element, { duration, easing }) =>
            element.animate(
                [
                    { opacity: 0, transform: 'scale(0.96)' },
                    { opacity: 1, transform: 'none' },
                ],
                { duration, easing },
            ).finished,
    );
    up.layer.config.modal.openAnimation = 'pop-in';
}
