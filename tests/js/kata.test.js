import { describe, expect, it } from 'vitest';
import { kataView, legendOf, movementOf } from '../../resources/js/kata.js';

const game = (overrides = {}) => ({
    type: 'tebak_kata',
    status: 'draft',
    question: null,
    leaderboard: null,
    final: null,
    ...overrides,
});
const question = (status, overrides = {}) => ({
    id: 'q1',
    status,
    number: 1,
    prompt: 'Capital?',
    boxes: [],
    winner: null,
    answer: null,
    ...overrides,
});

describe('Tebak Kata screens (E11, E12, E14, E17)', () => {
    it('picks what the screen shows from the game state', () => {
        expect(kataView(game())).toBe('next');
        expect(kataView(game({ question: question('shown') }))).toBe('question');
        expect(kataView(game({ question: question('won', { winner: 'Rita' }) }))).toBe('won');
        expect(kataView(game({ question: question('surrendered') }))).toBe('surrendered');
        expect(kataView(game({ question: question('won'), leaderboard: [] }))).toBe('leaderboard');
        expect(kataView(game({ status: 'finished', final: [] }))).toBe('final');
    });

    it('describes how each box opened for the legend', () => {
        expect(legendOf({ c: 'P', b: 0, o: 'i' })).toBe('initial');
        expect(legendOf({ c: 'A', b: 1, o: 'h' })).toBe('hint');
        expect(legendOf({ c: null, b: 2, o: null })).toBe('closed');
        expect(legendOf({ c: '-', b: null, o: null })).toBe('punctuation');
        expect(legendOf({ c: 'S', b: 4, o: null })).toBe('revealed');
    });

    it('reads the leaderboard move of a row (E11)', () => {
        expect(movementOf({ movement: 2 })).toEqual({ direction: 'up', steps: 2 });
        expect(movementOf({ movement: -1 })).toEqual({ direction: 'down', steps: 1 });
        expect(movementOf({ movement: 0 })).toEqual({ direction: 'same', steps: 0 });
        expect(movementOf({ movement: null })).toEqual({ direction: 'new', steps: 0 });
    });
});
