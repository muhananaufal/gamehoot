import { describe, expect, it, vi } from 'vitest';
import { closeDialogsOnBackdrop } from '../../resources/js/dialogs.js';

const fakeDocument = () => {
    const handlers = {};

    return {
        addEventListener: (type, handler) => (handlers[type] = handler),
        fire: (type, target) => handlers[type]?.({ target }),
    };
};

const dialog = () => ({ tagName: 'DIALOG', open: true, close: vi.fn() });

describe('U13 dialogs close on a click outside their box', () => {
    it('closes an open dialog when the press and the click both land on its backdrop', () => {
        const doc = fakeDocument();
        closeDialogsOnBackdrop(doc);
        const box = dialog();

        doc.fire('pointerdown', box);
        doc.fire('click', box);

        expect(box.close).toHaveBeenCalledOnce();
    });

    it('stays open when the press started inside, e.g. selecting text in a field', () => {
        const doc = fakeDocument();
        closeDialogsOnBackdrop(doc);
        const box = dialog();

        doc.fire('pointerdown', { tagName: 'INPUT' });
        doc.fire('click', box);

        expect(box.close).not.toHaveBeenCalled();
    });

    it('ignores clicks on anything inside the box', () => {
        const doc = fakeDocument();
        closeDialogsOnBackdrop(doc);
        const button = { tagName: 'BUTTON' };

        doc.fire('pointerdown', button);
        expect(() => doc.fire('click', button)).not.toThrow();
    });
});
