import { describe, expect, it } from 'vitest';
import { configureNavigation } from '../../resources/js/navigation.js';

const fakeUp = () => {
    const handlers = {};
    const rendered = [];

    return {
        handlers,
        rendered,
        link: { config: { followSelectors: [], noFollowSelectors: [] } },
        form: { config: { submitSelectors: [], noSubmitSelectors: [] } },
        on: (name, handler) => (handlers[name] = handler),
        render: (options) => rendered.push(options),
    };
};

describe('U18 navigation without reloads', () => {
    it('follows links and forms inside the host panel only, so phone and Public View pages reload normally', () => {
        const up = fakeUp();
        configureNavigation(up);

        // Unpoly puts modals at the end of <body>, outside the shell: their links and forms count too.
        expect(up.link.config.followSelectors).toEqual(['[data-host-shell] a[href]', 'up-modal a[href]']);
        expect(up.form.config.submitSelectors).toEqual(['[data-host-shell] form', 'up-modal form']);
    });

    it('lets file downloads load as plain requests', () => {
        const up = fakeUp();
        configureNavigation(up);

        expect(up.link.config.noFollowSelectors).toContain('a[href$=".csv"]');
    });

    it('draws the result page of a create modal in the main page and closes the modal (U13)', () => {
        const up = fakeUp();
        configureNavigation(up);
        const renderOptions = { layer: 'overlay-object', target: '[up-main~=modal]' };
        const layer = {
            isOverlay: () => true,
            acceptLocation: { recognize: (url) => (url.endsWith('/people') ? {} : undefined) },
        };

        up.handlers['up:fragment:loaded']({
            request: { layer },
            response: { ok: true, url: 'http://app.test/host/abc/people' },
            renderOptions,
        });

        expect(renderOptions).toEqual({
            layer: 'root',
            target: ':main',
            history: true,
            location: 'http://app.test/host/abc/people',
            peel: 'accept',
        });
    });

    it('keeps the modal for a form with errors, other pages and requests not made from a modal', () => {
        const up = fakeUp();
        configureNavigation(up);
        const layer = {
            isOverlay: () => true,
            acceptLocation: { recognize: (url) => (url.endsWith('/people') ? {} : undefined) },
        };
        const untouched = () => ({ layer: 'x', target: 'y' });

        const formAgain = untouched();
        up.handlers['up:fragment:loaded']({
            request: { layer },
            response: { ok: false, url: 'http://app.test/host/abc/people' },
            renderOptions: formAgain,
        });
        const otherPage = untouched();
        up.handlers['up:fragment:loaded']({
            request: { layer },
            response: { ok: true, url: 'http://app.test/host/events/create' },
            renderOptions: otherPage,
        });
        // A request that opens a modal names its layer as a string ("new").
        const opening = untouched();
        up.handlers['up:fragment:loaded']({
            request: { layer: 'new' },
            response: { ok: true, url: 'http://app.test/host/abc/people' },
            renderOptions: opening,
        });

        expect([formAgain, otherPage, opening]).toEqual([untouched(), untouched(), untouched()]);
    });
});
