import { describe, expect, it } from 'vitest';
import { kataBoxes } from '../../resources/js/kata-boxes.js';

describe('Tebak Kata box picker', () => {
    it('toggles boxes and keeps them sorted', () => {
        const picker = kataBoxes({ answer: 'Paris' });
        picker.toggle(4);
        picker.toggle(0);
        expect(picker.open).toEqual([0, 4]);
        picker.toggle(4);
        expect(picker.open).toEqual([0]);
    });

    it('drops opened boxes that no longer exist when the answer gets shorter', () => {
        const picker = kataBoxes({ answer: 'Paris', open: [1, 4] });
        picker.answer = 'Rom';
        picker.pruneOpen();
        expect(picker.open).toEqual([1]);
    });

    it('has no boxes for an unsupported answer', () => {
        expect(kataBoxes({ answer: 'hi!' }).boxes).toBeNull();
    });
});
