import { describe, expect, it } from 'vitest';
import { displayName, findDuplicates, nameReview, normalizeName } from '../../resources/js/name-review.js';

// Mirrors App\People\PersonName and App\People\DuplicateNames (B-4).
describe('B-4 name review', () => {
    it('tidies and normalizes names like the server', () => {
        expect(displayName('  Budi   Santoso\t')).toBe('Budi Santoso');
        expect(normalizeName('  BUDI  santoso ')).toBe('budi santoso');
        expect(normalizeName('René')).toBe(normalizeName('René'));
    });

    it('flags a repeat of an earlier row and a clash with an existing name', () => {
        const existing = { 'rita wulandari': 'Rita Wulandari' };

        expect(findDuplicates(['Budi', 'Ana', 'budi ', 'RITA wulandari'], existing)).toEqual({
            2: { row: 1 },
            3: { existing: 'Rita Wulandari' },
        });
    });

    it('clears a problem once the name is changed', () => {
        expect(findDuplicates(['Budi', 'Budi (IT)'], {})).toEqual({});
    });

    it('fills live counts into the translated labels, singular or plural', () => {
        const review = nameReview({
            names: ['Budi', 'budi', 'Ana'],
            labels: { save: { one: 'Save __N__ name', other: 'Save __N__ names' } },
        });
        expect(review.problemCount).toBe(1);
        review.remove(1);
        expect(review.label('save', review.rows.length)).toBe('Save 2 names');
        expect(review.problemCount).toBe(0);
        review.remove(0);
        expect(review.label('save', review.rows.length)).toBe('Save 1 name');
    });

    it('flags a name longer than the server takes, instead of calling it ready', () => {
        expect(findDuplicates(['Budi', 'x'.repeat(101), 'y'.repeat(100)], {})).toEqual({ 1: { tooLong: true } });
    });

    it('knows when the list no longer fits in the event (B-3: at most 500 names)', () => {
        const review = nameReview({ names: ['Ana', 'Budi', 'Citra'], room: 2 });
        expect(review.overLimit).toBe(true);

        review.remove(0);
        expect(review.overLimit).toBe(false);
        // Without a room given, nothing is over.
        expect(nameReview({ names: ['Ana'] }).overLimit).toBe(false);
    });
});

describe('B-4 knows when the review was changed, so leaving asks first', () => {
    it('is unchanged until a name is edited or a row removed', () => {
        const review = nameReview({ names: ['Rita Wulandari', 'Budi Santoso'] });
        expect(review.dirty).toBe(false);

        review.rows[0].name = 'Rita W.';
        expect(review.dirty).toBe(true);

        review.rows[0].name = 'Rita Wulandari';
        expect(review.dirty).toBe(false);

        review.remove(1);
        expect(review.dirty).toBe(true);
    });
});

describe('B-4 the review filters by status and searches names, keeping every row for Save', () => {
    const review = () => nameReview({ names: ['Rita Wulandari', 'rita  wulandari', 'Budi Santoso', 'Ana Putri'] });

    it('shows every row, the ready ones or the ones to fix, with their counts', () => {
        const list = review();

        expect(list.counts).toEqual({ all: 4, ready: 3, fix: 1 });
        expect(list.rows.filter((row, index) => list.shown(row, index)).map((row) => row.name)).toHaveLength(4);

        list.filter = 'fix';
        expect(list.rows.filter((row, index) => list.shown(row, index)).map((row) => row.name)).toEqual([
            'rita  wulandari',
        ]);

        list.filter = 'ready';
        expect(list.rows.filter((row, index) => list.shown(row, index))).toHaveLength(3);
    });

    it('searches names ignoring case and extra spaces, inside the chosen status', () => {
        const list = review();

        list.q = '  RITA ';
        expect(list.rows.filter((row, index) => list.shown(row, index)).map((row) => row.name)).toEqual([
            'Rita Wulandari',
            'rita  wulandari',
        ]);

        list.filter = 'ready';
        expect(list.rows.filter((row, index) => list.shown(row, index)).map((row) => row.name)).toEqual([
            'Rita Wulandari',
        ]);
        expect(list.nothingShown).toBe(false);

        list.q = 'nobody';
        expect(list.nothingShown).toBe(true);

        list.reset();
        expect([list.filter, list.q]).toEqual(['all', '']);
    });

    it('moves a fixed row from To fix to Ready', () => {
        const list = review();
        list.rows[1].name = 'Rita Wulandari (IT)';

        expect(list.counts).toEqual({ all: 4, ready: 4, fix: 0 });
    });
});
