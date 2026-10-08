import { describe, expect, it } from 'vitest';
import { guardLeaving } from '../../resources/js/import-guard.js';

function fakeLayer() {
    const handlers = {};

    return {
        dismissed: 0,
        on: (type, handler) => {
            handlers[type] = handler;

            return () => delete handlers[type];
        },
        dismiss() {
            const event = {
                defaultPrevented: false,
                preventDefault() {
                    this.defaultPrevented = true;
                },
            };
            handlers['up:layer:dismiss']?.(event);
            if (!event.defaultPrevented) this.dismissed++;
        },
    };
}

describe('B-4, U24 leaving the import modal after a change asks first', () => {
    it('closes at once when nothing was changed', () => {
        const layer = fakeLayer();
        guardLeaving({
            layer,
            dirty: () => false,
            ask: () => {
                throw new Error('asked');
            },
        });

        layer.dismiss();

        expect(layer.dismissed).toBe(1);
    });

    it('asks after a change, and closes only once the host agrees', () => {
        const layer = fakeLayer();
        let agree = null;
        guardLeaving({ layer, dirty: () => true, ask: (go) => (agree = go) });

        layer.dismiss();
        expect(layer.dismissed).toBe(0);

        agree();
        expect(layer.dismissed).toBe(1);
    });

    it('lets Save through: a page that is sent no longer asks', () => {
        const layer = fakeLayer();
        const guard = guardLeaving({
            layer,
            dirty: () => true,
            ask: () => {
                throw new Error('asked');
            },
        });

        guard.allow();
        layer.dismiss();

        expect(layer.dismissed).toBe(1);
    });
});
