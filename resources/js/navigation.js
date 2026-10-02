// U18: the host panel moves between pages without reloading (Unpoly). Only links and forms
// inside the panel shell are followed; phone pages and the Public View keep plain page loads.
// Opt out per element with up-follow="false" / up-submit="false" (e.g. sign out).

export function configureNavigation(up) {
    up.link.config.followSelectors.push('[data-host-shell] a[href]');
    up.form.config.submitSelectors.push('[data-host-shell] form');
    // CSV exports are downloads, not pages.
    up.link.config.noFollowSelectors.push('a[href$=".csv"]');
}
