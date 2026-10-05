import { describe, expect, it } from 'vitest';
import { trackClicks, withBusyButton } from '../../resources/js/action-busy.js';

const fakeButton = () => {
    const attrs = {};

    return {
        attrs,
        setAttribute: (name, value) => (attrs[name] = value),
        removeAttribute: (name) => delete attrs[name],
    };
};

const fakeDocument = () => {
    const listeners = [];

    return {
        addEventListener: (type, handler, capture) => listeners.push({ type, handler, capture }),
        click(target) {
            for (const listener of listeners) {
                if (listener.type === 'click') {
                    listener.handler({ target });
                }
            }
        },
        listeners,
    };
};

describe('U16 busy state for actions sent with fetch', () => {
    it('marks the clicked button busy while the action runs and clears it after', async () => {
        const doc = fakeDocument();
        trackClicks(doc);
        const button = fakeButton();
        doc.click({ closest: () => button });

        let busyDuring = null;
        await withBusyButton(async () => {
            busyDuring = button.attrs['aria-busy'];
        });

        expect(doc.listeners[0].capture).toBe(true);
        expect(busyDuring).toBe('true');
        expect(button.attrs['aria-busy']).toBeUndefined();
    });

    it('clears the busy state when the action fails', async () => {
        const doc = fakeDocument();
        trackClicks(doc);
        const button = fakeButton();
        doc.click({ closest: () => button });

        await expect(
            withBusyButton(async () => {
                throw new Error('network');
            }),
        ).rejects.toThrow('network');
        expect(button.attrs['aria-busy']).toBeUndefined();
    });

    it('runs actions that no click started, such as keyboard shortcuts, without marking anything', async () => {
        const doc = fakeDocument();
        trackClicks(doc);

        await expect(withBusyButton(async () => 'done')).resolves.toBe('done');
    });
});
