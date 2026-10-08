import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { HOVER_DELAY_MS, placeTip, tipText, tooltips } from '../../resources/js/tooltip.js';

function element({ tag = 'BUTTON', label = null, title = null, text = '', side = null } = {}) {
    const attributes = new Map();

    if (label !== null) {
        attributes.set('aria-label', label);
    }

    if (title !== null) {
        attributes.set('title', title);
    }

    if (side !== null) {
        attributes.set('data-tip-side', side);
    }

    return {
        tagName: tag,
        textContent: text,
        isConnected: true,
        getAttribute: (name) => attributes.get(name) ?? null,
        hasAttribute: (name) => attributes.has(name),
        setAttribute: (name, value) => attributes.set(name, String(value)),
        removeAttribute: (name) => attributes.delete(name),
        getBoundingClientRect: () => ({ top: 100, bottom: 140, left: 200, right: 240, width: 40, height: 40 }),
        closest(selector) {
            if (selector === '[data-host-shell]') {
                return this.inShell === false ? null : {};
            }

            return this.matches(selector) ? this : null;
        },
        matches: (selector) => selector.includes('[title]') || selector.includes('[data-tip-title]'),
        contains(other) {
            return other === this;
        },
    };
}

describe('U33 the text of a tooltip', () => {
    it('reads the aria-label, so the text is written once', () => {
        expect(tipText(element({ label: 'Switch theme', title: 'Switch theme' }))).toBe('Switch theme');
    });

    it('falls back to the title of an element without a label (drag handles)', () => {
        expect(tipText(element({ tag: 'SPAN', title: 'Drag to reorder' }))).toBe('Drag to reorder');
    });

    it('stays away when the text is already on screen (a sidebar link that is not collapsed)', () => {
        expect(tipText(element({ tag: 'A', title: 'Events', text: ' Events ' }))).toBeNull();
    });

    it('never takes the title of a frame, which names it for screen readers', () => {
        expect(tipText(element({ tag: 'IFRAME', title: 'Public View preview' }))).toBeNull();
    });
});

describe('U33 where a tooltip goes', () => {
    const viewport = { width: 1280, height: 800 };
    const tip = { width: 100, height: 30 };

    it('sits centred above its button', () => {
        expect(placeTip({ anchor: { top: 100, bottom: 140, left: 200, right: 240 }, tip, viewport })).toEqual({
            top: 62,
            left: 170,
            side: 'top',
        });
    });

    it('moves below when the top of the window would cut it', () => {
        expect(placeTip({ anchor: { top: 10, bottom: 50, left: 200, right: 240 }, tip, viewport }).side).toBe('bottom');
    });

    it('stays inside the window at the edges', () => {
        expect(placeTip({ anchor: { top: 100, bottom: 140, left: 0, right: 20 }, tip, viewport }).left).toBe(8);
        expect(placeTip({ anchor: { top: 100, bottom: 140, left: 1260, right: 1280 }, tip, viewport }).left).toBe(1172);
    });

    it('goes to the right of a collapsed sidebar link, or left when there is no room', () => {
        const anchor = { top: 100, bottom: 140, left: 10, right: 50 };
        expect(placeTip({ anchor, tip, viewport, prefer: 'right' })).toEqual({ top: 105, left: 58, side: 'right' });
        expect(
            placeTip({ anchor: { top: 100, bottom: 140, left: 1200, right: 1240 }, tip, viewport, prefer: 'right' })
                .side,
        ).toBe('left');
    });
});

describe('U33 the tooltip controller', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    function mount() {
        const listeners = {};
        const tip = {
            hidden: true,
            textContent: '',
            style: {},
            dataset: {},
            offsetWidth: 100,
            offsetHeight: 30,
            setAttribute: vi.fn(),
            addEventListener(type, fn) {
                listeners[`tip:${type}`] = fn;
            },
            contains: (other) => other === tip,
        };
        const doc = {
            documentElement: { dataset: { motion: 'full' } },
            body: { append: vi.fn() },
            createElement: () => tip,
            addEventListener(type, fn) {
                listeners[type] = fn;
            },
        };
        const win = { innerWidth: 1280, innerHeight: 800, addEventListener: vi.fn() };
        tooltips({ doc, win });

        return { listeners, tip };
    }

    it('shows after a short hover and hides the browser title meanwhile', () => {
        const { listeners, tip } = mount();
        const button = element({ label: 'Switch theme', title: 'Switch theme' });

        listeners.pointerover({ target: button });
        expect(button.hasAttribute('title')).toBe(false);
        expect(tip.hidden).toBe(true);

        vi.advanceTimersByTime(HOVER_DELAY_MS);
        expect(tip.hidden).toBe(false);
        expect(tip.textContent).toBe('Switch theme');
    });

    it('shows at once for keyboard focus and closes on Escape without moving the focus', () => {
        const { listeners, tip } = mount();
        const button = element({ label: 'Log out' });
        button.matches = (selector) => selector === ':focus-visible' || selector.includes('[aria-label]');

        listeners.focusin({ target: button });
        expect(tip.hidden).toBe(false);

        const event = { key: 'Escape', preventDefault: vi.fn() };
        listeners.keydown(event);
        expect(tip.hidden).toBe(true);
    });

    it('stays while the pointer moves from the button onto the tooltip', () => {
        const { listeners, tip } = mount();
        const button = element({ label: 'Switch theme', title: 'Switch theme' });

        listeners.pointerover({ target: button });
        vi.advanceTimersByTime(HOVER_DELAY_MS);
        listeners.pointerout({ target: button, relatedTarget: tip });
        vi.advanceTimersByTime(500);

        expect(tip.hidden).toBe(false);

        listeners['tip:pointerleave']({ relatedTarget: null });
        vi.advanceTimersByTime(500);
        expect(tip.hidden).toBe(true);
    });

    it('works only inside the host panel', () => {
        const { listeners, tip } = mount();
        const button = element({ label: 'Switch theme', title: 'Switch theme' });
        button.inShell = false;

        listeners.pointerover({ target: button });
        vi.advanceTimersByTime(HOVER_DELAY_MS);

        expect(tip.hidden).toBe(true);
        expect(button.hasAttribute('title')).toBe(true);
    });
});
