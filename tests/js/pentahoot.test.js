import { describe, expect, it } from 'vitest';
import {
    canVote,
    capRows,
    fill,
    phaseOf,
    podium,
    revealSteps,
    searchPeople,
    secondsLeft,
} from '../../resources/js/pentahoot.js';
import { realtimeStore } from '../../resources/js/realtime.js';

const at = (serverTime) => {
    const store = realtimeStore({ now: () => 0 });
    store.accept({ version: 1, server_now: serverTime, event: { status: 'open' } });
    return store;
};

const question = (status, endsAt = 20_000) => ({ id: 'q1', status, ends_at: endsAt, attempt: 1 });

describe('F4, E3 question phase on the server clock', () => {
    it('counts down in whole seconds and never below zero', () => {
        expect(secondsLeft(at(0), question('live'))).toBe(20);
        expect(secondsLeft(at(19_001), question('live'))).toBe(1);
        expect(secondsLeft(at(20_000), question('live'))).toBe(0);
        expect(secondsLeft(at(25_000), question('live'))).toBe(0);
    });

    it('locks the vote button at exactly zero, while the server still takes late votes', () => {
        expect(canVote(at(19_999), question('live'))).toBe(true);
        expect(canVote(at(20_000), question('live'))).toBe(false);
        expect(canVote(at(0), question('ready'))).toBe(false);
    });

    it('derives "closed" from the clock once no vote can arrive', () => {
        expect(phaseOf(at(10_000), question('live'))).toBe('live');
        expect(phaseOf(at(20_500), question('live'))).toBe('live');
        expect(phaseOf(at(21_001), question('live'))).toBe('closed');
        expect(phaseOf(at(0), question('revealed'))).toBe('revealed');
        expect(phaseOf(at(0), question('ready', null))).toBe('ready');
        expect(phaseOf(at(0), null)).toBe('none');
    });
});

const results = [
    { rank: 1, name: 'Rizky', votes: 10 },
    { rank: 2, name: 'Bunga', votes: 8 },
    { rank: 2, name: 'Hari', votes: 8 },
    { rank: 4, name: 'Budi', votes: 5 },
    { rank: 4, name: 'Ayu', votes: 5 },
];

describe('E2, E17 reveal and podium', () => {
    it('reveals from the lowest rank up, with ties in one step', () => {
        expect(revealSteps(results).map((step) => step.map((row) => row.name))).toEqual([
            ['Budi', 'Ayu'],
            ['Bunga', 'Hari'],
            ['Rizky'],
        ]);
        expect(revealSteps([])).toEqual([]);
    });

    it('puts ranks 1 to 3 on the steps and leaves a step empty when a tie skips it', () => {
        expect(podium(results)).toEqual({
            first: [{ rank: 1, name: 'Rizky', votes: 10 }],
            second: [
                { rank: 2, name: 'Bunga', votes: 8 },
                { rank: 2, name: 'Hari', votes: 8 },
            ],
            third: [],
            rest: [
                { rank: 4, name: 'Budi', votes: 5 },
                { rank: 4, name: 'Ayu', votes: 5 },
            ],
        });
    });
});

describe('F9 name search on the phone', () => {
    const people = [
        { id: 1, name: 'Rizky Pratama' },
        { id: 2, name: 'Rita Wulandari' },
        { id: 3, name: 'Hari Setiawan' },
        { id: 4, name: 'Budi Santoso' },
    ];

    it('finds names containing the typed text, ignoring case and extra spaces', () => {
        expect(searchPeople(people, '  RI ').map((p) => p.name)).toEqual([
            'Rizky Pratama',
            'Rita Wulandari',
            'Hari Setiawan',
        ]);
        expect(searchPeople(people, 'santoso').map((p) => p.name)).toEqual(['Budi Santoso']);
    });

    it('lists names starting with the text first, and caps the list', () => {
        expect(searchPeople(people, 'ri', 2).map((p) => p.name)).toEqual(['Rizky Pratama', 'Rita Wulandari']);
        expect(searchPeople(people, '')).toEqual([]);
    });
});

describe('A10 label placeholders', () => {
    it('fills counts into translated labels', () => {
        expect(fill('Question __N__ of __TOTAL__', { N: 3, TOTAL: 8 })).toBe('Question 3 of 8');
        expect(fill('__N__ / __TOTAL__ answered', { N: 0, TOTAL: 48 })).toBe('0 / 48 answered');
    });
});

describe('E20 projector reveal cap', () => {
    const tied = (rank, count, votes) =>
        Array.from({ length: count }, (_, i) => ({ rank, name: `N${rank}-${i}`, votes }));

    it('keeps whole rank groups while they fit, and sums up the group that would not', () => {
        const rows = capRows([{ rank: 1, name: 'A', votes: 10 }, ...tied(2, 2, 8), ...tied(4, 15, 1)], 10);

        expect(rows.map((row) => (row.summary ? `${row.rank}:${row.count}x${row.votes}` : row.name))).toEqual([
            'A',
            'N2-0',
            'N2-1',
            '4:15x1',
        ]);
    });

    it('leaves short lists alone', () => {
        const rows = [{ rank: 1, name: 'A', votes: 3 }, ...tied(2, 3, 1)];

        expect(capRows(rows, 10)).toEqual(rows);
    });

    it('never cuts inside a tie, even when the first rank alone is too long', () => {
        expect(capRows(tied(1, 12, 1), 10)).toEqual([{ rank: 1, summary: true, count: 12, votes: 1 }]);
    });

    it('sums up a podium step with more than three names (E17)', () => {
        const stand = podium([...tied(1, 4, 5), { rank: 5, name: 'Z', votes: 1 }]);

        expect(stand.first).toEqual([{ rank: 1, summary: true, count: 4, votes: 5 }]);
        expect(stand.rest).toEqual([{ rank: 5, name: 'Z', votes: 1 }]);
    });
});
