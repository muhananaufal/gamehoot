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

describe('D-10 no focus ring left on the opening button after a dialog closed with the mouse', () => {
    const setup = () => {
        const doc = fakeDocument();
        const opener = { tagName: 'BUTTON', blur: vi.fn() };
        doc.activeElement = opener;
        closeDialogsOnBackdrop(doc, { defer: (run) => run() });

        return { doc, opener };
    };

    it('drops the focus the browser gives back to the opening button after a mouse close', () => {
        const { doc, opener } = setup();

        doc.fire('pointerdown', dialog());
        doc.fire('close', dialog());

        expect(opener.blur).toHaveBeenCalledOnce();
    });

    it('keeps it for keyboard users, who need to see where they are', () => {
        const { doc, opener } = setup();

        doc.fire('keydown', dialog());
        doc.fire('close', dialog());

        expect(opener.blur).not.toHaveBeenCalled();
    });
});
