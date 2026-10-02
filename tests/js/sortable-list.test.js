import { describe, expect, it } from 'vitest';
import { moveItem } from '../../resources/js/sortable-list.js';

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
