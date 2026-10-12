import { describe, expect, it } from 'vitest';
import {
    BOARD_HOLD_MS,
    boardRows,
    canReveal,
    canSkip,
    hasBoardBefore,
    moveBadge,
    movementOf,
    tebakView,
} from '../../resources/js/tebak.js';

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

describe('E11 the leaderboard moves from the board before the last win', () => {
    const before = [
        { rank: 1, name: 'Budi', points: 1 },
        { rank: 2, name: 'Rita', points: 1 },
    ];
    const now = [
        { rank: 1, name: 'Rita', points: 2, movement: 1 },
        { rank: 2, name: 'Budi', points: 1, movement: -1 },
    ];

    it('shows the board before first, with no moves, then the board now with them', () => {
        expect(boardRows(before, now, 'before').map((row) => `${row.rank} ${row.name} ${row.points}`)).toEqual([
            '1 Budi 1',
            '2 Rita 1',
        ]);
        expect(boardRows(before, now, 'before').every((row) => row.settled === false)).toBe(true);
        expect(boardRows(before, now, 'after').map((row) => `${row.rank} ${row.name} ${row.points}`)).toEqual([
            '1 Rita 2',
            '2 Budi 1',
        ]);
        expect(boardRows(before, now, 'after').every((row) => row.settled === true)).toBe(true);
    });

    it('shows the board now at once when there was no board before (the first win)', () => {
        expect(hasBoardBefore([])).toBe(false);
        expect(hasBoardBefore(null)).toBe(false);
        expect(hasBoardBefore(before)).toBe(true);
        expect(boardRows([], now, 'before').map((row) => row.name)).toEqual(['Rita', 'Budi']);
        expect(boardRows(before, null, 'after')).toEqual([]);
    });

    it('holds the board before for about a second', () => {
        expect(BOARD_HOLD_MS).toBeGreaterThanOrEqual(800);
        expect(BOARD_HOLD_MS).toBeLessThanOrEqual(1500);
    });
});

describe('E11 the move of a row as an arrow or a NEW badge', () => {
    const labels = { moved_up: 'up __N__', moved_down: 'down __N__', new_entry: 'new' };

    it('gives the direction, the steps and a spoken label', () => {
        expect(moveBadge({ movement: 2, settled: true }, labels)).toEqual({ direction: 'up', steps: 2, label: 'up 2' });
        expect(moveBadge({ movement: -1, settled: true }, labels)).toEqual({
            direction: 'down',
            steps: 1,
            label: 'down 1',
        });
        expect(moveBadge({ movement: null, settled: true }, labels)).toEqual({
            direction: 'new',
            steps: 0,
            label: 'new',
        });
    });

    it('shows nothing for a name that kept its place, or before the cards moved', () => {
        expect(moveBadge({ movement: 0, settled: true }, labels)).toBe(null);
        expect(moveBadge({ movement: 2, settled: false }, labels)).toBe(null);
    });
});
