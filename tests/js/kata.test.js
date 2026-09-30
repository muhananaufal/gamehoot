import { describe, expect, it } from 'vitest';
import { legendOf } from '../../resources/js/kata.js';

describe('Tebak Kata boxes (E5)', () => {
    it('describes how each box opened for the legend', () => {
        expect(legendOf({ c: 'P', b: 0, o: 'i' })).toBe('initial');
        expect(legendOf({ c: 'A', b: 1, o: 'h' })).toBe('hint');
        expect(legendOf({ c: null, b: 2, o: null })).toBe('closed');
        expect(legendOf({ c: '-', b: null, o: null })).toBe('punctuation');
        expect(legendOf({ c: 'S', b: 4, o: null })).toBe('revealed');
    });
});
