import { describe, expect, it, vi } from 'vitest';
import { flipList } from '../../resources/js/flip.js';

/** A fake list: rows with a position and a key, inside a container at (0, 100). */
const list = (rows) => {
    const els = rows.map(([key, top]) => ({
        dataset: { flipKey: key },
        hidden: false,
        style: {},
        top,
        getBoundingClientRect() {
            return { left: 0, top: this.top, width: 100, height: 40 };
        },
    }));

    return {
        els,
        container: {
            querySelectorAll: () => els,
            getBoundingClientRect: () => ({ left: 0, top: 100 }),
        },
    };
};

describe('U8 rows that move, appear and stay (FLIP)', () => {
    it('slides rows that changed place from where they were', () => {
        const { els, container } = list([
            ['a', 100],
            ['b', 140],
        ]);
        const animate = vi.fn();
        const flip = flipList(container, { animate, observe: false, reduced: () => false });

        [els[0].top, els[1].top] = [140, 100];
        flip.play();

        const moves = animate.mock.calls.filter(([, frames]) => 'y' in frames && !('opacity' in frames));
        expect(moves).toHaveLength(2);
        expect(moves.find(([el]) => el === els[0])[1].y).toEqual([-40, 0]);
    });

    it('fades in rows it has not seen, and leaves still rows alone', () => {
        const { els, container } = list([['a', 100]]);
        const animate = vi.fn();
        const flip = flipList(container, { animate, observe: false, reduced: () => false });
        animate.mockClear();

        els.push({
            ...els[0],
            dataset: { flipKey: 'b' },
            top: 140,
            getBoundingClientRect: els[0].getBoundingClientRect,
        });
        flip.play();

        expect(animate).toHaveBeenCalledOnce();
        expect(animate.mock.calls[0][0]).toEqual([els[1]]);
        expect(animate.mock.calls[0][1]).toMatchObject({ opacity: [0, 1] });
    });

    it('measures against the list, so scrolling the page does not move rows', () => {
        const { els, container } = list([['a', 100]]);
        const animate = vi.fn();
        let listTop = 100;
        container.getBoundingClientRect = () => ({ left: 0, top: listTop });
        const flip = flipList(container, { animate, observe: false, reduced: () => false, enter: false });

        listTop = 40;
        els[0].top = 40;
        flip.play();

        expect(animate).not.toHaveBeenCalled();
    });

    it('forgets hidden rows, so a row shown again on another page fades in instead of flying', () => {
        const { els, container } = list([['a', 100]]);
        const animate = vi.fn();
        const flip = flipList(container, { animate, observe: false, reduced: () => false });
        animate.mockClear();

        els[0].hidden = true;
        flip.play();
        els[0].hidden = false;
        els[0].top = 300;
        flip.play();

        expect(animate.mock.calls[0][1]).toMatchObject({ opacity: [0, 1] });
    });

    it('stops the CSS enter animation of a row that only moved (rank lists)', () => {
        const { els, container } = list([['a', 100]]);
        const flip = flipList(container, { animate: vi.fn(), observe: false, reduced: () => false, enter: false });

        els[0].top = 140;
        flip.play();

        expect(els[0].style.animation).toBe('none');
    });

    it('keeps everything still with reduced motion', () => {
        const { els, container } = list([['a', 100]]);
        const animate = vi.fn();
        const flip = flipList(container, { animate, observe: false, reduced: () => true });

        els[0].top = 140;
        flip.play();

        expect(animate).not.toHaveBeenCalled();
    });

    it('lets rows enter one by one on a page that just opened, but not on a live refresh', () => {
        const first = list([
            ['a', 100],
            ['b', 140],
        ]);
        const animate = vi.fn();
        flipList(first.container, { animate, observe: false, reduced: () => false, entering: () => true });
        expect(animate).toHaveBeenCalledOnce();
        expect(typeof animate.mock.calls[0][2].delay).toBe('function');

        animate.mockClear();
        flipList(list([['a', 100]]).container, {
            animate,
            observe: false,
            reduced: () => false,
            entering: () => false,
        });
        expect(animate).not.toHaveBeenCalled();
    });
});
