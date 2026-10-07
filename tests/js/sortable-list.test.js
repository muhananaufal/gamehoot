import { describe, expect, it, vi } from 'vitest';
import { moveItem, numberAt, reloadTarget, saveLater } from '../../resources/js/sortable-list.js';

describe('U9 drag to reorder', () => {
    it('moves an item down and up, shifting the others', () => {
        expect(moveItem(['a', 'b', 'c', 'd'], 0, 2)).toEqual(['b', 'c', 'a', 'd']);
        expect(moveItem(['a', 'b', 'c', 'd'], 3, 1)).toEqual(['a', 'd', 'b', 'c']);
    });

    it('leaves the list alone for a drop on itself or outside the list', () => {
        const ids = ['a', 'b', 'c'];

        expect(moveItem(ids, 1, 1)).toEqual(ids);
        expect(moveItem(ids, 1, 7)).toEqual(ids);
        expect(moveItem(ids, -1, 0)).toEqual(ids);
    });

    it('never changes the list it was given', () => {
        const ids = ['a', 'b', 'c'];
        moveItem(ids, 0, 2);

        expect(ids).toEqual(['a', 'b', 'c']);
    });
});

describe('U14 arrows save the order once after the last click', () => {
    it('waits until the clicks stop, then saves the latest order once', () => {
        vi.useFakeTimers();
        const save = vi.fn();
        const later = saveLater(save, 900);

        later(['b', 'a', 'c']);
        vi.advanceTimersByTime(500);
        later(['b', 'c', 'a']);
        vi.advanceTimersByTime(899);
        expect(save).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(save).toHaveBeenCalledOnce();
        expect(save).toHaveBeenCalledWith(['b', 'c', 'a']);
        vi.useRealTimers();
    });
});

describe('D-9 lists that share their numbers with rows that do not move', () => {
    it('numbers a row by the places the list holds, or from 1 without them', () => {
        expect(numberAt(0, [3, 4, 6])).toBe(3);
        expect(numberAt(2, [3, 4, 6])).toBe(6);
        expect(numberAt(1, null)).toBe(2);
    });

    it('reloads the part of the page the list names after a failed save', () => {
        expect(reloadTarget({ reload: '#games-live' })).toBe('#games-live');
        expect(reloadTarget({})).toBe('#pack-panel');
    });
});
