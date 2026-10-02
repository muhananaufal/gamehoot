import { describe, expect, it } from 'vitest';
import { configureNavigation } from '../../resources/js/navigation.js';

const fakeUp = () => ({
    link: { config: { followSelectors: [], noFollowSelectors: [] } },
    form: { config: { submitSelectors: [], noSubmitSelectors: [] } },
});

describe('U18 navigation without reloads', () => {
    it('follows links and forms inside the host panel only, so phone and Public View pages reload normally', () => {
        const up = fakeUp();
        configureNavigation(up);

        expect(up.link.config.followSelectors).toEqual(['[data-host-shell] a[href]']);
        expect(up.form.config.submitSelectors).toEqual(['[data-host-shell] form']);
    });

    it('lets file downloads load as plain requests', () => {
        const up = fakeUp();
        configureNavigation(up);

        expect(up.link.config.noFollowSelectors).toContain('a[href$=".csv"]');
    });
});
