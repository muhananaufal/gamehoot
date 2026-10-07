import { describe, expect, it } from 'vitest';
import { replayView } from '../../resources/js/replay-screen.js';

const rows = [
    { rank: 1, name: 'Rita Wulandari', score: 3 },
    { rank: 2, name: 'Budi Santoso', score: 1 },
];

describe('D-11 what the Public View draws for a replayed moment', () => {
    it('draws nothing while no moment is replayed', () => {
        expect(replayView(null)).toBeNull();
        expect(replayView(undefined)).toBeNull();
    });

    it('draws a Pentahoot category and a final podium on the podium, scores as the ranking components read them', () => {
        const category = replayView({
            kind: 'result',
            type: 'pentahoot',
            game: 'Office Awards',
            number: 1,
            title: 'Most punctual?',
            rows,
        });
        const final = replayView({
            kind: 'final',
            type: 'tebak_kata',
            game: 'Word Round',
            number: null,
            title: 'Word Round',
            rows,
        });

        expect(category.shape).toBe('podium');
        expect(category.stand.first).toEqual([{ rank: 1, name: 'Rita Wulandari', votes: 3 }]);
        expect(category.stand.second).toEqual([{ rank: 2, name: 'Budi Santoso', votes: 1 }]);
        expect(final.shape).toBe('podium');
    });

    it('draws a Tebak leaderboard as the ranked list and a Tebak question with its winner', () => {
        const board = replayView({
            kind: 'leaderboard',
            type: 'tebak_kata',
            game: 'Word Round',
            number: 1,
            title: 'PARIS',
            rows,
        });
        const won = replayView({
            kind: 'result',
            type: 'tebak_gambar',
            game: 'Picture Guess',
            number: 2,
            title: 'Paris',
            prompt: 'Which city?',
            rows: rows.slice(0, 1),
        });
        const surrendered = replayView({
            kind: 'result',
            type: 'tebak_kata',
            game: 'Word Round',
            number: 2,
            title: 'PACIFIC',
            prompt: 'Largest ocean?',
            rows: [],
        });

        expect(board.shape).toBe('list');
        expect(board.rows[1]).toEqual({ rank: 2, name: 'Budi Santoso', votes: 1 });
        expect(won.shape).toBe('winner');
        expect(won.winner).toEqual({ rank: 1, name: 'Rita Wulandari', votes: 3 });
        expect(surrendered.winner).toBeNull();
    });
});
