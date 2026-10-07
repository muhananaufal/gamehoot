import { describe, expect, it, vi } from 'vitest';
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
        transitions: {},
        fragment: { config: { navigateOptions: {} } },
        animation: (name, fn) => (up.animations[name] = fn),
        transition: (name, fn) => (up.transitions[name] = fn),
        macros: {},
        macro: (selector, fn) => (up.macros[selector] = fn),
        link: { config: { followSelectors: [], noFollowSelectors: [] } },
        form: { config: { submitSelectors: [], noSubmitSelectors: [] } },
        network: { config: { evictCache: false } },
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

    it('leaves dialog forms to the browser, so Cancel closes the dialog instead of sending a DIALOG request', () => {
        const up = fakeUp();
        configureNavigation(up);

        expect(up.form.config.noSubmitSelectors).toContain('form[method=dialog]');
    });

    it('drops every cached page after a change of data, so no page shows the old data first (U19)', () => {
        const up = fakeUp();
        configureNavigation(up);

        expect(up.network.config.evictCache({ isSafe: () => false })).toBe(true);
        expect(up.network.config.evictCache({ isSafe: () => true })).toBe(false);
    });

    it('lets file downloads load as plain requests', () => {
        const up = fakeUp();
        configureNavigation(up);

        expect(up.link.config.noFollowSelectors).toContain('a[download]');
        // A query ending in .csv is still a page (Back from the import review, ?last=staff.csv).
        expect(up.link.config.noFollowSelectors).not.toContain('a[href$=".csv"]');
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

    const fakeElement = (inner = null, id = 'host-content') => ({ id, querySelector: () => inner });

    it('lets the old page fade out quickly while the new one rises in with a spring (U8)', async () => {
        const up = fakeUp();
        const animate = vi.fn(() => Promise.resolve());
        configureNavigation(up, { animate });
        const main = {};
        const oldPage = fakeElement();
        const newPage = fakeElement(main);

        expect(up.fragment.config.navigateOptions.transition).toBe('page');
        await up.transitions.page(oldPage, newPage, {});

        const [out, fadeIn, rise] = animate.mock.calls;
        expect(out[0]).toBe(oldPage);
        expect(out[1]).toMatchObject({ opacity: [1, 0] });
        expect(out[2].duration).toBeLessThanOrEqual(0.15);
        expect(fadeIn[0]).toBe(newPage);
        // Only <main> rises: a transform on the content column would carry its fixed toasts along.
        expect(rise[0]).toBe(main);
        expect(rise[2]).toMatchObject({ type: 'spring' });
    });

    it('updates a part of a page in place: a short fade, no exit and no movement (pack panel)', async () => {
        const up = fakeUp();
        const animate = vi.fn(() => Promise.resolve());
        configureNavigation(up, { animate });
        const panel = fakeElement({}, 'pack-panel');

        await up.transitions.page(fakeElement(null, 'pack-panel'), panel, {});

        expect(animate).toHaveBeenCalledOnce();
        expect(animate.mock.calls[0][0]).toBe(panel);
        expect(animate.mock.calls[0][1]).toEqual({ opacity: [0.4, 1] });
        expect(animate.mock.calls[0][2].duration).toBeLessThanOrEqual(0.15);
    });

    it('updates the same page in place after a change on it (a deleted name back on Names)', async () => {
        const up = fakeUp();
        const animate = vi.fn(() => Promise.resolve());
        configureNavigation(up, { animate });
        const page = (crumbs) => ({
            id: 'host-content',
            querySelector: (selector) => (selector === '#breadcrumbs' ? { textContent: crumbs } : {}),
        });
        const after = page('Events / Gathering / Names');

        await up.transitions.page(page('Events / Gathering / Names'), after, {});

        expect(animate).toHaveBeenCalledOnce();
        expect(animate.mock.calls[0][0]).toBe(after);
        expect(animate.mock.calls[0][1]).toEqual({ opacity: [0.4, 1] });
    });

    it('swaps pages without moving when the device asks for reduced motion', async () => {
        const up = fakeUp();
        up.motion.config.enabled = false;
        const animate = vi.fn();
        configureNavigation(up, { animate });

        await up.transitions.page(fakeElement(), fakeElement({}), {});

        expect(animate).not.toHaveBeenCalled();
    });

    it('pops modals in with a spring and lets them go a little faster', async () => {
        const up = fakeUp();
        const animate = vi.fn(() => Promise.resolve());
        configureNavigation(up, { animate });
        const box = {};

        expect(up.layer.config.modal.openAnimation).toBe('pop-in');
        expect(up.layer.config.modal.closeAnimation).toBe('pop-out');
        await up.animations['pop-in'](box, {});
        await up.animations['pop-out'](box, {});

        expect(animate.mock.calls[0][1]).toMatchObject({ opacity: [0, 1], scale: [0.95, 1] });
        expect(animate.mock.calls[0][2]).toMatchObject({ type: 'spring' });
        expect(animate.mock.calls[1][2].duration).toBeLessThanOrEqual(0.15);
    });

    it('marks a page as just opened before it is drawn, so its rows can come in one by one', () => {
        const up = fakeUp();
        const entered = vi.fn();
        configureNavigation(up, { animate: vi.fn(), onPageEnter: entered });

        up.handlers['up:fragment:loaded']({ request: { layer: 'root' }, response: { ok: true }, renderOptions: {} });

        expect(entered).toHaveBeenCalledOnce();
    });

    it('turns a light pack link into one that swaps the pack only and stays marked on its pages (U14)', () => {
        const up = fakeUp();
        configureNavigation(up, { animate: vi.fn() });
        const attrs = {};
        const link = { getAttribute: () => '/host/packs/p1', setAttribute: (name, value) => (attrs[name] = value) };

        up.macros['a[data-pack]'](link);

        expect(attrs).toEqual({
            'up-target': '#pack-panel, #content-head, #breadcrumbs',
            'up-history': 'true',
            'up-alias': '/host/packs/p1/*',
        });
    });
});

describe('U16 forms sent through Unpoly', () => {
    it('adds the busy lock to the previews of every submitted form, keeping its own', () => {
        const up = fakeUp();
        configureNavigation(up);
        const own = () => {};
        const event = { form: { querySelectorAll: () => [] }, submitButton: null, renderOptions: { preview: own } };

        up.handlers['up:form:submit'](event);

        expect(event.renderOptions.preview).toHaveLength(2);
        expect(event.renderOptions.preview[0]).toBe(own);
    });
});

describe('U13 a page that changes closes the dialogs of the old one', () => {
    const withOpenDialog = () => {
        const dialog = {
            closed: false,
            close() {
                this.closed = true;
            },
        };

        return { dialog, element: { id: 'host-content', querySelector: () => null, querySelectorAll: () => [dialog] } };
    };

    it('closes them at once, before the old page fades, so it never shows over the new one', async () => {
        const up = fakeUp();
        configureNavigation(up, { animate: vi.fn(() => Promise.resolve()) });
        const { dialog, element } = withOpenDialog();

        await up.transitions.page(element, { id: 'host-content', querySelector: () => null }, {});

        expect(dialog.closed).toBe(true);
    });

    it('closes them also without motion (prefers-reduced-motion)', async () => {
        const up = fakeUp();
        up.motion.config.enabled = false;
        configureNavigation(up, { animate: vi.fn(() => Promise.resolve()) });
        const { dialog, element } = withOpenDialog();

        await up.transitions.page(element, { id: 'host-content', querySelector: () => null }, {});

        expect(dialog.closed).toBe(true);
    });
});
