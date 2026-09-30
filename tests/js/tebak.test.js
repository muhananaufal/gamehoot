import { describe, expect, it } from 'vitest';
import { canReveal, canSkip, movementOf, tebakView } from '../../resources/js/tebak.js';

const game = (overrides = {}) => ({
    type: 'tebak_kata',
    status: 'draft',
    question: null,
    leaderboard: null,
    final: null,
    statuses: ['s', 'q'],
    ...overrides,
});
const question = (status, overrides = {}) => ({
    id: 'q1',
    status,
    number: 1,
    winner: null,
    skipped: false,
    ...overrides,
});

describe('Tebak screens (E11, E12, E14, E17)', () => {
    it('picks what the screen shows from the game state, for both Tebak games', () => {
        for (const type of ['tebak_kata', 'tebak_gambar']) {
            expect(tebakView(game({ type }))).toBe('next');
            expect(tebakView(game({ type, question: question('shown') }))).toBe('question');
            expect(tebakView(game({ type, question: question('won', { winner: 'Rita' }) }))).toBe('won');
            expect(tebakView(game({ type, question: question('surrendered') }))).toBe('surrendered');
            expect(tebakView(game({ type, question: question('won'), leaderboard: [] }))).toBe('leaderboard');
            expect(tebakView(game({ type, status: 'finished', final: [] }))).toBe('final');
        }
    });

    it('keeps a revealed Tebak Gambar question on the question view (E9)', () => {
        expect(tebakView(game({ type: 'tebak_gambar', question: question('shown', { revealed: true }) }))).toBe(
            'question',
        );
    });

    it('reads the leaderboard move of a row (E11)', () => {
        expect(movementOf({ movement: 2 })).toEqual({ direction: 'up', steps: 2 });
        expect(movementOf({ movement: -1 })).toEqual({ direction: 'down', steps: 1 });
        expect(movementOf({ movement: 0 })).toEqual({ direction: 'same', steps: 0 });
        expect(movementOf({ movement: null })).toEqual({ direction: 'new', steps: 0 });
    });
});

describe('Tebak host rules (E6, E7, E9)', () => {
    it('skips a shown question once, while another one is queued', () => {
        expect(canSkip(game({ question: question('shown') }))).toBe(true);
        expect(canSkip(game({ question: question('shown'), statuses: ['s', 'Q'] }))).toBe(true);
        expect(canSkip(game({ question: question('shown', { skipped: true }) }))).toBe(false);
        expect(canSkip(game({ question: question('shown'), statuses: ['s', 'w'] }))).toBe(false);
        expect(canSkip(game({ question: question('won') }))).toBe(false);
        expect(canSkip(game())).toBe(false);
    });

    it('turns Skip off once a Tebak Gambar answer is revealed (E7)', () => {
        expect(canSkip(game({ type: 'tebak_gambar', question: question('shown', { revealed: true }) }))).toBe(false);
    });

    it('reveals a shown Tebak Gambar answer once', () => {
        expect(canReveal(game({ type: 'tebak_gambar', question: question('shown', { revealed: false }) }))).toBe(true);
        expect(canReveal(game({ type: 'tebak_gambar', question: question('shown', { revealed: true }) }))).toBe(false);
        expect(canReveal(game({ type: 'tebak_gambar', question: question('won', { revealed: true }) }))).toBe(false);
        expect(canReveal(game({ question: question('shown') }))).toBe(false);
    });
});
