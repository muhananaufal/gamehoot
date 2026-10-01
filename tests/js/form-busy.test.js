import { describe, expect, it } from 'vitest';
import { markBusy, markIdle } from '../../resources/js/form-busy.js';

function button(attributes = {}) {
    const values = { ...attributes };

    return {
        disabled: false,
        getAttribute: (name) => values[name] ?? null,
        setAttribute: (name, value) => {
            values[name] = value;
        },
        removeAttribute: (name) => {
            delete values[name];
        },
        attributes: values,
    };
}

function form(buttons) {
    const values = {};

    return {
        querySelectorAll: () => buttons,
        setAttribute: (name, value) => {
            values[name] = value;
        },
        removeAttribute: (name) => {
            delete values[name];
        },
        attributes: values,
    };
}

describe('host forms while they are sent', () => {
    it('locks every submit button and marks the clicked one as busy, so a slow save is not sent twice', () => {
        const save = button();
        const other = button();
        const sent = form([save, other]);

        expect(markBusy(sent, save)).toBe(true);

        expect(save.disabled).toBe(true);
        expect(other.disabled).toBe(true);
        expect(save.attributes['aria-busy']).toBe('true');
        expect(sent.attributes['aria-busy']).toBe('true');
    });

    it('leaves dialog buttons alone: Cancel only closes the dialog', () => {
        const cancel = button({ formmethod: 'dialog' });

        expect(markBusy(form([cancel]), cancel)).toBe(false);
        expect(cancel.disabled).toBe(false);
    });

    it('unlocks again when the page comes back from the browser cache', () => {
        const save = button();
        const sent = form([save]);
        markBusy(sent, save);

        markIdle(sent);

        expect(save.disabled).toBe(false);
        expect(save.attributes['aria-busy']).toBeUndefined();
        expect(sent.attributes['aria-busy']).toBeUndefined();
    });
});
