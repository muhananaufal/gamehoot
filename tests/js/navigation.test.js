import { describe, expect, it } from 'vitest';
import { configureNavigation } from '../../resources/js/navigation.js';

const fakeUp = () => {
    const handlers = {};
    const rendered = [];

    const up = {
        handlers,
        rendered,
        animations: {},
        motion: { config: { enabled: true } },
        layer: { config: { modal: {} } },
        animation: (name, fn) => (up.animations[name] = fn),
        link: { config: { followSelectors: [], noFollowSelectors: [] } },
        form: { config: { submitSelectors: [], noSubmitSelectors: [] } },
        on: (name, handler) => (handlers[name] = handler),
        render: (options) => rendered.push(options),
    };

    return up;
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

    const fakeElement = (matching) => {
        const calls = [];

        return {
            calls,
            matches: (selector) => selector.split(',').some((part) => part.trim() === matching),
            animate: (frames, options) => {
                calls.push({ frames, options });

                return { finished: Promise.resolve() };
            },
        };
    };

    it('lets new page content and the pack panel arrive with a short rise (U8)', () => {
        const up = fakeUp();
        configureNavigation(up);
        const content = fakeElement('#host-content');
        const panel = fakeElement('#pack-panel');
        const sidebar = fakeElement('#host-nav');

        for (const target of [content, panel, sidebar]) {
            up.handlers['up:fragment:inserted']({ target });
        }

        expect(content.calls).toHaveLength(1);
        expect(content.calls[0].options.duration).toBeLessThanOrEqual(300);
        expect(panel.calls).toHaveLength(1);
        // The sidebar and the toasts update in place, without moving.
        expect(sidebar.calls).toHaveLength(0);
    });

    it('keeps everything still when the device asks for reduced motion', () => {
        const up = fakeUp();
        up.motion.config.enabled = false;
        configureNavigation(up);
        const content = fakeElement('#host-content');

        up.handlers['up:fragment:inserted']({ target: content });

        expect(content.calls).toHaveLength(0);
    });

    it('opens modals with a small pop instead of a plain fade', async () => {
        const up = fakeUp();
        configureNavigation(up);
        const box = fakeElement('up-modal-box');

        expect(up.layer.config.modal.openAnimation).toBe('pop-in');
        await up.animations['pop-in'](box, { duration: 175, easing: 'ease' });
        expect(box.calls[0].frames[0]).toMatchObject({ opacity: 0 });
    });
});
