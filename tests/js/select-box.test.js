import { describe, expect, it } from 'vitest';
import { filterOptions, moveActive, selectBox, typeAhead } from '../../resources/js/select-box.js';

const options = [
    { value: 'all', label: 'All', count: 5 },
    { value: 'draft', label: 'Draft', count: 2 },
    { value: 'open', label: 'Open', count: 3 },
];

describe('U22 custom dropdown', () => {
    it('shows a search box only when there are more than seven choices', () => {
        expect(selectBox({ options, value: 'all' }).searchable).toBe(false);
        expect(
            selectBox({ options: Array.from({ length: 8 }, (_, i) => ({ value: i, label: `O${i}` })), value: 0 })
                .searchable,
        ).toBe(true);
    });

    it('filters choices by every typed word, ignoring case', () => {
        const many = [{ label: 'Muha Admin' }, { label: 'Dina (HR)' }, { label: 'Arif Rahman' }];

        expect(filterOptions(many, 'ar ma').map((o) => o.label)).toEqual(['Arif Rahman']);
        expect(filterOptions(many, '')).toHaveLength(3);
    });

    it('moves the active choice with the arrow keys, Home and End, and stops at the ends', () => {
        expect(moveActive(0, 'ArrowDown', 3)).toBe(1);
        expect(moveActive(2, 'ArrowDown', 3)).toBe(2);
        expect(moveActive(0, 'ArrowUp', 3)).toBe(0);
        expect(moveActive(1, 'End', 3)).toBe(2);
        expect(moveActive(2, 'Home', 3)).toBe(0);
    });

    it('jumps to the next choice starting with a typed letter', () => {
        expect(typeAhead(options, 'o', 0)).toBe(2);
        expect(typeAhead(options, 'd', 2)).toBe(1);
        expect(typeAhead(options, 'z', 1)).toBe(1);
    });

    it('labels the button with the chosen choice and its count', () => {
        const box = selectBox({ options, value: 'draft' });

        expect(box.selectedLabel).toBe('Draft · 2');
    });

    it('chooses a value, closes, and gives focus back to its button', () => {
        const box = selectBox({ options, value: 'all' });
        let focused = false;
        box.$refs = { button: { focus: () => (focused = true) } };
        box.open = true;

        box.choose(options[2]);

        expect(box.value).toBe('open');
        expect(box.open).toBe(false);
        expect(focused).toBe(true);
    });

    it('keeps the chosen value when it is chosen again, unless the dropdown can be cleared', () => {
        const fixed = selectBox({ options, value: 'open' });
        fixed.$refs = { button: { focus: () => {} } };
        fixed.choose(options[2]);
        expect(fixed.value).toBe('open');

        // D-11: choosing the picked game again empties the Replay on screen card.
        const clearable = selectBox({ options, value: 'open', clearable: true, placeholder: 'Pick a game' });
        clearable.$refs = { button: { focus: () => {} } };
        clearable.choose(options[2]);
        expect(clearable.value).toBe(null);
        clearable.choose(options[1]);
        expect(clearable.value).toBe('draft');
    });

    it('shows the placeholder while nothing is chosen', () => {
        expect(selectBox({ options, value: null, placeholder: 'Pick a game' }).selectedLabel).toBe('Pick a game');
        expect(selectBox({ options, value: null, placeholder: 'Pick a game' }).empty).toBe(true);
        expect(selectBox({ options, value: 'draft', placeholder: 'Pick a game' }).empty).toBe(false);
    });
});

describe('U22 a dropdown that is never cut off by a scrolling box', () => {
    const boxAt = (rect, width = 1000) => {
        const box = selectBox({ options, value: 'all', view: { innerWidth: width } });
        box.$refs = { button: { getBoundingClientRect: () => rect } };
        box.$nextTick = () => {};

        return box;
    };

    it('places the list under its button, fixed to the screen', () => {
        const box = boxAt({ left: 40, right: 140, bottom: 100, width: 100 });
        box.show();

        expect(box.position).toEqual({ top: 104, left: 40, right: null, width: 100 });
    });

    it('lines the list up with the right edge of its button near the right of the screen', () => {
        const box = boxAt({ left: 900, right: 980, bottom: 100, width: 80 }, 1000);
        box.show();

        expect(box.position).toEqual({ top: 104, left: null, right: 20, width: 80 });
    });
});
