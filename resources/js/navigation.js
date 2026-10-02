// U18: the host panel moves between pages without reloading (Unpoly). Only links and forms
// inside the panel shell are followed; phone pages and the Public View keep plain page loads.
// Opt out per element with up-follow="false" / up-submit="false" (e.g. sign out).

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
}
