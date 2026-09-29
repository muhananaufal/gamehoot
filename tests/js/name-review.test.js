import { describe, expect, it } from 'vitest';
import { displayName, findDuplicates, normalizeName } from '../../resources/js/name-review.js';

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
});
