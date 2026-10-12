import { describe, expect, it } from 'vitest';
import { packPicker, togglePick } from '../../resources/js/pack-picker.js';

describe('D-9 picking several packs to add as games', () => {
    it('adds a pack at the end of the pick and takes it out again', () => {
        expect(togglePick([], 'a')).toEqual(['a']);
        expect(togglePick(['a'], 'b')).toEqual(['a', 'b']);
        expect(togglePick(['a', 'b', 'c'], 'b')).toEqual(['a', 'c']);
    });

    it('numbers each picked pack in the order it was picked, and names the button by the count', () => {
        const picker = packPicker({ labels: { one: 'Add 1 game', other: 'Add __N__ games' } });
        picker.pick('b', 'Office Awards');
        picker.pick('a', 'Team Trivia');

        expect(picker.orderOf('b')).toBe(1);
        expect(picker.orderOf('a')).toBe(2);
        expect(picker.orderOf('c')).toBe(0);
        expect(picker.chips()).toEqual([
            { id: 'b', number: 1, title: 'Office Awards' },
            { id: 'a', number: 2, title: 'Team Trivia' },
        ]);
        expect(picker.addLabel()).toBe('Add 2 games');

        picker.pick('b', 'Office Awards');
        expect(picker.orderOf('a')).toBe(1);
        expect(picker.addLabel()).toBe('Add 1 game');
    });

    it('starts empty again when the dialog opens', () => {
        const picker = packPicker({ labels: { one: '', other: '' } });
        picker.pick('a', 'Team Trivia');
        picker.clear();

        expect(picker.picked).toEqual([]);
    });
});
